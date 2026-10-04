<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Entity\Enum\LegalDocumentType;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\LegalDocument;
use App\Entity\LegalDocumentVersion;
use App\Entity\User;
use App\Legal\Exception\LegalPublicationException;
use App\Legal\LegalDocumentPublisher;
use App\Legal\LegalLanguageResolver;
use App\Repository\GeneralSettingsRepository;
use App\Repository\LegalDocumentRepository;
use App\Repository\LegalDocumentVersionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_array;
use function is_string;

#[Route('/admin/legal')]
#[IsGranted('ROLE_DEVELOPER')]
final class LegalDocumentController extends AbstractController
{
    public function __construct(
        private readonly LegalDocumentRepository $documentRepository,
        private readonly LegalDocumentVersionRepository $versionRepository,
        private readonly LegalDocumentPublisher $publisher,
        private readonly LegalLanguageResolver $languageResolver,
        private readonly GeneralSettingsRepository $generalSettingsRepository,
    ) {
    }

    #[Route('', name: 'app_backend_legal', methods: ['GET'])]
    public function index(): Response
    {
        $this->publisher->ensureDocuments();
        $settings = $this->generalSettingsRepository->getOrCreateSingleton();
        $enabledLanguages = $this->languageResolver->enabledLanguageCodes($settings);
        $rows = [];

        foreach ($this->documentRepository->findAllOrdered() as $document) {
            $published = $this->versionRepository->findPublished($document);
            $rows[] = [
                'document' => $document,
                'published' => $published,
                'draft' => $this->versionRepository->findDraft($document),
                'missing_languages' => $published instanceof LegalDocumentVersion
                    ? $this->publisher->missingLanguages($published, $settings)
                    : $enabledLanguages,
            ];
        }

        return $this->render('@admin/legal/index.html.twig', [
            'active_menu' => 'legal',
            'active_page' => 'legal',
            'rows' => $rows,
            'enabled_languages' => $enabledLanguages,
        ]);
    }

    #[Route('/{type}', name: 'app_backend_legal_show', methods: ['GET'], requirements: ['type' => 'privacy_policy|terms_of_use'])]
    public function show(string $type): Response
    {
        $document = $this->document($type);
        $settings = $this->generalSettingsRepository->getOrCreateSingleton();
        $published = $this->versionRepository->findPublished($document);

        return $this->render('@admin/legal/show.html.twig', [
            'active_menu' => 'legal',
            'active_page' => 'legal',
            'document' => $document,
            'published' => $published,
            'draft' => $this->versionRepository->findDraft($document),
            'enabled_languages' => $this->languageResolver->enabledLanguageCodes($settings),
            'missing_languages' => $published instanceof LegalDocumentVersion
                ? $this->publisher->missingLanguages($published, $settings)
                : [],
        ]);
    }

