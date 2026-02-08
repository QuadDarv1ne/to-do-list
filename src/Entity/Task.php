<?php

namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\PrePersist;
use Doctrine\ORM\Mapping\PreUpdate;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'tasks')]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank(message: 'Заголовок задачи не может быть пустым')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Заголовок задачи не может быть длиннее {{ limit }} символов'
    )]
    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[Assert\Length(
        max: 10000,
        maxMessage: 'Описание задачи не может быть длиннее {{ limit }} символов'
    )]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[Assert\Choice(
        choices: ['pending', 'in_progress', 'completed'],
        message: 'Статус задачи должен быть одним из: pending, in_progress, completed'
    )]
    #[ORM\Column(length: 20, options: ['default' => 'pending'])]
    private string $status = 'pending';

    #[Assert\Choice(
        choices: ['low', 'medium', 'high'],
        message: 'Приоритет задачи должен быть одним из: low, medium, high'
    )]
    #[ORM\Column(length: 20, options: ['default' => 'medium'])]
    private string $priority = 'medium';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[Assert\GreaterThanOrEqual(
        propertyPath: 'createdAt',
        message: 'Срок выполнения не может быть раньше даты создания задачи'
    )]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'assignedTasks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $assignedUser = null;

    #[ORM\ManyToOne(targetEntity: TaskCategory::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: true)]
    private ?TaskCategory $category = null;

    #[ORM\OneToMany(mappedBy: 'task', targetEntity: Comment::class, orphanRemoval: true)]
    private Collection $comments;

    #[ORM\OneToMany(mappedBy: 'task', targetEntity: ActivityLog::class, orphanRemoval: true)]
    private Collection $activityLogs;

    #[ORM\OneToMany(mappedBy: 'task', targetEntity: TaskNotification::class)]
    private Collection $notifications;

    #[ORM\OneToOne(mappedBy: 'task', targetEntity: TaskRecurrence::class, cascade: ['persist', 'remove'])]
    private ?TaskRecurrence $recurrence = null;

    #[ORM\OneToMany(mappedBy: 'task', targetEntity: TaskTimeTracking::class, orphanRemoval: true)]
    private Collection $timeTrackings;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'tasks')]
    #[ORM\JoinTable(name: 'task_tags')]
    private Collection $tags;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->activityLogs = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->timeTrackings = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    #[PrePersist]
    #[PreUpdate]
    public function updateTimestamps(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTime();
        }
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Alias for getTitle() for backward compatibility
     */
    public function getName(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    /**
     * Returns the localized priority label
     */
    public function getPriorityLabel(): string
    {
        $labels = [
            'low' => 'Низкий',
            'medium' => 'Средний',
            'high' => 'Высокий'
        ];
        
        return $labels[$this->priority] ?? $this->priority;
    }

    public function setPriority(string $priority): static
    {
        $this->priority = $priority;
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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    /**
     * Alias for getDueDate() for backward compatibility
     */
    public function getDeadline(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeInterface $dueDate): static
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Alias for getUser() for backward compatibility
     */
    public function getCreatedBy(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Alias for setUser() for backward compatibility
     */
    public function setCreatedBy(?User $user): static
    {
        $this->user = $user;
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

    public function getCategory(): ?TaskCategory
    {
        return $this->category;
    }

    public function setCategory(?TaskCategory $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function isOverdue(): bool
    {
        return $this->dueDate && $this->dueDate < new \DateTime() && $this->status !== 'completed';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * @deprecated Use isCompleted() instead
     */
    public function isDone(): bool
    {
        return $this->isCompleted();
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setTask($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            // set the owning side to null (unless already changed)
            if ($comment->getTask() === $this) {
                $comment->setTask(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    public function getActivityLogs(): Collection
    {
        return $this->activityLogs;
    }

    public function addActivityLog(ActivityLog $activityLog): static
    {
        if (!$this->activityLogs->contains($activityLog)) {
            $this->activityLogs->add($activityLog);
            $activityLog->setTask($this);
        }

        return $this;
    }

    public function removeActivityLog(ActivityLog $activityLog): static
    {
        if ($this->activityLogs->removeElement($activityLog)) {
            // set the owning side to null (unless already changed)
            if ($activityLog->getTask() === $this) {
                $activityLog->setTask(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TaskNotification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(TaskNotification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setTask($this);
        }

        return $this;
    }

    public function removeNotification(TaskNotification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getTask() === $this) {
                $notification->setTask(null);
            }
        }

        return $this;
    }

    public function getRecurrence(): ?TaskRecurrence
    {
        return $this->recurrence;
    }

    public function setRecurrence(?TaskRecurrence $recurrence): static
    {
        // unset the owning side of the relation if necessary
        if ($recurrence === null && $this->recurrence !== null) {
            $this->recurrence->setTask(null);
        }

        // set the owning side of the relation if necessary
        if ($recurrence !== null && $recurrence->getTask() !== $this) {
            $recurrence->setTask($this);
        }

        $this->recurrence = $recurrence;

        return $this;
    }

    /**
     * @return Collection<int, TaskTimeTracking>
     */
    public function getTimeTrackings(): Collection
    {
        return $this->timeTrackings;
    }

    public function addTimeTracking(TaskTimeTracking $timeTracking): static
    {
        if (!$this->timeTrackings->contains($timeTracking)) {
            $this->timeTrackings->add($timeTracking);
            $timeTracking->setTask($this);
        }

        return $this;
    }

    public function removeTimeTracking(TaskTimeTracking $timeTracking): static
    {
        if ($this->timeTrackings->removeElement($timeTracking)) {
            // set the owning side to null (unless already changed)
            if ($timeTracking->getTask() === $this) {
                $timeTracking->setTask(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }
}