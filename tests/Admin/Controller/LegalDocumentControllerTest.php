<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Entity\Enum\LegalDocumentType;
use App\Entity\Enum\NotificationType;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\LegalDocumentVersion;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\UserLegalAcceptance;
use App\Legal\LegalDocumentPublisher;
use App\Repository\GeneralSettingsRepository;
use App\Repository\LegalDocumentRepository;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Group('functional')]
final class LegalDocumentControllerTest extends WebTestCase
{
    use AdminAuthenticatedClientTrait;

    private ?EntityManagerInterface $entityManager = null;

    private ?int $versionId = null;

    private string $notificationTitle = '';

    protected function tearDown(): void
    {
        if ($this->entityManager instanceof EntityManagerInterface && $this->entityManager->isOpen()) {
            if ('' !== $this->notificationTitle) {
                $notifications = $this->entityManager->getRepository(Notification::class)->findBy([
                    'type' => NotificationType::LegalVersionPublished,
                    'title' => $this->notificationTitle,
                ]);

                foreach ($notifications as $notification) {
                    $this->entityManager->remove($notification);
                }
            }

            if (null !== $this->versionId) {
                $version = $this->entityManager->find(LegalDocumentVersion::class, $this->versionId);

                if ($version instanceof LegalDocumentVersion) {
                    $acceptances = $this->entityManager->getRepository(UserLegalAcceptance::class)->findBy([
                        'version' => $version,
                    ]);

                    foreach ($acceptances as $acceptance) {
                        $this->entityManager->remove($acceptance);
                    }

                    $this->entityManager->flush();
                    $this->entityManager->remove($version);
                }
            }

            $this->entityManager->flush();
        }

        parent::tearDown();
    }

    public function testDeveloperCanOpenTheLegalModule(): void
    {
        $client = static::createClient();
        $this->loginAsDeveloper($client);

        $client->request('GET', '/admin/legal');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Documentos vigentes');

        $client->request('GET', '/admin/legal/privacy_policy');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('nav', 'Legal');
        self::assertSelectorTextContains('body', 'Historial');
    }

    public function testSavingADraftReturnsToTheVersionHistoryWithASuccessMessage(): void
    {
        $client = static::createClient();
        $developer = $this->loginAsDeveloper($client);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        /** @var LegalDocumentPublisher $publisher */
        $publisher = static::getContainer()->get(LegalDocumentPublisher::class);
        $publisher->ensureDocuments();

        /** @var LegalDocumentRepository $documents */
        $documents = static::getContainer()->get(LegalDocumentRepository::class);
        $document = $documents->findOneByType(LegalDocumentType::TermsOfUse);
        self::assertNotNull($document);

        $draft = $publisher->startDraft($document, $developer);
        $this->versionId = $draft->getId();

        $crawler = $client->request('GET', '/admin/legal/terms_of_use/versions/' . $draft->getVersionNumber() . '/edit');
        self::assertResponseIsSuccessful();

        $client->submit($crawler->selectButton('Guardar borrador')->form());

        self::assertResponseRedirects('/admin/legal/terms_of_use');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Borrador guardado.');
        self::assertSelectorTextContains('body', 'Historial');
    }

    public function testDeletingADraftReturnsToTheVersionHistoryWithASuccessMessage(): void
    {
        $client = static::createClient();
        $developer = $this->loginAsDeveloper($client);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        /** @var LegalDocumentPublisher $publisher */
        $publisher = static::getContainer()->get(LegalDocumentPublisher::class);
        $publisher->ensureDocuments();

        /** @var LegalDocumentRepository $documents */
        $documents = static::getContainer()->get(LegalDocumentRepository::class);
        $document = $documents->findOneByType(LegalDocumentType::PrivacyPolicy);
        self::assertNotNull($document);

        $draft = $publisher->startDraft($document, $developer);
        $this->versionId = $draft->getId();

        $crawler = $client->request('GET', '/admin/legal/privacy_policy/versions/' . $draft->getVersionNumber() . '/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Eliminar borrador');

        $client->submit($crawler->filter('form[action$="/delete"]')->form());

        self::assertResponseRedirects('/admin/legal/privacy_policy');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Borrador eliminado.');
        self::assertSelectorTextContains('body', 'Historial');
    }