    #[Route('/{type}/draft', name: 'app_backend_legal_draft_create', methods: ['POST'], requirements: ['type' => 'privacy_policy|terms_of_use'])]
    public function createDraft(Request $request, string $type): Response
    {
        $document = $this->document($type);

        if (!$this->isCsrfTokenValid('legal_draft_create_' . $document->getType()->value, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        try {
            $draft = $this->publisher->startDraft($document, $this->currentUser());
        } catch (LegalPublicationException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_backend_legal_show', ['type' => $type]);
        }

        return $this->redirectToRoute('app_backend_legal_edit', [
            'type' => $type,
            'versionNumber' => $draft->getVersionNumber(),
        ]);
    }

    #[Route('/{type}/versions/{versionNumber}/edit', name: 'app_backend_legal_edit', methods: ['GET', 'POST'], requirements: ['type' => 'privacy_policy|terms_of_use', 'versionNumber' => '\d+'])]
    public function edit(Request $request, string $type, int $versionNumber): Response
    {
        $document = $this->document($type);
        $version = $this->version($document, $versionNumber);

        if (!$version->isDraft()) {
            $this->addFlash('error', 'Las traducciones publicadas no se editan. Crea una nueva versión.');

            return $this->redirectToRoute('app_backend_legal_show', ['type' => $type]);
        }

        $settings = $this->generalSettingsRepository->getOrCreateSingleton();
        $languages = $this->languageResolver->enabledLanguageCodes($settings);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('legal_draft_save_' . $version->getId(), $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            try {
                $this->publisher->updateDraft($version, $this->translationPayload($request));
                $this->addFlash('success', 'Borrador guardado.');
            } catch (LegalPublicationException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }

            return $this->redirectToRoute('app_backend_legal_edit', [
                'type' => $type,
                'versionNumber' => $versionNumber,
            ]);
        }

        return $this->render('@admin/legal/edit.html.twig', [
            'active_menu' => 'legal',
            'active_page' => 'legal',
            'document' => $document,
            'version' => $version,
            'languages' => $this->languageOptions($languages),
        ]);
    }

    #[Route('/{type}/versions/{versionNumber}/preview', name: 'app_backend_legal_preview', methods: ['GET'], requirements: ['type' => 'privacy_policy|terms_of_use', 'versionNumber' => '\d+'])]
    public function preview(Request $request, string $type, int $versionNumber): Response
    {
        $document = $this->document($type);
        $version = $this->version($document, $versionNumber);
        $settings = $this->generalSettingsRepository->getOrCreateSingleton();
        $preferred = $this->languageResolver->preferredFromCode($request->query->getString('lang'), $settings);
        $translation = $this->languageResolver->resolveTranslation($version, $preferred, $settings);

        return $this->render('@admin/legal/preview.html.twig', [
            'active_menu' => 'legal',
            'active_page' => 'legal',
            'document' => $document,
            'version' => $version,
            'translation' => $translation,
            'preferred' => $preferred,
            'languages' => $this->languageOptions($this->languageResolver->enabledLanguageCodes($settings)),
        ]);
    }

    #[Route('/{type}/versions/{versionNumber}/publish', name: 'app_backend_legal_publish', methods: ['GET', 'POST'], requirements: ['type' => 'privacy_policy|terms_of_use', 'versionNumber' => '\d+'])]
    public function publish(Request $request, string $type, int $versionNumber): Response
    {
        $document = $this->document($type);
        $version = $this->version($document, $versionNumber);

        if (!$version->isDraft()) {
            $this->addFlash('error', 'Esta versión ya no es un borrador.');

            return $this->redirectToRoute('app_backend_legal_show', ['type' => $type]);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('legal_publish_' . $version->getId(), $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $requiresReacceptance = $request->request->getBoolean('requires_reacceptance');

            try {
                $this->publisher->publish(
                    $version,
                    $requiresReacceptance,
                    $request->request->getString('notification_title'),
                    $request->request->getString('notification_message'),
                    $this->currentUser(),
                );
                $this->addFlash('success', $requiresReacceptance
                    ? 'Versión publicada y usuarios notificados.'
                    : 'Versión publicada sin exigir una nueva aceptación.');
            } catch (LegalPublicationException $exception) {
                $this->addFlash('error', $exception->getMessage());

                return $this->redirectToRoute('app_backend_legal_publish', [
                    'type' => $type,
                    'versionNumber' => $versionNumber,
                ]);
            }

            return $this->redirectToRoute('app_backend_legal_show', ['type' => $type]);
        }

        return $this->render('@admin/legal/publish.html.twig', [
            'active_menu' => 'legal',
            'active_page' => 'legal',
            'document' => $document,
            'version' => $version,
            'notification_title' => $this->publisher->defaultNotificationTitle($version),
            'notification_message' => $this->publisher->defaultNotificationMessage($version),
            'first_release' => 0 === $this->versionRepository->countReleased($document),
        ]);
    }

    #[Route('/{type}/versions/{versionNumber}/languages', name: 'app_backend_legal_languages', methods: ['GET', 'POST'], requirements: ['type' => 'privacy_policy|terms_of_use', 'versionNumber' => '\d+'])]
    public function completeLanguages(Request $request, string $type, int $versionNumber): Response
    {
        $document = $this->document($type);
        $version = $this->version($document, $versionNumber);
        $settings = $this->generalSettingsRepository->getOrCreateSingleton();
        $missing = $this->publisher->missingLanguages($version, $settings);

        if (!$version->isPublished() || [] === $missing) {
            $this->addFlash('error', 'No hay idiomas pendientes en la versión vigente.');

            return $this->redirectToRoute('app_backend_legal_show', ['type' => $type]);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('legal_languages_' . $version->getId(), $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            try {
                $this->publisher->completeMissingLanguages($version, $this->translationPayload($request));
                $this->addFlash('success', 'Traducciones añadidas a la versión vigente.');
            } catch (LegalPublicationException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }

            return $this->redirectToRoute('app_backend_legal_show', ['type' => $type]);
        }

        return $this->render('@admin/legal/languages.html.twig', [
            'active_menu' => 'legal',
            'active_page' => 'legal',
            'document' => $document,
            'version' => $version,
            'languages' => $this->languageOptions($missing),
        ]);
    }

    private function document(string $type): LegalDocument
    {
        $documentType = LegalDocumentType::tryFrom($type);

        if (!$documentType instanceof LegalDocumentType) {
            throw $this->createNotFoundException();
        }

        $this->publisher->ensureDocuments();
        $document = $this->documentRepository->findOneByType($documentType);

        if (!$document instanceof LegalDocument) {
            throw $this->createNotFoundException();
        }

        return $document;
    }

    private function version(LegalDocument $document, int $versionNumber): LegalDocumentVersion
    {
        $version = $this->versionRepository->findOneByDocumentAndNumber($document, $versionNumber);

        if (!$version instanceof LegalDocumentVersion) {
            throw $this->createNotFoundException();
        }

        return $version;
    }

    /**
     * @return array<string, array{title?: string, contentHtml?: string, acceptanceLabel?: string, summary?: string|null}>
     */
    private function translationPayload(Request $request): array
    {
        $raw = $request->request->all('translations');
        $payload = [];

        foreach ($raw as $languageCode => $fields) {
            if (!is_string($languageCode) || !is_array($fields)) {
                continue;
            }

            $payload[$languageCode] = [
                'title' => is_string($fields['title'] ?? null) ? $fields['title'] : '',
                'contentHtml' => is_string($fields['contentHtml'] ?? null) ? $fields['contentHtml'] : '',
                'acceptanceLabel' => is_string($fields['acceptanceLabel'] ?? null) ? $fields['acceptanceLabel'] : '',
                'summary' => is_string($fields['summary'] ?? null) ? $fields['summary'] : null,
            ];
        }

        return $payload;
    }

    /**
     * @param list<string> $languageCodes
     *
     * @return list<array{code: string, label: string}>
     */
    private function languageOptions(array $languageCodes): array
    {
        $options = [];

        foreach ($languageCodes as $languageCode) {
            $language = SupportedLanguage::tryFrom($languageCode);
            $options[] = [
                'code' => $languageCode,
                'label' => $language instanceof SupportedLanguage ? $language->label() : $languageCode,
            ];
        }

        return $options;
    }

    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
