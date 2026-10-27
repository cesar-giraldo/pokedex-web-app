<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\PasswordResetChannel;
use App\Repository\PasswordResetTokenRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PasswordResetTokenRepository::class)]
#[ORM\Table(name: 'password_reset_tokens')]
#[ORM\UniqueConstraint(name: 'uniq_password_reset_tokens_selector', columns: ['selector'])]
#[ORM\Index(name: 'idx_password_reset_tokens_user_channel', columns: ['user_id', 'channel'])]
class PasswordResetToken
{
    public const int TTL_HOURS = 12;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 32)]
    private string $selector;

    #[ORM\Column(length: 64)]
    private string $verifierHash;

    #[ORM\Column(enumType: PasswordResetChannel::class, length: 16)]
    private PasswordResetChannel $channel;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTime $expiresAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $consumedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTime $createdAt;

    public function __construct(
        User $user,
        string $selector,
        string $verifierHash,
        PasswordResetChannel $channel,
        DateTime $expiresAt,
        ?DateTime $createdAt = null,
    ) {
        $this->user = $user;
        $this->selector = $selector;
        $this->verifierHash = $verifierHash;
        $this->channel = $channel;
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

    public function getSelector(): string
    {
        return $this->selector;
    }

    public function getVerifierHash(): string
    {
        return $this->verifierHash;
    }

    public function getChannel(): PasswordResetChannel
    {
        return $this->channel;
    }

    public function getExpiresAt(): DateTime
    {
        return $this->expiresAt;
    }

    public function getConsumedAt(): ?DateTime
    {
        return $this->consumedAt;
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
}
