<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\SearchPokemonType;
use App\Repository\PokemonRepository;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PokemonController extends AbstractController
{
    #[Route('/secure/pokemons', name: 'app_backend_pokemons')]
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

        $queryBuilder = $pokemonRepository->findPokemonsQueryBuilder($term, $sort, $direction);

        // create a QueryAdapter for Pagerfanta
        $adapter = new QueryAdapter($queryBuilder);
        $pagerfanta = new Pagerfanta($adapter);

        $pagerfanta->setMaxPerPage(10);
        $currentPage = $request->query->getInt('page', 1);
        $pagerfanta->setCurrentPage($currentPage);

        return $this->render('pokemons/index.html.twig', [
            'controller_name' => 'HomeController',
            'active_menu' => 'dashboard',
            'active_page' => 'pokemon_list',
            'pokemons' => $pagerfanta,
            'search_form' => $form->createView(),
            'current_sort' => $sort,
            'current_direction' => $direction,
        ]);
    }
}
