<?php

declare(strict_types=1);

namespace App\Legal;

use App\Entity\Enum\SupportedLanguage;
use App\Entity\GeneralSettings;
use App\Entity\LegalDocumentVersion;
use App\Entity\LegalDocumentVersionTranslation;
use App\Entity\User;

use function in_array;

final class LegalLanguageResolver
{
    public function preferredForUser(User $user, GeneralSettings $settings): SupportedLanguage
    {
        $language = $user->getLocale()->language();

        if (!in_array($language->value, $settings->getEnabledLanguages(), true)) {
            return $this->defaultLanguage($settings);
        }

        return $language;
    }

    public function preferredFromCode(?string $languageCode, GeneralSettings $settings): SupportedLanguage
    {
        $language = SupportedLanguage::tryFrom((string) $languageCode);

        if (!$language instanceof SupportedLanguage || !in_array($language->value, $settings->getEnabledLanguages(), true)) {
            return $this->defaultLanguage($settings);
        }

        return $language;
    }

    public function resolveTranslation(
        LegalDocumentVersion $version,
        SupportedLanguage $preferred,
        GeneralSettings $settings,
    ): ?LegalDocumentVersionTranslation {
        $direct = $version->findTranslation($preferred->value);

        if ($direct instanceof LegalDocumentVersionTranslation && $direct->isComplete()) {
            return $direct;
        }

        $fallback = $version->findTranslation($this->defaultLanguage($settings)->value);

        if ($fallback instanceof LegalDocumentVersionTranslation && $fallback->isComplete()) {
            return $fallback;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function enabledLanguageCodes(GeneralSettings $settings): array
    {
        $codes = [];

        foreach ($settings->getEnabledLanguages() as $languageCode) {
            if (SupportedLanguage::tryFrom($languageCode) instanceof SupportedLanguage) {
                $codes[] = $languageCode;
            }
        }

        return $codes;
    }

    public function defaultLanguage(GeneralSettings $settings): SupportedLanguage
    {
        return SupportedLanguage::tryFrom($settings->getWebsiteDefaultLanguage()) ?? SupportedLanguage::Spanish;
    }
}
