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
final class UserControllerIndexTest extends WebTestCase
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

    public function testAdminSeesNonHiddenDeveloperInUserList(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $developer = $this->createListedUser('ulvd', UserRole::Developer, isHidden: false);

        $client->request('GET', '/admin/users', ['limit' => 'all']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('tbody', $developer->getNickname());
    }

    public function testAdminDoesNotSeeHiddenUserInUserList(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $hiddenUser = $this->createListedUser('ulhd', UserRole::Operator, isHidden: true);

        $client->request('GET', '/admin/users', ['limit' => 'all']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('tbody', $hiddenUser->getNickname());
    }

    public function testAdminCannotEditDeveloper(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $developer = $this->createListedUser('uled', UserRole::Developer, isHidden: false);
        $editUrl = sprintf('/admin/users/%d/edit', $developer->getId());

        $client->request('GET', $editUrl);
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', $editUrl);
        self::assertResponseStatusCodeSame(403);
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
            ->setName('Listed')
            ->setLastname('User')
            ->setEmail($nickname . '@example.com')
            ->setNickname($nickname)
            ->setCountryCode(57)
            ->setCellphone('3018' . sprintf('%06d', random_int(0, 999999)))
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
