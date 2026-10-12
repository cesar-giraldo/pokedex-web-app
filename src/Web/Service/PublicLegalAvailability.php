<?php

declare(strict_types=1);

namespace App\Web\Service;

use App\Entity\Enum\LegalDocumentType;
use App\Repository\LegalDocumentVersionRepository;

use function in_array;

final class PublicLegalAvailability
{
    public function __construct(
        private readonly LegalDocumentVersionRepository $versions,
    ) {
    }

    /**
     * @return array{privacy: bool, terms: bool}
     */
    public function links(): array
    {
        $published = $this->versions->findPublishedDocumentTypes();

        return [
            'privacy' => in_array(LegalDocumentType::PrivacyPolicy, $published, true),
            'terms' => in_array(LegalDocumentType::TermsOfUse, $published, true),
        ];
    }
}
