<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Form\LoginFormType;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/admin/login', name: 'app_admin_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser() && $this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_backend_pokemons');
        }

        $loginForm = $this->createForm(LoginFormType::class, [
            '_username' => $authenticationUtils->getLastUsername(),
        ]);

        return $this->render('@admin/security/login.html.twig', [
            'loginForm' => $loginForm->createView(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/admin/logout', name: 'app_admin_logout')]
    public function logout(): never
    {
        throw new LogicException('Symfony intercepts this route.');
    }
}
