<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserStatus;
use App\Entity\LegalDocumentVersionTranslation;
use App\Entity\User;
use App\Legal\Exception\LegalPublicationException;
use App\Legal\LegalAcceptanceService;
use App\Web\Service\PendingFavoriteReturn;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('PUBLIC_ACCOUNT')]
final class LegalAcceptanceController extends AbstractController
{
    public function __construct(
        private readonly LegalAcceptanceService $acceptanceService,
        private readonly PendingFavoriteReturn $pendingFavoriteReturn,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        '/{_locale}/legal/accept',
        name: 'app_public_legal_accept',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET', 'POST'],
    )]
    public function accept(Request $request): Response
    {
        $user = $this->currentUser();

        if (UserStatus::Active !== $user->getStatus()) {
            return $this->redirectToRoute('app_public_profile', [
                '_locale' => $request->getLocale(),
            ]);
        }

        $pending = $this->acceptanceService->currentPending($user);

        if (null === $pending) {
            return $this->homeOrFavorite($request, $user);
        }

        $translation = $this->acceptanceService->translationForLocale($user, $pending, $request->getLocale());

        if (!$translation instanceof LegalDocumentVersionTranslation) {
            throw $this->createNotFoundException('No hay una traducción disponible para este documento.');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('public_legal_accept', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            if (!$request->request->getBoolean('accepted')) {
                $error = $this->translator->trans('legal.accept.checkbox_required');
            } else {
                try {
                    $this->acceptanceService->acceptCurrent(
                        $user,
                        $request->getClientIp(),
                        $request->headers->get('User-Agent'),
                        $request->getLocale(),
                    );
                } catch (LegalPublicationException $exception) {
                    if (!$this->acceptanceService->hasPending($user)) {
                        return $this->homeOrFavorite($request, $user);
                    }

                    $error = $exception->getMessage();
                }

                if (null === $error) {
                    return $this->redirectToRoute('app_public_legal_accept', [
                        '_locale' => $request->getLocale(),
                    ]);
                }
            }
        }

        return $this->render('@web/legal/accept.html.twig', [
            'version' => $pending,
            'translation' => $translation,
            'content_language' => SupportedLanguage::tryFrom($translation->getLanguage()),
            'error' => $error ?? '',
        ]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function homeOrFavorite(Request $request, User $user): Response
    {
        return $this->pendingFavoriteReturn->redirectInsteadOf(
            $user,
            $request,
            $request->getLocale(),
            $this->redirectToRoute('app_public_home', [
                '_locale' => $request->getLocale(),
            ]),
        );
    }
}
