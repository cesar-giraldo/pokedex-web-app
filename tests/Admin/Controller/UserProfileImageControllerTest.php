<?php

declare(strict_types=1);

namespace App\Tests\Admin\Controller;

use App\Admin\Service\Storage\UserProfileImageStorage;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Tests\Admin\Support\AdminAuthenticatedClientTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

#[Group('functional')]
final class UserProfileImageControllerTest extends WebTestCase
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

    public function testAuthenticatedViewerReceivesAvatarWebp(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $user = $this->createUserWithProfileImage(isHidden: false);

        $client->request('GET', sprintf('/admin/media/user-profile/%d', $user->getId()));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/webp');
        self::assertStringContainsString('private', (string) $client->getResponse()->headers->get('Cache-Control'));
    }

    public function testOriginalVariantKeepsJpeg(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $user = $this->createUserWithProfileImage(isHidden: false);

        $client->request('GET', sprintf('/admin/media/user-profile/%d/original', $user->getId()));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/jpeg');
    }

    public function testAdminCannotViewHiddenUserAvatar(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $user = $this->createUserWithProfileImage(isHidden: true);

        $client->request('GET', sprintf('/admin/media/user-profile/%d', $user->getId()));

        self::assertResponseStatusCodeSame(404);
    }

    public function testInvalidVariantIsNotRouted(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);
        $user = $this->createUserWithProfileImage(isHidden: false);

        $client->request('GET', sprintf('/admin/media/user-profile/%d/thumb', $user->getId()));

        self::assertResponseStatusCodeSame(404);
    }

    private function createUserWithProfileImage(bool $isHidden): User
    {
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $suffix = bin2hex(random_bytes(4));
        $nickname = 'upimg' . $suffix;

        $user = new User()
            ->setName('Avatar')
            ->setLastname('User')
            ->setEmail($nickname . '@example.com')
            ->setNickname($nickname)
            ->setCountryCode(57)
            ->setCellphone('3018' . sprintf('%06d', random_int(0, 999999)))
            ->setApplicationRoles([UserRole::Operator])
            ->setStatus(UserStatus::Active)
            ->setIsHidden($isHidden);
        $user->setPassword($hasher->hashPassword($user, 'Secret123'));

        $entityManager->persist($user);
        $entityManager->flush();

        $userId = $user->getId();
        self::assertNotNull($userId);
        $this->createdUserIds[] = $userId;

        /** @var UserProfileImageStorage $storage */
        $storage = $container->get(UserProfileImageStorage::class);
        $objectKey = $storage->upload($user, $this->createUploadedFile());
        $user->setProfileImagePath($objectKey);
        $entityManager->flush();

        return $user;
    }

    private function createUploadedFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'profile-public-');
        self::assertNotFalse($path);

        $image = imagecreatetruecolor(64, 64);
        self::assertNotFalse($image);
        imagejpeg($image, $path, 90);

        return new UploadedFile($path, 'avatar.jpg', 'image/jpeg', test: true);
    }
}
