<?php

declare(strict_types=1);

namespace App\Api\Controller;

use App\Repository\PokemonRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

use function max;
use function min;

use const JSON_UNESCAPED_UNICODE;

class PokemonApiController extends AbstractController
{
    private const int DEFAULT_LIMIT = 20;

    private const int MAX_LIMIT = 100;

    #[Route('/pokemon', name: 'api_pokemons_list', methods: ['GET'])]
    public function list(Request $request, PokemonRepository $pokemonRepository): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', self::DEFAULT_LIMIT);
        $limit = max(1, min(self::MAX_LIMIT, $limit));

        $queryBuilder = $pokemonRepository->findPokemonsQueryBuilder(null, 'p.listOrder', 'asc');
        $total = (int) (clone $queryBuilder)
            ->select('COUNT(p.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $pokemones = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json($pokemones, 200, [
            'X-Total-Count' => (string) $total,
            'X-Page' => (string) $page,
            'X-Per-Page' => (string) $limit,
        ], [
            'json_encode_options' => JSON_UNESCAPED_UNICODE,
            'groups' => ['pokemon:read'],
        ]);
    }
}
