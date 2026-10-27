<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserPokemonFavoriteRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserPokemonFavoriteRepository::class)]
#[ORM\Table(name: 'user_pokemon_favorites')]
#[ORM\UniqueConstraint(name: 'uniq_user_pokemon_favorite', columns: ['user_id', 'pokemon_id'])]
#[ORM\Index(name: 'idx_user_pokemon_favorites_user_favorited', columns: ['user_id', 'favorited_at'])]
class UserPokemonFavorite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Pokemon $pokemon;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $favoritedAt;

    public function __construct(User $user, Pokemon $pokemon, ?DateTimeImmutable $favoritedAt = null)
    {
        $this->user = $user;
        $this->pokemon = $pokemon;
        $this->favoritedAt = $favoritedAt ?? new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getPokemon(): Pokemon
    {
        return $this->pokemon;
    }

    public function getFavoritedAt(): DateTimeImmutable
    {
        return $this->favoritedAt;
    }
}
