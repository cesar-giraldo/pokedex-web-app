<?php

declare(strict_types=1);

namespace App\Web\Registration;

use App\Entity\Enum\LegalDocumentType;
use App\Entity\LegalDocumentVersionTranslation;

final readonly class SignupLegalOffer
{
    public function __construct(
        public LegalDocumentType $type,
        public LegalDocumentVersionTranslation $translation,
    ) {
    }

    public function isPrivacy(): bool
    {
        return LegalDocumentType::PrivacyPolicy === $this->type;
    }

    public function isTerms(): bool
    {
        return LegalDocumentType::TermsOfUse === $this->type;
    }

    public function routeName(): string
    {
        return match ($this->type) {
            LegalDocumentType::PrivacyPolicy => 'app_public_privacy',
            LegalDocumentType::TermsOfUse => 'app_public_terms',
        };
    }
}
