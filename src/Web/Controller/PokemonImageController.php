<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Admin\Service\Storage\ImageVariant;
use App\Admin\Service\Storage\PokemonImageStorage;
use App\Entity\PokemonImage;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

use function fclose;
use function fopen;
use function stream_copy_to_stream;

final class PokemonImageController extends AbstractController
{
    public function __construct(
        private readonly PokemonImageStorage $pokemonImageStorage,
    ) {
    }

    #[Route(
        '/media/pokemon-images/{publicToken}/{variant}',
        name: 'app_pokemon_image',
        requirements: [
            'publicToken' => PokemonImage::PUBLIC_TOKEN_PATTERN,
            'variant' => 'thumb|display|original',
        ],
        defaults: ['variant' => 'original'],
        methods: ['GET'],
    )]
    public function show(
        #[MapEntity(mapping: ['publicToken' => 'publicToken'])]
        PokemonImage $image,
        string $variant,
    ): Response {
        $imageVariant = ImageVariant::tryFrom($variant);
        if (!$imageVariant instanceof ImageVariant || !$imageVariant->isAllowedForPokemon()) {
            throw new NotFoundHttpException();
        }

        $imagePath = $image->getImagePath();
        if ('' === $imagePath) {
            throw new NotFoundHttpException();
        }

        try {
            $stream = $this->pokemonImageStorage->readStream($imagePath, $imageVariant);
        } catch (Throwable) {
            throw new NotFoundHttpException();
        }

        $response = new StreamedResponse(static function () use ($stream): void {
            $output = fopen('php://output', 'w');

            if (false === $output) {
                return;
            }

            stream_copy_to_stream($stream, $output);
            fclose($stream);
            fclose($output);
        });

        $response->headers->set('Content-Type', $this->pokemonImageStorage->resolveMimeType($imagePath, $imageVariant));
        $response->headers->set('Cache-Control', 'public, max-age=3600');

        return $response;
    }
}
