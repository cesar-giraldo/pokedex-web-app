<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Service\Storage\PokemonImageUploadException;
use App\Admin\Service\Storage\PokemonImageUploadService;
use App\Entity\Pokemon;
use App\Entity\PokemonImage;
use App\Repository\PokemonImageRepository;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;

#[Route('/admin')]
final class PokemonImageController extends AbstractController
{
    public function __construct(
        private readonly PokemonImageUploadService $pokemonImageUploadService,
        private readonly PokemonImageRepository $pokemonImageRepository,
    ) {
    }

    #[Route('/pokemons/{id}/images/reorder', name: 'app_backend_pokemon_images_reorder', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function reorder(Pokemon $pokemon, Request $request): JsonResponse
    {
        $this->assertPokemonImageCsrf($request);

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload) || !isset($payload['ids']) || !is_array($payload['ids'])) {
            return $this->json(['error' => 'El listado de imágenes no es válido.'], Response::HTTP_BAD_REQUEST);
        }

        $orderedIds = [];
        foreach ($payload['ids'] as $id) {
            if (!is_numeric($id)) {
                return $this->json(['error' => 'El listado de imágenes no es válido.'], Response::HTTP_BAD_REQUEST);
            }

            $orderedIds[] = (int) $id;
        }

        try {
            $this->pokemonImageUploadService->reorder($pokemon, $orderedIds);
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'message' => 'El orden de las imágenes fue actualizado correctamente.',
        ]);
    }

    #[Route('/pokemons/{id}/images/{imageId}', name: 'app_backend_pokemon_image_delete', methods: ['DELETE'], requirements: ['id' => '\d+', 'imageId' => '\d+'])]
    public function delete(Pokemon $pokemon, int $imageId, Request $request): JsonResponse
    {
        $this->assertPokemonImageCsrf($request);

        $image = $this->pokemonImageRepository->find($imageId);
        if (!$image instanceof PokemonImage || $image->getPokemon()->getId() !== $pokemon->getId()) {
            throw new NotFoundHttpException();
        }

        try {
            $this->pokemonImageUploadService->delete($pokemon, $image);
        } catch (PokemonImageUploadException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'success' => true,
            'message' => 'La imagen se eliminó correctamente.',
        ]);
    }

    private function assertPokemonImageCsrf(Request $request): void
    {
        $token = $request->headers->get('X-CSRF-TOKEN');
        if (!is_string($token) || !$this->isCsrfTokenValid('pokemon_image', $token)) {
            throw new AccessDeniedHttpException('Token CSRF inválido.');
        }
    }
}
