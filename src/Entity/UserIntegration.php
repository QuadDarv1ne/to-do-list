<?php

namespace App\Entity;

use App\Repository\UserIntegrationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserIntegrationRepository::class)]
#[ORM\Table(name: 'user_integrations')]
#[ORM\UniqueConstraint(name: 'user_integration_unique', columns: ['user_id', 'integration_type'])]
#[ORM\Index(columns: ['user_id'], name: 'idx_user_integrations_user')]
#[ORM\Index(columns: ['integration_type'], name: 'idx_user_integrations_type')]
#[ORM\Index(columns: ['is_active'], name: 'idx_user_integrations_active')]
class UserIntegration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private string $integrationType = ''; // github, slack, jira, trello, google_calendar, telegram, zapier

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalId = null; // ID пользователя во внешней системе

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $accessToken = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $refreshToken = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $tokenExpiresAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null; // Дополнительные данные (webhook_url, domain, etc.)

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastSyncAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): int
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

    public function getIntegrationType(): string
    {
        return $this->integrationType;
    }

    public function setIntegrationType(string $integrationType): static
    {
        $this->integrationType = $integrationType;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): static
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): static
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    public function getTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->tokenExpiresAt;
    }

    public function setTokenExpiresAt(?\DateTimeInterface $tokenExpiresAt): static
    {
        $this->tokenExpiresAt = $tokenExpiresAt;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

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

    public function getLastSyncAt(): ?\DateTimeInterface
    {
        return $this->lastSyncAt;
    }

    public function setLastSyncAt(?\DateTimeInterface $lastSyncAt): static
    {
        $this->lastSyncAt = $lastSyncAt;

        return $this;
    }

    /**
     * Проверка валидности токена
     */
    public function isTokenValid(): bool
    {
        if (!$this->accessToken) {
            return false;
        }

        if (!$this->tokenExpiresAt) {
            return true; // Токен без срока действия
        }

        return $this->tokenExpiresAt > new \DateTime();
    }

    /**
     * Получить название интеграции
     */
    public function getIntegrationName(): string
    {
        return match($this->integrationType) {
            'github' => 'GitHub',
            'slack' => 'Slack',
            'jira' => 'Jira',
            'trello' => 'Trello',
            'google_calendar' => 'Google Calendar',
            'telegram' => 'Telegram',
            'zapier' => 'Zapier',
            default => $this->integrationType,
        };
    }
}
