<?php

namespace App\Entity;

use App\Repository\TaskTimeTrackingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;
use App\Entity\Task;

#[ORM\Entity(repositoryClass: TaskTimeTrackingRepository::class)]
#[ORM\Table(name: 'task_time_tracking')]
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

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private ?\DateTimeImmutable $timeSpent = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateLogged = null;

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

    public function setTimeSpent(\DateTimeImmutable $timeSpent): static
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
}
