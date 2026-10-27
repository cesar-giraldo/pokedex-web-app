<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Admin\Twig\Extensions\PokemonImageExtension;
use App\Entity\Enum\SupportedLanguage;
use App\Entity\Pokemon;
use App\Entity\User;
use App\Web\Service\PokemonFavoriteManager;
use App\Web\Service\PublicPokemonCatalog;
use App\Web\Service\PublicStructuredData;
use App\Web\View\PublicPokemonPage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

use function is_numeric;

final class PokemonController extends AbstractController
{
    public function __construct(
        private readonly PublicPokemonCatalog $catalog,
        private readonly PublicStructuredData $structuredData,
        private readonly PokemonImageExtension $pokemonImages,
        private readonly PokemonFavoriteManager $favorites,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        '/{_locale}/pokemon',
        name: 'app_public_pokedex',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT],
        methods: ['GET'],
    )]
    public function index(Request $request): Response
    {
        $type = $request->query->get('type');
        $typeId = is_numeric($type) ? (int) $type : null;
        $page = $this->catalog->page(
            $request->query->getString('q'),
            $typeId,
            $request->query->getInt('page', 1),
        );
        $description = $this->translator->trans('listing.meta_description');

        return $this->render('@web/pokemon/index.html.twig', [
            'page' => $page,
            'types' => $this->catalog->types(),
            'meta_description' => $description,
            'favorite_gate' => $this->favoriteGate(),
            'favorite_ids' => $this->favoriteIds($page),
            'structured_data_json' => $this->structuredData->collectionJson(
                $request->getLocale(),
                $this->translator->trans('listing.title'),
                $description,
            ),
        ]);
    }

    #[Route(
        '/{_locale}/pokemon/{id}',
        name: 'app_public_pokemon',
        requirements: ['_locale' => SupportedLanguage::ROUTE_REQUIREMENT, 'id' => '\d+'],
        methods: ['GET'],
    )]
    public function show(Request $request, int $id): Response
    {
        $pokemon = $this->catalog->findVisible($id);

        if (!$pokemon instanceof Pokemon) {
            throw $this->createNotFoundException();
        }

        $imageUrls = [];

        foreach ($pokemon->getImages() as $image) {
            $relative = $this->pokemonImages->resolveUrl($image, 'display');

            if (null === $relative || '' === $relative) {
                continue;
            }

            $imageUrls[] = $request->getSchemeAndHttpHost() . $relative;
        }

        $description = $this->structuredData->excerpt(
            $pokemon->getDescription(),
            $this->translator->trans('pokemon.meta_fallback', ['name' => $pokemon->getName()]),
        );

        $pokemonId = $pokemon->getId();

        return $this->render('@web/pokemon/show.html.twig', [
            'pokemon' => $pokemon,
            'meta_description' => $description,
            'og_image' => $imageUrls[0] ?? $pokemon->getSpriteFront(),
            'favorite_gate' => $this->favoriteGate(),
            'favorite_ids' => null !== $pokemonId ? $this->favoriteIdsFor([$pokemonId]) : [],
            'structured_data_json' => $this->structuredData->pokemonJson(
                $request->getLocale(),
                $pokemon,
                $imageUrls,
            ),
        ]);
    }

    private function favoriteGate(): string
    {
        $user = $this->getUser();

        return $this->favorites->gateFor($user instanceof User ? $user : null);
    }

    /**
     * @return list<int>
     */
    private function favoriteIds(PublicPokemonPage $page): array
    {
        return $this->favoriteIdsFor($page->pokemonIds());
    }

    /**
     * @param list<int> $pokemonIds
     *
     * @return list<int>
     */
    private function favoriteIdsFor(array $pokemonIds): array
    {
        $user = $this->getUser();

        if (!$user instanceof User || PokemonFavoriteManager::GATE_TOGGLE !== $this->favorites->gateFor($user)) {
            return [];
        }

        return $this->favorites->idsFor($user, $pokemonIds);
    }
}
