<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\LegalDocumentVersionStatus;
use App\Repository\LegalDocumentVersionRepository;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

use function trim;

#[ORM\Entity(repositoryClass: LegalDocumentVersionRepository::class)]
#[ORM\Table(name: 'legal_document_versions')]
#[ORM\UniqueConstraint(name: 'uniq_legal_document_version_number', columns: ['document_id', 'version_number'])]
#[ORM\Index(name: 'idx_legal_document_versions_status', columns: ['document_id', 'status'])]
class LegalDocumentVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'versions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LegalDocument $document;

    #[ORM\Column]
    private int $versionNumber;

    #[ORM\Column(length: 16, enumType: LegalDocumentVersionStatus::class)]
    private LegalDocumentVersionStatus $status = LegalDocumentVersionStatus::Draft;

    #[ORM\Column]
    private bool $requiresReacceptance = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $publishedAt = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $notificationTitle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notificationMessage = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTime $createdAt;

    /**
     * @var Collection<int, LegalDocumentVersionTranslation>
     */
    #[ORM\OneToMany(targetEntity: LegalDocumentVersionTranslation::class, mappedBy: 'version', cascade: ['persist'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct(LegalDocument $document, int $versionNumber)
    {
        if ($versionNumber < 1) {
            throw new LogicException('El número de versión debe ser mayor o igual a 1.');
        }

        $this->document = $document;
        $this->versionNumber = $versionNumber;
        $this->createdAt = new DateTime();
        $this->translations = new ArrayCollection();
        $document->addVersion($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocument(): LegalDocument
    {
        return $this->document;
    }

    public function setDocument(LegalDocument $document): static
    {
        $this->document = $document;

        return $this;
    }

    public function getVersionNumber(): int
    {
        return $this->versionNumber;
    }

    public function getStatus(): LegalDocumentVersionStatus
    {
        return $this->status;
    }

    public function isDraft(): bool
    {
        return LegalDocumentVersionStatus::Draft === $this->status;
    }

    public function isPublished(): bool
    {
        return LegalDocumentVersionStatus::Published === $this->status;
    }

    public function requiresReacceptance(): bool
    {
        return $this->requiresReacceptance;
    }

    public function getPublishedAt(): ?DateTime
    {
        return $this->publishedAt;
    }

    public function getNotificationTitle(): ?string
    {
        return $this->notificationTitle;
    }

    public function getNotificationMessage(): ?string
    {
        return $this->notificationMessage;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, LegalDocumentVersionTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(LegalDocumentVersionTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setVersion($this);
        }

        return $this;
    }

    public function findTranslation(string $language): ?LegalDocumentVersionTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLanguage() === $language) {
                return $translation;
            }
        }

        return null;
    }

    public function publish(bool $requiresReacceptance, ?string $notificationTitle, ?string $notificationMessage, DateTime $publishedAt): void
    {
        if (!$this->isDraft()) {
            throw new LogicException('Solo se puede publicar un borrador.');
        }

        $this->status = LegalDocumentVersionStatus::Published;
        $this->requiresReacceptance = $requiresReacceptance;
        $this->publishedAt = $publishedAt;
        $this->notificationTitle = $this->normalizeNullable($notificationTitle);
        $this->notificationMessage = $this->normalizeNullable($notificationMessage);

        foreach ($this->translations as $translation) {
            $translation->refreshContentHash();
        }
    }

    public function archive(): void
    {
        if (!$this->isPublished()) {
            throw new LogicException('Solo se puede archivar la versión vigente.');
        }

        $this->status = LegalDocumentVersionStatus::Archived;
    }

    private function normalizeNullable(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
