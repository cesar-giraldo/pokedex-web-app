<?php

declare(strict_types=1);

namespace App\Tests\Web\Controller;

use App\Entity\Enum\LegalDocumentType;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\LegalDocumentVersion;
use App\Entity\LegalDocumentVersionTranslation;
use App\Entity\User;
use App\Entity\UserLegalAcceptance;
use App\Legal\LegalAcceptanceService;
use App\Legal\LegalDocumentPublisher;
use App\Repository\LegalDocumentRepository;
use App\Repository\LegalDocumentVersionRepository;
use App\Repository\UserLegalAcceptanceRepository;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function bin2hex;
use function count;
use function random_bytes;
use function sprintf;

#[Group('functional')]
final class LegalAcceptanceControllerTest extends WebTestCase
{
    private const string LOCALE = 'es';

    /** @var list<int> */
    private array $createdVersionIds = [];

    /** @var list<string> */
    private array $nicknames = [];

    protected function tearDown(): void
    {
        if (([] !== $this->createdVersionIds || [] !== $this->nicknames) && static::$booted) {
            /** @var EntityManagerInterface $entityManager */
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);

            foreach ($this->createdVersionIds as $versionId) {
                $version = $entityManager->find(LegalDocumentVersion::class, $versionId);

                if (!$version instanceof LegalDocumentVersion) {
                    continue;
                }

                $acceptances = $entityManager->getRepository(UserLegalAcceptance::class)->findBy([
                    'version' => $version,
                ]);

                foreach ($acceptances as $acceptance) {
                    $entityManager->remove($acceptance);
                }

                $entityManager->flush();
                $entityManager->remove($version);
            }

            $entityManager->flush();

            /** @var UserRepository $users */
            $users = static::getContainer()->get(UserRepository::class);

            foreach ($this->nicknames as $nickname) {
                $user = $users->findOneByNickname($nickname);

                if ($user instanceof User) {
                    $entityManager->remove($user);
                }
            }

            $entityManager->flush();
        }

