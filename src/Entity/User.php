<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Google\TwoFactorInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[ORM\Index(name: 'idx_user_email', columns: ['email'])]
#[ORM\Index(name: 'idx_user_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_user_last_login', columns: ['last_login_at'])]
#[UniqueEntity(fields: ['username'], message: 'Пользователь с таким логином уже существует')]
#[UniqueEntity(fields: ['email'], message: 'Пользователь с таким email уже существует')]
#[ORM\Index(columns: ['last_login_at'], name: 'idx_users_last_login')]
#[ORM\Index(columns: ['is_active'], name: 'idx_users_is_active')]
#[ORM\Index(columns: ['created_at'], name: 'idx_users_created_at')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 180)]
    private ?string $username = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(
        pattern: '/^\\+7\\d{10}$/',
        message: 'Телефон должен быть в формате +79993332211',
    )]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $position = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $department = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastLoginAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $totpSecret = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $totpSecretTemp = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isTotpEnabled = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $totpEnabledAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $backupCodes = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Task::class, orphanRemoval: true)]
    private Collection $tasks;

    #[ORM\OneToMany(mappedBy: 'assignedUser', targetEntity: Task::class)]
    private Collection $assignedTasks;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: TaskCategory::class, orphanRemoval: true)]
    private Collection $taskCategories;

    #[ORM\OneToMany(mappedBy: 'author', targetEntity: Comment::class, orphanRemoval: true)]
    private Collection $comments;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ActivityLog::class, orphanRemoval: true)]
    private Collection $activityLogs;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: TaskRecurrence::class)]
    private Collection $taskRecurrences;

    #[ORM\OneToMany(mappedBy: 'recipient', targetEntity: TaskNotification::class)]
    private Collection $notifications;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Notification::class)]
    private Collection $systemNotifications;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: TaskTimeTracking::class)]
    private Collection $timeTrackings;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Tag::class, orphanRemoval: true)]
    private Collection $tags;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $passwordChangedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lockedUntil = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $failedLoginAttempts = 0;

    #[ORM\OneToMany(mappedBy: 'author', targetEntity: KnowledgeBaseArticle::class)]
    private Collection $articles;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Webhook::class, orphanRemoval: true)]
    private Collection $webhooks;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: FilterView::class, orphanRemoval: true)]
    private Collection $filterViews;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
        $this->assignedTasks = new ArrayCollection();
        $this->taskCategories = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->activityLogs = new ArrayCollection();
        $this->taskRecurrences = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->systemNotifications = new ArrayCollection();
        $this->timeTrackings = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->webhooks = new ArrayCollection();
        $this->filterViews = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        $this->passwordChangedAt = new \DateTime();

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        $fullName = trim($this->firstName . ' ' . $this->lastName);

        return $fullName ?: $this->username;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(?string $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): static
    {
        $this->department = $department;

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

    public function getLastLoginAt(): ?\DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeInterface $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): static
    {
        $this->totpSecret = $totpSecret;

        return $this;
    }

    public function getTotpSecretTemp(): ?string
    {
        return $this->totpSecretTemp;
    }

    public function setTotpSecretTemp(?string $totpSecretTemp): static
    {
        $this->totpSecretTemp = $totpSecretTemp;

        return $this;
    }

    public function isTotpEnabled(): bool
    {
        return $this->isTotpEnabled;
    }

    public function setIsTotpEnabled(bool $isTotpEnabled): static
    {
        $this->isTotpEnabled = $isTotpEnabled;

        return $this;
    }

    public function getTotpEnabledAt(): ?\DateTimeImmutable
    {
        return $this->totpEnabledAt;
    }

    public function setTotpEnabledAt(\DateTimeImmutable $totpEnabledAt): static
    {
        $this->totpEnabledAt = $totpEnabledAt;

        return $this;
    }

    public function getBackupCodes(): ?array
    {
        return $this->backupCodes;
    }

    public function setBackupCodes(?array $backupCodes): static
    {
        $this->backupCodes = $backupCodes;

        return $this;
    }

    // Google Authenticator Interface methods
    public function isGoogleAuthenticatorEnabled(): bool
    {
        return $this->isTotpEnabled && $this->totpSecret !== null;
    }

    public function getGoogleAuthenticatorUsername(): string
    {
        return $this->email;
    }

    public function getGoogleAuthenticatorSecret(): ?string
    {
        // Return temp secret during setup, or main secret if enabled
        if ($this->totpSecretTemp !== null) {
            return $this->totpSecretTemp;
        }

        return $this->isTotpEnabled ? $this->totpSecret : null;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(Task $task): static
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->setUser($this);
        }

        return $this;
    }

    public function removeTask(Task $task): static
    {
        if ($this->tasks->removeElement($task)) {
            // set the owning side to null (unless already changed)
            if ($task->getUser() === $this) {
                $task->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getAssignedTasks(): Collection
    {
        return $this->assignedTasks;
    }

    public function addAssignedTask(Task $assignedTask): static
    {
        if (!$this->assignedTasks->contains($assignedTask)) {
            $this->assignedTasks->add($assignedTask);
            $assignedTask->setAssignedUser($this);
        }

        return $this;
    }

    public function removeAssignedTask(Task $assignedTask): static
    {
        if ($this->assignedTasks->removeElement($assignedTask)) {
            // set the owning side to null (unless already changed)
            if ($assignedTask->getAssignedUser() === $this) {
                $assignedTask->setAssignedUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TaskRecurrence>
     */
    public function getTaskRecurrences(): Collection
    {
        return $this->taskRecurrences;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getSystemNotifications(): Collection
    {
        return $this->systemNotifications;
    }

    public function addSystemNotification(Notification $notification): static
    {
        if (!$this->systemNotifications->contains($notification)) {
            $this->systemNotifications->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeSystemNotification(Notification $notification): static
    {
        if ($this->systemNotifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }

        return $this;
    }

    public function addTaskRecurrence(TaskRecurrence $taskRecurrence): static
    {
        if (!$this->taskRecurrences->contains($taskRecurrence)) {
            $this->taskRecurrences->add($taskRecurrence);
            $taskRecurrence->setUser($this);
        }

        return $this;
    }

    public function removeTaskRecurrence(TaskRecurrence $taskRecurrence): static
    {
        if ($this->taskRecurrences->removeElement($taskRecurrence)) {
            // set the owning side to null (unless already changed)
            if ($taskRecurrence->getUser() === $this) {
                $taskRecurrence->setUser(null);
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
            $notification->setRecipient($this);
        }

        return $this;
    }

    public function removeNotification(TaskNotification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getRecipient() === $this) {
                $notification->setRecipient(null);
            }
        }

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
            $timeTracking->setUser($this);
        }

        return $this;
    }

    public function removeTimeTracking(TaskTimeTracking $timeTracking): static
    {
        if ($this->timeTrackings->removeElement($timeTracking)) {
            // set the owning side to null (unless already changed)
            if ($timeTracking->getUser() === $this) {
                $timeTracking->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TaskCategory>
     */
    public function getTaskCategories(): Collection
    {
        return $this->taskCategories;
    }

    public function addTaskCategory(TaskCategory $taskCategory): static
    {
        if (!$this->taskCategories->contains($taskCategory)) {
            $this->taskCategories->add($taskCategory);
            $taskCategory->setUser($this);
        }

        return $this;
    }

    public function removeTaskCategory(TaskCategory $taskCategory): static
    {
        if ($this->taskCategories->removeElement($taskCategory)) {
            // set the owning side to null (unless already changed)
            if ($taskCategory->getUser() === $this) {
                $taskCategory->setUser(null);
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
            $tag->setUser($this);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        if ($this->tags->removeElement($tag)) {
            // set the owning side to null (unless already changed)
            if ($tag->getUser() === $this) {
                $tag->setUser(null);
            }
        }

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

    public function getPasswordChangedAt(): ?\DateTimeInterface
    {
        return $this->passwordChangedAt;
    }

    public function setPasswordChangedAt(?\DateTimeInterface $passwordChangedAt): static
    {
        $this->passwordChangedAt = $passwordChangedAt;

        return $this;
    }

    public function hasRole(string $role): bool
    {
        return \in_array($role, $this->getRoles());
    }

    public function getLockedUntil(): ?\DateTimeInterface
    {
        return $this->lockedUntil;
    }

    public function setLockedUntil(?\DateTimeInterface $lockedUntil): static
    {
        $this->lockedUntil = $lockedUntil;

        return $this;
    }

    public function getFailedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }

    public function setFailedLoginAttempts(int $failedLoginAttempts): static
    {
        $this->failedLoginAttempts = $failedLoginAttempts;

        return $this;
    }

    /**
     * Check if the user account is currently locked
     *
     * @return bool True if the account is locked, false otherwise
     */
    public function isAccountLocked(): bool
    {
        if ($this->lockedUntil === null) {
            return false;
        }

        return new \DateTime() < $this->lockedUntil;
    }

    /**
     * Lock the user account until the specified time
     *
     * @param \DateTimeInterface|null $until Time until which the account should be locked
     */
    public function lockAccount(?\DateTimeInterface $until): static
    {
        $this->lockedUntil = $until;
        $this->failedLoginAttempts = 0; // Reset failed attempts when locking

        return $this;
    }

    /**
     * Unlock the user account
     *
     */
    public function unlockAccount(): static
    {
        $this->lockedUntil = null;
        $this->failedLoginAttempts = 0;

        return $this;
    }

    /**
     * Get avatar URL (using Gravatar)
     */
    public function getAvatarUrl(): string
    {
        // Use Gravatar based on email
        $hash = md5(strtolower(trim($this->email ?? '')));
        $default = 'identicon'; // Default avatar style
        $size = 40;

        return "https://www.gravatar.com/avatar/{$hash}?d={$default}&s={$size}";
    }

    /**
     * Get initials for avatar fallback
     */
    public function getInitials(): string
    {
        $firstName = $this->firstName ?? '';
        $lastName = $this->lastName ?? '';

        if ($firstName && $lastName) {
            return strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
        }

        if ($this->username) {
            return strtoupper(substr($this->username, 0, 2));
        }

        return 'U';
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return \in_array('ROLE_ADMIN', $this->roles, true) || \in_array('ROLE_SUPER_ADMIN', $this->roles, true);
    }

    /**
     * Check if user is manager
     */
    public function isManager(): bool
    {
        return \in_array('ROLE_MANAGER', $this->roles, true) || $this->isAdmin();
    }

    /**
     * Check if user is analyst
     */
    public function isAnalyst(): bool
    {
        return \in_array('ROLE_ANALYST', $this->roles, true) || $this->isManager() || $this->isAdmin();
    }

    /**
     * Get role display name
     */
    public function getRoleDisplayName(): string
    {
        if ($this->hasRole('ROLE_SUPER_ADMIN')) {
            return 'Супер Администратор';
        }
        if ($this->hasRole('ROLE_ADMIN')) {
            return 'Администратор';
        }
        if ($this->hasRole('ROLE_MANAGER')) {
            return 'Менеджер';
        }
        if ($this->hasRole('ROLE_ANALYST')) {
            return 'Аналитик';
        }

        return 'Пользователь';
    }

    /**
     * Can manage users
     */
    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Can view reports
     */
    public function canViewReports(): bool
    {
        return $this->isAnalyst() || $this->isManager() || $this->isAdmin();
    }

    /**
     * Can manage budget
     */
    public function canManageBudget(): bool
    {
        return $this->isManager() || $this->isAdmin();
    }

    /**
     * Can manage resources
     */
    public function canManageResources(): bool
    {
        return $this->isManager() || $this->isAdmin();
    }

    /**
     * @return Collection<int, KnowledgeBaseArticle>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(KnowledgeBaseArticle $article): static
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
            $article->setAuthor($this);
        }

        return $this;
    }

    public function removeArticle(KnowledgeBaseArticle $article): static
    {
        if ($this->articles->removeElement($article)) {
            if ($article->getAuthor() === $this) {
                $article->setAuthor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Webhook>
     */
    public function getWebhooks(): Collection
    {
        return $this->webhooks;
    }

    public function addWebhook(Webhook $webhook): static
    {
        if (!$this->webhooks->contains($webhook)) {
            $this->webhooks->add($webhook);
            $webhook->setUser($this);
        }

        return $this;
    }

    public function removeWebhook(Webhook $webhook): static
    {
        if ($this->webhooks->removeElement($webhook)) {
            // set the owning side to null (unless already changed)
            if ($webhook->getUser() === $this) {
                $webhook->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, FilterView>
     */
    public function getFilterViews(): Collection
    {
        return $this->filterViews;
    }

    public function addFilterView(FilterView $filterView): static
    {
        if (!$this->filterViews->contains($filterView)) {
            $this->filterViews->add($filterView);
            $filterView->setUser($this);
        }

        return $this;
    }

    public function removeFilterView(FilterView $filterView): static
    {
        if ($this->filterViews->removeElement($filterView)) {
            // set the owning side to null (unless already changed)
            if ($filterView->getUser() === $this) {
                $filterView->setUser(null);
            }
        }

        return $this;
    }
}
