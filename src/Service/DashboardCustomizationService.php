<?php

namespace App\Service;

use App\Entity\User;

class DashboardCustomizationService
{
    /**
     * Get user dashboard layout
     */
    public function getUserLayout(User $user): array
    {
        // TODO: Get from database
        return [
            'widgets' => [
                ['id' => 'task_stats', 'position' => 1, 'size' => 'col-md-6'],
                ['id' => 'recent_tasks', 'position' => 2, 'size' => 'col-md-6'],
                ['id' => 'upcoming_deadlines', 'position' => 3, 'size' => 'col-md-12']
            ],
            'theme' => 'light',
            'compact_mode' => false
        ];
    }

    /**
     * Save user layout
     */
    public function saveLayout(User $user, array $layout): bool
    {
        // TODO: Save to database
        return true;
    }

    /**
     * Reset to default layout
     */
    public function resetToDefault(User $user): array
    {
        $defaultLayout = $this->getDefaultLayout();
        $this->saveLayout($user, $defaultLayout);
        return $defaultLayout;
    }

    /**
     * Get default layout
     */
    public function getDefaultLayout(): array
    {
        return [
            'widgets' => [
                ['id' => 'task_stats', 'position' => 1, 'size' => 'col-md-6'],
                ['id' => 'recent_tasks', 'position' => 2, 'size' => 'col-md-6'],
                ['id' => 'upcoming_deadlines', 'position' => 3, 'size' => 'col-md-6'],
                ['id' => 'productivity_chart', 'position' => 4, 'size' => 'col-md-6']
            ],
            'theme' => 'light',
            'compact_mode' => false
        ];
    }

    /**
     * Get available themes
     */
    public function getAvailableThemes(): array
    {
        return [
            'light' => [
                'name' => 'Светлая',
                'description' => 'Классическая светлая тема',
                'preview' => '/images/themes/light.png'
            ],
            'dark' => [
                'name' => 'Темная',
                'description' => 'Темная тема для работы ночью',
                'preview' => '/images/themes/dark.png'
            ],
            'blue' => [
                'name' => 'Синяя',
                'description' => 'Профессиональная синяя тема',
                'preview' => '/images/themes/blue.png'
            ],
            'green' => [
                'name' => 'Зеленая',
                'description' => 'Успокаивающая зеленая тема',
                'preview' => '/images/themes/green.png'
            ]
        ];
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