        parent::tearDown();
    }

    public function testGuestDoesNotSeeTheDialogAndCannotOpenTheRoute(): void
    {
        $client = static::createClient();

        $client->request('GET', '/' . self::LOCALE);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-legal-accept]');

        $client->request('GET', '/' . self::LOCALE . '/legal/accept');
        self::assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        self::assertIsString($location);
        self::assertStringContainsString('/' . self::LOCALE . '/login', $location);
    }

    public function testIncompleteProfileSkipsAcceptanceAndStaysOnTheProfile(): void
    {
        $client = static::createClient();
        $user = $this->createAccountUser(UserStatus::UncompleteProfileInfo);
        $this->login($client, $user);

        $client->request('GET', '/' . self::LOCALE);
        self::assertResponseRedirects('/' . self::LOCALE . '/profile');

        $client->request('GET', '/' . self::LOCALE . '/legal/accept');
        self::assertResponseRedirects('/' . self::LOCALE . '/profile');
        $client->followRedirect();
        self::assertSelectorNotExists('[data-legal-accept]');
    }

    public function testActiveUserAcceptsPendingDocumentsInOrderAndReturnsHome(): void
    {
        $client = static::createClient();
        $user = $this->createAccountUser(UserStatus::Active);
        $versions = $this->pendingVersions();
        self::assertGreaterThanOrEqual(2, count($versions));
        $titles = $this->titlesFor($user, $versions);

        $this->login($client, $user);

        $client->request('GET', '/' . self::LOCALE . '/privacy');
        self::assertResponseRedirects('/' . self::LOCALE . '/legal/accept');

        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-legal-accept]');
        self::assertSelectorNotExists('[role="dialog"]');
        self::assertSelectorNotExists('.legal-accept-overlay');
        self::assertSelectorExists(sprintf('a[href="/%s/logout"]', self::LOCALE));
        self::assertSelectorExists('[data-header-notifications-target="toggle"]');
        self::assertSelectorTextContains('#legal-accept-title', $titles[0]);

        $client->submit($crawler->selectButton('Aceptar y continuar')->form());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#legal-accepted-error', 'Debes marcar la casilla para continuar.');
        self::assertFalse($this->hasAcceptance($user, $versions[0]));

        $accepted = 0;

        while ($accepted < count($versions)) {
            $crawler = $client->getCrawler();
            self::assertSelectorTextContains('#legal-accept-title', $titles[$accepted]);
            $this->submitAcceptance($client, $crawler);
            self::assertResponseRedirects('/' . self::LOCALE . '/legal/accept');
            $crawler = $client->followRedirect();
            ++$accepted;

            if ($accepted < count($versions)) {
                self::assertResponseIsSuccessful();
            }
        }

        self::assertResponseRedirects('/' . self::LOCALE);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-legal-accept]');

        foreach ($versions as $version) {
            self::assertTrue($this->hasAcceptance($user, $version));
        }
    }

    public function testAcceptingDoesNotClearAnotherUsersPendingDocuments(): void
    {
        $client = static::createClient();
        $owner = $this->createAccountUser(UserStatus::Active);
        $viewer = $this->createAccountUser(UserStatus::Active);
        $versions = $this->pendingVersions();
        self::assertNotEmpty($versions);
        $this->login($client, $viewer);

        $client->request('GET', '/' . self::LOCALE . '/legal/accept');

        while ($this->acceptanceService()->hasPending($viewer)) {
            $this->submitAcceptance($client, $client->getCrawler());
            self::assertResponseRedirects('/' . self::LOCALE . '/legal/accept');
            $client->followRedirect();
        }

        foreach ($versions as $version) {
            self::assertTrue($this->hasAcceptance($viewer, $version));
            self::assertFalse($this->hasAcceptance($owner, $version));
        }
    }

    public function testLogoutRemainsAvailableWhileAcceptanceIsPending(): void
    {
        $client = static::createClient();
        $user = $this->createAccountUser(UserStatus::Active);
        $this->pendingVersions();
        $this->login($client, $user);

        $client->request('GET', '/' . self::LOCALE . '/logout');
        self::assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location');
        self::assertIsString($location);
        self::assertStringNotContainsString('/legal/accept', $location);
    }

    public function testProfileAvatarRemainsAvailableWhileAcceptanceIsPending(): void
    {
        $client = static::createClient();
        $user = $this->createAccountUser(UserStatus::Active);
        $this->pendingVersions();
        $this->login($client, $user);

        $client->request('GET', '/' . self::LOCALE . '/profile/avatar');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return list<LegalDocumentVersion>
     */
    private function pendingVersions(): array
    {
        /** @var LegalDocumentPublisher $publisher */
        $publisher = static::getContainer()->get(LegalDocumentPublisher::class);
        $publisher->ensureDocuments();

        /** @var LegalDocumentRepository $documents */
        $documents = static::getContainer()->get(LegalDocumentRepository::class);
        /** @var LegalDocumentVersionRepository $versions */
        $versions = static::getContainer()->get(LegalDocumentVersionRepository::class);

        foreach (LegalDocumentType::cases() as $type) {
            $alreadyPending = false;

            foreach ($versions->findPublishedRequiringAcceptance() as $existing) {
                if ($existing->getDocument()->getType() === $type) {
                    $alreadyPending = true;
                    break;
                }
            }

            if ($alreadyPending) {
                continue;
            }

            $document = $documents->findOneByType($type);
            self::assertNotNull($document);
            $version = new LegalDocumentVersion($document, $versions->nextVersionNumber($document));
            $translation = new LegalDocumentVersionTranslation($version, self::LOCALE);
            $translation
                ->setTitle(LegalDocumentType::PrivacyPolicy === $type ? 'Política de aceptación web' : 'Términos de aceptación web')
                ->setContentHtml('<p>Texto de aceptación web.</p>')
                ->setAcceptanceLabel(LegalDocumentType::PrivacyPolicy === $type ? 'Acepto la política de prueba web' : 'Acepto los términos de prueba web')
                ->setSummary('Resumen de prueba');
            $version->publish(true, 'Aviso', 'Mensaje', new DateTime());
            $this->entityManager()->persist($version);
            $this->entityManager()->flush();
            $versionId = $version->getId();
            self::assertNotNull($versionId);
            $this->createdVersionIds[] = $versionId;
        }

        return $versions->findPublishedRequiringAcceptance();
    }

    /**
     * @param list<LegalDocumentVersion> $versions
     *
     * @return list<string>
     */
    private function titlesFor(User $user, array $versions): array
    {
        $titles = [];

        foreach ($versions as $version) {
            $translation = $this->acceptanceService()->translationForUser($user, $version);
            self::assertNotNull($translation);
            $titles[] = $translation->getTitle();
        }

        return $titles;
    }

    private function submitAcceptance(KernelBrowser $client, Crawler $crawler): void
    {
        $form = $crawler->selectButton('Aceptar y continuar')->form();
        $accepted = $form['accepted'];
        self::assertInstanceOf(ChoiceFormField::class, $accepted);
        $accepted->tick();
        $client->submit($form);
    }

    private function hasAcceptance(User $user, LegalDocumentVersion $version): bool
    {
        /** @var UserLegalAcceptanceRepository $acceptances */
        $acceptances = static::getContainer()->get(UserLegalAcceptanceRepository::class);

        return $acceptances->hasAcceptance($user, $version);
    }

    private function acceptanceService(): LegalAcceptanceService
    {
        /** @var LegalAcceptanceService $service */
        $service = static::getContainer()->get(LegalAcceptanceService::class);

        return $service;
    }

    private function createAccountUser(UserStatus $status): User
    {
        $nickname = 'weblegal' . bin2hex(random_bytes(4));
        $entityManager = $this->entityManager();
        $user = new User()
            ->setName('Ada')
            ->setLastname('Tester')
            ->setNickname($nickname)
            ->setEmail($nickname . '@example.com')
            ->setApplicationRoles([UserRole::User])
            ->setStatus($status);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, 'Secret123'));
        $entityManager->persist($user);
        $entityManager->flush();
        $this->nicknames[] = $user->getNickname();

        return $user;
    }

    private function login(KernelBrowser $client, User $user): void
    {
        $nickname = $user->getNickname();
        $this->entityManager()->clear();
        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        $reloaded = $users->findOneByNickname($nickname);
        self::assertInstanceOf(User::class, $reloaded);
        $client->loginUser($reloaded, 'public');
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }
}
