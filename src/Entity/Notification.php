<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(columns: ['user_id', 'is_read', 'created_at'], name: 'idx_user_read_created')]
#[ORM\Index(columns: ['user_id', 'created_at'], name: 'idx_user_created')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created_at')]
#[ORM\Index(columns: ['type', 'channel'], name: 'idx_type_channel')]
#[ORM\Index(columns: ['status'], name: 'idx_status')]
class Notification
{
    public const TYPE_INFO = 'info';
    public const TYPE_SUCCESS = 'success';
    public const TYPE_WARNING = 'warning';
    public const TYPE_ERROR = 'error';
    public const TYPE_TASK = 'task';
    public const TYPE_DEADLINE = 'deadline';
    public const TYPE_SYSTEM = 'system';
    
    public const CHANNEL_IN_APP = 'in_app';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_PUSH = 'push';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_SLACK = 'slack';
    public const CHANNEL_TELEGRAM = 'telegram';
    
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DELIVERED = 'delivered';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isRead = false;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'systemNotifications')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Task::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Task $task = null;
    
    #[ORM\Column(type: 'string', length: 50, options: ['default' => self::TYPE_INFO])]
    private string $type = self::TYPE_INFO;
    
    #[ORM\Column(type: 'string', length: 50, options: ['default' => self::CHANNEL_IN_APP])]
    private string $channel = self::CHANNEL_IN_APP;
    
    #[ORM\Column(type: 'string', length: 50, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;
    
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;
    
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;
    
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;
    
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;
    
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $templateKey = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getTask(): ?Task
    {
        return $this->task;
    }

    public function setTask(?Task $task): self
    {
        $this->task = $task;

        return $this;
    }
    
    public function getType(): string
    {
        return $this->type;
    }
    
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }
    
    public function getChannel(): string
    {
        return $this->channel;
    }
    
    public function setChannel(string $channel): self
    {
        $this->channel = $channel;
        return $this;
    }
    
    public function getStatus(): string
    {
        return $this->status;
    }
    
    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }
    
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }
    
    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }
    
    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }
    
    public function setSentAt(?\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }
    
    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }
    
    public function setDeliveredAt(?\DateTimeImmutable $deliveredAt): self
    {
        $this->deliveredAt = $deliveredAt;
        return $this;
    }
    
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
    
    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }
    
    public function getTemplateKey(): ?string
    {
        return $this->templateKey;
    }
    
    public function setTemplateKey(?string $templateKey): self
    {
        $this->templateKey = $templateKey;
        return $this;
    }
    
    public function markAsSent(): self
    {
        $this->status = self::STATUS_SENT;
        $this->sentAt = new \DateTimeImmutable();
        return $this;
    }
    
    public function markAsDelivered(): self
    {
        $this->status = self::STATUS_DELIVERED;
        $this->deliveredAt = new \DateTimeImmutable();
        return $this;
    }
    
    public function markAsFailed(string $errorMessage): self
    {
        $this->status = self::STATUS_FAILED;
        $this->errorMessage = $errorMessage;
        return $this;
    }
    
    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }
    
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
