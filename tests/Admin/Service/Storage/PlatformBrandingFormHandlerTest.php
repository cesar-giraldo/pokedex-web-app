<?php

declare(strict_types=1);

namespace App\Tests\Admin\Service\Storage;

use App\Admin\Service\Storage\ObjectStorage;
use App\Admin\Service\Storage\PlatformBrandingAsset;
use App\Admin\Service\Storage\PlatformBrandingFormHandler;
use App\Admin\Service\Storage\PlatformBrandingStorage;
use App\Admin\Service\Storage\PlatformBrandingUploadBatch;
use App\Admin\Service\Storage\PlatformBrandingUploadException;
use App\Entity\GeneralSettings;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function bin2hex;
use function fclose;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function unlink;

use const DIRECTORY_SEPARATOR;

#[CoversClass(PlatformBrandingFormHandler::class)]
#[CoversClass(PlatformBrandingUploadBatch::class)]
#[Group('unit')]
final class PlatformBrandingFormHandlerTest extends TestCase
{
    private string $tempDirectory = '';

    private PlatformBrandingStorage $storage;

    protected function setUp(): void
    {
        $this->tempDirectory = sys_get_temp_dir() . '/pokedex-branding-handler-' . bin2hex(random_bytes(8));
        mkdir($this->tempDirectory, 0o777, true);

        $this->storage = new PlatformBrandingStorage(
            new ObjectStorage(new Filesystem(new LocalFilesystemAdapter($this->tempDirectory))),
            'dev',
        );
    }

    protected function tearDown(): void
    {
        if ('' !== $this->tempDirectory && is_dir($this->tempDirectory)) {
            $this->removeDirectory($this->tempDirectory);
        }
    }

    public function testStageKeepsThePreviousFileUntilTheBatchDeletesIt(): void
    {
        $settings = GeneralSettings::createWithDefaults();
        $previousPath = $this->storage->allocateObjectKey(PlatformBrandingAsset::Logo);
        $this->storage->write($previousPath, $this->createUploadedFile());
        $settings->setPlatformLogo($previousPath);
        $settings->setPlatformIcon('dev/private/settings/branding/platform-icon/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.svg');

        $handler = new PlatformBrandingFormHandler($this->storage);
        $batch = $handler->handleFromForm($settings, $this->formWithFile(PlatformBrandingAsset::Logo));

        self::assertNotNull($settings->getPlatformLogo());
        self::assertNotSame($previousPath, $settings->getPlatformLogo());
        self::assertMatchesRegularExpression(
            '#^dev/private/settings/branding/platform-logo/[a-f0-9]{32}\.svg$#',
            (string) $settings->getPlatformLogo(),
        );
        self::assertSame(
            'dev/private/settings/branding/platform-icon/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.svg',
            $settings->getPlatformIcon(),
        );
        $newPath = (string) $settings->getPlatformLogo();
        fclose($this->storage->readStream($previousPath));
        fclose($this->storage->readStream($newPath));

        $batch->deleteReplaced();

        $this->expectException(RuntimeException::class);
        $this->storage->readStream($previousPath);
    }

    public function testStageKeepsThePreviousPathWhenWriteFails(): void
    {
        $settings = GeneralSettings::createWithDefaults();
        $previousPath = 'dev/private/settings/branding/platform-logo-dark/cccccccccccccccccccccccccccccccc.svg';
        $settings->setPlatformLogoDark($previousPath);

        $filesystem = $this->createStub(FilesystemOperator::class);
        $filesystem->method('writeStream')->willThrowException(UnableToWriteFile::atLocation('key'));
        $filesystem->method('fileExists')->willReturn(false);

        $handler = new PlatformBrandingFormHandler(
            new PlatformBrandingStorage(new ObjectStorage($filesystem), 'dev'),
        );

        try {
            $handler->handleFromForm($settings, $this->formWithFile(PlatformBrandingAsset::LogoDark));
            self::fail('Expected PlatformBrandingUploadException.');
        } catch (PlatformBrandingUploadException) {
        }

        self::assertSame($previousPath, $settings->getPlatformLogoDark());
    }

    public function testHandleFromFormRestoresEarlierUploadsWhenALaterWriteFails(): void
    {
        $settings = GeneralSettings::createWithDefaults()
            ->setPlatformName('Pokédex')
            ->setPlatformLogo('dev/private/settings/branding/platform-logo/dddddddddddddddddddddddddddddddd.svg');

        $writes = 0;
        $filesystem = $this->createStub(FilesystemOperator::class);
        $filesystem->method('writeStream')->willReturnCallback(static function () use (&$writes): void {
            ++$writes;

            if ($writes > 1) {
                throw UnableToWriteFile::atLocation('key');
            }
        });
        $filesystem->method('fileExists')->willReturn(false);

        $handler = new PlatformBrandingFormHandler(
            new PlatformBrandingStorage(new ObjectStorage($filesystem), 'dev'),
        );

        try {
            $handler->handleFromForm($settings, $this->formWithFile());
            self::fail('Expected PlatformBrandingUploadException.');
        } catch (PlatformBrandingUploadException) {
        }

        self::assertSame('Pokédex', $settings->getPlatformName());
        self::assertSame(
            'dev/private/settings/branding/platform-logo/dddddddddddddddddddddddddddddddd.svg',
            $settings->getPlatformLogo(),
        );
        self::assertNull($settings->getPlatformLogoDark());
    }

    public function testHandleFromFormIgnoresEmptyFileFields(): void
    {
        $settings = GeneralSettings::createWithDefaults()
            ->setPlatformLogo('dev/private/settings/branding/platform-logo/dddddddddddddddddddddddddddddddd.svg');

        $field = $this->createStub(FormInterface::class);
        $field->method('getData')->willReturn(null);

        $form = $this->createStub(FormInterface::class);
        $form->method('has')->willReturn(true);
        $form->method('get')->willReturn($field);

        $handler = new PlatformBrandingFormHandler($this->storage);

        $batch = $handler->handleFromForm($settings, $form);
        $batch->deleteReplaced();

        self::assertSame(
            'dev/private/settings/branding/platform-logo/dddddddddddddddddddddddddddddddd.svg',
            $settings->getPlatformLogo(),
        );
    }

    /**
     * @return FormInterface<mixed>
     */
    private function formWithFile(?PlatformBrandingAsset $asset = null): FormInterface
    {
        $field = $this->createStub(FormInterface::class);
        $field->method('getData')->willReturn($this->createUploadedFile());

        $form = $this->createStub(FormInterface::class);
        $form->method('has')->willReturnCallback(
            static function (string $fieldName) use ($asset): bool {
                if (!$asset instanceof PlatformBrandingAsset) {
                    return true;
                }

                return $fieldName === $asset->formField();
            },
        );
        $form->method('get')->willReturn($field);

        return $form;
    }

    private function createUploadedFile(): UploadedFile
    {
        $path = $this->tempDirectory . '/upload-' . bin2hex(random_bytes(4)) . '.svg';
        self::assertNotFalse(file_put_contents(
            $path,
            '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
        ));

        return new UploadedFile($path, 'logo.svg', 'image/svg+xml', test: true);
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
}
