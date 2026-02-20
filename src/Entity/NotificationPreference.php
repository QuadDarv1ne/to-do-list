<?php

namespace App\Entity;

use App\Repository\NotificationPreferenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationPreferenceRepository::class)]
#[ORM\Table(name: 'notification_preferences')]
#[ORM\Index(columns: ['user_id'], name: 'idx_notif_pref_user')]
class NotificationPreference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'notificationPreference', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $emailSettings = [];

    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $pushSettings = [];

    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $inAppSettings = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $quietHours = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $frequency = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        // Default settings
        $this->emailSettings = [
            'enabled' => true,
            'task_assigned' => true,
            'task_completed' => true,
            'task_commented' => true,
            'deadline_reminder' => true,
            'daily_digest' => false,
            'weekly_summary' => true,
        ];
        $this->pushSettings = [
            'enabled' => true,
            'task_assigned' => true,
            'task_completed' => false,
            'task_commented' => true,
            'deadline_reminder' => true,
        ];
        $this->inAppSettings = [
            'enabled' => true,
            'task_assigned' => true,
            'task_completed' => true,
            'task_commented' => true,
            'deadline_reminder' => true,
            'mentions' => true,
        ];
        $this->quietHours = [
            'enabled' => false,
            'start' => '22:00',
            'end' => '08:00',
        ];
        $this->frequency = [
            'immediate' => true,
            'batched' => false,
            'batch_interval' => 60,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getEmailSettings(): array
    {
        return $this->emailSettings;
    }

    public function setEmailSettings(array $emailSettings): self
    {
        $this->emailSettings = $emailSettings;
        return $this;
    }

    public function getPushSettings(): array
    {
        return $this->pushSettings;
    }

    public function setPushSettings(array $pushSettings): self
    {
        $this->pushSettings = $pushSettings;
        return $this;
    }

    public function getInAppSettings(): array
    {
        return $this->inAppSettings;
    }

    public function setInAppSettings(array $inAppSettings): self
    {
        $this->inAppSettings = $inAppSettings;
        return $this;
    }

    public function getQuietHours(): ?array
    {
        return $this->quietHours;
    }

    public function setQuietHours(?array $quietHours): self
    {
        $this->quietHours = $quietHours;
        return $this;
    }

    public function getFrequency(): ?array
    {
        return $this->frequency;
    }

    public function setFrequency(?array $frequency): self
    {
        $this->frequency = $frequency;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
