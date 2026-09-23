<?php

declare(strict_types=1);

namespace App\Admin\Twig\Extensions;

use App\Admin\Service\Storage\ImageVariant;
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

    public function resolveUrl(?User $user, string $variant = 'avatar'): ?string
    {
        if (!$user instanceof User || null === $user->getId()) {
            return null;
        }

        $profileImagePath = $user->getProfileImagePath();
        if (null === $profileImagePath || '' === $profileImagePath) {
            return null;
        }

        $imageVariant = ImageVariant::tryFrom($variant);
        if (!$imageVariant instanceof ImageVariant || !$imageVariant->isAllowedForProfile()) {
            return null;
        }

        $parameters = ['id' => $user->getId()];
        if (ImageVariant::Avatar !== $imageVariant) {
            $parameters['variant'] = $imageVariant->value;
        }

        return $this->urlGenerator->generate('app_backend_user_profile_image', $parameters);
    }
}
