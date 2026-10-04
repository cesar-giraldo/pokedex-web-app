<?php

declare(strict_types=1);

namespace App\Legal;

use App\Entity\Enum\LegalAcceptanceMethod;
use App\Entity\Enum\UserStatus;
use App\Entity\LegalDocumentVersion;
use App\Entity\LegalDocumentVersionTranslation;
use App\Entity\User;
use App\Entity\UserLegalAcceptance;
use App\Legal\Exception\LegalPublicationException;
use App\Repository\GeneralSettingsRepository;
use App\Repository\LegalDocumentVersionRepository;
use App\Repository\UserLegalAcceptanceRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

use function mb_substr;

final class LegalAcceptanceService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LegalDocumentVersionRepository $versionRepository,
        private readonly UserLegalAcceptanceRepository $acceptanceRepository,
        private readonly GeneralSettingsRepository $generalSettingsRepository,
        private readonly LegalLanguageResolver $languageResolver,
    ) {
    }

    /**
     * @return list<LegalDocumentVersion>
     */
    public function pendingVersions(User $user): array
    {
        if (UserStatus::Active !== $user->getStatus()) {
            return [];
        }

        $pending = [];

        foreach ($this->versionRepository->findPublishedRequiringAcceptance() as $version) {
            if (!$this->acceptanceRepository->hasAcceptance($user, $version)) {
                $pending[] = $version;
            }
        }

        return $pending;
    }

    public function hasPending(User $user): bool
    {
        return [] !== $this->pendingVersions($user);
    }

    public function currentPending(User $user): ?LegalDocumentVersion
    {
        $pending = $this->pendingVersions($user);

        return $pending[0] ?? null;
    }

    public function translationForUser(User $user, LegalDocumentVersion $version): ?LegalDocumentVersionTranslation
    {
        $settings = $this->generalSettingsRepository->getOrCreateSingleton();

        return $this->languageResolver->resolveTranslation(
            $version,
            $this->languageResolver->preferredForUser($user, $settings),
            $settings,
        );
    }

    public function acceptCurrent(User $user, ?string $ipAddress, ?string $userAgent): UserLegalAcceptance
    {
        $version = $this->currentPending($user);

        if (!$version instanceof LegalDocumentVersion) {
            throw new LegalPublicationException('No hay documentos pendientes de aceptación.');
        }

        $translation = $this->translationForUser($user, $version);

        if (!$translation instanceof LegalDocumentVersionTranslation) {
            throw new LegalPublicationException('No hay una traducción disponible para aceptar este documento.');
        }

        $acceptance = new UserLegalAcceptance(
            $user,
            $translation,
            LegalAcceptanceMethod::ForcedRedirect,
            new DateTime(),
            $this->limit($ipAddress, 45),
            $this->limit($userAgent, 512),
        );

        $this->entityManager->persist($acceptance);
        $this->entityManager->flush();

        return $acceptance;
    }

    private function limit(?string $value, int $max): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return mb_substr($value, 0, $max);
    }
}
