<?php

namespace App\Entity;

use App\Repository\HabitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HabitRepository::class)]
#[ORM\Table(name: 'habits')]
class Habit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private ?string $frequency = 'daily'; // daily, weekly, custom

    #[ORM\Column(type: Types::JSON)]
    private array $weekDays = []; // For weekly habits: [1,2,3,4,5] = Mon-Fri

    #[ORM\Column]
    private ?int $targetCount = 1; // How many times per day/week

    #[ORM\Column(length: 50)]
    private ?string $category = 'health'; // health, productivity, learning, fitness, mindfulness

    #[ORM\Column(length: 50)]
    private ?string $icon = 'fa-check';

    #[ORM\Column(length: 7)]
    private ?string $color = '#667eea';

    #[ORM\Column]
    private ?bool $active = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'habit', targetEntity: HabitLog::class, cascade: ['persist', 'remove'])]
    private Collection $logs;

    public function __construct()
    {
        $this->logs = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getFrequency(): ?string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): static
    {
        $this->frequency = $frequency;

        return $this;
    }

    public function getWeekDays(): array
    {
        return $this->weekDays;
    }

    public function setWeekDays(array $weekDays): static
    {
        $this->weekDays = $weekDays;

        return $this;
    }

    public function getTargetCount(): ?int
    {
        return $this->targetCount;
    }

    public function setTargetCount(int $targetCount): static
    {
        $this->targetCount = $targetCount;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getLogs(): Collection
    {
        return $this->logs;
    }

    public function addLog(HabitLog $log): static
    {
        if (!$this->logs->contains($log)) {
            $this->logs->add($log);
            $log->setHabit($this);
        }

        return $this;
    }

    public function removeLog(HabitLog $log): static
    {
        if ($this->logs->removeElement($log)) {
            if ($log->getHabit() === $this) {
                $log->setHabit(null);
            }
        }

        return $this;
    }

    public function getCurrentStreak(): int
    {
        $logs = $this->logs->toArray();
        usort($logs, fn ($a, $b) => $b->getDate() <=> $a->getDate());

        $streak = 0;
        $currentDate = new \DateTime();
        $currentDate->setTime(0, 0, 0);

        foreach ($logs as $log) {
            $logDate = clone $log->getDate();
            $logDate->setTime(0, 0, 0);

            $diff = $currentDate->diff($logDate)->days;

            if ($diff === $streak) {
                $streak++;
                $currentDate->modify('-1 day');
            } else {
                break;
            }
        }

        return $streak;
    }

    public function getLongestStreak(): int
    {
        $logs = $this->logs->toArray();
        usort($logs, fn ($a, $b) => $a->getDate() <=> $b->getDate());

        $maxStreak = 0;
        $currentStreak = 0;
        $previousDate = null;

        foreach ($logs as $log) {
            $logDate = clone $log->getDate();
            $logDate->setTime(0, 0, 0);

            if ($previousDate === null) {
                $currentStreak = 1;
            } else {
                $diff = $previousDate->diff($logDate)->days;
                if ($diff === 1) {
                    $currentStreak++;
                } else {
                    $maxStreak = max($maxStreak, $currentStreak);
                    $currentStreak = 1;
                }
            }

            $previousDate = $logDate;
        }

        return max($maxStreak, $currentStreak);
    }

    public function getCompletionRate(int $days = 30): float
    {
        $startDate = new \DateTime("-{$days} days");
        $completedDays = 0;

        foreach ($this->logs as $log) {
            if ($log->getDate() >= $startDate) {
                $completedDays++;
            }
        }

        return $days > 0 ? ($completedDays / $days) * 100 : 0;
    }
}
