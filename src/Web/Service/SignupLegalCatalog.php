<?php

declare(strict_types=1);

namespace App\Web\Service;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\LegalDocumentType;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentVersion;
use App\Entity\LegalDocumentVersionTranslation;
use App\Legal\LegalLanguageResolver;
use App\Repository\LegalDocumentRepository;
use App\Repository\LegalDocumentVersionRepository;
use App\Web\Registration\SignupLegalOffer;

final class SignupLegalCatalog
{
    public function __construct(
        private readonly LegalDocumentRepository $documents,
        private readonly LegalDocumentVersionRepository $versions,
        private readonly LegalLanguageResolver $languages,
        private readonly GeneralSettingsProvider $settings,
    ) {
    }

    /**
     * @return list<SignupLegalOffer>
     */
    public function available(SupportedLanguage $language): array
    {
        $settings = $this->settings->get();
        $offers = [];

        foreach (LegalDocumentType::cases() as $type) {
            $document = $this->documents->findOneByType($type);

            if (!$document instanceof LegalDocument) {
                continue;
            }

            $version = $this->versions->findPublished($document);

            if (!$version instanceof LegalDocumentVersion) {
                continue;
            }

            $translation = $this->languages->resolveTranslation($version, $language, $settings);

            if (!$translation instanceof LegalDocumentVersionTranslation || !$this->canBeAccepted($translation)) {
                continue;
            }

            $offers[] = new SignupLegalOffer($type, $translation);
        }

        usort(
            $offers,
            static fn (SignupLegalOffer $left, SignupLegalOffer $right): int => $left->type->acceptanceOrder() <=> $right->type->acceptanceOrder(),
        );

        return $offers;
    }

    private function canBeAccepted(LegalDocumentVersionTranslation $translation): bool
    {
        $hash = $translation->getContentHash();

        return $translation->isComplete() && null !== $hash && '' !== $hash;
    }
}
