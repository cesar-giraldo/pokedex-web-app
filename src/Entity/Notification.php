<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\NotificationType;
use App\Repository\NotificationRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use function trim;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(name: 'idx_notifications_recipient_read', columns: ['recipient_id', 'read_at'])]
#[ORM\Index(name: 'idx_notifications_recipient_created', columns: ['recipient_id', 'created_at'])]
#[ORM\Index(name: 'IDX_NOTIFICATIONS_ACTOR', columns: ['actor_id'])]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $recipient;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $actor = null;

    #[ORM\Column(length: 64, enumType: NotificationType::class)]
    private NotificationType $type;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $actionUrl = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $payload = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTime $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTime $readAt = null;

    public function __construct(User $recipient, NotificationType $type, string $title, string $message)
    {
        $this->recipient = $recipient;
        $this->type = $type;
        $this->title = $title;
        $this->message = $message;
        $this->createdAt = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipient(): User
    {
        return $this->recipient;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function setActor(?User $actor): static
    {
        $this->actor = $actor;

        return $this;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getActionUrl(): ?string
    {
        return $this->actionUrl;
    }

    public function setActionUrl(?string $actionUrl): static
    {
        $actionUrl = null !== $actionUrl ? trim($actionUrl) : null;
        $this->actionUrl = null === $actionUrl || '' === $actionUrl ? null : $actionUrl;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPayload(): ?array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function setPayload(?array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getReadAt(): ?DateTime
    {
        return $this->readAt;
    }

    public function isRead(): bool
    {
        return $this->readAt instanceof DateTime;
    }

    public function markAsRead(DateTime $readAt): static
    {
        if (!$this->isRead()) {
            $this->readAt = $readAt;
        }

        return $this;
    }
}
