<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Admin\Service\GeneralSettingsProvider;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Pokemon;
use App\Web\Service\PublicLanguageResolver;
use App\Web\Service\PublicPokemonCatalog;
use App\Web\Service\PublicStructuredData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly GeneralSettingsProvider $settings,
        private readonly PublicLanguageResolver $languages,
        private readonly PublicPokemonCatalog $catalog,
        private readonly PublicStructuredData $structuredData,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function root(): Response
    {
        return $this->redirectToRoute('app_public_home', [
            '_locale' => $this->languages->defaultLanguage($this->settings->get())->value,
        ]);
    }

    #[Route(
        '/{_locale}',
        name: 'app_public_home',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function index(Request $request): Response
    {
        return $this->render('@web/home/index.html.twig', [
            'slides' => $this->catalog->featuredImages(),
            'structured_data_json' => $this->structuredData->websiteJson($request->getLocale()),
        ]);
    }

    #[Route('/internal-pokemon-search/{name}', name: 'app_internal_pokemon_search', methods: ['GET'])]
    public function internalPokemonSearch(EntityManagerInterface $em, string $name): JsonResponse
    {
        $pokemon = $em->getRepository(Pokemon::class)->findOneByName($name);

        $response = [
            'success' => $pokemon instanceof Pokemon,
            'data' => $pokemon ? [
                'name' => $pokemon->getName(),
                'spriteFront' => $pokemon->getSpriteFront(),
                'healthPoints' => $pokemon->getHealthPoints(),
            ] : null,
            'errors' => $pokemon ? null : ['Pokemon not found'],
        ];

        return $this->json($response);
    }
}
