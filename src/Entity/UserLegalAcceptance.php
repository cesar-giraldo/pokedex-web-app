<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\LegalAcceptanceMethod;
use App\Entity\Enum\SupportedLanguage;
use App\Repository\UserLegalAcceptanceRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

#[ORM\Entity(repositoryClass: UserLegalAcceptanceRepository::class)]
#[ORM\Table(name: 'user_legal_acceptances')]
#[ORM\UniqueConstraint(name: 'uniq_user_legal_acceptance_version', columns: ['user_id', 'version_id'])]
#[ORM\Index(name: 'idx_user_legal_acceptances_user', columns: ['user_id'])]
class UserLegalAcceptance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private LegalDocumentVersion $version;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private LegalDocumentVersionTranslation $translation;

    #[ORM\Column(length: 5)]
    private string $language;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTime $acceptedAt;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 32, enumType: LegalAcceptanceMethod::class)]
    private LegalAcceptanceMethod $method;

    #[ORM\Column(length: 64)]
    private string $contentHash;

    public function __construct(
        User $user,
        LegalDocumentVersionTranslation $translation,
        LegalAcceptanceMethod $method,
        DateTime $acceptedAt,
        ?string $ipAddress,
        ?string $userAgent,
    ) {
        $hash = $translation->getContentHash();
        if (null === $hash || '' === $hash) {
            throw new LogicException('No se puede registrar la aceptación de una traducción sin hash de contenido.');
        }

        $this->user = $user;
        $this->version = $translation->getVersion();
        $this->translation = $translation;
        $this->language = $translation->getLanguage();
        $this->method = $method;
        $this->acceptedAt = $acceptedAt;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->contentHash = (string) $hash;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getVersion(): LegalDocumentVersion
    {
        return $this->version;
    }

    public function getTranslation(): LegalDocumentVersionTranslation
    {
        return $this->translation;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function languageLabel(): string
    {
        return SupportedLanguage::tryFrom($this->language)?->label() ?? $this->language;
    }

    public function getAcceptedAt(): DateTime
    {
        return $this->acceptedAt;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getMethod(): LegalAcceptanceMethod
    {
        return $this->method;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }
}
