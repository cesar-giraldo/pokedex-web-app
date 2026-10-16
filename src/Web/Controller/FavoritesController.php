<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('PUBLIC_ACCOUNT')]
final class FavoritesController extends AbstractController
{
    #[Route(
        '/{_locale}/favorites',
        name: 'app_public_favorites',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function index(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User || UserStatus::Active !== $user->getStatus()) {
            return $this->redirectToRoute('app_public_profile', [
                '_locale' => $request->getLocale(),
            ]);
        }

        return $this->render('@web/account/favorites.html.twig');
    }
}
