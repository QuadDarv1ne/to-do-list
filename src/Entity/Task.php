<?php

namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\{PrePersist, PreUpdate};
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column]
    private ?bool $isDone = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updateAt = null;
    
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deadline = null;
    
    #[ORM\Column(length: 20, options: ['default' => 'normal'])]
    private string $priority = 'normal';
    
        #[ORM\ManyToOne(inversedBy: 'tasks')]
        #[ORM\JoinColumn(nullable: true)]
        private ?User $assignedUser = null;
            
        #[ORM\ManyToOne(inversedBy: 'createdTasks')]
        #[ORM\JoinColumn(nullable: false)]
        private ?User $createdBy = null;
            
        #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'task', orphanRemoval: true)]
        private Collection $comments;
            
        #[ORM\OneToMany(targetEntity: ActivityLog::class, mappedBy: 'task', orphanRemoval: true)]
        private Collection $activityLogs;
            
        #[ORM\OneToMany(targetEntity: TaskTimeTracking::class, mappedBy: 'task', orphanRemoval: true)]
        private Collection $timeTrackings;

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

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isDone(): ?bool
    {
        return $this->isDone;
    }

    public function setIsDone(bool $isDone): static
    {
        $this->isDone = $isDone;

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

    public function getUpdateAt(): ?\DateTimeImmutable
    {
        return $this->updateAt;
    }

    public function setUpdateAt(\DateTimeImmutable $updateAt): static
    {
        $this->updateAt = $updateAt;

        return $this;
    }

    public function getAssignedUser(): ?User
    {
        return $this->assignedUser;
    }

    public function setAssignedUser(?User $assignedUser): static
    {
        $this->assignedUser = $assignedUser;

        return $this;    
    }
    
    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }
    
    public function getDeadline(): ?\DateTimeImmutable
    {
        return $this->deadline;
    }

    public function setDeadline(?\DateTimeImmutable $deadline): static
    {
        $this->deadline = $deadline;

        return $this;
    }
    
    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): static
    {
        $this->priority = $priority;

        return $this;
    }
    
    public function getPriorityLabel(): string
    {
        return match($this->priority) {
            'low' => 'Низкий',
            'high' => 'Высокий',
            'urgent' => 'Срочный',
            default => 'Обычный',
        };
    }
    
    public function getPriorityClass(): string
    {
        return match($this->priority) {
            'low' => 'badge bg-success',
            'high' => 'badge bg-warning',
            'urgent' => 'badge bg-danger',
            default => 'badge bg-secondary',
        };
    }
    
    public function isOverdue(): bool
    {
        if (!$this->deadline || $this->isDone()) {
            return false;
        }
        
        return $this->deadline < new \DateTimeImmutable();
    }
    
    #[PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updateAt = new \DateTimeImmutable();
    }
    
    #[PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updateAt = new \DateTimeImmutable();
    }
    
    public function __construct()
    {
        $this->comments = new ArrayCollection();
    }
}
