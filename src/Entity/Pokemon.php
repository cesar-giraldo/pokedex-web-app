<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PokemonRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: PokemonRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Pokemon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['pokemon:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100, nullable: false)]
    #[Groups(['pokemon:read'])]
    private string $name;

    #[ORM\Column(type: Types::INTEGER, nullable: false)]
    #[Groups(['pokemon:read'])]
    private int $height;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['pokemon:read'])]
    private int $weight;

    #[ORM\ManyToOne(inversedBy: 'pokemons')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['pokemon:read'])]
    private ?PokemonType $type = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['pokemon:read'])]
    private ?int $listOrder = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['pokemon:read'])]
    private ?string $spriteFront = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['pokemon:read'])]
    private ?string $spriteBack = null;

    #[ORM\Column(type: Types::INTEGER, precision: 10, nullable: true)]
    #[Groups(['pokemon:read'])]
    private ?int $attack = null;

    #[ORM\Column(type: Types::INTEGER, precision: 10, nullable: true)]
    #[Groups(['pokemon:read'])]
    private ?int $defense = null;

    #[ORM\Column(type: Types::INTEGER, precision: 10, nullable: true)]
    #[Groups(['pokemon:read'])]
    private ?int $speed = null;

    #[ORM\Column(type: Types::INTEGER, precision: 10, nullable: true)]
    #[Groups(['pokemon:read'])]
    private ?int $healthPoints = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['pokemon:read'])]
    private ?DateTime $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['pokemon:read'])]
    private ?DateTime $lastUpdatedAt = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    #[Groups(['pokemon:read'])]
    private ?bool $isHidden = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['pokemon:read'])]
    private ?string $description = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getWeight(): int
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

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getLastUpdatedAt(): ?DateTime
    {
        return $this->lastUpdatedAt;
    }

    public function setLastUpdatedAt(?DateTime $lastUpdatedAt): static
    {
        $this->lastUpdatedAt = $lastUpdatedAt;

        return $this;
    }

    public function isHidden(): ?bool
    {
        return $this->isHidden;
    }

    public function setIsHidden(?bool $isHidden): static
    {
        $this->isHidden = $isHidden;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function setDefaults(): static
    {
        if (!$this->createdAt) {
            $this->createdAt = new DateTime();
        }

        // Always update the lastUpdatedAt timestamp on persist or update
        $this->lastUpdatedAt = new DateTime();

        return $this;
    }
}
