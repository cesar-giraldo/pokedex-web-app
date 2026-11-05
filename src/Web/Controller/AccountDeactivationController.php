<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Entity\Enum\SupportedLanguage;
use App\Entity\User;
use App\Web\Service\AccountDeactivationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('PUBLIC_ACCOUNT')]
final class AccountDeactivationController extends AbstractController
{
    public function __construct(
        private readonly AccountDeactivationService $accountDeactivation,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    #[Route(
        '/{_locale}/profile/deactivate',
        name: 'app_public_profile_deactivate',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['POST'],
    )]
    public function deactivate(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $locale = $request->getLocale();

        if (!$this->isCsrfTokenValid('deactivate_account', $request->request->getString('_token'))) {
            $this->addFlash('error', 'account.deactivate_forbidden');

            return $this->redirectToRoute('app_public_profile', ['_locale' => $locale]);
        }

        $error = $this->accountDeactivation->deactivate(
            $user,
            $request->request->get('current_password'),
            $this->tokenStorage->getToken() instanceof SwitchUserToken,
        );

        if (null !== $error) {
            $this->addFlash('error', $error);

            return $this->redirectToRoute('app_public_profile', ['_locale' => $locale]);
        }

        $request->getSession()->set(AccountDeactivationService::LOGOUT_NOTICE, true);

        return $this->redirectToRoute('app_public_logout', ['_locale' => $locale]);
    }
}
