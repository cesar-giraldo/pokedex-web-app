<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserRole;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Web\Form\LoginFormType;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class AuthController extends AbstractController
{
    #[Route(
        '/{_locale}/login',
        name: 'app_public_login',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET', 'POST'],
    )]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $user = $this->getUser();

        if ($user instanceof User && $user->hasAssignedRole(UserRole::User)) {
            $route = UserStatus::UncompleteProfileInfo === $user->getStatus()
                ? 'app_public_profile'
                : 'app_public_home';

            return $this->redirectToRoute($route, ['_locale' => $request->getLocale()]);
        }

        $loginForm = $this->createForm(LoginFormType::class, [
            '_username' => $authenticationUtils->getLastUsername(),
        ]);

        return $this->render('@web/auth/login.html.twig', [
            'loginForm' => $loginForm->createView(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route(
        '/{_locale}/logout',
        name: 'app_public_logout',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
    )]
    public function logout(): never
    {
        throw new LogicException('Symfony intercepts this route.');
    }

    #[Route(
        '/{_locale}/register',
        name: 'app_public_register',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET', 'POST'],
    )]
    public function register(Request $request): Response
    {
        return $this->render('@web/auth/register.html.twig', [
            'submitted' => $request->isMethod('POST'),
        ]);
    }
}
