<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskTemplate as TaskTemplateEntity;
use App\Entity\User;
use App\Repository\TaskTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;

class TaskTemplateLibraryService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskTemplateRepository $templateRepository,
    ) {
    }

    /**
     * Get all templates
     */
    public function getAllTemplates(): array
    {
        return [
            'bug_report' => [
                'name' => 'Отчет об ошибке',
                'icon' => 'fa-bug',
                'category' => 'Разработка',
                'template' => [
                    'title' => 'Исправить ошибку: ',
                    'description' => "**Описание ошибки:**\n\n**Шаги воспроизведения:**\n1. \n2. \n3. \n\n**Ожидаемое поведение:**\n\n**Фактическое поведение:**\n\n**Скриншоты:**",
                    'priority' => 'high',
                    'status' => 'pending',
                ],
            ],
            'feature_request' => [
                'name' => 'Запрос функции',
                'icon' => 'fa-lightbulb',
                'category' => 'Разработка',
                'template' => [
                    'title' => 'Новая функция: ',
                    'description' => "**Описание функции:**\n\n**Зачем это нужно:**\n\n**Предлагаемое решение:**\n\n**Альтернативы:**",
                    'priority' => 'medium',
                    'status' => 'pending',
                ],
            ],
            'meeting' => [
                'name' => 'Встреча',
                'icon' => 'fa-users',
                'category' => 'Общее',
                'template' => [
                    'title' => 'Встреча: ',
                    'description' => "**Дата и время:**\n\n**Участники:**\n\n**Повестка дня:**\n1. \n2. \n3. \n\n**Заметки:**",
                    'priority' => 'medium',
                    'status' => 'pending',
                ],
            ],
            'code_review' => [
                'name' => 'Ревью кода',
                'icon' => 'fa-code',
                'category' => 'Разработка',
                'template' => [
                    'title' => 'Ревью: ',
                    'description' => "**PR/MR:**\n\n**Что проверить:**\n- [ ] Код соответствует стандартам\n- [ ] Тесты написаны\n- [ ] Документация обновлена\n- [ ] Нет конфликтов\n\n**Комментарии:**",
                    'priority' => 'high',
                    'status' => 'pending',
                ],
            ],
            'deployment' => [
                'name' => 'Деплой',
                'icon' => 'fa-rocket',
                'category' => 'DevOps',
                'template' => [
                    'title' => 'Деплой: ',
                    'description' => "**Версия:**\n\n**Окружение:**\n\n**Чеклист:**\n- [ ] Тесты пройдены\n- [ ] Бэкап создан\n- [ ] Миграции готовы\n- [ ] Документация обновлена\n\n**Rollback план:**",
                    'priority' => 'urgent',
                    'status' => 'pending',
                ],
            ],
            'research' => [
                'name' => 'Исследование',
                'icon' => 'fa-search',
                'category' => 'Общее',
                'template' => [
                    'title' => 'Исследовать: ',
                    'description' => "**Цель исследования:**\n\n**Вопросы:**\n1. \n2. \n3. \n\n**Источники:**\n\n**Выводы:**",
                    'priority' => 'medium',
                    'status' => 'pending',
                ],
            ],
            'documentation' => [
                'name' => 'Документация',
                'icon' => 'fa-book',
                'category' => 'Общее',
                'template' => [
                    'title' => 'Документировать: ',
                    'description' => "**Что документировать:**\n\n**Целевая аудитория:**\n\n**Структура:**\n1. Введение\n2. Основная часть\n3. Примеры\n4. FAQ\n\n**Ссылки:**",
                    'priority' => 'low',
                    'status' => 'pending',
                ],
            ],
            'testing' => [
                'name' => 'Тестирование',
                'icon' => 'fa-vial',
                'category' => 'QA',
                'template' => [
                    'title' => 'Тестировать: ',
                    'description' => "**Что тестировать:**\n\n**Тест-кейсы:**\n1. \n2. \n3. \n\n**Окружение:**\n\n**Результаты:**",
                    'priority' => 'high',
                    'status' => 'pending',
                ],
            ],
            'refactoring' => [
                'name' => 'Рефакторинг',
                'icon' => 'fa-tools',
                'category' => 'Разработка',
                'template' => [
                    'title' => 'Рефакторинг: ',
                    'description' => "**Текущее состояние:**\n\n**Проблемы:**\n\n**Предлагаемые изменения:**\n\n**Преимущества:**\n\n**Риски:**",
                    'priority' => 'medium',
                    'status' => 'pending',
                ],
            ],
            'customer_support' => [
                'name' => 'Поддержка клиента',
                'icon' => 'fa-headset',
                'category' => 'Поддержка',
                'template' => [
                    'title' => 'Запрос поддержки: ',
                    'description' => "**Клиент:**\n\n**Проблема:**\n\n**Приоритет:**\n\n**Шаги решения:**\n1. \n2. \n3. \n\n**Статус:**",
                    'priority' => 'high',
                    'status' => 'pending',
                ],
            ],
        ];
    }

    /**
     * Get template by key
     */
    public function getTemplate(string $key): ?array
    {
        $templates = $this->getAllTemplates();

        return $templates[$key] ?? null;
    }

    /**
     * Create task from template
     */
    public function createFromTemplate(string $templateKey, User $user, array $overrides = []): Task
    {
        $template = $this->getTemplate($templateKey);
        if (!$template) {
            throw new \InvalidArgumentException("Template not found: $templateKey");
        }

        $task = new Task();
        $task->setTitle($overrides['title'] ?? $template['template']['title']);
        $task->setDescription($overrides['description'] ?? $template['template']['description']);
        $task->setPriority($overrides['priority'] ?? $template['template']['priority']);
        $task->setStatus($overrides['status'] ?? $template['template']['status']);
        $task->setUser($user);

        if (isset($overrides['deadline'])) {
            $task->setDeadline(new \DateTime($overrides['deadline']));
        }

        if (isset($overrides['assigned_user'])) {
            $task->setAssignedUser($overrides['assigned_user']);
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $task;
    }

    /**
     * Get templates by category
     */
    public function getTemplatesByCategory(string $category): array
    {
        $templates = $this->getAllTemplates();

        return array_filter($templates, fn ($t) => $t['category'] === $category);
    }

    /**
     * Get categories
     */
    public function getCategories(): array
    {
        $templates = $this->getAllTemplates();
        $categories = array_unique(array_column($templates, 'category'));

        return array_values($categories);
    }

    /**
     * Search templates
     */
    public function searchTemplates(string $query): array
    {
        $templates = $this->getAllTemplates();
        $query = strtolower($query);

        return array_filter($templates, function ($template) use ($query) {
            return str_contains(strtolower($template['name']), $query) ||
                   str_contains(strtolower($template['category']), $query);
        });
    }

    /**
     * Create custom template
     */
    public function createCustomTemplate(string $name, array $template, User $user, ?string $icon = null, ?string $category = null): TaskTemplateEntity
    {
        $templateEntity = new TaskTemplateEntity();
        $templateEntity->setName($name);
        $templateEntity->setIcon($icon ?? 'fa-file');
        $templateEntity->setCategory($category ?? 'Пользовательские');
        $templateEntity->setTemplateData($template);
        $templateEntity->setUser($user);

        $this->templateRepository->save($templateEntity);

        return $templateEntity;
    }

    /**
     * Get user custom templates
     */
    public function getUserCustomTemplates(User $user): array
    {
        $templates = $this->templateRepository->findByUser($user);
        $result = [];
        
        foreach ($templates as $template) {
            $result['custom_' . $template->getId()] = [
                'name' => $template->getName(),
                'icon' => $template->getIcon() ?? 'fa-file',
                'category' => $template->getCategory(),
                'template' => $template->getTemplateData(),
                'is_custom' => true,
                'id' => $template->getId(),
                'usage_count' => $template->getUsageCount(),
            ];
        }
        
        return $result;
    }

    /**
     * Get popular templates
     */
    public function getPopularTemplates(int $limit = 5): array
    {
        $popular = $this->templateRepository->findPopular($limit);
        $result = [];
        
        foreach ($popular as $template) {
            $result['popular_' . $template->getId()] = [
                'name' => $template->getName(),
                'icon' => $template->getIcon() ?? 'fa-star',
                'category' => $template->getCategory(),
                'template' => $template->getTemplateData(),
                'usage_count' => $template->getUsageCount(),
            ];
        }
        
        // Если мало популярных шаблонов, дополняем стандартными
        if (count($popular) < $limit) {
            $allTemplates = $this->getAllTemplates();
            $remaining = $limit - count($popular);
            $result = array_merge($result, array_slice($allTemplates, 0, $remaining));
        }
        
        return $result;
    }

    /**
     * Track template usage
     */
    public function trackUsage(int $templateId): void
    {
        $template = $this->templateRepository->find($templateId);
        if ($template) {
            $template->incrementUsageCount();
            $this->templateRepository->save($template);
        }
    }

    /**
     * Get all templates including user custom
     */
    public function getAllTemplatesWithUser(User $user): array
    {
        return array_merge($this->getAllTemplates(), $this->getUserCustomTemplates($user));
    }

    /**
     * Update template
     */
    public function updateTemplate(int $templateId, array $data, User $user): ?TaskTemplateEntity
    {
        $template = $this->templateRepository->findOneByUserAndId($user, $templateId);
        
        if (!$template) {
            return null;
        }

        if (isset($data['name'])) {
            $template->setName($data['name']);
        }
        if (isset($data['icon'])) {
            $template->setIcon($data['icon']);
        }
        if (isset($data['category'])) {
            $template->setCategory($data['category']);
        }
        if (isset($data['template'])) {
            $template->setTemplateData($data['template']);
        }

        $this->templateRepository->save($template);

        return $template;
    }

    /**
     * Delete template
     */
    public function deleteTemplate(int $templateId, User $user): bool
    {
        $template = $this->templateRepository->findOneByUserAndId($user, $templateId);
        
        if (!$template) {
            return false;
        }

        $this->templateRepository->remove($template);

        return true;
    }
}
