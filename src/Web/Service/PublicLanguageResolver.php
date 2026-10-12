<?php

declare(strict_types=1);

namespace App\Web\Service;

use App\Entity\Enum\SupportedLanguage;
use App\Entity\GeneralSettings;

use function in_array;

final class PublicLanguageResolver
{
    public function defaultLanguage(GeneralSettings $settings): SupportedLanguage
    {
        $candidate = SupportedLanguage::tryFrom($settings->getWebsiteDefaultLanguage());

        if ($candidate instanceof SupportedLanguage && $this->isEnabled($candidate->value, $settings)) {
            return $candidate;
        }

        foreach ($this->enabled($settings) as $language) {
            return $language;
        }

        return SupportedLanguage::Spanish;
    }

    public function isEnabled(string $code, GeneralSettings $settings): bool
    {
        return SupportedLanguage::tryFrom($code) instanceof SupportedLanguage
            && in_array($code, $settings->getEnabledLanguages(), true);
    }

    public function resolve(?string $code, GeneralSettings $settings): SupportedLanguage
    {
        $language = SupportedLanguage::tryFrom((string) $code);

        if ($language instanceof SupportedLanguage && $this->isEnabled($language->value, $settings)) {
            return $language;
        }

        return $this->defaultLanguage($settings);
    }

    /**
     * @return list<SupportedLanguage>
     */
    public function enabled(GeneralSettings $settings): array
    {
        return SupportedLanguage::fromStoredValues($settings->getEnabledLanguages());
    }
}
