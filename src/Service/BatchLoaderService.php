<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Сервис для пакетной загрузки связанных сущностей
 * Решает проблему N+1 запросов
 */
class BatchLoaderService
{
    private array $cache = [];

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Пакетная загрузка пользователей по ID
     */
    public function loadUsers(array $userIds): array
    {
        $cacheKey = 'users_' . md5(serialize($userIds));
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $users = $this->entityManager->getRepository('App\Entity\User')
            ->createQueryBuilder('u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $userIds)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($users as $user) {
            $indexed[$user->getId()] = $user;
        }

        $this->cache[$cacheKey] = $indexed;
        return $indexed;
    }

    /**
     * Пакетная загрузка задач по ID
     */
    public function loadTasks(array $taskIds): array
    {
        $cacheKey = 'tasks_' . md5(serialize($taskIds));
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $tasks = $this->entityManager->getRepository('App\Entity\Task')
            ->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')->addSelect('u')
            ->leftJoin('t.assignedUser', 'au')->addSelect('au')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $taskIds)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($tasks as $task) {
            $indexed[$task->getId()] = $task;
        }

        $this->cache[$cacheKey] = $indexed;
        return $indexed;
    }

    /**
     * Пакетная загрузка комментариев для задач
     */
    public function loadCommentsForTasks(array $taskIds): array
    {
        $cacheKey = 'comments_' . md5(serialize($taskIds));
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $comments = $this->entityManager->getRepository('App\Entity\Comment')
            ->createQueryBuilder('c')
            ->leftJoin('c.author', 'a')->addSelect('a')
            ->where('c.task IN (:taskIds)')
            ->setParameter('taskIds', $taskIds)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        // Группируем по задачам
        $grouped = [];
        foreach ($comments as $comment) {
            $taskId = $comment->getTask()->getId();
            if (!isset($grouped[$taskId])) {
                $grouped[$taskId] = [];
            }
            $grouped[$taskId][] = $comment;
        }

        $this->cache[$cacheKey] = $grouped;
        return $grouped;
    }

    /**
     * Пакетная загрузка тегов для задач
     */
    public function loadTagsForTasks(array $taskIds): array
    {
        $cacheKey = 'tags_' . md5(serialize($taskIds));
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $qb = $this->entityManager->createQueryBuilder();
        $results = $qb->select('t.id as task_id, tag')
            ->from('App\Entity\Task', 't')
            ->leftJoin('t.tags', 'tag')
            ->where('t.id IN (:taskIds)')
            ->setParameter('taskIds', $taskIds)
            ->getQuery()
            ->getResult();

        // Группируем теги по задачам
        $grouped = [];
        foreach ($results as $result) {
            $taskId = $result['task_id'];
            if (!isset($grouped[$taskId])) {
                $grouped[$taskId] = [];
            }
            if ($result['tag']) {
                $grouped[$taskId][] = $result['tag'];
            }
        }

        $this->cache[$cacheKey] = $grouped;
        return $grouped;
    }

    /**
     * Пакетная загрузка уведомлений для пользователей
     */
    public function loadNotificationsForUsers(array $userIds, bool $unreadOnly = false): array
    {
        $cacheKey = 'notifications_' . md5(serialize($userIds) . $unreadOnly);
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $qb = $this->entityManager->getRepository('App\Entity\Notification')
            ->createQueryBuilder('n')
            ->leftJoin('n.task', 't')->addSelect('t')
            ->where('n.user IN (:userIds)')
            ->setParameter('userIds', $userIds)
            ->orderBy('n.createdAt', 'DESC');

        if ($unreadOnly) {
            $qb->andWhere('n.isRead = :isRead')
               ->setParameter('isRead', false);
        }

        $notifications = $qb->getQuery()->getResult();

        // Группируем по пользователям
        $grouped = [];
        foreach ($notifications as $notification) {
            $userId = $notification->getUser()->getId();
            if (!isset($grouped[$userId])) {
                $grouped[$userId] = [];
            }
            $grouped[$userId][] = $notification;
        }

        $this->cache[$cacheKey] = $grouped;
        return $grouped;
    }

    /**
     * Пакетная загрузка категорий по ID
     */
    public function loadCategories(array $categoryIds): array
    {
        $cacheKey = 'categories_' . md5(serialize($categoryIds));
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $categories = $this->entityManager->getRepository('App\Entity\TaskCategory')
            ->createQueryBuilder('c')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', $categoryIds)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($categories as $category) {
            $indexed[$category->getId()] = $category;
        }

        $this->cache[$cacheKey] = $indexed;
        return $indexed;
    }

    /**
     * Предзагрузка всех связанных данных для списка задач
     */
    public function preloadTaskRelations(array $tasks): void
    {
        if (empty($tasks)) {
            return;
        }

        $taskIds = array_map(fn($task) => $task->getId(), $tasks);

        // Загружаем все связанные данные одним пакетом
        $this->loadCommentsForTasks($taskIds);
        $this->loadTagsForTasks($taskIds);

        // Собираем ID пользователей и категорий
        $userIds = [];
        $categoryIds = [];

        foreach ($tasks as $task) {
            if ($task->getUser()) {
                $userIds[] = $task->getUser()->getId();
            }
            if ($task->getAssignedUser()) {
                $userIds[] = $task->getAssignedUser()->getId();
            }
            if ($task->getCategory()) {
                $categoryIds[] = $task->getCategory()->getId();
            }
        }

        if (!empty($userIds)) {
            $this->loadUsers(array_unique($userIds));
        }

        if (!empty($categoryIds)) {
            $this->loadCategories(array_unique($categoryIds));
        }
    }

    /**
     * Очистка кэша
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Получение статистики кэша
     */
    public function getCacheStats(): array
    {
        return [
            'entries' => count($this->cache),
            'keys' => array_keys($this->cache),
            'memory' => memory_get_usage(true)
        ];
    }
}
