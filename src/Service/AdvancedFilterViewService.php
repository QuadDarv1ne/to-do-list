<?php

namespace App\Service;

use App\Entity\FilterView;
use App\Entity\User;
use App\Repository\FilterViewRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

class AdvancedFilterViewService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private FilterViewRepository $filterViewRepository,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Create custom view
     */
    public function createCustomView(string $name, array $filters, array $columns, User $user, ?array $sort = null, ?string $groupBy = null, ?string $icon = null): FilterView
    {
        $filterView = new FilterView();
        $filterView->setName($name);
        $filterView->setFilters($filters);
        $filterView->setColumns($columns);
        $filterView->setSort($sort);
        $filterView->setGroupBy($groupBy);
        $filterView->setIcon($icon ?? 'fa-filter');
        $filterView->setUser($user);

        $this->filterViewRepository->save($filterView);

        return $filterView;
    }

    /**
     * Get user views
     */
    public function getUserViews(User $user): array
    {
        $views = [];
        
        // Добавляем стандартные views
        $views = $this->getDefaultViews();
        
        // Добавляем пользовательские views из БД
        $userViews = $this->filterViewRepository->findByUser($user);
        foreach ($userViews as $view) {
            $views['custom_' . $view->getId()] = [
                'name' => $view->getName(),
                'icon' => $view->getIcon() ?? 'fa-filter',
                'filters' => $view->getFilters(),
                'columns' => $view->getColumns(),
                'sort' => $view->getSort() ?? ['createdAt' => 'DESC'],
                'group_by' => $view->getGroupBy(),
                'is_custom' => true,
                'id' => $view->getId(),
            ];
        }
        
        return $views;
    }

    /**
     * Get default views
     */
    public function getDefaultViews(): array
    {
        return [
            'all_tasks' => [
                'name' => 'Все задачи',
                'icon' => 'fa-list',
                'filters' => [],
                'columns' => ['title', 'status', 'priority', 'deadline', 'assigned_user'],
                'sort' => ['createdAt' => 'DESC'],
            ],
            'my_active' => [
                'name' => 'Мои активные',
                'icon' => 'fa-tasks',
                'filters' => [
                    'assigned_to_me' => true,
                    'status' => ['pending', 'in_progress'],
                ],
                'columns' => ['title', 'priority', 'deadline', 'category'],
                'sort' => ['priority' => 'DESC', 'deadline' => 'ASC'],
            ],
            'urgent_today' => [
                'name' => 'Срочные сегодня',
                'icon' => 'fa-fire',
                'filters' => [
                    'priority' => ['urgent', 'high'],
                    'deadline' => 'today',
                ],
                'columns' => ['title', 'status', 'assigned_user', 'deadline'],
                'sort' => ['priority' => 'DESC'],
            ],
            'overdue' => [
                'name' => 'Просроченные',
                'icon' => 'fa-exclamation-triangle',
                'filters' => [
                    'is_overdue' => true,
                ],
                'columns' => ['title', 'priority', 'deadline', 'assigned_user', 'days_overdue'],
                'sort' => ['deadline' => 'ASC'],
            ],
            'completed_this_week' => [
                'name' => 'Завершено за неделю',
                'icon' => 'fa-check-circle',
                'filters' => [
                    'status' => 'completed',
                    'completed_at' => 'this_week',
                ],
                'columns' => ['title', 'completed_at', 'assigned_user', 'category'],
                'sort' => ['completedAt' => 'DESC'],
            ],
            'by_category' => [
                'name' => 'По категориям',
                'icon' => 'fa-folder',
                'filters' => [],
                'columns' => ['category', 'title', 'status', 'priority'],
                'sort' => ['category' => 'ASC', 'priority' => 'DESC'],
                'group_by' => 'category',
            ],
            'by_priority' => [
                'name' => 'По приоритету',
                'icon' => 'fa-sort-amount-down',
                'filters' => [],
                'columns' => ['priority', 'title', 'status', 'deadline'],
                'sort' => ['priority' => 'DESC', 'deadline' => 'ASC'],
                'group_by' => 'priority',
            ],
            'unassigned' => [
                'name' => 'Не назначенные',
                'icon' => 'fa-user-slash',
                'filters' => [
                    'is_unassigned' => true,
                ],
                'columns' => ['title', 'priority', 'status', 'created_at'],
                'sort' => ['priority' => 'DESC'],
            ],
        ];
    }

    /**
     * Apply view
     */
    public function applyView(string $viewKey, User $user): array
    {
        $views = $this->getUserViews($user);

        if (!isset($views[$viewKey])) {
            return [];
        }

        $view = $views[$viewKey];

        return $this->executeView($view, $user);
    }

    /**
     * Execute view
     */
    private function executeView(array $view, User $user): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t');

        // Apply filters
        if (isset($view['filters'])) {
            $this->applyFilters($qb, $view['filters'], $user);
        }

        // Apply sorting
        if (isset($view['sort'])) {
            foreach ($view['sort'] as $field => $direction) {
                $qb->addOrderBy("t.$field", $direction);
            }
        }

        $tasks = $qb->getQuery()->getResult();

        // Apply grouping if needed
        if (isset($view['group_by'])) {
            $tasks = $this->groupTasks($tasks, $view['group_by']);
        }

        return $tasks;
    }

    /**
     * Apply filters
     */
    private function applyFilters($qb, array $filters, User $user): void
    {
        foreach ($filters as $key => $value) {
            match($key) {
                'assigned_to_me' => $qb->andWhere('t.assignedUser = :user')
                    ->setParameter('user', $user),
                'created_by_me' => $qb->andWhere('t.user = :user')
                    ->setParameter('user', $user),
                'status' => \is_array($value)
                    ? $qb->andWhere('t.status IN (:statuses)')
                        ->setParameter('statuses', $value)
                    : $qb->andWhere('t.status = :status')
                        ->setParameter('status', $value),
                'priority' => \is_array($value)
                    ? $qb->andWhere('t.priority IN (:priorities)')
                        ->setParameter('priorities', $value)
                    : $qb->andWhere('t.priority = :priority')
                        ->setParameter('priority', $value),
                'is_overdue' => $qb->andWhere('t.dueDate < :now')
                    ->andWhere('t.status != :completed')
                    ->setParameter('now', new \DateTime())
                    ->setParameter('completed', 'completed'),
                'is_unassigned' => $qb->andWhere('t.assignedUser IS NULL'),
                'deadline' => $this->applyDeadlineFilter($qb, $value),
                'completed_at' => $this->applyCompletedAtFilter($qb, $value),
                default => null
            };
        }
    }

    /**
     * Apply deadline filter
     */
    private function applyDeadlineFilter($qb, string $value): void
    {
        match($value) {
            'today' => $qb->andWhere('DATE(t.dueDate) = :today')
                ->setParameter('today', (new \DateTime())->format('Y-m-d')),
            'this_week' => $qb->andWhere('t.dueDate BETWEEN :week_start AND :week_end')
                ->setParameter('week_start', new \DateTime('monday this week'))
                ->setParameter('week_end', new \DateTime('sunday this week')),
            'this_month' => $qb->andWhere('t.dueDate BETWEEN :month_start AND :month_end')
                ->setParameter('month_start', new \DateTime('first day of this month'))
                ->setParameter('month_end', new \DateTime('last day of this month')),
            default => null
        };
    }

    /**
     * Apply completed at filter
     */
    private function applyCompletedAtFilter($qb, string $value): void
    {
        match($value) {
            'today' => $qb->andWhere('DATE(t.completedAt) = :today')
                ->setParameter('today', (new \DateTime())->format('Y-m-d')),
            'this_week' => $qb->andWhere('t.completedAt BETWEEN :week_start AND :week_end')
                ->setParameter('week_start', new \DateTime('monday this week'))
                ->setParameter('week_end', new \DateTime('sunday this week')),
            'this_month' => $qb->andWhere('t.completedAt BETWEEN :month_start AND :month_end')
                ->setParameter('month_start', new \DateTime('first day of this month'))
                ->setParameter('month_end', new \DateTime('last day of this month')),
            default => null
        };
    }

    /**
     * Group tasks
     */
    private function groupTasks(array $tasks, string $groupBy): array
    {
        $grouped = [];

        foreach ($tasks as $task) {
            $key = match($groupBy) {
                'category' => $task->getCategory()?->getName() ?? 'Без категории',
                'priority' => $task->getPriority(),
                'status' => $task->getStatus(),
                'assigned_user' => $task->getAssignedUser()?->getUsername() ?? 'Не назначено',
                default => 'Другое'
            };

            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $task;
        }

        return $grouped;
    }

    /**
     * Update view
     */
    public function updateView(int $viewId, array $data, User $user): ?FilterView
    {
        $view = $this->filterViewRepository->findOneByUserAndId($user, $viewId);
        
        if (!$view) {
            return null;
        }

        if (isset($data['name'])) {
            $view->setName($data['name']);
        }
        if (isset($data['filters'])) {
            $view->setFilters($data['filters']);
        }
        if (isset($data['columns'])) {
            $view->setColumns($data['columns']);
        }
        if (isset($data['sort'])) {
            $view->setSort($data['sort']);
        }
        if (isset($data['group_by'])) {
            $view->setGroupBy($data['group_by']);
        }
        if (isset($data['icon'])) {
            $view->setIcon($data['icon']);
        }

        $this->filterViewRepository->save($view);

        return $view;
    }

    /**
     * Delete view
     */
    public function deleteView(int $viewId, User $user): bool
    {
        $view = $this->filterViewRepository->findOneByUserAndId($user, $viewId);
        
        if (!$view) {
            return false;
        }

        $this->filterViewRepository->remove($view);

        return true;
    }

    /**
     * Set default view
     */
    public function setDefaultView(int $viewId, User $user): bool
    {
        // Сбрасываем все default view у пользователя
        $currentDefault = $this->filterViewRepository->findDefaultView($user);
        if ($currentDefault) {
            $currentDefault->setIsDefault(false);
            $this->filterViewRepository->save($currentDefault);
        }

        // Устанавливаем новый default view
        $view = $this->filterViewRepository->findOneByUserAndId($user, $viewId);
        if (!$view) {
            return false;
        }

        $view->setIsDefault(true);
        $this->filterViewRepository->save($view);

        return true;
    }

    /**
     * Get default view
     */
    public function getDefaultView(User $user): ?array
    {
        $defaultView = $this->filterViewRepository->findDefaultView($user);
        
        if ($defaultView) {
            return [
                'name' => $defaultView->getName(),
                'icon' => $defaultView->getIcon() ?? 'fa-filter',
                'filters' => $defaultView->getFilters(),
                'columns' => $defaultView->getColumns(),
                'sort' => $defaultView->getSort() ?? ['createdAt' => 'DESC'],
                'group_by' => $defaultView->getGroupBy(),
                'is_custom' => true,
                'id' => $defaultView->getId(),
            ];
        }
        
        return $this->getDefaultViews()['all_tasks'];
    }

    /**
     * Share view
     */
    public function shareView(int $viewId, array $userIds, User $owner): bool
    {
        $view = $this->filterViewRepository->findOneByUserAndId($owner, $viewId);
        
        if (!$view) {
            return false;
        }

        $userRepository = $this->em->getRepository(User::class);
        
        foreach ($userIds as $userId) {
            $user = $userRepository->find($userId);
            if ($user) {
                $view->addSharedUser($user);
            }
        }

        $this->filterViewRepository->save($view);

        return true;
    }

    /**
     * Get shared views
     */
    public function getSharedViews(User $user): array
    {
        $views = $this->filterViewRepository->findSharedWithUser($user);
        $result = [];
        
        foreach ($views as $view) {
            $result['shared_' . $view->getId()] = [
                'name' => $view->getName(),
                'icon' => $view->getIcon() ?? 'fa-share-alt',
                'filters' => $view->getFilters(),
                'columns' => $view->getColumns(),
                'sort' => $view->getSort() ?? ['createdAt' => 'DESC'],
                'group_by' => $view->getGroupBy(),
                'is_shared' => true,
                'owner' => $view->getUser()->getUsername(),
                'id' => $view->getId(),
            ];
        }
        
        return $result;
    }

    /**
     * Duplicate view
     */
    public function duplicateView(int $viewId, User $user): ?FilterView
    {
        $view = $this->filterViewRepository->findOneByUserAndId($user, $viewId);
        
        if (!$view) {
            return null;
        }

        $newView = new FilterView();
        $newView->setName($view->getName() . ' (копия)');
        $newView->setFilters($view->getFilters());
        $newView->setColumns($view->getColumns());
        $newView->setSort($view->getSort());
        $newView->setGroupBy($view->getGroupBy());
        $newView->setIcon($view->getIcon());
        $newView->setUser($user);

        $this->filterViewRepository->save($newView);

        return $newView;
    }

    /**
     * Export view
     */
    public function exportView(string $viewKey, User $user, string $format = 'csv'): string
    {
        $tasks = $this->applyView($viewKey, $user);

        return match($format) {
            'csv' => $this->exportToCSV($tasks),
            'json' => $this->exportToJSON($tasks),
            'excel' => $this->exportToExcel($tasks),
            default => ''
        };
    }

    /**
     * Export to CSV
     */
    private function exportToCSV(array $tasks): string
    {
        $csv = "ID,Название,Статус,Приоритет,Дедлайн\n";

        foreach ($tasks as $task) {
            if (\is_array($task)) {
                // Grouped tasks
                continue;
            }

            $csv .= \sprintf(
                "%d,%s,%s,%s,%s\n",
                $task->getId(),
                $task->getTitle(),
                $task->getStatus(),
                $task->getPriority(),
                $task->getDeadline()?->format('Y-m-d') ?? '',
            );
        }

        return $csv;
    }

    /**
     * Export to JSON
     */
    private function exportToJSON(array $tasks): string
    {
        $data = [];
        
        foreach ($tasks as $task) {
            if (\is_array($task)) {
                // Grouped tasks
                continue;
            }
            
            $data[] = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'status' => $task->getStatus(),
                'priority' => $task->getPriority(),
                'due_date' => $task->getDueDate()?->format('Y-m-d H:i:s'),
                'completed_at' => $task->getCompletedAt()?->format('Y-m-d H:i:s'),
                'category' => $task->getCategory()?->getName(),
                'assigned_user' => $task->getAssignedUser()?->getUsername(),
            ];
        }
        
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Export to Excel
     */
    private function exportToExcel(array $tasks): string
    {
        // Генерируем CSV с BOM для Excel
        $bom = "\xEF\xBB\xBF";
        $csv = $bom . "ID;Название;Статус;Приоритет;Дедлайн;Категория;Исполнитель;Прогресс\n";

        foreach ($tasks as $task) {
            if (\is_array($task)) {
                // Grouped tasks
                continue;
            }

            $csv .= \sprintf(
                "%d;\"%s\";%s;%s;%s;%s;%s;%d\n",
                $task->getId(),
                str_replace('"', '""', $task->getTitle()),
                $task->getStatus(),
                $task->getPriority(),
                $task->getDueDate()?->format('d.m.Y H:i') ?? '',
                $task->getCategory()?->getName() ?? '',
                $task->getAssignedUser()?->getUsername() ?? '',
                $task->getProgress(),
            );
        }

        return $csv;
    }
}
