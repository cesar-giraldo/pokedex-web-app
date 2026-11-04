<?php

declare(strict_types=1);

namespace App\Tests\Web\Support;

use App\Entity\Module;
use App\Repository\ModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @phpstan-require-extends WebTestCase
 */
trait EnablesPokemonFavoritesTrait
{
    private const string POKEMON_PARENT_SLUG = 'module_pokemons';

    private const string POKEMON_FAVORITES_SLUG = 'module_pokemons_favorites';

    private ?bool $originalPokemonParentEnabled = null;

    private ?bool $originalPokemonFavoritesEnabled = null;

    private ?int $originalPokemonFavoritesParentId = null;

    private bool $pokemonFavoritesHadNoParent = false;

    private bool $createdPokemonParent = false;

    private bool $createdPokemonFavorites = false;

    protected function enablePokemonFavorites(): void
    {
        $this->setPokemonFavoritesState(true, true);
    }

    protected function enablePokemonModule(): void
    {
        $this->setPokemonModuleEnabled(true);
    }

    protected function setPokemonModuleEnabled(bool $enabled): void
    {
        $entityManager = $this->pokemonFavoritesEntityManager();
        $repository = $this->pokemonFavoritesRepository();
        $parent = $repository->findOneBy(['slug' => self::POKEMON_PARENT_SLUG]);

        if (!$parent instanceof Module) {
            $parent = new Module('Pokemons', self::POKEMON_PARENT_SLUG, '9.00');
            $entityManager->persist($parent);
            $this->createdPokemonParent = true;
        } elseif (!$this->createdPokemonParent && null === $this->originalPokemonParentEnabled) {
            $this->originalPokemonParentEnabled = $parent->isEnabled();
        }

        $parent->setIsEnabled($enabled);
        $entityManager->flush();
    }

    protected function setPokemonFavoritesState(bool $parentEnabled, bool $favoritesEnabled): void
    {
        $entityManager = $this->pokemonFavoritesEntityManager();
        $repository = $this->pokemonFavoritesRepository();
        $parent = $repository->findOneBy(['slug' => self::POKEMON_PARENT_SLUG]);

        if (!$parent instanceof Module) {
            $parent = new Module('Pokemons', self::POKEMON_PARENT_SLUG, '9.00');
            $entityManager->persist($parent);
            $this->createdPokemonParent = true;
        } elseif (!$this->createdPokemonParent && null === $this->originalPokemonParentEnabled) {
            $this->originalPokemonParentEnabled = $parent->isEnabled();
        }

        $favorites = $repository->findOneBy(['slug' => self::POKEMON_FAVORITES_SLUG]);

        if (!$favorites instanceof Module) {
            $favorites = new Module('Pokemones Favoritos', self::POKEMON_FAVORITES_SLUG, '9.10');
            $favorites->setParent($parent);
            $entityManager->persist($favorites);
            $this->createdPokemonFavorites = true;
        } elseif (!$this->createdPokemonFavorites && null === $this->originalPokemonFavoritesEnabled) {
            $this->originalPokemonFavoritesEnabled = $favorites->isEnabled();
            $favoritesParent = $favorites->getParent();

            if (!$favoritesParent instanceof Module) {
                $this->pokemonFavoritesHadNoParent = true;
                $favorites->setParent($parent);
            } elseif (self::POKEMON_PARENT_SLUG !== $favoritesParent->getSlug()) {
                $this->originalPokemonFavoritesParentId = $favoritesParent->getId();
                $favorites->setParent($parent);
            }
        }

        $parent->setIsEnabled($parentEnabled);
        $favorites->setIsEnabled($favoritesEnabled);
        $entityManager->flush();
    }

    protected function restorePokemonFavorites(): void
    {
        if (
            !$this->createdPokemonParent
            && !$this->createdPokemonFavorites
            && null === $this->originalPokemonParentEnabled
            && null === $this->originalPokemonFavoritesEnabled
        ) {
            return;
        }

        $entityManager = $this->pokemonFavoritesEntityManager();
        $repository = $this->pokemonFavoritesRepository();
        $favorites = $repository->findOneBy(['slug' => self::POKEMON_FAVORITES_SLUG]);
        $parent = $repository->findOneBy(['slug' => self::POKEMON_PARENT_SLUG]);

        if ($this->createdPokemonFavorites && $favorites instanceof Module) {
            $favorites->setParent(null);
            $entityManager->flush();
            $entityManager->remove($favorites);
            $entityManager->flush();
            $favorites = null;
        }

        if ($this->createdPokemonParent && $parent instanceof Module) {
            $entityManager->remove($parent);
            $entityManager->flush();
            $parent = null;
        }

        if (!$this->createdPokemonFavorites && $favorites instanceof Module && null !== $this->originalPokemonFavoritesEnabled) {
            $favorites->setIsEnabled($this->originalPokemonFavoritesEnabled);

            if ($this->pokemonFavoritesHadNoParent) {
                $favorites->setParent(null);
            } elseif (null !== $this->originalPokemonFavoritesParentId) {
                $originalParent = $entityManager->find(Module::class, $this->originalPokemonFavoritesParentId);

                if ($originalParent instanceof Module) {
                    $favorites->setParent($originalParent);
                }
            }
        }

        if (!$this->createdPokemonParent && $parent instanceof Module && null !== $this->originalPokemonParentEnabled) {
            $parent->setIsEnabled($this->originalPokemonParentEnabled);
        }

        $entityManager->flush();
    }

    private function pokemonFavoritesEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }

    private function pokemonFavoritesRepository(): ModuleRepository
    {
        /** @var ModuleRepository $repository */
        $repository = static::getContainer()->get(ModuleRepository::class);

        return $repository;
    }
}
