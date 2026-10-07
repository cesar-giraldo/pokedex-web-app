<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Entity\Enum\LegalDocumentType;
use App\Entity\Enum\LegalDocumentVersionStatus;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentVersion;
use App\Legal\LegalLanguageResolver;
use App\Repository\GeneralSettingsRepository;
use App\Repository\LegalDocumentRepository;
use App\Repository\LegalDocumentVersionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/legal')]
final class LegalController extends AbstractController
{
    public function __construct(
        private readonly LegalDocumentRepository $documentRepository,
        private readonly LegalDocumentVersionRepository $versionRepository,
        private readonly LegalLanguageResolver $languageResolver,
        private readonly GeneralSettingsRepository $generalSettingsRepository,
    ) {
    }

    #[Route('/{type}', name: 'app_legal_current', methods: ['GET'], requirements: ['type' => 'privacy_policy|terms_of_use'])]
    public function current(Request $request, string $type): Response
    {
        $document = $this->document($type);
        $version = $this->versionRepository->findPublished($document);

        if (!$version instanceof LegalDocumentVersion) {
            throw $this->createNotFoundException();
        }

        return $this->renderDocument($request, $document, $version);
    }

    #[Route('/{type}/{versionNumber}', name: 'app_legal_version', methods: ['GET'], requirements: ['type' => 'privacy_policy|terms_of_use', 'versionNumber' => '\d+'])]
    public function version(Request $request, string $type, int $versionNumber): Response
    {
        $document = $this->document($type);
        $version = $this->versionRepository->findOneByDocumentAndNumber($document, $versionNumber);

        if (!$version instanceof LegalDocumentVersion || LegalDocumentVersionStatus::Draft === $version->getStatus()) {
            throw $this->createNotFoundException();
        }

        return $this->renderDocument($request, $document, $version);
    }

    private function renderDocument(Request $request, LegalDocument $document, LegalDocumentVersion $version): Response
    {
        $settings = $this->generalSettingsRepository->getOrCreateSingleton();
        $preferred = $this->languageResolver->preferredFromCode($request->query->getString('lang'), $settings);
        $translation = $this->languageResolver->resolveTranslation($version, $preferred, $settings);

        if (null === $translation) {
            throw $this->createNotFoundException();
        }

        return $this->render('@web/legal/show.html.twig', [
            'document' => $document,
            'version' => $version,
            'translation' => $translation,
            'language' => $translation->getLanguage(),
        ]);
    }

    private function document(string $type): LegalDocument
    {
        $documentType = LegalDocumentType::tryFrom($type);

        if (!$documentType instanceof LegalDocumentType) {
            throw $this->createNotFoundException();
        }

        $document = $this->documentRepository->findOneByType($documentType);

        if (!$document instanceof LegalDocument) {
            throw $this->createNotFoundException();
        }

        return $document;
    }
}
