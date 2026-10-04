<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\LegalDocumentType;
use App\Repository\LegalDocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LegalDocumentRepository::class)]
#[ORM\Table(name: 'legal_documents')]
#[ORM\UniqueConstraint(name: 'uniq_legal_documents_type', columns: ['type'])]
class LegalDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32, enumType: LegalDocumentType::class)]
    private LegalDocumentType $type;

    #[ORM\Column(length: 180)]
    private string $name;

    /**
     * @var Collection<int, LegalDocumentVersion>
     */
    #[ORM\OneToMany(targetEntity: LegalDocumentVersion::class, mappedBy: 'document', cascade: ['persist'])]
    #[ORM\OrderBy(['versionNumber' => 'DESC'])]
    private Collection $versions;

    public function __construct(LegalDocumentType $type)
    {
        $this->type = $type;
        $this->name = $type->label();
        $this->versions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): LegalDocumentType
    {
        return $this->type;
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

    /**
     * @return Collection<int, LegalDocumentVersion>
     */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function addVersion(LegalDocumentVersion $version): static
    {
        if (!$this->versions->contains($version)) {
            $this->versions->add($version);
            $version->setDocument($this);
        }

        return $this;
    }
}
