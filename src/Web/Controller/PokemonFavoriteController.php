<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Entity\Enum\SupportedLanguage;
use App\Entity\Pokemon;
use App\Entity\User;
use App\Web\Service\PokemonFavoriteManager;
use App\Web\Service\PublicPokemonCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function is_string;

final class PokemonFavoriteController extends AbstractController
{
    public function __construct(
        private readonly PublicPokemonCatalog $catalog,
        private readonly PokemonFavoriteManager $favorites,
    ) {
    }

    #[Route(
        '/{_locale}/pokemon/{id}/favorite',
        name: 'app_public_pokemon_favorite',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT, 'id' => '\d+'],
        methods: ['POST', 'DELETE'],
    )]
    public function toggle(Request $request, int $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User || PokemonFavoriteManager::GATE_TOGGLE !== $this->favorites->gateFor($user)) {
            return $this->json(['favorited' => false], Response::HTTP_FORBIDDEN);
        }

        $token = $request->headers->get('X-CSRF-TOKEN');

        if (!is_string($token) || !$this->isCsrfTokenValid('pokemon_favorite', $token)) {
            return $this->json(['favorited' => false], Response::HTTP_FORBIDDEN);
        }

        $pokemon = $this->catalog->findVisible($id);

        if (!$pokemon instanceof Pokemon) {
            return $this->json(['favorited' => false], Response::HTTP_NOT_FOUND);
        }

        if ($request->isMethod('DELETE')) {
            $this->favorites->remove($user, $pokemon);

            return $this->json(['favorited' => false]);
        }

        $this->favorites->add($user, $pokemon);

        return $this->json(['favorited' => true]);
    }
}
