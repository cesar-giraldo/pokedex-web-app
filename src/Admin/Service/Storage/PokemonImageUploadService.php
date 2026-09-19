<?php

declare(strict_types=1);

namespace App\Admin\Service\Storage;

use App\Entity\Pokemon;
use App\Entity\PokemonImage;
use App\Repository\PokemonImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function array_unique;
use function count;
use function trim;

final class PokemonImageUploadService
{
    public function __construct(
        private readonly PokemonImageStorage $pokemonImageStorage,
        private readonly PokemonImageRepository $pokemonImageRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function upload(Pokemon $pokemon, UploadedFile $file, ?string $description): PokemonImage
    {
        $objectKey = $this->pokemonImageStorage->upload($pokemon, $file);
        $sortOrder = $this->pokemonImageRepository->getNextSortOrder($pokemon);

        $image = new PokemonImage()
            ->setImagePath($objectKey)
            ->setDescription($this->normalizeDescription($description))
            ->setSortOrder($sortOrder);

        $pokemon->addImage($image);
        $this->entityManager->persist($image);
        $this->entityManager->flush();

        return $image;
    }

    public function delete(Pokemon $pokemon, PokemonImage $image): void
    {
        if ($image->getPokemon()->getId() !== $pokemon->getId()) {
            throw new NotFoundHttpException();
        }

        $objectKey = $image->getImagePath();
        $pokemon->removeImage($image);
        $this->entityManager->remove($image);
        $this->entityManager->flush();

        $this->pokemonImageStorage->delete($objectKey);
        $this->recompactSortOrder($pokemon);
    }

    /**
     * @param list<int> $orderedIds
     */
    public function reorder(Pokemon $pokemon, array $orderedIds): void
    {
        $images = $this->pokemonImageRepository->findByPokemonOrdered($pokemon);
        $imagesById = [];

        foreach ($images as $image) {
            $imageId = $image->getId();
            if (null === $imageId) {
                continue;
            }

            $imagesById[$imageId] = $image;
        }

        $normalizedIds = [];
        foreach ($orderedIds as $id) {
            if ($id < 1) {
                throw new InvalidArgumentException('El listado de imágenes no es válido.');
            }

            $normalizedIds[] = $id;
        }

        if (count($normalizedIds) !== count($imagesById) || count($normalizedIds) !== count(array_unique($normalizedIds))) {
            throw new InvalidArgumentException('El listado de imágenes no coincide con la galería.');
        }

        $position = 1;
        foreach ($normalizedIds as $id) {
            if (!isset($imagesById[$id])) {
                throw new InvalidArgumentException('El listado de imágenes no coincide con la galería.');
            }

            $imagesById[$id]->setSortOrder($position);
            ++$position;
        }

        $this->entityManager->flush();
    }

    private function recompactSortOrder(Pokemon $pokemon): void
    {
        $images = $this->pokemonImageRepository->findByPokemonOrdered($pokemon);
        $position = 1;

        foreach ($images as $image) {
            $image->setSortOrder($position);
            ++$position;
        }

        $this->entityManager->flush();
    }

    private function normalizeDescription(?string $description): ?string
    {
        if (null === $description) {
            return null;
        }

        $trimmed = trim($description);

        return '' === $trimmed ? null : $trimmed;
    }
}
