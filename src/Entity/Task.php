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
#[ORM\Index(columns: ['user_id'], name: 'idx_task_user')]
#[ORM\Index(columns: ['status'], name: 'idx_task_status')]
#[ORM\Index(columns: ['priority'], name: 'idx_task_priority')]
#[ORM\Index(columns: ['due_date'], name: 'idx_task_due_date')]
#[ORM\Index(columns: ['created_at'], name: 'idx_task_created_at')]
#[ORM\Index(columns: ['assigned_user_id'], name: 'idx_task_assigned_user')]
#[ORM\Index(columns: ['category_id'], name: 'idx_task_category')]
#[ORM\Index(columns: ['completed_at'], name: 'idx_task_completed_at')]
#[ORM\Index(columns: ['updated_at'], name: 'idx_task_updated_at')]
#[ORM\Index(columns: ['created_at', 'status'], name: 'idx_task_created_at_status')]
#[ORM\Index(columns: ['user_id', 'status'], name: 'idx_task_user_status')]
#[ORM\Index(columns: ['user_id', 'priority'], name: 'idx_task_user_priority')]
#[ORM\Index(columns: ['assigned_user_id', 'status'], name: 'idx_task_assigned_user_status')]
#[ORM\Index(columns: ['category_id', 'status'], name: 'idx_task_category_status')]
#[ORM\Index(columns: ['due_date', 'status'], name: 'idx_task_due_date_status')]
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
        choices: ['low', 'medium', 'high', 'urgent'],
        message: 'Приоритет задачи должен быть одним из: low, medium, high, urgent'
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

    #[ORM\OneToMany(mappedBy: 'dependentTask', targetEntity: TaskDependency::class, cascade: ['persist', 'remove'])]
    private Collection $dependencies;

    #[ORM\OneToMany(mappedBy: 'dependencyTask', targetEntity: TaskDependency::class)]
    private Collection $dependents;

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
        // If changing to completed status and not already completed, set completion timestamp
        if ($status === 'completed' && $this->status !== 'completed') {
            $this->completedAt = new \DateTime();
        }
        // If changing from completed to another status, clear completion timestamp
        elseif ($this->status === 'completed' && $status !== 'completed') {
            $this->completedAt = null;
        }
        
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
            'high' => 'Высокий',
            'urgent' => 'Критический'
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

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): static
    {
        $this->completedAt = $completedAt;
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

    /**
     * @return Collection<int, TaskDependency>
     */
    public function getDependencies(): Collection
    {
        return $this->dependencies;
    }

    public function addDependency(TaskDependency $dependency): static
    {
        if (!$this->dependencies->contains($dependency)) {
            $this->dependencies->add($dependency);
            $dependency->setDependentTask($this);
        }

        return $this;
    }

    public function removeDependency(TaskDependency $dependency): static
    {
        if ($this->dependencies->removeElement($dependency)) {
            // set the owning side to null (unless already changed)
            if ($dependency->getDependentTask() === $this) {
                $dependency->setDependentTask(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TaskDependency>
     */
    public function getDependents(): Collection
    {
        return $this->dependents;
    }

    public function addDependent(TaskDependency $dependent): static
    {
        if (!$this->dependents->contains($dependent)) {
            $this->dependents->add($dependent);
        }

        return $this;
    }

    public function removeDependent(TaskDependency $dependent): static
    {
        if ($this->dependents->removeElement($dependent)) {
            // set the owning side to null (unless already changed)
            if ($dependent->getDependencyTask() === $this) {
                $dependent->setDependencyTask(null);
            }
        }

        return $this;
    }

    /**
     * Check if this task can be started (all blocking dependencies are satisfied)
     */
    public function canStart(): bool
    {
        foreach ($this->dependencies as $dependency) {
            if (!$dependency->canStartDependentTask()) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get all tasks that this task depends on
     */
    public function getDependencyTasks(): array
    {
        $tasks = [];
        foreach ($this->dependencies as $dependency) {
            $tasks[] = $dependency->getDependencyTask();
        }
        return $tasks;
    }

    /**
     * Get all tasks that depend on this task
     */
    public function getDependentTasks(): array
    {
        $tasks = [];
        foreach ($this->dependents as $dependent) {
            $tasks[] = $dependent->getDependentTask();
        }
        return $tasks;
    }
    
    /**
     * Calculate the time taken to complete this task (in hours)
     */
    public function getCompletionTimeInHours(): ?float
    {
        if (!$this->isCompleted() || !$this->getCompletedAt()) {
            return null;
        }
        
        $interval = $this->getCreatedAt()->diff($this->getCompletedAt());
        $hours = $interval->days * 24 + $interval->h + ($interval->i / 60.0);
        
        return round($hours, 2);
    }
    
    /**
     * Calculate the number of days between creation and completion
     */
    public function getCompletionTimeInDays(): ?int
    {
        if (!$this->isCompleted() || !$this->getCompletedAt()) {
            return null;
        }
        
        $interval = $this->getCreatedAt()->diff($this->getCompletedAt());
        return $interval->days;
    }
    
    /**
     * Check if the task was completed late (after its due date)
     */
    public function isCompletedLate(): bool
    {
        if (!$this->isCompleted() || !$this->getCompletedAt() || !$this->getDueDate()) {
            return false;
        }
        
        return $this->getCompletedAt() > $this->getDueDate();
    }
    
    /**
     * Get the number of days the task was overdue (if completed late)
     */
    public function getOverdueDays(): ?int
    {
        if (!$this->isCompletedLate()) {
            return null;
        }
        
        $interval = $this->getDueDate()->diff($this->getCompletedAt());
        return $interval->days;
    }
    
    /**
     * Get the total time spent on this task through time tracking
     * Returns the sum of all tracked time in hours
     */
    public function getTotalTimeSpent(): float
    {
        $totalTime = 0;
        foreach ($this->getTimeTrackings() as $tracking) {
            // Convert time spent (DateTimeImmutable) to hours
            // The timeSpent field stores time in HH:MM:SS format, so we need to extract hours
            $timeSpent = $tracking->getTimeSpent();
            if ($timeSpent) {
                $hours = $timeSpent->format('H');
                $minutes = $timeSpent->format('i');
                $seconds = $timeSpent->format('s');
                
                $totalTime += $hours + ($minutes / 60.0) + ($seconds / 3600.0);
            }
        }
        return $totalTime;
    }
}