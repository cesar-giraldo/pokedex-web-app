<?php

declare(strict_types=1);

namespace App\Legal;

use App\Entity\Enum\LegalDocumentType;
use App\Entity\Enum\NotificationType;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserStatus;
use App\Entity\GeneralSettings;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentVersion;
use App\Entity\LegalDocumentVersionTranslation;
use App\Entity\User;
use App\Legal\Exception\LegalPublicationException;
use App\Notification\NotificationService;
use App\Repository\GeneralSettingsRepository;
use App\Repository\LegalDocumentRepository;
use App\Repository\LegalDocumentVersionRepository;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function implode;
use function trim;

final class LegalDocumentPublisher
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LegalDocumentRepository $documentRepository,
        private readonly LegalDocumentVersionRepository $versionRepository,
        private readonly UserRepository $userRepository,
        private readonly GeneralSettingsRepository $generalSettingsRepository,
        private readonly LegalLanguageResolver $languageResolver,
        private readonly LegalContentSanitizer $contentSanitizer,
        private readonly NotificationService $notificationService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function ensureDocuments(): void
    {
        $created = false;

        foreach (LegalDocumentType::cases() as $type) {
            if ($this->documentRepository->findOneByType($type) instanceof LegalDocument) {
                continue;
            }

            $this->entityManager->persist(new LegalDocument($type));
            $created = true;
        }

        if ($created) {
            $this->entityManager->flush();
        }
    }

    public function startDraft(LegalDocument $document, User $actor): LegalDocumentVersion
    {
        if ($this->versionRepository->findDraft($document) instanceof LegalDocumentVersion) {
            throw new LegalPublicationException('Ya existe un borrador para este documento. Edítalo o publícalo antes de crear otro.');
        }

        $settings = $this->generalSettingsRepository->getOrCreateSingleton();
        $draft = new LegalDocumentVersion($document, $this->versionRepository->nextVersionNumber($document));
        $draft->setCreatedBy($actor);

        $published = $this->versionRepository->findPublished($document);

        foreach ($this->languageResolver->enabledLanguageCodes($settings) as $languageCode) {
            $translation = new LegalDocumentVersionTranslation($draft, $languageCode);
            $source = $published?->findTranslation($languageCode);

            if (!$source instanceof LegalDocumentVersionTranslation && $published instanceof LegalDocumentVersion) {
                $source = $published->findTranslation($this->languageResolver->defaultLanguage($settings)->value);
            }

            if ($source instanceof LegalDocumentVersionTranslation) {
                $translation->copyContentFrom($source);
            }
        }

        $this->entityManager->persist($draft);
        $this->entityManager->flush();

        return $draft;
    }

    /**
     * @param array<string, array{title?: string, contentHtml?: string, acceptanceLabel?: string, summary?: string|null}> $translations
     */
    public function updateDraft(LegalDocumentVersion $draft, array $translations): void
    {
        if (!$draft->isDraft()) {
            throw new LegalPublicationException('Solo se pueden editar borradores.');
        }

        $settings = $this->generalSettingsRepository->getOrCreateSingleton();
        $this->applyTranslations($draft, $translations, $settings, false);
        $this->entityManager->flush();
    }

    public function deleteDraft(LegalDocumentVersion $version): void
    {
        if (!$version->isDraft()) {
            throw new LegalPublicationException('Solo se puede eliminar un borrador.');
        }

        $this->entityManager->remove($version);
        $this->entityManager->flush();
    }

    public function publish(
        LegalDocumentVersion $draft,
        bool $requiresReacceptance,
        ?string $notificationTitle,
        ?string $notificationMessage,
        ?User $actor,
    ): void {
        if (!$draft->isDraft()) {
            throw new LegalPublicationException('Solo se puede publicar un borrador.');
        }

        $settings = $this->generalSettingsRepository->getOrCreateSingleton();
        $missing = $this->missingLanguages($draft, $settings);

        if ([] !== $missing) {
            throw new LegalPublicationException('Faltan traducciones completas para: ' . implode(', ', $missing) . '.');
        }

        if (0 === $this->versionRepository->countReleased($draft->getDocument()) && !$requiresReacceptance) {
            throw new LegalPublicationException('La primera versión publicada debe exigir aceptación.');
        }

        $notificationTitle = $this->blankToNull($notificationTitle);
        $notificationMessage = $this->blankToNull($notificationMessage);

        if ($requiresReacceptance && (null === $notificationTitle || null === $notificationMessage)) {
            throw new LegalPublicationException('Indica el título y el mensaje de la notificación.');
        }

        $current = $this->versionRepository->findPublished($draft->getDocument());
        $current?->archive();
        $draft->publish($requiresReacceptance, $notificationTitle, $notificationMessage, new DateTime());
        $this->entityManager->flush();

        if (!$requiresReacceptance) {
            return;
        }

        $this->notificationService->notify(
            recipients: $this->userRepository->findByStatus(UserStatus::Active),
            type: NotificationType::LegalVersionPublished,
            title: (string) $notificationTitle,
            message: (string) $notificationMessage,
            actionUrl: $this->urlGenerator->generate('app_backend_legal_accept'),
            actor: $actor,
            payload: [
                'documentType' => $draft->getDocument()->getType()->value,
                'versionNumber' => $draft->getVersionNumber(),
            ],
        );
    }

    /**
     * @param array<string, array{title?: string, contentHtml?: string, acceptanceLabel?: string, summary?: string|null}> $translations
     */
    public function completeMissingLanguages(LegalDocumentVersion $version, array $translations): void
    {
        if (!$version->isPublished()) {
            throw new LegalPublicationException('Solo se pueden completar idiomas de la versión vigente.');
        }

        $settings = $this->generalSettingsRepository->getOrCreateSingleton();
        $allowed = [];

        foreach ($this->missingLanguages($version, $settings) as $languageCode) {
            $allowed[$languageCode] = true;
        }

        if ([] === $allowed) {
            throw new LegalPublicationException('Esta versión ya cubre todos los idiomas habilitados.');
        }

        $filtered = [];

        foreach ($translations as $languageCode => $payload) {
            if (isset($allowed[$languageCode])) {
                $filtered[$languageCode] = $payload;
            }
        }

        if ([] === $filtered) {
            throw new LegalPublicationException('No hay idiomas nuevos para guardar.');
        }

        $this->applyTranslations($version, $filtered, $settings, true);

        foreach ($filtered as $languageCode => $payload) {
            $translation = $version->findTranslation($languageCode);

            if ($translation instanceof LegalDocumentVersionTranslation && $translation->isComplete()) {
                $translation->refreshContentHash();
            }
        }

        $this->entityManager->flush();
    }

    /**
     * @return list<string>
     */
    public function missingLanguages(LegalDocumentVersion $version, GeneralSettings $settings): array
    {
        $missing = [];

        foreach ($this->languageResolver->enabledLanguageCodes($settings) as $languageCode) {
            $translation = $version->findTranslation($languageCode);

            if (!$translation instanceof LegalDocumentVersionTranslation || !$translation->isComplete()) {
                $missing[] = $languageCode;
            }
        }

        return $missing;
    }

    public function defaultNotificationTitle(LegalDocumentVersion $version): string
    {
        return $version->getDocument()->getType()->defaultNotificationTitle($version->getVersionNumber());
    }

    public function defaultNotificationMessage(LegalDocumentVersion $version): string
    {
        return $version->getDocument()->getType()->defaultNotificationMessage();
    }

    /**
     * @param array<string, array{title?: string, contentHtml?: string, acceptanceLabel?: string, summary?: string|null}> $translations
     */
    private function applyTranslations(
        LegalDocumentVersion $version,
        array $translations,
        GeneralSettings $settings,
        bool $onlyMissing,
    ): void {
        foreach ($this->languageResolver->enabledLanguageCodes($settings) as $languageCode) {
            if (!isset($translations[$languageCode])) {
                continue;
            }

            $existing = $version->findTranslation($languageCode);

            if ($onlyMissing && $existing instanceof LegalDocumentVersionTranslation && $existing->isComplete()) {
                continue;
            }

            if (!$existing instanceof LegalDocumentVersionTranslation) {
                if (!SupportedLanguage::tryFrom($languageCode) instanceof SupportedLanguage) {
                    continue;
                }

                $existing = new LegalDocumentVersionTranslation($version, $languageCode);
            }

            $payload = $translations[$languageCode];
            $existing
                ->setTitle((string) ($payload['title'] ?? ''))
                ->setContentHtml($this->contentSanitizer->sanitize((string) ($payload['contentHtml'] ?? '')))
                ->setAcceptanceLabel((string) ($payload['acceptanceLabel'] ?? ''))
                ->setSummary($payload['summary'] ?? null);
        }
    }

    private function blankToNull(?string $value): ?string
    {
        $value = null !== $value ? trim($value) : null;

        return null === $value || '' === $value ? null : $value;
    }
}
