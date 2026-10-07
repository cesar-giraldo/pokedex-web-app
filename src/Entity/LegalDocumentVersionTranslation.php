<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LegalDocumentVersionTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

use function trim;

#[ORM\Entity(repositoryClass: LegalDocumentVersionTranslationRepository::class)]
#[ORM\Table(name: 'legal_document_version_translations')]
#[ORM\UniqueConstraint(name: 'uniq_legal_translation_version_language', columns: ['version_id', 'language'])]
class LegalDocumentVersionTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LegalDocumentVersion $version;

    #[ORM\Column(length: 5)]
    private string $language;

    #[ORM\Column(length: 180)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $contentHtml = '';

    #[ORM\Column(length: 255)]
    private string $acceptanceLabel = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $contentHash = null;

    public function __construct(LegalDocumentVersion $version, string $language)
    {
        $this->version = $version;
        $this->language = $language;
        $version->addTranslation($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVersion(): LegalDocumentVersion
    {
        return $this->version;
    }

    public function setVersion(LegalDocumentVersion $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->assertEditable();
        $this->title = trim($title);

        return $this;
    }

    public function getContentHtml(): string
    {
        return $this->contentHtml;
    }

    public function setContentHtml(string $contentHtml): static
    {
        $this->assertEditable();
        $this->contentHtml = trim($contentHtml);

        return $this;
    }

    public function getAcceptanceLabel(): string
    {
        return $this->acceptanceLabel;
    }

    public function setAcceptanceLabel(string $acceptanceLabel): static
    {
        $this->assertEditable();
        $this->acceptanceLabel = trim($acceptanceLabel);

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->assertEditable();
        $summary = null !== $summary ? trim($summary) : null;
        $this->summary = null === $summary || '' === $summary ? null : $summary;

        return $this;
    }

    public function getContentHash(): ?string
    {
        return $this->contentHash;
    }

    public function refreshContentHash(): void
    {
        $this->contentHash = hash('sha256', $this->contentHtml);
    }

    public function isComplete(): bool
    {
        return '' !== $this->title && '' !== $this->contentHtml && '' !== $this->acceptanceLabel;
    }

    public function copyContentFrom(self $source): void
    {
        $this->assertEditable();
        $this->title = $source->title;
        $this->contentHtml = $source->contentHtml;
        $this->acceptanceLabel = $source->acceptanceLabel;
        $this->summary = $source->summary;
    }

    private function assertEditable(): void
    {
        if (null !== $this->contentHash) {
            throw new LogicException('No se puede modificar una traducción ya publicada.');
        }
    }
}
