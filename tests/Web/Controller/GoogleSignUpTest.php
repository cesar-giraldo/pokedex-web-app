<?php

declare(strict_types=1);

namespace App\Tests\Web\Controller;

use App\Entity\AccountConfirmationCode;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\Module;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\UserLegalAcceptance;
use App\Module\EventSubscriber\ModuleAccessSubscriber;
use App\Repository\ModuleRepository;
use App\Repository\UserRepository;
use App\Tests\Double\FakeGoogleIdTokenVerifier;
use App\Web\Security\GoogleIdentity;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use function bin2hex;
use function parse_str;
use function parse_url;
use function random_bytes;

use const PHP_URL_PATH;
use const PHP_URL_QUERY;

#[Group('functional')]
final class GoogleSignUpTest extends WebTestCase
{
    private const string PARENT_SLUG = 'module_web_secure_area';

    private const string GOOGLE_SLUG = 'module_web_google_auth';

    /** @var list<string> */
    private array $emails = [];

    private ?bool $originalParentEnabled = null;

    private ?bool $originalGoogleEnabled = null;

    private ?int $originalGoogleParentId = null;

    private bool $googleHadNoParent = false;

    private bool $createdParent = false;

    private bool $createdGoogle = false;

    protected function tearDown(): void
    {
        FakeGoogleIdTokenVerifier::$identity = null;

        if (static::$booted) {
            $this->restoreGoogleModule();
        }

        if ([] !== $this->emails && static::$booted) {
            /** @var EntityManagerInterface $entityManager */
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            /** @var UserRepository $users */
            $users = static::getContainer()->get(UserRepository::class);

            foreach ($this->emails as $email) {
                $entityManager->createQueryBuilder()
                    ->delete(Notification::class, 'notification')
                    ->andWhere('notification.message LIKE :email')
                    ->setParameter('email', '%' . $email . '%')
                    ->getQuery()
                    ->execute();

                $user = $users->findOneByEmail($email);

                if ($user instanceof User) {
                    $entityManager->remove($user);
                }
            }

            $entityManager->flush();
        }

        parent::tearDown();
    }

    public function testTheGoogleButtonSitsOutsideTheManualForms(): void
    {
        $client = static::createClient();
        $this->setGoogleModuleState(true, true);

        $register = $client->request('GET', '/es/register');
        self::assertResponseIsSuccessful();
        self::assertSame(0, $register->filter('form')->first()->filter('a[href*="/connect/google"]')->count());
        self::assertGreaterThan(0, $register->filter('a[href*="from=register"]')->count());
        self::assertSelectorTextContains('a[href*="from=register"]', 'Regístrate con Google');

        $login = $client->request('GET', '/es/login');
        self::assertResponseIsSuccessful();
        self::assertSame(0, $login->filter('form')->first()->filter('a[href*="/connect/google"]')->count());
        self::assertGreaterThan(0, $login->filter('a[href*="from=login"]')->count());
        self::assertSelectorTextContains('a[href*="from=login"]', 'Entra con Google');
    }

    public function testANewGoogleAccountLandsOnTheProfileWithoutAConfirmationCode(): void
    {
        $email = 'google-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->emails[] = $email;
        FakeGoogleIdTokenVerifier::$identity = new GoogleIdentity(
            sub: 'sub-' . bin2hex(random_bytes(4)),
            email: $email,
            givenName: 'Ada',
            familyName: 'Lovelace',
        );

        $client = static::createClient();
        $this->setGoogleModuleState(true, true);
        $client->request('GET', '/es/connect/google?from=register');
        self::assertResponseRedirects();
        $state = $this->oauthState($client);

        $client->request('GET', '/connect/google/check?code=ok&state=' . rawurlencode($state));
        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        $path = parse_url($location, PHP_URL_PATH);
        self::assertIsString($path);
        self::assertStringEndsWith('/es/profile', $path);

        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $tokenUser = $client->getContainer()->get(TokenStorageInterface::class)->getToken()?->getUser();
        self::assertInstanceOf(User::class, $tokenUser);
        self::assertSame($email, $tokenUser->getUserIdentifier());

        /** @var UserRepository $users */
        $users = static::getContainer()->get(UserRepository::class);
        $user = $users->findOneByEmail($email);
        self::assertInstanceOf(User::class, $user);
        self::assertSame(UserStatus::UncompleteProfileInfo, $user->getStatus());
        self::assertTrue($user->hasAssignedRole(UserRole::User));
        self::assertSame(SupportedLanguage::Spanish, $user->getPreferredLanguage());
        self::assertNotNull($user->getGoogleSub());

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame(0, $entityManager->getRepository(AccountConfirmationCode::class)->count(['user' => $user]));
        self::assertSame(0, $entityManager->getRepository(UserLegalAcceptance::class)->count(['user' => $user]));
    }

