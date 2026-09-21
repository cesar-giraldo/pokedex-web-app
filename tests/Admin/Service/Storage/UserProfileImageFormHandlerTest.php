<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\Storage;

use App\Admin\Service\Storage\ObjectStorage;
use App\Admin\Service\Storage\UserProfileImageFormHandler;
use App\Admin\Service\Storage\UserProfileImageStorage;
use App\Admin\Service\Storage\UserProfileImageUploadException;
use App\Entity\User;
use App\Tests\Admin\Support\ImageStorageFactory;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use const DIRECTORY_SEPARATOR;

#[CoversClass(UserProfileImageFormHandler::class)]
#[Group('unit')]
final class UserProfileImageFormHandlerTest extends TestCase
{
    private string $tempDirectory = '';

    private UserProfileImageStorage $profileImageStorage;

    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;

    private UserProfileImageFormHandler $handler;

    protected function setUp(): void
    {
        $this->tempDirectory = sys_get_temp_dir() . '/pokedex-profile-image-' . bin2hex(random_bytes(8));
        mkdir($this->tempDirectory, 0o777, true);

        $this->profileImageStorage = ImageStorageFactory::profile(
            new ObjectStorage(new Filesystem(new LocalFilesystemAdapter($this->tempDirectory))),
        );
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->handler = new UserProfileImageFormHandler($this->profileImageStorage, $this->entityManager);
    }

    protected function tearDown(): void
    {
        if ('' !== $this->tempDirectory && is_dir($this->tempDirectory)) {
            $this->removeDirectory($this->tempDirectory);
        }
    }

    public function testUploadReplacesPreviousImage(): void
    {
        $user = $this->createUser(7);
        $previousPath = $this->profileImageStorage->upload($user, $this->createUploadedFile());
        $user->setProfileImagePath($previousPath);

        $this->entityManager->expects(self::once())->method('flush');

        $this->handler->handle($user, $this->createUploadedFile(), false, true);

        self::assertNotNull($user->getProfileImagePath());
        self::assertNotSame($previousPath, $user->getProfileImagePath());
        self::assertMatchesRegularExpression('#^dev/private/user/profile-images/7/[a-f0-9]{32}\.jpg$#', $user->getProfileImagePath());

        $this->expectException(RuntimeException::class);
        $this->profileImageStorage->readStream($previousPath);
    }

    public function testReplaceRestoresPreviousPathWhenWriteFails(): void
    {
        $user = $this->createUser(7);
        $previousPath = 'dev/private/user/profile-images/7/old.jpg';
        $user->setProfileImagePath($previousPath);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('flush');

        $handler = new UserProfileImageFormHandler($this->createFailingWriteStorage(), $entityManager);

        try {
            $handler->handle($user, $this->createUploadedFile(), false, true);
            self::fail('Expected UserProfileImageUploadException.');
        } catch (UserProfileImageUploadException) {
        }

        self::assertSame($previousPath, $user->getProfileImagePath());
    }

    public function testRemoveClearsProfileImagePath(): void
    {
        $user = $this->createUser(8);
        $objectKey = $this->profileImageStorage->upload($user, $this->createUploadedFile());
        $user->setProfileImagePath($objectKey);

        $this->entityManager->expects(self::once())->method('flush');

        $this->handler->handle($user, null, true, true);

        self::assertNull($user->getProfileImagePath());
    }

    public function testRemoveDoesNotClearPathWhenStorageDeleteFails(): void
    {
        $user = $this->createUser(8);
        $objectKey = 'dev/private/user/profile-images/8/file.jpg';
        $user->setProfileImagePath($objectKey);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $handler = new UserProfileImageFormHandler($this->createFailingDeleteStorage(), $entityManager);

        try {
            $handler->handle($user, null, true, true);
            self::fail('Expected UserProfileImageUploadException.');
        } catch (UserProfileImageUploadException) {
        }

        self::assertSame($objectKey, $user->getProfileImagePath());
    }

    public function testUploadHasPriorityOverRemoveFlag(): void
    {
        $user = $this->createUser(9);

        $this->entityManager->expects(self::once())->method('flush');

        $this->handler->handle($user, $this->createUploadedFile(), true, true);

        self::assertNotNull($user->getProfileImagePath());
        self::assertMatchesRegularExpression('#^dev/private/user/profile-images/9/[a-f0-9]{32}\.jpg$#', $user->getProfileImagePath());
    }

    private function removeDirectory(string $directory): void
    {
        $items = scandir($directory);
        if (false === $items) {
            return;
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }

    private function createUser(int $id): User
    {
        $user = new User();
        $user->setName('Misty');
        $user->setLastname('Waterflower');
        $user->setNickname('misty-' . $id);

        $reflection = new ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, $id);

        return $user;
    }

    private function createFailingWriteStorage(): UserProfileImageStorage
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('writeStream')->willThrowException(UnableToWriteFile::atLocation('key'));
        $filesystem->method('fileExists')->willReturn(false);

        return ImageStorageFactory::profile(new ObjectStorage($filesystem));
    }

    private function createFailingDeleteStorage(): UserProfileImageStorage
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('fileExists')->willReturn(true);
        $filesystem->method('delete')->willThrowException(UnableToDeleteFile::atLocation('key'));

        return ImageStorageFactory::profile(new ObjectStorage($filesystem));
    }

    private function createUploadedFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'profile-image-');
        self::assertNotFalse($path);

        file_put_contents(
            $path,
            base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQP/AABEIAAEAAQMBIgACEQEDEQH/xABTAAEBAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/xAAUAQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGfAP/AABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEBAD8Af//Z'),
        );

        return new UploadedFile($path, 'avatar.jpg', 'image/jpeg', test: true);
    }
}
