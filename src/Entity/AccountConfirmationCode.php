<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AccountConfirmationCodeRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccountConfirmationCodeRepository::class)]
#[ORM\Table(name: 'account_confirmation_codes')]
#[ORM\Index(name: 'idx_account_confirmation_codes_user', columns: ['user_id'])]
class AccountConfirmationCode
{
    public const int TTL_HOURS = 2;

    public const int MAX_ATTEMPTS = 5;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 255)]
    private string $codeHash;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTime $expiresAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $consumedAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $attempts = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTime $createdAt;

    public function __construct(
        User $user,
        string $codeHash,
        DateTime $expiresAt,
        ?DateTime $createdAt = null,
    ) {
        $this->user = $user;
        $this->codeHash = $codeHash;
        $this->expiresAt = $expiresAt;
        $this->createdAt = $createdAt ?? new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCodeHash(): string
    {
        return $this->codeHash;
    }

    public function getExpiresAt(): DateTime
    {
        return $this->expiresAt;
    }

    public function getConsumedAt(): ?DateTime
    {
        return $this->consumedAt;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt instanceof DateTime;
    }

    public function isExpired(DateTime $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function consume(DateTime $consumedAt): void
    {
        if (!$this->isConsumed()) {
            $this->consumedAt = $consumedAt;
        }
    }

    public function registerFailedAttempt(): void
    {
        ++$this->attempts;
    }
}
