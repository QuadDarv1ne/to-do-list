<?php

namespace App\Entity;

use App\Repository\UserPreferenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserPreferenceRepository::class)]
#[ORM\Table(name: 'user_preferences')]
#[ORM\UniqueConstraint(name: 'user_preference_unique', columns: ['user_id', 'preference_key'])]
#[ORM\Index(columns: ['user_id'], name: 'idx_user_preferences_user')]
#[ORM\Index(columns: ['preference_key'], name: 'idx_user_preferences_key')]
class UserPreference
{
    public const KEY_WIDGET_SETTINGS = 'widget_settings';
    public const KEY_NOTIFICATION_SETTINGS = 'notification_settings';
    public const KEY_DASHBOARD_SETTINGS = 'dashboard_settings';
    public const KEY_TASK_SETTINGS = 'task_settings';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 100)]
    private string $preferenceKey = '';

    /**
     * Значение настройки (JSON)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $preferenceValue = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getPreferenceKey(): string
    {
        return $this->preferenceKey;
    }

    public function setPreferenceKey(string $preferenceKey): static
    {
        $this->preferenceKey = $preferenceKey;

        return $this;
    }

    public function getPreferenceValue(): ?array
    {
        return $this->preferenceValue;
    }

    public function setPreferenceValue(?array $preferenceValue): static
    {
        $this->preferenceValue = $preferenceValue;

        return $this;
    }

    /**
     * Получить конкретное значение из настройки
     */
    public function getValue(string $key, mixed $default = null): mixed
    {
        if (!$this->preferenceValue) {
            return $default;
        }

        return $this->preferenceValue[$key] ?? $default;
    }

    /**
     * Установить конкретное значение в настройку
     */
    public function setValue(string $key, mixed $value): static
    {
        if (!$this->preferenceValue) {
            $this->preferenceValue = [];
        }

        $this->preferenceValue[$key] = $value;

        return $this;
    }

    /**
     * Получить настройки виджетов
     */
    public function getWidgetSettings(): array
    {
        if ($this->preferenceKey !== self::KEY_WIDGET_SETTINGS) {
            return [];
        }

        return $this->preferenceValue ?? $this->getDefaultWidgetSettings();
    }

    /**
     * Получить настройку виджета по ID
     */
    public function getWidgetSetting(string $widgetId): ?array
    {
        $settings = $this->getWidgetSettings();
        return $settings[$widgetId] ?? null;
    }

    /**
     * Установить настройку виджета
     */
    public function setWidgetSetting(string $widgetId, array $setting): static
    {
        if ($this->preferenceKey !== self::KEY_WIDGET_SETTINGS) {
            return $this;
        }

        if (!$this->preferenceValue) {
            $this->preferenceValue = [];
        }

        $this->preferenceValue[$widgetId] = $setting;

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

    /**
     * Получить настройки виджетов по умолчанию
     */
    private function getDefaultWidgetSettings(): array
    {
        return [
            'task_stats' => [
                'enabled' => true,
                'position' => 1,
                'collapsed' => false,
            ],
            'recent_tasks' => [
                'enabled' => true,
                'position' => 2,
                'collapsed' => false,
                'limit' => 5,
            ],
            'upcoming_deadlines' => [
                'enabled' => true,
                'position' => 3,
                'collapsed' => false,
                'days_ahead' => 7,
            ],
            'productivity_chart' => [
                'enabled' => true,
                'position' => 4,
                'collapsed' => false,
                'chart_type' => 'line',
            ],
        ];
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
