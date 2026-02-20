<?php

namespace App\Entity;

use App\Repository\TaskTimeTrackingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TaskTimeTrackingRepository::class)]
#[ORM\Table(name: 'task_time_tracking')]
#[ORM\Index(columns: ['user_id'], name: 'idx_time_tracking_user')]
#[ORM\Index(columns: ['task_id'], name: 'idx_time_tracking_task')]
#[ORM\Index(columns: ['is_active'], name: 'idx_time_tracking_active')]
class TaskTimeTracking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'timeTrackings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'timeTrackings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Task $task = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $timeSpent = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateLogged = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $durationSeconds = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $isActive = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $activity = null;

    public function __construct()
    {
        $this->dateLogged = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTimeSpent(): ?\DateTimeImmutable
    {
        return $this->timeSpent;
    }

    public function setTimeSpent(?\DateTimeImmutable $timeSpent): static
    {
        $this->timeSpent = $timeSpent;

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

    public function getDateLogged(): ?\DateTimeImmutable
    {
        return $this->dateLogged;
    }

    public function setDateLogged(\DateTimeImmutable $dateLogged): static
    {
        $this->dateLogged = $dateLogged;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTimeImmutable $endedAt): static
    {
        $this->endedAt = $endedAt;

        return $this;
    }

    public function getDurationSeconds(): int
    {
        return $this->durationSeconds;
    }

    public function setDurationSeconds(int $durationSeconds): static
    {
        $this->durationSeconds = $durationSeconds;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getActivity(): ?string
    {
        return $this->activity;
    }

    public function setActivity(?string $activity): static
    {
        $this->activity = $activity;

        return $this;
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDuration(): string
    {
        return $this->formatDuration($this->durationSeconds);
    }

    /**
     * Calculate duration from started_at to ended_at
     */
    public function calculateDuration(): int
    {
        if ($this->startedAt === null) {
            return 0;
        }

        $end = $this->endedAt ?? new \DateTimeImmutable();
        return $this->startedAt->diff($end)->s + 
               ($this->startedAt->diff($end)->i * 60) + 
               ($this->startedAt->diff($end)->h * 3600);
    }

    /**
     * Format duration in seconds to human readable format
     */
    public static function formatDuration(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $parts = [];

        if ($days > 0) {
            $parts[] = "{$days}д";
        }
        if ($hours > 0) {
            $parts[] = "{$hours}ч";
        }
        if ($minutes > 0) {
            $parts[] = "{$minutes}м";
        }
        if ($secs > 0 || empty($parts)) {
            $parts[] = "{$secs}с";
        }

        return implode(' ', $parts);
    }

    /**
     * Start tracking
     */
    public function start(): self
    {
        $this->startedAt = new \DateTimeImmutable();
        $this->isActive = true;
        $this->endedAt = null;
        
        return $this;
    }

    /**
     * Stop tracking
     */
    public function stop(): self
    {
        $this->endedAt = new \DateTimeImmutable();
        $this->isActive = false;
        $this->durationSeconds = $this->calculateDuration();
        
        return $this;
    }
}
