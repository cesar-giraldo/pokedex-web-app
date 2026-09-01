<?php

declare(strict_types=1);

namespace App\Admin\Twig\Extensions;

use App\Entity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class UserProfileImageExtension extends AbstractExtension
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('user_profile_image_url', $this->resolveUrl(...)),
        ];
    }

    public function resolveUrl(?User $user): ?string
    {
        if (!$user instanceof User || null === $user->getId()) {
            return null;
        }

        $profileImagePath = $user->getProfileImagePath();
        if (null === $profileImagePath || '' === $profileImagePath) {
            return null;
        }

        return $this->urlGenerator->generate(
            'app_backend_user_profile_image',
            ['id' => $user->getId()],
        );
    }
}
