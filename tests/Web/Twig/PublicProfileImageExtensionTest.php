<?php

declare(strict_types=1);

namespace App\Tests\Web\Twig;

use App\Entity\User;
use App\Web\Twig\PublicProfileImageExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function hash;
use function substr;

#[CoversClass(PublicProfileImageExtension::class)]
#[Group('unit')]
final class PublicProfileImageExtensionTest extends TestCase
{
    public function testRegistersTwigFunction(): void
    {
        $extension = new PublicProfileImageExtension(
            $this->createStub(UrlGeneratorInterface::class),
            new RequestStack(),
        );
        $functions = $extension->getFunctions();

        self::assertCount(1, $functions);
        self::assertSame('public_profile_image_url', $functions[0]->getName());
    }

    public function testResolveUrlReturnsNullWithoutImageOrLocale(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::never())->method('generate');

        $extension = new PublicProfileImageExtension($urlGenerator, new RequestStack());

        self::assertNull($extension->resolveUrl(null));
        self::assertNull($extension->resolveUrl($this->createUser(null)));
        self::assertNull($extension->resolveUrl($this->createUser('dev/private/user/profile-images/1/file.jpg')));
    }

    public function testResolveUrlVersionsAvatarFromTheStoredPath(): void
    {
        $path = 'dev/private/user/profile-images/9/file.jpg';
        $version = substr(hash('sha256', $path), 0, 16);
        $requestStack = new RequestStack();
        $request = new Request();
        $request->setLocale('en');
        $requestStack->push($request);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_public_profile_avatar', ['_locale' => 'en', 'v' => $version])
            ->willReturn('/en/profile/avatar?v=' . $version);

        $extension = new PublicProfileImageExtension($urlGenerator, $requestStack);

        self::assertSame('/en/profile/avatar?v=' . $version, $extension->resolveUrl($this->createUser($path)));
    }

    public function testResolveUrlChangesWhenTheStoredPathChanges(): void
    {
        $requestStack = new RequestStack();
        $request = new Request();
        $request->setLocale('es');
        $requestStack->push($request);

        /** @var list<string> $versions */
        $versions = [];
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::exactly(3))
            ->method('generate')
            ->willReturnCallback(static function (string $route, array $parameters) use (&$versions): string {
                /** @var array<string, mixed> $parameters */
                $version = $parameters['v'] ?? null;
                self::assertIsString($version);
                $versions[] = $version;

                return '/' . $route . '?v=' . $version;
            });

        $extension = new PublicProfileImageExtension($urlGenerator, $requestStack);
        $first = $extension->resolveUrl($this->createUser('dev/private/user/profile-images/1/aaaa.jpg'));
        $again = $extension->resolveUrl($this->createUser('dev/private/user/profile-images/1/aaaa.jpg'));
        $replaced = $extension->resolveUrl($this->createUser('dev/private/user/profile-images/1/bbbb.jpg'), 'display');

        self::assertSame($first, $again);
        self::assertNotSame($versions[0], $versions[2]);
        self::assertIsString($replaced);
        self::assertStringContainsString('app_public_profile_image', $replaced);
    }

    public function testResolveUrlReturnsNullForDisallowedVariant(): void
    {
        $requestStack = new RequestStack();
        $request = new Request();
        $request->setLocale('en');
        $requestStack->push($request);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::never())->method('generate');

        $extension = new PublicProfileImageExtension($urlGenerator, $requestStack);

        self::assertNull($extension->resolveUrl(
            $this->createUser('dev/private/user/profile-images/3/file.jpg'),
            'thumb',
        ));
    }

    private function createUser(?string $profileImagePath): User
    {
        $user = new User();
        $user->setProfileImagePath($profileImagePath);

        return $user;
    }
}
