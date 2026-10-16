<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

#[Group('functional')]
final class UserWebImpersonationTest extends WebTestCase
{
    use AdminAuthenticatedClientTrait;

    /** @var list<int> */
    private array $createdUserIds = [];

    private ?EntityManagerInterface $entityManager = null;

    protected function tearDown(): void
    {
        if (null !== $this->entityManager) {
            foreach ($this->createdUserIds as $userId) {
                $user = $this->entityManager->find(User::class, $userId);
                if ($user instanceof User) {
                    $this->entityManager->remove($user);
                }
            }

            $this->entityManager->flush();
        }

        parent::tearDown();
    }

    public function testAdminImpersonatesPublicUserAndKeepsTheAdminSession(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $publicUser = $this->createUser('wimp', [UserRole::User], UserStatus::Active);

        $crawler = $client->request('GET', '/admin/users', ['limit' => 'all']);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(sprintf(
            'a[title="Impersonar en el admin"][href*="_switch_user=%s"]',
            $publicUser->getNickname(),
        ));

        $form = $crawler->filter(sprintf(
            'form[action$="/%d/impersonate-web"]',
            $publicUser->getId(),
        ))->form();
        $client->submit($form);

        self::assertResponseRedirects('/es');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            'Estás viendo la aplicación como Listed User (@' . $publicUser->getNickname() . ')',
            (string) $client->getResponse()->getContent(),
        );
        self::assertStringContainsString('Volver a mi sesión', (string) $client->getResponse()->getContent());

        $client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Listado de Usuarios', (string) $client->getResponse()->getContent());

        $client->request('GET', '/es/impersonation/exit');
        self::assertResponseRedirects('/admin/users');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Listado de Usuarios', (string) $client->getResponse()->getContent());

        $client->request('GET', '/es');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString(
            'Estás viendo la aplicación como',
            (string) $client->getResponse()->getContent(),
        );
        self::assertStringContainsString('Ingresar', (string) $client->getResponse()->getContent());
    }

    public function testDeveloperSeesAdminAndWebButtonsForAnAdminWithUserRole(): void
    {
        $client = static::createClient();
        $this->loginAsDeveloper($client);
        $target = $this->createUser('wadm', [UserRole::Admin, UserRole::User], UserStatus::Active);

        $client->request('GET', '/admin/users', ['limit' => 'all']);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf(
            'a[title="Impersonar en el admin"][href*="_switch_user=%s"]',
            $target->getNickname(),
        ));
        self::assertSelectorExists(sprintf(
            'form[action$="/%d/impersonate-web"] button[title="Impersonar en la web"]',
            $target->getId(),
        ));
    }

    public function testIncompleteProfileOpensTheProfileWithTheBanner(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $publicUser = $this->createUser('winc', [UserRole::User], UserStatus::UncompleteProfileInfo);

        $crawler = $client->request('GET', '/admin/users', ['limit' => 'all']);
        $form = $crawler->filter(sprintf(
            'form[action$="/%d/impersonate-web"]',
            $publicUser->getId(),
        ))->form();
        $client->submit($form);

        self::assertResponseRedirects('/es/profile');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            'Estás viendo la aplicación como Listed User (@' . $publicUser->getNickname() . ')',
            (string) $client->getResponse()->getContent(),
        );

        $client->request('GET', '/es/impersonation/exit');
        self::assertResponseRedirects('/admin/users');
    }

    public function testAdminCannotImpersonateADeveloperOnTheWeb(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $target = $this->createUser('wdev', [UserRole::Developer, UserRole::User], UserStatus::Active);

        $client->request('GET', '/admin/users', ['limit' => 'all']);
        self::assertSelectorNotExists(sprintf(
            'form[action$="/%d/impersonate-web"]',
            $target->getId(),
        ));

        $client->request('POST', sprintf('/admin/users/%d/impersonate-web', $target->getId()), [
            '_token' => $this->csrfToken($client, 'impersonate-web-' . $target->getId()),
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString(
            'No puedes impersonar este usuario.',
            (string) $client->getResponse()->getContent(),
        );
    }

    public function testPublicLogoutEndsWebImpersonationWithoutDroppingTheAdminSession(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $publicUser = $this->createUser('wout', [UserRole::User], UserStatus::Active);

        $crawler = $client->request('GET', '/admin/users', ['limit' => 'all']);
        $form = $crawler->filter(sprintf(
            'form[action$="/%d/impersonate-web"]',
            $publicUser->getId(),
        ))->form();
        $client->submit($form);
        $client->followRedirect();

        $client->request('GET', '/es/logout');
        self::assertResponseRedirects('/admin/users');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Listado de Usuarios', (string) $client->getResponse()->getContent());

        $client->request('GET', '/es');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString(
            'Estás viendo la aplicación como',
            (string) $client->getResponse()->getContent(),
        );
        self::assertStringContainsString('Ingresar', (string) $client->getResponse()->getContent());
    }

    /**
     * @param list<UserRole> $roles
     */
    private function createUser(string $nicknamePrefix, array $roles, UserStatus $status): User
    {
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $nickname = $nicknamePrefix . bin2hex(random_bytes(4));
        $user = new User()
            ->setName('Listed')
            ->setLastname('User')
            ->setEmail($nickname . '@example.com')
            ->setNickname($nickname)
            ->setCountryCode(57)
            ->setCellphone('3019' . sprintf('%06d', random_int(0, 999999)))
            ->setApplicationRoles($roles)
            ->setStatus($status)
            ->setIsHidden(false);
        $user->setPassword($hasher->hashPassword($user, 'Secret123'));

        $entityManager->persist($user);
        $entityManager->flush();

        $userId = $user->getId();
        self::assertNotNull($userId);
        $this->createdUserIds[] = $userId;

        return $user;
    }

    private function csrfToken(KernelBrowser $client, string $tokenId): string
    {
        $session = $client->getRequest()->getSession();
        $value = bin2hex(random_bytes(16));
        $session->set('_csrf/' . $tokenId, $value);
        $session->save();

        return $value;
    }
}
