<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskTemplate as TaskTemplateEntity;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Repository\TaskTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;

class TemplateService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager,
        private TaskTemplateRepository $templateRepository,
    ) {
    }

    /**
     * Create task from template
     */
    public function createFromTemplate(Task $template, User $user): Task
    {
        $task = new Task();
        $task->setTitle($template->getTitle());
        $task->setDescription($template->getDescription());
        $task->setPriority($template->getPriority());
        $task->setStatus('pending');
        $task->setUser($user);
        $task->setCategory($template->getCategory());

        // Copy tags
        foreach ($template->getTags() as $tag) {
            $task->addTag($tag);
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $task;
    }

    /**
     * Save task as template
     */
    public function saveAsTemplate(Task $task, string $templateName, User $user): TaskTemplateEntity
    {
        $template = new TaskTemplateEntity();
        $template->setName($templateName);
        $template->setCategory($task->getCategory()?->getName());
        $template->setIcon('fa-file');
        $template->setUser($user);
        $template->setTemplateData([
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'priority' => $task->getPriority(),
            'category_id' => $task->getCategory()?->getId(),
            'tags' => array_map(fn ($tag) => $tag->getName(), $task->getTags()->toArray()),
        ]);

        $this->templateRepository->save($template);

        return $template;
    }

    /**
     * Get user templates
     */
    public function getUserTemplates(User $user): array
    {
        $templates = $this->templateRepository->findByUser($user);
        $result = [];
        
        foreach ($templates as $template) {
            $result[] = [
                'id' => $template->getId(),
                'name' => $template->getName(),
                'icon' => $template->getIcon() ?? 'fa-file',
                'category' => $template->getCategory(),
                'template' => $template->getTemplateData(),
                'usage_count' => $template->getUsageCount(),
                'created_at' => $template->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }
        
        return $result;
    }

    /**
     * Get predefined templates
     */
    public function getPredefinedTemplates(): array
    {
        return [
            'bug_report' => [
                'name' => 'Отчет об ошибке',
                'title' => 'Исправить ошибку: ',
                'description' => "**Описание ошибки:**\n\n**Шаги для воспроизведения:**\n1. \n2. \n3. \n\n**Ожидаемое поведение:**\n\n**Фактическое поведение:**\n\n**Скриншоты:**\n",
                'priority' => 'high',
                'tags' => ['bug', 'исправление'],
            ],
            'feature_request' => [
                'name' => 'Запрос функции',
                'title' => 'Добавить функцию: ',
                'description' => "**Описание функции:**\n\n**Зачем это нужно:**\n\n**Предлагаемое решение:**\n\n**Альтернативы:**\n",
                'priority' => 'medium',
                'tags' => ['feature', 'улучшение'],
            ],
            'meeting' => [
                'name' => 'Встреча',
                'title' => 'Встреча: ',
                'description' => "**Тема:**\n\n**Участники:**\n\n**Повестка дня:**\n1. \n2. \n3. \n\n**Заметки:**\n",
                'priority' => 'medium',
                'tags' => ['встреча'],
            ],
            'code_review' => [
                'name' => 'Ревью кода',
                'title' => 'Ревью: ',
                'description' => "**Ссылка на PR/MR:**\n\n**Что проверить:**\n- [ ] Код соответствует стандартам\n- [ ] Тесты написаны\n- [ ] Документация обновлена\n- [ ] Нет конфликтов\n\n**Комментарии:**\n",
                'priority' => 'high',
                'tags' => ['code-review', 'разработка'],
            ],
            'deployment' => [
                'name' => 'Развертывание',
                'title' => 'Развернуть: ',
                'description' => "**Версия:**\n\n**Окружение:**\n\n**Чеклист:**\n- [ ] Резервная копия создана\n- [ ] Миграции применены\n- [ ] Конфигурация обновлена\n- [ ] Тесты пройдены\n- [ ] Мониторинг настроен\n\n**Откат:**\n",
                'priority' => 'urgent',
                'tags' => ['deployment', 'devops'],
            ],
            'research' => [
                'name' => 'Исследование',
                'title' => 'Исследовать: ',
                'description' => "**Цель исследования:**\n\n**Вопросы для изучения:**\n1. \n2. \n3. \n\n**Источники:**\n\n**Выводы:**\n",
                'priority' => 'low',
                'tags' => ['исследование'],
            ],
            'documentation' => [
                'name' => 'Документация',
                'title' => 'Документировать: ',
                'description' => "**Что документировать:**\n\n**Целевая аудитория:**\n\n**Структура:**\n1. Введение\n2. Основная часть\n3. Примеры\n4. FAQ\n\n**Ссылки:**\n",
                'priority' => 'medium',
                'tags' => ['документация'],
            ],
            'testing' => [
                'name' => 'Тестирование',
                'title' => 'Протестировать: ',
                'description' => "**Что тестировать:**\n\n**Тест-кейсы:**\n1. \n2. \n3. \n\n**Окружение:**\n\n**Результаты:**\n",
                'priority' => 'high',
                'tags' => ['тестирование', 'qa'],
            ],
        ];
    }

    /**
     * Apply template to existing task
     */
    public function applyTemplate(Task $task, array $template): Task
    {
        if (isset($template['title'])) {
            $task->setTitle($template['title']);
        }

        if (isset($template['description'])) {
            $task->setDescription($template['description']);
        }

        if (isset($template['priority'])) {
            $task->setPriority($template['priority']);
        }

        $this->entityManager->flush();

        return $task;
    }

    /**
     * Get template by key
     */
    public function getTemplate(string $key): ?array
    {
        $templates = $this->getPredefinedTemplates();

        return $templates[$key] ?? null;
    }

    /**
     * Get template statistics
     */
    public function getTemplateStats(): array
    {
        $predefined = \count($this->getPredefinedTemplates());
        
        // Получаем количество пользовательских шаблонов
        $qb = $this->entityManager->createQueryBuilder();
        $userCreated = (int) $qb->select('COUNT(t.id)')
            ->from(TaskTemplateEntity::class, 't')
            ->getQuery()
            ->getSingleScalarResult();

        // Получаем самые используемые шаблоны
        $mostUsed = $this->templateRepository->findPopular(5);
        $mostUsedList = [];
        foreach ($mostUsed as $template) {
            $mostUsedList[] = [
                'id' => $template->getId(),
                'name' => $template->getName(),
                'usage_count' => $template->getUsageCount(),
            ];
        }

        return [
            'total_templates' => $predefined + $userCreated,
            'predefined' => $predefined,
            'user_created' => $userCreated,
            'most_used' => $mostUsedList,
        ];
    }
}
