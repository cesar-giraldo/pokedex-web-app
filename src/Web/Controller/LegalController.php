<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\LegalDocumentType;
use App\Entity\Enum\LegalDocumentVersionStatus;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentVersion;
use App\Legal\LegalLanguageResolver;
use App\Repository\LegalDocumentRepository;
use App\Repository\LegalDocumentVersionRepository;
use App\Web\Service\PublicLanguageResolver;
use App\Web\Service\PublicStructuredData;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

final class LegalController extends AbstractController
{
    public function __construct(
        private readonly LegalDocumentRepository $documentRepository,
        private readonly LegalDocumentVersionRepository $versionRepository,
        private readonly LegalLanguageResolver $languageResolver,
        private readonly GeneralSettingsProvider $settings,
        private readonly PublicLanguageResolver $publicLanguages,
        private readonly PublicStructuredData $structuredData,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/legal/{type}', name: 'app_legal_current', methods: ['GET'], requirements: ['type' => 'privacy_policy|terms_of_use'])]
    public function current(Request $request, string $type): Response
    {
        $documentType = LegalDocumentType::tryFrom($type);

        if (!$documentType instanceof LegalDocumentType) {
            throw $this->createNotFoundException();
        }

        $language = $this->publicLanguages->resolve(
            $request->query->getString('lang'),
            $this->settings->get(),
        );

        return $this->redirectToRoute($this->publicRoute($documentType), [
            '_locale' => $language->value,
        ]);
    }

    #[Route('/legal/{type}/{versionNumber}', name: 'app_legal_version', methods: ['GET'], requirements: ['type' => 'privacy_policy|terms_of_use', 'versionNumber' => '\d+'])]
    public function version(Request $request, string $type, int $versionNumber): Response
    {
        $document = $this->document($type);
        $version = $this->versionRepository->findOneByDocumentAndNumber($document, $versionNumber);

        if (!$version instanceof LegalDocumentVersion || LegalDocumentVersionStatus::Draft === $version->getStatus()) {
            throw $this->createNotFoundException();
        }

        $settings = $this->settings->get();
        $preferred = $this->languageResolver->preferredFromCode($request->query->getString('lang'), $settings);

        return $this->localeSwitcher->runWithLocale(
            $preferred->value,
            fn (): Response => $this->renderDocument($document, $version, $preferred),
        );
    }

    #[Route(
        '/{_locale}/privacidad',
        name: 'app_public_privacy',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function privacy(Request $request): Response
    {
        return $this->renderCurrent($request, LegalDocumentType::PrivacyPolicy);
    }

    #[Route(
        '/{_locale}/terminos',
        name: 'app_public_terms',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function terms(Request $request): Response
    {
        return $this->renderCurrent($request, LegalDocumentType::TermsOfUse);
    }

    private function renderCurrent(Request $request, LegalDocumentType $type): Response
    {
        $document = $this->documentRepository->findOneByType($type);

        if (!$document instanceof LegalDocument) {
            throw $this->createNotFoundException();
        }

        $version = $this->versionRepository->findPublished($document);

        if (!$version instanceof LegalDocumentVersion) {
            throw $this->createNotFoundException();
        }

        $settings = $this->settings->get();
        $preferred = SupportedLanguage::tryFrom($request->getLocale()) ?? $this->publicLanguages->defaultLanguage($settings);

        return $this->renderDocument($document, $version, $preferred);
    }

    private function renderDocument(
        LegalDocument $document,
        LegalDocumentVersion $version,
        SupportedLanguage $preferred,
    ): Response {
        $settings = $this->settings->get();
        $translation = $this->languageResolver->resolveTranslation($version, $preferred, $settings);

        if (null === $translation) {
            throw $this->createNotFoundException();
        }

        $contentLanguage = SupportedLanguage::tryFrom($translation->getLanguage());
        $description = $translation->getSummary();

        if (null === $description || '' === $description) {
            $description = $this->translator->trans('legal.meta_description', ['title' => $translation->getTitle()]);
        }

        $publicRoute = $this->publicRoute($document->getType());

        return $this->render('@web/legal/show.html.twig', [
            'document' => $document,
            'version' => $version,
            'translation' => $translation,
            'content_language' => $contentLanguage,
            'meta_description' => $description,
            'structured_data_json' => $this->structuredData->webPageJson(
                $preferred->value,
                $publicRoute,
                $translation->getTitle(),
                $description,
            ),
            'canonical_route' => $publicRoute,
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

    private function publicRoute(LegalDocumentType $type): string
    {
        return match ($type) {
            LegalDocumentType::PrivacyPolicy => 'app_public_privacy',
            LegalDocumentType::TermsOfUse => 'app_public_terms',
        };
    }
}
