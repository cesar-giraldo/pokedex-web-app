<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Entity\User;
use App\Legal\Exception\LegalPublicationException;
use App\Legal\LegalAcceptanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_string;

#[Route('/admin/legal')]
#[IsGranted('ROLE_OPERATOR')]
final class LegalAcceptanceController extends AbstractController
{
    public function __construct(
        private readonly LegalAcceptanceService $acceptanceService,
    ) {
    }

    #[Route('/accept', name: 'app_backend_legal_accept', methods: ['GET', 'POST'])]
    public function accept(Request $request): Response
    {
        $user = $this->currentUser();
        $pending = $this->acceptanceService->currentPending($user);

        if (null === $pending) {
            return $this->redirect($this->returnPath($request));
        }

        $translation = $this->acceptanceService->translationForUser($user, $pending);

        if (null === $translation) {
            throw $this->createNotFoundException('No hay una traducción disponible para este documento.');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('legal_accept', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            if (!$request->request->getBoolean('accepted')) {
                $error = 'Debes marcar la casilla para continuar.';
            } else {
                try {
                    $this->acceptanceService->acceptCurrent(
                        $user,
                        $request->getClientIp(),
                        $request->headers->get('User-Agent'),
                    );
                } catch (LegalPublicationException $exception) {
                    $this->addFlash('error', $exception->getMessage());
                }

                return $this->redirectToRoute('app_backend_legal_accept');
            }
        }

        return $this->render('@admin/legal/accept.html.twig', [
            'active_menu' => 'legal',
            'active_page' => 'legal_accept',
            'version' => $pending,
            'translation' => $translation,
            'error' => $error,
        ]);
    }

    private function returnPath(Request $request): string
    {
        $session = $request->getSession();
        $target = $session->get('legal_accept_target');
        $session->remove('legal_accept_target');

        if (is_string($target) && str_starts_with($target, '/admin') && !str_starts_with($target, '//')) {
            return $target;
        }

        return $this->generateUrl('app_backend_home');
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