    public function testOperatorCannotManageLegalDocuments(): void
    {
        $client = static::createClient();
        $this->loginAsOperator($client);

        $client->request('GET', '/admin/legal');

        self::assertResponseStatusCodeSame(403);
    }

    public function testPublishNotifiesActiveUsersAndBlocksUntilAccepted(): void
    {
        $client = static::createClient();
        $developer = $this->loginAsDeveloper($client);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->publishPrivacyPolicy($developer);

        $client->request('GET', '/admin/home');
        self::assertResponseRedirects('/admin/legal/accept');

        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Acepto la política de prueba');

        $form = $crawler->selectButton('Aceptar y continuar')->form();
        $accepted = $form['accepted'];
        self::assertInstanceOf(ChoiceFormField::class, $accepted);
        $accepted->tick();
        $client->submit($form);
        self::assertResponseRedirects('/admin/legal/accept');

        $client->followRedirect();
        self::assertResponseRedirects('/admin/home');

        $client->request('GET', '/legal/privacy_policy');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Política de prueba');
    }

    public function testIncompleteProfileSkipsTheLegalGate(): void
    {
        $client = static::createClient();
        $developer = $this->loginAsDeveloper($client);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->publishPrivacyPolicy($developer);

        $incomplete = $this->createIncompleteUser();
        $client->loginUser($incomplete, 'main');
        $client->request('GET', '/admin/home');

        self::assertResponseRedirects('/admin/profile');
        $this->entityManager->remove($incomplete);
        $this->entityManager->flush();
    }

    private function publishPrivacyPolicy(User $actor): LegalDocumentVersion
    {
        /** @var LegalDocumentPublisher $publisher */
        $publisher = static::getContainer()->get(LegalDocumentPublisher::class);
        $publisher->ensureDocuments();

        /** @var LegalDocumentRepository $documents */
        $documents = static::getContainer()->get(LegalDocumentRepository::class);
        $document = $documents->findOneByType(LegalDocumentType::PrivacyPolicy);
        self::assertNotNull($document);

        /** @var GeneralSettingsRepository $settingsRepository */
        $settingsRepository = static::getContainer()->get(GeneralSettingsRepository::class);
        $languages = $settingsRepository->getOrCreateSingleton()->getEnabledLanguages();

        $draft = $publisher->startDraft($document, $actor);
        $this->versionId = $draft->getId();
        $payload = [];

        foreach ($languages as $language) {
            $payload[$language] = [
                'title' => 'Política de prueba',
                'contentHtml' => '<p>Contenido de prueba</p>',
                'acceptanceLabel' => 'Acepto la política de prueba',
                'summary' => 'Versión de prueba',
            ];
        }

        $publisher->updateDraft($draft, $payload);
        $this->notificationTitle = 'Política de prueba ' . $draft->getVersionNumber() . ' ' . uniqid('', true);
        $publisher->publish($draft, true, $this->notificationTitle, 'Debes aceptar el documento de prueba.', $actor);

        return $draft;
    }

    private function createIncompleteUser(): User
    {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $suffix = uniqid('', true);
        $user = new User()
            ->setName('Incomplete')
            ->setLastname('Legal')
            ->setEmail('legal-incomplete-' . $suffix . '@example.com')
            ->setNickname('legal-incomplete-' . $suffix)
            ->setCountryCode(57)
            ->setCellphone(substr(str_replace('.', '', $suffix), 0, 15))
            ->setApplicationRoles([UserRole::Operator])
            ->setStatus(UserStatus::UncompleteProfileInfo);
        $user->setPassword($hasher->hashPassword($user, 'Secret123'));

        $this->entityManager?->persist($user);
        $this->entityManager?->flush();

        return $user;
    }
}
