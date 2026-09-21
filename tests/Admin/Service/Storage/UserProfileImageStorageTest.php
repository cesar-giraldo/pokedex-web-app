<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\Storage;

use App\Admin\Service\Storage\ImageVariant;
use App\Admin\Service\Storage\ObjectStorage;
use App\Admin\Service\Storage\UserProfileImageStorage;
use App\Entity\User;
use App\Tests\Admin\Support\ImageStorageFactory;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use const DIRECTORY_SEPARATOR;

#[CoversClass(UserProfileImageStorage::class)]
#[Group('unit')]
final class UserProfileImageStorageTest extends TestCase
{
    private string $tempDirectory = '';

    private ObjectStorage $objectStorage;

    private UserProfileImageStorage $profileImageStorage;

    protected function setUp(): void
    {
        $this->tempDirectory = sys_get_temp_dir() . '/pokedex-profile-image-' . bin2hex(random_bytes(8));
        mkdir($this->tempDirectory, 0o777, true);

        $this->objectStorage = new ObjectStorage(new Filesystem(new LocalFilesystemAdapter($this->tempDirectory)));
        $this->profileImageStorage = ImageStorageFactory::profile($this->objectStorage);
    }

    protected function tearDown(): void
    {
        if ('' !== $this->tempDirectory && is_dir($this->tempDirectory)) {
            $this->removeDirectory($this->tempDirectory);
        }
    }

    public function testUploadStoresOriginalAndGeneratedVariants(): void
    {
        $user = $this->createUser(42);
        $objectKey = $this->profileImageStorage->upload($user, $this->createUploadedFile());

        self::assertMatchesRegularExpression('#^dev/private/user/profile-images/42/[a-f0-9]{32}\.jpg$#', $objectKey);
        self::assertTrue($this->objectStorage->fileExists($objectKey));
        self::assertTrue($this->objectStorage->fileExists(ImageVariant::Avatar->objectKey($objectKey)));
        self::assertTrue($this->objectStorage->fileExists(ImageVariant::Display->objectKey($objectKey)));

        $stream = $this->profileImageStorage->readStream($objectKey);
        try {
            self::assertNotSame('', stream_get_contents($stream));
        } finally {
            fclose($stream);
        }
    }

    public function testUploadRequiresPersistedUser(): void
    {
        $this->expectException(RuntimeException::class);

        $this->profileImageStorage->upload(new User(), $this->createUploadedFile());
    }

    public function testDeleteIgnoresMissingPath(): void
    {
        $this->expectNotToPerformAssertions();

        $this->profileImageStorage->delete(null);
        $this->profileImageStorage->delete('');
    }

    public function testDeleteRemovesOriginalAndVariants(): void
    {
        $user = $this->createUser(1);
        $objectKey = $this->profileImageStorage->upload($user, $this->createUploadedFile());
        $avatarKey = ImageVariant::Avatar->objectKey($objectKey);

        $this->profileImageStorage->delete($objectKey);

        self::assertFalse($this->objectStorage->fileExists($objectKey));
        self::assertFalse($this->objectStorage->fileExists($avatarKey));

        $this->expectException(RuntimeException::class);
        $this->profileImageStorage->readStream($objectKey);
    }

    public function testResolveMimeTypeFromExtensionAndVariant(): void
    {
        self::assertSame(
            'image/jpeg',
            $this->profileImageStorage->resolveMimeType('dev/private/user/profile-images/1/file.jpg'),
        );
        self::assertSame(
            'image/png',
            $this->profileImageStorage->resolveMimeType('dev/private/user/profile-images/1/file.png'),
        );
        self::assertSame(
            'image/webp',
            $this->profileImageStorage->resolveMimeType('dev/private/user/profile-images/1/file.jpg', ImageVariant::Avatar),
        );
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
        $user->setName('Ash');
        $user->setLastname('Ketchum');
        $user->setNickname('ash-' . $id);

        $reflection = new ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, $id);

        return $user;
    }

    private function createUploadedFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'profile-image-');
        self::assertNotFalse($path);

        $image = imagecreatetruecolor(32, 32);
        self::assertNotFalse($image);
        imagejpeg($image, $path, 90);

        return new UploadedFile($path, 'avatar.jpg', 'image/jpeg', test: true);
    }
}
