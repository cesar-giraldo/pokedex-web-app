<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Entity\Enum\SupportedLanguage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    #[Route(
        '/{_locale}/iniciar-sesion',
        name: 'app_public_login',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET', 'POST'],
    )]
    public function login(Request $request): Response
    {
        return $this->render('@web/auth/login.html.twig', [
            'submitted' => $request->isMethod('POST'),
        ]);
    }

    #[Route(
        '/{_locale}/registro',
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
