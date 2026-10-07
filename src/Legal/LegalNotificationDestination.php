<?php

declare(strict_types=1);

namespace App\Legal;

use App\Entity\Enum\LegalDocumentType;
use App\Entity\Enum\NotificationType;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentVersion;
use App\Entity\LegalDocumentVersionTranslation;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\GeneralSettingsRepository;
use App\Repository\LegalDocumentRepository;
use App\Repository\LegalDocumentVersionRepository;
use App\Repository\UserLegalAcceptanceRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function ctype_digit;
use function is_int;
use function is_string;

final class LegalNotificationDestination
{
    public function __construct(
        private readonly LegalDocumentRepository $documentRepository,
        private readonly LegalDocumentVersionRepository $versionRepository,
        private readonly UserLegalAcceptanceRepository $acceptanceRepository,
        private readonly GeneralSettingsRepository $generalSettingsRepository,
        private readonly LegalLanguageResolver $languageResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function urlForAcceptedVersion(User $user, Notification $notification): ?string
    {
        if (NotificationType::LegalVersionPublished !== $notification->getType()) {
            return null;
        }

        $version = $this->versionFromPayload($notification->getPayload());

        if (!$version instanceof LegalDocumentVersion || $version->isDraft()) {
            return null;
        }

        if (!$this->acceptanceRepository->hasAcceptance($user, $version)) {
            return null;
        }

        $settings = $this->generalSettingsRepository->getOrCreateSingleton();
        $language = $this->languageResolver->preferredForUser($user, $settings);

        if (!$this->languageResolver->resolveTranslation($version, $language, $settings) instanceof LegalDocumentVersionTranslation) {
            return null;
        }

        return $this->urlGenerator->generate('app_legal_version', [
            'type' => $version->getDocument()->getType()->value,
            'versionNumber' => $version->getVersionNumber(),
            'lang' => $language->value,
        ]);
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function versionFromPayload(?array $payload): ?LegalDocumentVersion
    {
        if (null === $payload) {
            return null;
        }

        $documentType = $payload['documentType'] ?? null;
        $versionNumber = $payload['versionNumber'] ?? null;

        if (!is_string($documentType)) {
            return null;
        }

        $type = LegalDocumentType::tryFrom($documentType);

        if (!$type instanceof LegalDocumentType) {
            return null;
        }

        if (is_string($versionNumber) && ctype_digit($versionNumber)) {
            $versionNumber = (int) $versionNumber;
        }

        if (!is_int($versionNumber) || $versionNumber < 1) {
            return null;
        }

        $document = $this->documentRepository->findOneByType($type);

        if (!$document instanceof LegalDocument) {
            return null;
        }

        return $this->versionRepository->findOneByDocumentAndNumber($document, $versionNumber);
    }
}
