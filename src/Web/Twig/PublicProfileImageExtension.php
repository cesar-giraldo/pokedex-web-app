<?php

declare(strict_types=1);

namespace App\Web\Twig;

use App\Admin\Service\Storage\ImageVariant;
use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function hash;
use function is_string;
use function substr;

final class PublicProfileImageExtension extends AbstractExtension
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('public_profile_image_url', $this->resolveUrl(...)),
        ];
    }

    /**
     * The public image route stays the same when the file is replaced, and the
     * response is cached for an hour. The version follows the stored object key,
     * which changes on every upload, so the browser fetches the new file.
     */
    public function resolveUrl(?User $user, string $variant = 'avatar'): ?string
    {
        if (!$user instanceof User) {
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

        $locale = $this->requestStack->getCurrentRequest()?->getLocale();
        if (!is_string($locale) || '' === $locale) {
            return null;
        }

        $parameters = [
            '_locale' => $locale,
            'v' => substr(hash('sha256', $profileImagePath), 0, 16),
        ];

        if (ImageVariant::Avatar === $imageVariant) {
            return $this->urlGenerator->generate('app_public_profile_avatar', $parameters);
        }

        $parameters['variant'] = $imageVariant->value;

        return $this->urlGenerator->generate('app_public_profile_image', $parameters);
    }
}
