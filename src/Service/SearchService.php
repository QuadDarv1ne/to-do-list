<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\TagRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;

class SearchService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private CommentRepository $commentRepository,
        private UserRepository $userRepository,
        private TagRepository $tagRepository,
    ) {
    }

    /**
     * Universal quick search
     */
    public function quickSearch(string $query, User $currentUser, int $limit = 10): array
    {
        return [
            'tasks' => $this->searchTasks($query, $currentUser, ['limit' => $limit]),
            'users' => $this->searchUsers($query, ['limit' => $limit]),
            'tags' => $this->searchTags($query, $limit),
            'commands' => $this->searchCommands($query),
        ];
    }

    /**
     * Smart search across all entities
     */
    public function search(string $query, User $user, array $options = []): array
    {
        $results = [
            'tasks' => [],
            'comments' => [],
            'users' => [],
            'total' => 0,
        ];

        if (!isset($options['entities']) || \in_array('tasks', $options['entities'])) {
            $results['tasks'] = $this->searchTasks($query, $user, $options);
        }

        if (!isset($options['entities']) || \in_array('comments', $options['entities'])) {
            $results['comments'] = $this->searchComments($query, $user, $options);
        }

        if (!isset($options['entities']) || \in_array('users', $options['entities'])) {
            $results['users'] = $this->searchUsers($query, $options);
        }

        $results['total'] = \count($results['tasks']) + \count($results['comments']) + \count($results['users']);

        return $results;
    }

    /**
     * Advanced search with multiple criteria
     */
    public function advancedSearch(array $criteria, User $user): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.tags', 'tg');

        if (!\in_array('ROLE_ADMIN', $user->getRoles())) {
            $qb->andWhere('t.user = :user OR t.assignedUser = :user')
               ->setParameter('user', $user);
        }

        if (!empty($criteria['query'])) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('t.title', ':query'),
                    $qb->expr()->like('t.description', ':query'),
                    $qb->expr()->like('c.name', ':query'),
                    $qb->expr()->like('tg.name', ':query'),
                ),
            )->setParameter('query', '%' . $criteria['query'] . '%');
        }

        if (!empty($criteria['status'])) {
            if (\is_array($criteria['status'])) {
                $qb->andWhere('t.status IN (:statuses)')
                   ->setParameter('statuses', $criteria['status']);
            } else {
                $qb->andWhere('t.status = :status')
                   ->setParameter('status', $criteria['status']);
            }
        }

        if (!empty($criteria['priority'])) {
            if (\is_array($criteria['priority'])) {
                $qb->andWhere('t.priority IN (:priorities)')
                   ->setParameter('priorities', $criteria['priority']);
            } else {
                $qb->andWhere('t.priority = :priority')
                   ->setParameter('priority', $criteria['priority']);
            }
        }

        if (!empty($criteria['category'])) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $criteria['category']);
        }

        if (!empty($criteria['tags'])) {
            $qb->andWhere('tg.id IN (:tags)')
               ->setParameter('tags', $criteria['tags']);
        }

        if (!empty($criteria['assigned_user'])) {
            $qb->andWhere('t.assignedUser = :assignedUser')
               ->setParameter('assignedUser', $criteria['assigned_user']);
        }

        if (!empty($criteria['created_from']) || !empty($criteria['date_from'])) {
            $dateFrom = $criteria['created_from'] ?? $criteria['date_from'];
            $qb->andWhere('t.createdAt >= :createdFrom')
               ->setParameter('createdFrom', new \DateTime($dateFrom));
        }

        if (!empty($criteria['created_to']) || !empty($criteria['date_to'])) {
            $dateTo = $criteria['created_to'] ?? $criteria['date_to'];
            $qb->andWhere('t.createdAt <= :createdTo')
               ->setParameter('createdTo', new \DateTime($dateTo));
        }

        if (!empty($criteria['deadline_from'])) {
            $qb->andWhere('t.deadline >= :deadlineFrom')
               ->setParameter('deadlineFrom', new \DateTime($criteria['deadline_from']));
        }

        if (!empty($criteria['deadline_to'])) {
            $qb->andWhere('t.deadline <= :deadlineTo')
               ->setParameter('deadlineTo', new \DateTime($criteria['deadline_to']));
        }

        if (!empty($criteria['overdue']) || !empty($criteria['is_overdue'])) {
            $qb->andWhere('t.deadline < :now')
               ->andWhere('t.status != :completed')
               ->setParameter('now', new \DateTime())
               ->setParameter('completed', 'completed');
        }

        if (isset($criteria['has_deadline'])) {
            if ($criteria['has_deadline']) {
                $qb->andWhere('t.deadline IS NOT NULL');
            } else {
                $qb->andWhere('t.deadline IS NULL');
            }
        }

        $sortBy = $criteria['sort_by'] ?? 'createdAt';
        $sortOrder = $criteria['sort_order'] ?? 'DESC';

        $allowedSortFields = ['createdAt', 'updatedAt', 'deadline', 'priority', 'title'];
        if (\in_array($sortBy, $allowedSortFields)) {
            $qb->orderBy('t.' . $sortBy, $sortOrder);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Search tasks
     */
    private function searchTasks(string $query, User $user, array $options): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('(t.title LIKE :query OR t.description LIKE :query)')
            ->setParameter('user', $user)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.createdAt', 'DESC');

        if (isset($options['status'])) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $options['status']);
        }

        if (isset($options['priority'])) {
            $qb->andWhere('t.priority = :priority')
               ->setParameter('priority', $options['priority']);
        }

        if (isset($options['category'])) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $options['category']);
        }

        $limit = $options['limit'] ?? 10;
        $qb->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Search comments
     */
    private function searchComments(string $query, User $user, array $options): array
    {
        $qb = $this->commentRepository->createQueryBuilder('c')
            ->join('c.task', 't')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('c.content LIKE :query')
            ->setParameter('user', $user)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.createdAt', 'DESC');

        $limit = $options['limit'] ?? 10;
        $qb->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Search users
     */
    private function searchUsers(string $query, array $options): array
    {
        $qb = $this->userRepository->createQueryBuilder('u')
            ->where('u.username LIKE :query OR u.email LIKE :query OR CONCAT(u.firstName, \' \', u.lastName) LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC');

        $limit = $options['limit'] ?? 10;
        $qb->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Search tags
     */
    private function searchTags(string $query, int $limit): array
    {
        return $this->tagRepository->createQueryBuilder('t')
            ->where('t.name LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search commands (quick actions)
     */
    private function searchCommands(string $query): array
    {
        $allCommands = [
            ['name' => 'Новая задача', 'url' => '/tasks/new', 'icon' => 'fa-plus', 'keywords' => ['новая', 'создать', 'добавить', 'задача']],
            ['name' => 'Календарь', 'url' => '/calendar', 'icon' => 'fa-calendar', 'keywords' => ['календарь', 'расписание']],
            ['name' => 'Канбан', 'url' => '/kanban', 'icon' => 'fa-columns', 'keywords' => ['канбан', 'доска']],
            ['name' => 'Отчеты', 'url' => '/reports', 'icon' => 'fa-chart-bar', 'keywords' => ['отчет', 'статистика']],
            ['name' => 'Аналитика', 'url' => '/analytics/advanced', 'icon' => 'fa-chart-line', 'keywords' => ['аналитика', 'анализ']],
            ['name' => 'Настройки', 'url' => '/settings', 'icon' => 'fa-cog', 'keywords' => ['настройки', 'параметры']],
            ['name' => 'Импорт', 'url' => '/import', 'icon' => 'fa-file-import', 'keywords' => ['импорт', 'загрузка']],
            ['name' => 'Шаблоны', 'url' => '/templates', 'icon' => 'fa-file-alt', 'keywords' => ['шаблон', 'template']],
            ['name' => 'Коллаборация', 'url' => '/collaboration', 'icon' => 'fa-users', 'keywords' => ['команда', 'коллаборация']],
            ['name' => 'Активность', 'url' => '/activity', 'icon' => 'fa-stream', 'keywords' => ['активность', 'лента']],
            ['name' => 'Учет времени', 'url' => '/time-tracking', 'icon' => 'fa-stopwatch', 'keywords' => ['время', 'таймер']],
        ];

        $query = mb_strtolower($query);
        $matched = [];

        foreach ($allCommands as $command) {
            if (mb_stripos($command['name'], $query) !== false) {
                $matched[] = $command;
                continue;
            }

            foreach ($command['keywords'] as $keyword) {
                if (mb_stripos($keyword, $query) !== false) {
                    $matched[] = $command;
                    break;
                }
            }
        }

        return $matched;
    }

    /**
     * Get search suggestions
     */
    public function getSuggestions(string $query, User $user, int $limit = 5): array
    {
        $suggestions = [];

        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->select('t.title')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.title LIKE :query')
            ->setParameter('user', $user)
            ->setParameter('query', $query . '%')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            $suggestions[] = $task['title'];
        }

        return array_unique($suggestions);
    }

    /**
     * Get popular search terms
     */
    public function getPopularSearchTerms(User $user, int $limit = 10): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->select('t.title, COUNT(t.id) as task_count')
            ->groupBy('t.title')
            ->orderBy('task_count', 'DESC')
            ->setMaxResults($limit);

        if (!\in_array('ROLE_ADMIN', $user->getRoles())) {
            $qb->andWhere('t.user = :user OR t.assignedUser = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Save search query
     */
    public function saveSearch(string $name, array $filters, User $user): array
    {
        // TODO: Save to database
        return [
            'name' => $name,
            'filters' => $filters,
            'user_id' => $user->getId(),
            'created_at' => new \DateTime(),
        ];
    }

    /**
     * Get saved searches
     */
    public function getSavedSearches(User $user): array
    {
        // TODO: Get from database
        return [];
    }
}
