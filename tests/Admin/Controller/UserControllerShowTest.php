<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

#[Group('functional')]
final class UserControllerShowTest extends WebTestCase
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

    public function testAdminCanViewNonHiddenUserAndSeesViewIconInList(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $operator = $this->createListedUser('ushw', UserRole::Operator, isHidden: false);
        $showUrl = sprintf('/admin/users/%d', $operator->getId());

        $client->request('GET', '/admin/users', ['limit' => 'all']);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf('a[href="%s"]', $showUrl));

        $client->request('GET', $showUrl);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $operator->getNickname());
        self::assertSelectorTextContains('body', 'Información general');
        self::assertSelectorTextNotContains('body', 'Información de sesión');
        self::assertSelectorTextContains('body', 'Volver al listado');
        self::assertSelectorExists(sprintf('a[href="/admin/users/%d/edit"]', $operator->getId()));
    }

    public function testAdminDoesNotSeeEditButtonForDeveloper(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $developer = $this->createListedUser('ushd', UserRole::Developer, isHidden: false);

        $client->request('GET', sprintf('/admin/users/%d', $developer->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $developer->getNickname());
        self::assertSelectorNotExists(sprintf('a[href="/admin/users/%d/edit"]', $developer->getId()));
    }

    public function testAdminCannotViewHiddenUser(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $hiddenUser = $this->createListedUser('ushx', UserRole::Operator, isHidden: true);

        $client->request('GET', sprintf('/admin/users/%d', $hiddenUser->getId()));

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeveloperCanViewHiddenUserAndSessionFields(): void
    {
        $client = static::createClient();
        $this->loginAsDeveloper($client);
        $hiddenUser = $this->createListedUser('ushv', UserRole::Operator, isHidden: true);

        $client->request('GET', sprintf('/admin/users/%d', $hiddenUser->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $hiddenUser->getNickname());
        self::assertSelectorTextContains('body', 'Información de sesión');
        self::assertSelectorTextContains('body', 'IP del último login');
        self::assertSelectorTextContains('body', 'Usuario oculto');
    }

    private function createListedUser(string $nicknamePrefix, UserRole $role, bool $isHidden): User
    {
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $suffix = bin2hex(random_bytes(4));
        $nickname = $nicknamePrefix . $suffix;

        $user = new User()
            ->setName('Ficha')
            ->setLastname('User')
            ->setEmail($nickname . '@example.com')
            ->setNickname($nickname)
            ->setCountryCode(57)
            ->setCellphone('3017' . sprintf('%06d', random_int(0, 999999)))
            ->setApplicationRoles([$role])
            ->setStatus(UserStatus::Active)
            ->setIsHidden($isHidden);
        $user->setPassword($hasher->hashPassword($user, 'Secret123'));

        $entityManager->persist($user);
        $entityManager->flush();

        $userId = $user->getId();
        self::assertNotNull($userId);
        $this->createdUserIds[] = $userId;

        return $user;
    }
}
