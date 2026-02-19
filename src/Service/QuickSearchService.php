<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TagRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;

class QuickSearchService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private UserRepository $userRepository,
        private TagRepository $tagRepository,
    ) {
    }

    /**
     * Universal quick search
     */
    public function search(string $query, User $currentUser, int $limit = 10): array
    {
        $results = [
            'tasks' => [],
            'users' => [],
            'tags' => [],
            'commands' => [],
        ];

        // Search tasks
        $results['tasks'] = $this->searchTasks($query, $currentUser, $limit);

        // Search users
        $results['users'] = $this->searchUsers($query, $limit);

        // Search tags
        $results['tags'] = $this->searchTags($query, $limit);

        // Search commands
        $results['commands'] = $this->searchCommands($query);

        return $results;
    }

    /**
     * Search tasks
     */
    private function searchTasks(string $query, User $user, int $limit): array
    {
        return $this->taskRepository->createQueryBuilder('t')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.title LIKE :query OR t.description LIKE :query')
            ->setParameter('user', $user)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search users
     */
    private function searchUsers(string $query, int $limit): array
    {
        return $this->userRepository->createQueryBuilder('u')
            ->where('u.username LIKE :query OR u.email LIKE :query OR u.fullName LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
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
            // Check if query matches name
            if (mb_stripos($command['name'], $query) !== false) {
                $matched[] = $command;

                continue;
            }

            // Check if query matches keywords
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
    public function getSuggestions(string $query, User $user): array
    {
        $suggestions = [];

        // Recent searches (TODO: implement)

        // Popular searches
        $suggestions[] = 'срочные задачи';
        $suggestions[] = 'мои задачи';
        $suggestions[] = 'просроченные';
        $suggestions[] = 'на этой неделе';

        return \array_slice($suggestions, 0, 5);
    }

    /**
     * Get search statistics
     */
    public function getStatistics(User $user): array
    {
        return [
            'total_searches' => 0, // TODO: Track
            'most_searched' => [],
            'recent_searches' => [],
        ];
    }
}
