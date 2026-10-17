<?php

declare(strict_types=1);

namespace App\Legal;

use App\Entity\Enum\UserRole;
use App\Entity\User;
use App\Repository\GeneralSettingsRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class LegalAcceptanceLink
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LegalLanguageResolver $languageResolver,
        private readonly GeneralSettingsRepository $generalSettingsRepository,
    ) {
    }

    public function urlFor(User $user): string
    {
        if (!$user->hasAssignedRole(UserRole::User)) {
            return $this->urlGenerator->generate('app_backend_legal_accept');
        }

        $settings = $this->generalSettingsRepository->getOrCreateSingleton();
        $language = $this->languageResolver->preferredForUser($user, $settings);

        return $this->urlGenerator->generate('app_public_legal_accept', [
            '_locale' => $language->value,
        ]);
    }
}
