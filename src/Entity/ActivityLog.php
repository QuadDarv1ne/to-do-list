<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\{PrePersist};

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Table(name: 'activity_logs')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['user_id', 'created_at'], name: 'idx_activity_user_date')]
#[ORM\Index(columns: ['task_id'], name: 'idx_activity_task')]
#[ORM\Index(columns: ['event_type'], name: 'idx_activity_event_type')]
#[ORM\Index(columns: ['created_at'], name: 'idx_activity_created')]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $action = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'activityLogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'activityLogs')]
    #[ORM\JoinColumn(nullable: true)]  // Made nullable to support login events
    private ?Task $task = null;

    #[ORM\Column(length: 20, nullable: true)]  // Added to distinguish event types
    private ?string $eventType = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getTask(): ?Task
    {
        return $this->task;
    }

    public function setTask(?Task $task): static
    {
        $this->task = $task;

        return $this;
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(?string $eventType): static
    {
        $this->eventType = $eventType;

        return $this;
    }

    #[PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
