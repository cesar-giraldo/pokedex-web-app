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
        $preferred = $user->getPreferredLanguage();

        if ($preferred instanceof SupportedLanguage && in_array($preferred->value, $settings->getEnabledLanguages(), true)) {
            return $preferred;
        }

        return $this->enabledDefaultLanguage($settings);
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

    private function enabledDefaultLanguage(GeneralSettings $settings): SupportedLanguage
    {
        $candidate = SupportedLanguage::tryFrom($settings->getWebsiteDefaultLanguage());

        if ($candidate instanceof SupportedLanguage && in_array($candidate->value, $settings->getEnabledLanguages(), true)) {
            return $candidate;
        }

        foreach ($settings->getEnabledLanguages() as $languageCode) {
            $language = SupportedLanguage::tryFrom($languageCode);

            if ($language instanceof SupportedLanguage) {
                return $language;
            }
        }

        return SupportedLanguage::Spanish;
    }
}
