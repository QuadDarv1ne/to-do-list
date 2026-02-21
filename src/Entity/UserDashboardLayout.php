<?php

namespace App\Entity;

use App\Repository\UserDashboardLayoutRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserDashboardLayoutRepository::class)]
#[ORM\Table(name: 'user_dashboard_layouts')]
#[ORM\UniqueConstraint(name: 'user_layout_unique', columns: ['user_id'])]
#[ORM\Index(columns: ['user_id'], name: 'idx_user_dashboard_user')]
#[ORM\Index(columns: ['theme'], name: 'idx_user_dashboard_theme')]
class UserDashboardLayout
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /**
     * Конфигурация виджетов (JSON)
     * Пример: [{"id": "task_stats", "position": 1, "size": "col-md-6", "enabled": true}]
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $widgets = null;

    /**
     * Тема оформления: light, dark, auto
     */
    #[ORM\Column(length: 20, options: ['default' => 'light'])]
    private string $theme = 'light';

    /**
     * Компактный режим
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $compactMode = false;

    /**
     * Показывать ли пустые виджеты
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $showEmptyWidgets = true;

    /**
     * Количество колонок (1-4)
     */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 2])]
    private int $columns = 2;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->widgets = $this->getDefaultWidgets();
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

    public function getWidgets(): ?array
    {
        return $this->widgets;
    }

    public function setWidgets(?array $widgets): static
    {
        $this->widgets = $widgets;

        return $this;
    }

    /**
     * Получить виджет по ID
     */
    public function getWidgetById(string $widgetId): ?array
    {
        if (!$this->widgets) {
            return null;
        }

        foreach ($this->widgets as $widget) {
            if (($widget['id'] ?? '') === $widgetId) {
                return $widget;
            }
        }

        return null;
    }

    /**
     * Добавить виджет
     */
    public function addWidget(array $widget): static
    {
        if (!$this->widgets) {
            $this->widgets = [];
        }

        // Проверяем, существует ли уже виджет
        foreach ($this->widgets as $key => $existingWidget) {
            if (($existingWidget['id'] ?? '') === ($widget['id'] ?? '')) {
                $this->widgets[$key] = $widget;
                return $this;
            }
        }

        $this->widgets[] = $widget;

        return $this;
    }

    /**
     * Удалить виджет
     */
    public function removeWidget(string $widgetId): static
    {
        if (!$this->widgets) {
            return $this;
        }

        $this->widgets = array_filter(
            $this->widgets,
            fn($widget) => ($widget['id'] ?? '') !== $widgetId
        );

        return $this;
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    public function setTheme(string $theme): static
    {
        if (!in_array($theme, ['light', 'dark', 'auto'], true)) {
            throw new \InvalidArgumentException('Theme must be one of: light, dark, auto');
        }

        $this->theme = $theme;

        return $this;
    }

    public function isCompactMode(): bool
    {
        return $this->compactMode;
    }

    public function setIsCompactMode(bool $compactMode): static
    {
        $this->compactMode = $compactMode;

        return $this;
    }

    public function isShowEmptyWidgets(): bool
    {
        return $this->showEmptyWidgets;
    }

    public function setIsShowEmptyWidgets(bool $showEmptyWidgets): static
    {
        $this->showEmptyWidgets = $showEmptyWidgets;

        return $this;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    public function setColumns(int $columns): static
    {
        if ($columns < 1 || $columns > 4) {
            throw new \InvalidArgumentException('Columns must be between 1 and 4');
        }

        $this->columns = $columns;

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
     * Получить виджеты, отсортированные по позиции
     */
    public function getSortedWidgets(): array
    {
        if (!$this->widgets) {
            return [];
        }

        $widgets = $this->widgets;
        usort($widgets, fn($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

        return $widgets;
    }

    /**
     * Получить только включённые виджеты
     */
    public function getEnabledWidgets(): array
    {
        if (!$this->widgets) {
            return [];
        }

        return array_filter(
            $this->widgets,
            fn($widget) => $widget['enabled'] ?? true
        );
    }

    /**
     * Получить конфигурацию по умолчанию
     */
    private function getDefaultWidgets(): array
    {
        return [
            ['id' => 'task_stats', 'position' => 1, 'size' => 'col-md-6', 'enabled' => true],
            ['id' => 'recent_tasks', 'position' => 2, 'size' => 'col-md-6', 'enabled' => true],
            ['id' => 'upcoming_deadlines', 'position' => 3, 'size' => 'col-md-12', 'enabled' => true],
        ];
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
