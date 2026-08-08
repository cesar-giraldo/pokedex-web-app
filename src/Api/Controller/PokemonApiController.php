<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Repository\PokemonRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

use const JSON_UNESCAPED_UNICODE;

class PokemonApiController extends AbstractController
{
    #[Route('/pokemones', name: 'api_pokemons_list', methods: ['GET'])]
    public function list(PokemonRepository $pokemonRepository): JsonResponse
    {
        $pokemones = $pokemonRepository->findPokemonsQueryBuilder(null, 'p.listOrder', 'asc')->getQuery()->getResult();

        return $this->json($pokemones, 200, [], [
            'json_encode_options' => JSON_UNESCAPED_UNICODE,
            'groups' => ['pokemon:read'],
        ]);
    }
}
