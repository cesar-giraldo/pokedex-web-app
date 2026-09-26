<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PokemonImageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

use function trim;

#[ORM\Entity(repositoryClass: PokemonImageRepository::class)]
#[ORM\Table(name: 'pokemon_image')]
#[ORM\Index(name: 'IDX_POKEMON_IMAGE_POKEMON', columns: ['pokemon_id'])]
#[ORM\UniqueConstraint(name: 'uniq_pokemon_image_public_token', fields: ['publicToken'])]
class PokemonImage
{
    public const int PUBLIC_TOKEN_LENGTH = 22;

    public const string PUBLIC_TOKEN_PATTERN = '[1-9A-HJ-NP-Za-km-z]{22}';

    public const int DESCRIPTION_MAX_LENGTH = 255;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Pokemon $pokemon;

    #[ORM\Column(length: self::PUBLIC_TOKEN_LENGTH)]
    private string $publicToken;

    #[ORM\Column(length: 512)]
    private string $imagePath;

    #[ORM\Column(length: self::DESCRIPTION_MAX_LENGTH, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'sort_order', type: Types::INTEGER)]
    private int $sortOrder = 1;

    public function __construct()
    {
        $this->publicToken = Uuid::v7()->toBase58();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicToken(): string
    {
        return $this->publicToken;
    }

    public function getPokemon(): Pokemon
    {
        return $this->pokemon;
    }

    public function setPokemon(Pokemon $pokemon): static
    {
        $this->pokemon = $pokemon;

        return $this;
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    public function setImagePath(string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $trimmed = null !== $description ? trim($description) : null;
        $this->description = (null === $trimmed || '' === $trimmed) ? null : $trimmed;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }
}
