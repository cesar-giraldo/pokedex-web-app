<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Entity\Enum\SupportedLanguage;
use App\Security\PublicUserImpersonator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ImpersonationController extends AbstractController
{
    public function __construct(
        private readonly PublicUserImpersonator $publicUserImpersonator,
    ) {
    }

    #[Route(
        '/{_locale}/impersonation/exit',
        name: 'app_public_impersonation_exit',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function exit(Request $request): Response
    {
        if ($this->publicUserImpersonator->stop($request)) {
            return $this->redirectToRoute('app_backend_users');
        }

        return $this->redirectToRoute('app_public_home', [
            '_locale' => $request->getLocale(),
        ]);
    }
}
