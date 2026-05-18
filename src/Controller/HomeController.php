<?php

namespace App\Controller;

use App\Entity\Pokemon;
use App\Entity\PokemonType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $em): Response
    {
        // Insertar un pokemon si no existe ninguno
        $repo = $em->getRepository(Pokemon::class);
        if (0 === $repo->count([])) {
            $type = (new PokemonType())
                ->setName('electric')
                ->setGeneration('generation-i')
                ->setSprite('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/types/generation-iii/colosseum/13.png');

            $em->persist($type);

            $pokemon = (new Pokemon())
                ->setName('Pikachu')
                ->setType($type)
                ->setListOrder(35)
                ->setHealthPoints(35)
                ->setAttack(55)
                ->setDefense(40)
                ->setSpeed(90)
                ->setHeight(4)
                ->setWeight(60)
                ->setSpriteFront('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/25.png')
                ->setSpriteBack('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/25.png');

            $em->persist($pokemon);
            $em->flush();
        }

        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'message' => 'Hi from Symfony 8 + Docker!',
            'pokemons' => $repo->findAll(),
        ]);
    }

    #[Route('/internal-pokemon-search', name: 'app_internal_pokemon_search', methods: ['GET'])]
    public function internalPokemonSearch(EntityManagerInterface $em, Request $request): Response
    {
        $pokemonName = $request->query->get('name');

        $pokemon = $em->getRepository(Pokemon::class)->findOneByName($pokemonName);

        $response = [
            'success' => $pokemon instanceof Pokemon,
            'data' => $pokemon ? [
                'name' => $pokemon->getName(),
                'spriteFront' => $pokemon->getSpriteFront(),
                'healthPoints' => $pokemon->getHealthPoints()
            ] : null,
            'errors' => $pokemon ? null : ['Pokemon not found']
        ];

        return $this->json($response);
    }
}
