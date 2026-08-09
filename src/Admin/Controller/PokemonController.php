<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Form\SearchPokemonType;
use App\Repository\PokemonRepository;
use Doctrine\ORM\QueryBuilder;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function in_array;

#[Route('/admin')]
final class PokemonController extends AbstractController
{
    /**
     * @return array{entities: Pagerfanta<object>, current_limit: string, allowed_limits: list<int>}
     */
    private function getPagination(
        QueryBuilder $queryBuilder,
        Request $request
    ): array {
        // create a QueryAdapter for Pagerfanta
        $adapter = new QueryAdapter($queryBuilder);
        $pagerfanta = new Pagerfanta($adapter);

        $strLimit = $request->query->get('limit', '10');
        $allowedLimits = [10, 25, 50];

        if ('all' === $strLimit) {
            $totalRecords = $pagerfanta->getNbResults();
            // If list is empty, we ensure there is at least one entry to avoid division errors
            $pagerfanta->setMaxPerPage($totalRecords > 0 ? $totalRecords : 1);
        } else {
            // Standard validation for numbers (10, 25, 50)
            $currentLimit = (int) $strLimit;
            if (!in_array($currentLimit, $allowedLimits, true)) {
                $currentLimit = 10;
            }
            $pagerfanta->setMaxPerPage($currentLimit);
        }

        $currentPage = $request->query->getInt('page', 1);
        $pagerfanta->setCurrentPage($currentPage);

        return [
            'entities' => $pagerfanta,
            'current_limit' => $strLimit,
            'allowed_limits' => $allowedLimits,
        ];
    }

    #[Route('/pokemons', name: 'app_backend_pokemons')]
    public function pokemons(PokemonRepository $pokemonRepository, Request $request): Response
    {
        $form = $this->createForm(SearchPokemonType::class);
        $form->handleRequest($request);

        $term = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $term = $data['q'] ?? null;
        }

        $sort = $request->query->get('sort', 'p.listOrder');
        $direction = $request->query->get('direction', 'asc');
        $queryBuilder = $pokemonRepository->findPokemonsQueryBuilder(
            $term,
            $sort,
            $direction,
            ['includeHidden' => true],
        );

        $pagination = $this->getPagination($queryBuilder, $request);

        return $this->render('@admin/pokemons/index.html.twig', [
            'controller_name' => 'PokemonController',
            'active_menu' => 'dashboard',
            'active_page' => 'pokemon_list',
            'search_form' => $form->createView(),
            'current_sort' => $sort,
            'current_direction' => $direction,
            ...$pagination,
        ]);
    }
}
