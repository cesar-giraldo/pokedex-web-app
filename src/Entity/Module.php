<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ModuleRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

use function array_shift;
use function count;
use function explode;
use function preg_match;
use function spl_object_id;
use function str_contains;
use function str_pad;
use function str_replace;
use function trim;

/**
 * Módulo configurable de la plataforma.
 * Sin padre es un módulo raíz; con padre, un submódulo a cualquier profundidad.
 */
#[ORM\Entity(repositoryClass: ModuleRepository::class)]
#[ORM\Table(name: 'modules')]
#[ORM\UniqueConstraint(name: 'uniq_modules_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_modules_parent_sort_order', columns: ['parent_id', 'sort_order'])]
#[UniqueEntity(fields: ['slug'], message: 'Ya existe un módulo con este slug.')]
class Module
{
    public const int NAME_MAX_LENGTH = 120;

    public const int SLUG_MAX_LENGTH = 64;

    public const int DESCRIPTION_MAX_LENGTH = 500;

    public const string SLUG_PATTERN = '/^[a-z][a-z_]*$/';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: self::NAME_MAX_LENGTH)]
    private string $name;

    #[ORM\Column(length: self::SLUG_MAX_LENGTH)]
    private string $slug;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?self $parent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $children;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $sortOrder;

    #[ORM\Column(options: ['default' => false])]
    private bool $isEnabled = false;

    #[ORM\Column(length: self::DESCRIPTION_MAX_LENGTH, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTime $createdAt;

    public function __construct(string $name, string $slug, string $sortOrder)
    {
        $this->children = new ArrayCollection();
        $this->setName($name);
        $this->setSlug($slug);
        $this->setSortOrder($sortOrder);
        $this->createdAt = new DateTime();
    }

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
        $this->name = trim($name);

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = trim($slug);

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        if ($this->parent === $parent) {
            return $this;
        }

        $previous = $this->parent;
        $this->parent = $parent;
        $previous?->removeChild($this);
        $parent?->addChild($this);

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): static
    {
        if ($this->children->removeElement($child) && $child->getParent() === $this) {
            $child->setParent(null);
        }

        return $this;
    }

    /**
     * Este módulo y todos sus descendientes, sin recorrer un ciclo más de una vez.
     *
     * @return list<self>
     */
    public function subtree(): array
    {
        $nodes = [];
        $pending = [$this];
        $seen = [];

        while ([] !== $pending && count($nodes) < 1000) {
            $current = array_shift($pending);
            $objectId = spl_object_id($current);
            if (isset($seen[$objectId])) {
                continue;
            }

            $seen[$objectId] = true;
            $nodes[] = $current;

            foreach ($current->getChildren() as $child) {
                $pending[] = $child;
            }
        }

        return $nodes;
    }

    public function hasInactiveAncestor(): bool
    {
        $cursor = $this->parent;
        $seen = [];

        while (null !== $cursor) {
            $objectId = spl_object_id($cursor);
            if (isset($seen[$objectId])) {
                return true;
            }

            $seen[$objectId] = true;
            if (!$cursor->isEnabled()) {
                return true;
            }

            $cursor = $cursor->getParent();
        }

        return false;
    }

    public function getSortOrder(): string
    {
        return $this->sortOrder;
    }

    public function setSortOrder(string $sortOrder): static
    {
        $normalized = self::tryNormalizeSortOrder($sortOrder);
        $this->sortOrder = $normalized ?? trim($sortOrder);

        return $this;
    }

    public static function tryNormalizeSortOrder(string $sortOrder): ?string
    {
        $normalized = str_replace(',', '.', trim($sortOrder));
        if (1 !== preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $normalized)) {
            return null;
        }

        if (!str_contains($normalized, '.')) {
            return $normalized . '.00';
        }

        [$whole, $fraction] = explode('.', $normalized, 2);

        return $whole . '.' . str_pad($fraction, 2, '0');
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): static
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        if (null === $description) {
            $this->description = null;

            return $this;
        }

        $description = trim($description);
        $this->description = '' === $description ? null : $description;

        return $this;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
}
