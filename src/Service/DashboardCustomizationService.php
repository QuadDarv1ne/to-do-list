<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserDashboardLayout;
use App\Repository\UserDashboardLayoutRepository;
use Doctrine\ORM\EntityManagerInterface;

class DashboardCustomizationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserDashboardLayoutRepository $layoutRepository,
    ) {
    }

    /**
     * Get user dashboard layout
     */
    public function getUserLayout(User $user): array
    {
        $layout = $this->layoutRepository->findByUser($user->getId());

        if (!$layout) {
            return $this->getDefaultLayout();
        }

        return [
            'widgets' => $layout->getSortedWidgets(),
            'theme' => $layout->getTheme(),
            'compact_mode' => $layout->isCompactMode(),
            'show_empty_widgets' => $layout->isShowEmptyWidgets(),
            'columns' => $layout->getColumns(),
        ];
    }

    public function saveLayout(User $user, array $layout): bool
    {
        $userLayout = $this->layoutRepository->findByUser($user->getId());

        if (!$userLayout) {
            $userLayout = new UserDashboardLayout();
            $userLayout->setUser($user);
        }

        if (isset($layout['widgets'])) {
            $userLayout->setWidgets($layout['widgets']);
        }
        if (isset($layout['theme'])) {
            $userLayout->setTheme($layout['theme']);
        }
        if (isset($layout['compact_mode'])) {
            $userLayout->setIsCompactMode($layout['compact_mode']);
        }
        if (isset($layout['show_empty_widgets'])) {
            $userLayout->setIsShowEmptyWidgets($layout['show_empty_widgets']);
        }
        if (isset($layout['columns'])) {
            $userLayout->setColumns($layout['columns']);
        }

        $this->layoutRepository->save($userLayout);

        return true;
    }

    /**
     * Reset to default layout
     */
    public function resetToDefault(User $user): array
    {
        $layout = $this->layoutRepository->findByUser($user->getId());

        if ($layout) {
            $layout->setWidgets(null);
            $layout->setTheme('light');
            $layout->setIsCompactMode(false);
            $layout->setIsShowEmptyWidgets(true);
            $layout->setColumns(2);

            $this->layoutRepository->save($layout);
        }

        return $this->getDefaultLayout();
    }

    /**
     * Get default layout
     */
    public function getDefaultLayout(): array
    {
        return [
            'widgets' => [
                ['id' => 'task_stats', 'position' => 1, 'size' => 'col-md-6', 'enabled' => true],
                ['id' => 'recent_tasks', 'position' => 2, 'size' => 'col-md-6', 'enabled' => true],
                ['id' => 'upcoming_deadlines', 'position' => 3, 'size' => 'col-md-12', 'enabled' => true],
                ['id' => 'productivity_chart', 'position' => 4, 'size' => 'col-md-6', 'enabled' => true],
            ],
            'theme' => 'light',
            'compact_mode' => false,
            'show_empty_widgets' => true,
            'columns' => 2,
        ];
    }

    /**
     * Enable widget for user
     */
    public function enableWidget(User $user, string $widgetId, ?int $position = null): bool
    {
        $layout = $this->layoutRepository->findByUser($user->getId());

        if (!$layout) {
            $layout = new UserDashboardLayout();
            $layout->setUser($user);
        }

        $widget = $layout->getWidgetById($widgetId);

        if ($widget) {
            $widget['enabled'] = true;
            if ($position !== null) {
                $widget['position'] = $position;
            }
            $layout->addWidget($widget);
        } else {
            $layout->addWidget([
                'id' => $widgetId,
                'position' => $position ?? 999,
                'size' => 'col-md-6',
                'enabled' => true,
            ]);
        }

        $this->layoutRepository->save($layout);

        return true;
    }

    /**
     * Disable widget for user
     */
    public function disableWidget(User $user, string $widgetId): bool
    {
        $layout = $this->layoutRepository->findByUser($user->getId());

        if (!$layout) {
            return false;
        }

        $layout->removeWidget($widgetId);
        $this->layoutRepository->save($layout);

        return true;
    }

    /**
     * Update widget position
     */
    public function updateWidgetPosition(User $user, string $widgetId, int $position): bool
    {
        $layout = $this->layoutRepository->findByUser($user->getId());

        if (!$layout) {
            return false;
        }

        $widget = $layout->getWidgetById($widgetId);

        if ($widget) {
            $widget['position'] = $position;
            $layout->addWidget($widget);
            $this->layoutRepository->save($layout);
            return true;
        }

        return false;
    }

    /**
     * Get available themes
     */
    public function getAvailableThemes(): array
    {
        return [
            ['id' => 'light', 'name' => 'Светлая', 'icon' => 'fa-sun'],
            ['id' => 'dark', 'name' => 'Тёмная', 'icon' => 'fa-moon'],
            ['id' => 'auto', 'name' => 'Авто', 'icon' => 'fa-adjust'],
        ];
    }

    /**
     * Update theme
     */
    public function updateTheme(User $user, string $theme): bool
    {
        $layout = $this->layoutRepository->findByUser($user->getId());

        if (!$layout) {
            $layout = new UserDashboardLayout();
            $layout->setUser($user);
        }

        $layout->setTheme($theme);
        $this->layoutRepository->save($layout);

        return true;
    }

    /**
     * Toggle compact mode
     */
    public function toggleCompactMode(User $user): bool
    {
        $layout = $this->layoutRepository->findByUser($user->getId());

        if (!$layout) {
            $layout = new UserDashboardLayout();
            $layout->setUser($user);
        }

        $layout->setIsCompactMode(!$layout->isCompactMode());
        $this->layoutRepository->save($layout);

        return true;
    }

    /**
     * Export layout
     */
    public function exportLayout(User $user): string
    {
        $layout = $this->getUserLayout($user);

        return json_encode($layout, JSON_PRETTY_PRINT);
    }

    /**
     * Import layout
     */
    public function importLayout(User $user, string $json): bool
    {
        try {
            $layout = json_decode($json, true);

            return $this->saveLayout($user, $layout);
        } catch (\Exception $e) {
            return false;
        }
    }
}
