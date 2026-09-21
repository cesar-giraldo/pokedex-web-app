<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Admin\Form\PokemonImageEditType;
use App\Admin\Service\Storage\PokemonImageUploadException;
use App\Admin\Service\Storage\PokemonImageUploadService;
use App\Entity\Pokemon;
use App\Entity\PokemonImage;
use App\Repository\PokemonImageRepository;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
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

    #[Route('/pokemons/{id}/images/{imageId}', name: 'app_backend_pokemon_image_update', methods: ['PATCH'], requirements: ['id' => '\d+', 'imageId' => '\d+'])]
    public function update(Pokemon $pokemon, int $imageId, Request $request): JsonResponse
    {
        $this->assertPokemonImageCsrf($request);

        $image = $this->pokemonImageRepository->find($imageId);
        if (!$image instanceof PokemonImage || $image->getPokemon()->getId() !== $pokemon->getId()) {
            throw new NotFoundHttpException();
        }

        $content = (string) $request->getContent();
        $payload = '' === $content ? [] : json_decode($content, true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'La descripción no es válida.'], Response::HTTP_BAD_REQUEST);
        }

        $rawDescription = $payload['description'] ?? '';
        if (!is_string($rawDescription)) {
            return $this->json(['error' => 'La descripción no es válida.'], Response::HTTP_BAD_REQUEST);
        }

        $form = $this->createForm(PokemonImageEditType::class);
        $form->submit(['description' => $rawDescription]);
        if (!$form->isValid()) {
            return $this->json(['error' => $this->getFirstFormError($form)], Response::HTTP_BAD_REQUEST);
        }

        $description = $form->get('description')->getData();
        $image = $this->pokemonImageUploadService->updateDescription(
            $pokemon,
            $image,
            is_string($description) ? $description : null,
        );

        return $this->json([
            'success' => true,
            'message' => 'La descripción se actualizó correctamente.',
            'description' => $image->getDescription() ?? '',
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

    /**
     * @param FormInterface<mixed> $form
     */
    private function getFirstFormError(FormInterface $form): string
    {
        foreach ($form->getErrors(true) as $error) {
            return $error->getMessage();
        }

        return 'La descripción no es válida.';
    }
}
