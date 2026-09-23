<?php

declare(strict_types=1);

namespace App\Tests\Admin\Twig\Extensions;

use App\Admin\Twig\Extensions\UserProfileImageExtension;
use App\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(UserProfileImageExtension::class)]
#[Group('unit')]
final class UserProfileImageExtensionTest extends TestCase
{
    public function testRegistersTwigFunction(): void
    {
        $extension = new UserProfileImageExtension($this->createMock(UrlGeneratorInterface::class));
        $functions = $extension->getFunctions();

        self::assertCount(1, $functions);
        self::assertSame('user_profile_image_url', $functions[0]->getName());
    }

    public function testResolveUrlReturnsNullWithoutImage(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::never())->method('generate');

        $extension = new UserProfileImageExtension($urlGenerator);

        self::assertNull($extension->resolveUrl(null));
        self::assertNull($extension->resolveUrl($this->createUser(4, null)));
    }

    public function testResolveUrlOmitsVariantForDefaultAvatar(): void
    {
        $user = $this->createUser(9, 'dev/private/user/profile-images/9/file.jpg');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_backend_user_profile_image', ['id' => 9])
            ->willReturn('/admin/media/user-profile/9');

        $extension = new UserProfileImageExtension($urlGenerator);

        self::assertSame('/admin/media/user-profile/9', $extension->resolveUrl($user));
    }

    public function testResolveUrlIncludesVariantForOriginal(): void
    {
        $user = $this->createUser(9, 'dev/private/user/profile-images/9/file.jpg');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_backend_user_profile_image', ['id' => 9, 'variant' => 'original'])
            ->willReturn('/admin/media/user-profile/9/original');

        $extension = new UserProfileImageExtension($urlGenerator);

        self::assertSame('/admin/media/user-profile/9/original', $extension->resolveUrl($user, 'original'));
    }

    public function testResolveUrlReturnsNullForDisallowedVariant(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::never())->method('generate');

        $extension = new UserProfileImageExtension($urlGenerator);

        self::assertNull($extension->resolveUrl(
            $this->createUser(3, 'dev/private/user/profile-images/3/file.jpg'),
            'thumb',
        ));
    }

    private function createUser(int $id, ?string $profileImagePath): User
    {
        $user = new User();
        $user->setName('Brock');
        $user->setLastname('Harrison');
        $user->setNickname('brock-' . $id);
        $user->setProfileImagePath($profileImagePath);

        $reflection = new ReflectionProperty(User::class, 'id');
        $reflection->setValue($user, $id);

        return $user;
    }
}