    public function testTheGoogleOptionIsHiddenWhenTheModuleIsDisabled(): void
    {
        $client = static::createClient();
        $this->setGoogleModuleState(true, false);

        $this->assertGoogleOptionIsHidden($client);
        $this->assertGoogleAccessIsDenied($client, '/es/connect/google?from=login');
        $this->assertGoogleAccessIsDenied($client, '/connect/google/check?code=ok&state=ignored');
    }

    public function testTheGoogleOptionIsHiddenWhenTheParentModuleIsDisabled(): void
    {
        $client = static::createClient();
        $this->setGoogleModuleState(false, true);

        $this->assertGoogleOptionIsHidden($client);
        $this->assertGoogleAccessIsDenied($client, '/es/connect/google?from=register');
    }

    private function assertGoogleOptionIsHidden(KernelBrowser $client): void
    {
        $client->request('GET', '/es/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href*="/connect/google"]');

        $client->request('GET', '/es/register');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href*="/connect/google"]');
    }

    private function assertGoogleAccessIsDenied(KernelBrowser $client, string $path): void
    {
        $client->request('GET', $path);

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', ModuleAccessSubscriber::DENIED_MESSAGE);
    }

    private function setGoogleModuleState(bool $parentEnabled, bool $googleEnabled): void
    {
        $entityManager = $this->entityManager();
        $repository = $this->moduleRepository();
        $parent = $repository->findOneBy(['slug' => self::PARENT_SLUG]);

        if (!$parent instanceof Module) {
            $parent = new Module('Web Secure Area', self::PARENT_SLUG, '1.00');
            $entityManager->persist($parent);
            $this->createdParent = true;
        } elseif (null === $this->originalParentEnabled) {
            $this->originalParentEnabled = $parent->isEnabled();
        }

        $google = $repository->findOneBy(['slug' => self::GOOGLE_SLUG]);

        if (!$google instanceof Module) {
            $google = new Module('Login con Google', self::GOOGLE_SLUG, '1.10');
            $google->setParent($parent);
            $entityManager->persist($google);
            $this->createdGoogle = true;
        } elseif (null === $this->originalGoogleEnabled) {
            $this->originalGoogleEnabled = $google->isEnabled();
            $googleParent = $google->getParent();

            if (!$googleParent instanceof Module) {
                $this->googleHadNoParent = true;
                $google->setParent($parent);
            } elseif (self::PARENT_SLUG !== $googleParent->getSlug()) {
                $this->originalGoogleParentId = $googleParent->getId();
                $google->setParent($parent);
            }
        }

        $parent->setIsEnabled($parentEnabled);
        $google->setIsEnabled($googleEnabled);
        $entityManager->flush();
    }

    private function restoreGoogleModule(): void
    {
        if (null === $this->originalParentEnabled && null === $this->originalGoogleEnabled && !$this->createdParent && !$this->createdGoogle) {
            return;
        }

        $entityManager = $this->entityManager();
        $repository = $this->moduleRepository();
        $google = $repository->findOneBy(['slug' => self::GOOGLE_SLUG]);
        $parent = $repository->findOneBy(['slug' => self::PARENT_SLUG]);

        if ($this->createdGoogle && $google instanceof Module) {
            $google->setParent(null);
            $entityManager->flush();
            $entityManager->remove($google);
            $entityManager->flush();
            $google = null;
        }

        if ($this->createdParent && $parent instanceof Module) {
            $entityManager->remove($parent);
            $entityManager->flush();
            $parent = null;
        }

        if (!$this->createdGoogle && $google instanceof Module) {
            if (null !== $this->originalGoogleEnabled) {
                $google->setIsEnabled($this->originalGoogleEnabled);
            }

            if ($this->googleHadNoParent) {
                $google->setParent(null);
            } elseif (null !== $this->originalGoogleParentId) {
                $originalParent = $entityManager->find(Module::class, $this->originalGoogleParentId);

                if ($originalParent instanceof Module) {
                    $google->setParent($originalParent);
                }
            }
        }

        if (!$this->createdParent && $parent instanceof Module && null !== $this->originalParentEnabled) {
            $parent->setIsEnabled($this->originalParentEnabled);
        }

        $entityManager->flush();
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }

    private function moduleRepository(): ModuleRepository
    {
        /** @var ModuleRepository $repository */
        $repository = static::getContainer()->get(ModuleRepository::class);

        return $repository;
    }

    private function oauthState(KernelBrowser $client): string
    {
        $location = $client->getResponse()->headers->get('Location');
        self::assertIsString($location);
        $queryString = parse_url($location, PHP_URL_QUERY);
        self::assertIsString($queryString);
        $query = [];
        parse_str($queryString, $query);
        $state = $query['state'] ?? null;
        self::assertIsString($state);
        self::assertNotSame('', $state);

        return $state;
    }
}
