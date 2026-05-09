<?php

namespace App\Entity;

use App\Repository\PokemonRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PokemonRepository::class)]
class Pokemon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: Types::FLOAT, nullable: false)]
    private ?string $height = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $weight = null;

    #[ORM\ManyToOne(inversedBy: 'pokemons')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PokemonType $type = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $listOrder = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $spriteFront = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $spriteBack = null;

    #[ORM\Column(type: Types::INTEGER, precision: 10,nullable: true)]
    private ?int $attack = null;

    #[ORM\Column(type: Types::INTEGER, precision: 10, nullable: true)]
    private ?int $defense = null;

    #[ORM\Column(type: Types::INTEGER, precision: 10, nullable: true)]
    private ?int $speed = null;

    #[ORM\Column(type: Types::INTEGER, precision: 10, nullable: true)]
    private ?int $healthPoints = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $lastUpdatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getHeight(): ?string
    {
        return $this->height;
    }

    public function setHeight(string $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getWeight(): ?int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): static
    {
        $this->weight = $weight;

        return $this;
    }

    public function getType(): ?PokemonType
    {
        return $this->type;
    }

    public function setType(?PokemonType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getListOrder(): ?int
    {
        return $this->listOrder;
    }

    public function setListOrder(int $listOrder): static
    {
        $this->listOrder = $listOrder;

        return $this;
    }

    public function getSpriteFront(): ?string
    {
        return $this->spriteFront;
    }

    public function setSpriteFront(?string $spriteFront): static
    {
        $this->spriteFront = $spriteFront;

        return $this;
    }

    public function getSpriteBack(): ?string
    {
        return $this->spriteBack;
    }

    public function setSpriteBack(?string $spriteBack): static
    {
        $this->spriteBack = $spriteBack;

        return $this;
    }

    public function getAttack(): ?int
    {
        return $this->attack;
    }

    public function setAttack(?int $attack): static
    {
        $this->attack = $attack;

        return $this;
    }

    public function getDefense(): ?int
    {
        return $this->defense;
    }

    public function setDefense(?int $defense): static
    {
        $this->defense = $defense;

        return $this;
    }

    public function getSpeed(): ?int
    {
        return $this->speed;
    }

    public function setSpeed(?int $speed): static
    {
        $this->speed = $speed;

        return $this;
    }

    public function getHealthPoints(): ?int
    {
        return $this->healthPoints;
    }

    public function setHealthPoints(?int $healthPoints): static
    {
        $this->healthPoints = $healthPoints;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getLastUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->lastUpdatedAt;
    }

    public function setLastUpdatedAt(?\DateTimeImmutable $lastUpdatedAt): static
    {
        $this->lastUpdatedAt = $lastUpdatedAt;

        return $this;
    }
}
