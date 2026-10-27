<?php

declare(strict_types=1);

namespace App\Notification\Email;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\User;
use App\Legal\LegalLanguageResolver;

use function in_array;
use function trim;

final class PasswordResetLanguage
{
    public function __construct(
        private readonly LegalLanguageResolver $legalLanguageResolver,
        private readonly GeneralSettingsProvider $generalSettingsProvider,
    ) {
    }

    public function resolve(User $user, SupportedLanguage $requestLanguage): SupportedLanguage
    {
        $settings = $this->generalSettingsProvider->get();
        $enabled = $settings->getEnabledLanguages();
        $preferred = $user->getPreferredLanguage();

        if ($preferred instanceof SupportedLanguage && in_array($preferred->value, $enabled, true)) {
            return $preferred;
        }

        if (in_array($requestLanguage->value, $enabled, true)) {
            return $requestLanguage;
        }

        return $this->legalLanguageResolver->preferredForUser($user, $settings);
    }

    public function recipientName(User $user, string $emailAddress): string
    {
        $name = trim($user->getName() . ' ' . $user->getLastname());

        return '' !== $name ? $name : $emailAddress;
    }
}
