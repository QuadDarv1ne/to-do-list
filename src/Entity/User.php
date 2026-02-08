<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Entity\Task;
use App\Entity\Notification;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Логин не может быть пустым')]
    #[Assert\Length(
        min: 3,
        max: 180,
        minMessage: 'Логин должен содержать минимум {{ limit }} символа',
        maxMessage: 'Логин не может быть длиннее {{ limit }} символов'
    )]
    private ?string $username = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'Email не может быть пустым')]
    #[Assert\Email(message: 'Некорректный email адрес')]
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
    #[Assert\Length(max: 100, maxMessage: 'Имя не может быть длиннее {{ limit }} символов')]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Фамилия не может быть длиннее {{ limit }} символов')]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(
        pattern: '/^\+7\d{10}$/',
        message: 'Телефон должен быть в формате +79993332211'
    )]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $position = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $department = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastLoginAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $passwordChangedAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $timezone = 'Europe/Moscow';

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $locale = 'ru';

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $failedLoginAttempts = 0;
    
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resetPasswordToken = null;
    
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $resetPasswordRequestedAt = null;
    
    private ?string $plainPassword = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lockedUntil = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $avatar = null;

    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'assignedUser', orphanRemoval: true)]
    private Collection $tasks;

    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'createdBy', orphanRemoval: true)]
    private Collection $createdTasks;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Notification::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $notifications;

    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'author', orphanRemoval: true)]
    private Collection $comments;

    #[ORM\OneToMany(targetEntity: ActivityLog::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $activityLogs;
    
    #[ORM\OneToMany(targetEntity: TaskTimeTracking::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $timeTrackings;
    
    #[ORM\OneToMany(targetEntity: TaskCategory::class, mappedBy: 'owner', orphanRemoval: true)]
    private Collection $categories;
    
    #[ORM\OneToMany(mappedBy: 'recipient', targetEntity: TaskNotification::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $taskNotifications;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->roles = ['ROLE_USER'];
        $this->tasks = new \Doctrine\Common\Collections\ArrayCollection();
        $this->createdTasks = new \Doctrine\Common\Collections\ArrayCollection();
        $this->comments = new \Doctrine\Common\Collections\ArrayCollection();
        $this->activityLogs = new \Doctrine\Common\Collections\ArrayCollection();
        $this->timeTrackings = new \Doctrine\Common\Collections\ArrayCollection();
        $this->categories = new \Doctrine\Common\Collections\ArrayCollection();
        $this->notifications = new \Doctrine\Common\Collections\ArrayCollection();
        $this->taskNotifications = new \Doctrine\Common\Collections\ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
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
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        if (!in_array('ROLE_USER', $roles)) {
            $roles[] = 'ROLE_USER';
        }

        return array_unique($roles);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function addRole(string $role): static
    {
        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }
        return $this;
    }

    public function removeRole(string $role): static
    {
        $key = array_search($role, $this->roles, true);
        if ($key !== false) {
            unset($this->roles[$key]);
        }
        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        $this->passwordChangedAt = new \DateTime();
        return $this;
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
        return trim($this->firstName . ' ' . $this->lastName) ?: $this->username;
    }

    public function getInitials(): string
    {
        $initials = '';
        if ($this->firstName) {
            $initials .= mb_substr($this->firstName, 0, 1);
        }
        if ($this->lastName) {
            $initials .= mb_substr($this->lastName, 0, 1);
        }
        return $initials ?: mb_substr($this->username, 0, 2);
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
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

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
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

    public function getPasswordChangedAt(): ?\DateTimeInterface
    {
        return $this->passwordChangedAt;
    }

    public function setPasswordChangedAt(?\DateTimeInterface $passwordChangedAt): static
    {
        $this->passwordChangedAt = $passwordChangedAt;
        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): static
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): static
    {
        $this->locale = $locale;
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

    public function incrementFailedLoginAttempts(): static
    {
        $this->failedLoginAttempts++;
        return $this;
    }

    public function resetFailedLoginAttempts(): static
    {
        $this->failedLoginAttempts = 0;
        return $this;
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

    public function isLocked(): bool
    {
        if (!$this->lockedUntil) {
            return false;
        }
        return $this->lockedUntil > new \DateTime();
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

    public function getResetPasswordToken(): ?string
    {
        return $this->resetPasswordToken;
    }

    public function setResetPasswordToken(?string $resetPasswordToken): static
    {
        $this->resetPasswordToken = $resetPasswordToken;

        return $this;
    }

    public function getResetPasswordRequestedAt(): ?\DateTimeImmutable
    {
        return $this->resetPasswordRequestedAt;
    }

    public function setResetPasswordRequestedAt(\DateTimeImmutable $resetPasswordRequestedAt): static
    {
        $this->resetPasswordRequestedAt = $resetPasswordRequestedAt;

        return $this;
    }
    
    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }
    
    public function getAvatarUrl(): ?string
    {
        if ($this->avatar) {
            return '/uploads/avatars/' . $this->avatar;
        }
        
        // Генерация аватара по умолчанию
        $initials = $this->getInitials();
        $colors = ['FF6B6B', '4ECDC4', '45B7D1', '96CEB4', 'FFEAA7'];
        $color = $colors[abs(crc32($this->email) % count($colors))];
        
        return sprintf(
            'https://ui-avatars.com/api/?name=%s&background=%s&color=fff&size=128',
            urlencode($initials),
            $color
        );
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'roles' => $this->roles,
            'password' => hash('crc32c', $this->password ?? ''),
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->id = $data['id'] ?? null;
        $this->username = $data['username'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->roles = $data['roles'] ?? [];
        // Password не восстанавливаем из сериализованных данных
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'fullName' => $this->getFullName(),
            'roles' => $this->getRoles(),
            'isActive' => $this->isActive,
            'isVerified' => $this->isVerified,
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'lastLoginAt' => $this->lastLoginAt?->format('Y-m-d H:i:s'),
            'avatarUrl' => $this->getAvatarUrl(),
        ];
    }

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getCreatedTasks(): Collection
    {
        return $this->createdTasks;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    public function getActivityLogs(): Collection
    {
        return $this->activityLogs;
    }

    /**
     * @return Collection<int, TaskTimeTracking>
     */
    public function getTimeTrackings(): Collection
    {
        return $this->timeTrackings;
    }

    /**
     * @return Collection<int, TaskCategory>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }
    
    /**
     * @return Collection<int, TaskNotification>
     */
    public function getTaskNotifications(): Collection
    {
        return $this->taskNotifications;
    }
}