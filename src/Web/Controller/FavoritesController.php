<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Entity\Enum\SupportedLanguage;
use App\Entity\Enum\UserStatus;
use App\Entity\User;
use App\Web\Service\PokemonFavoriteManager;
use App\Web\Service\PublicPokemonCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_numeric;

#[IsGranted('PUBLIC_ACCOUNT')]
final class FavoritesController extends AbstractController
{
    public function __construct(
        private readonly PublicPokemonCatalog $catalog,
    ) {
    }

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

        $type = $request->query->get('type');
        $typeId = is_numeric($type) ? (int) $type : null;
        $page = $this->catalog->page(
            $request->query->getString('q'),
            $typeId,
            $request->query->getInt('page', 1),
            $user,
        );

        return $this->render('@web/account/favorites.html.twig', [
            'page' => $page,
            'types' => $this->catalog->types(),
            'favorite_gate' => PokemonFavoriteManager::GATE_TOGGLE,
            'favorite_ids' => $page->pokemonIds(),
        ]);
    }
}
