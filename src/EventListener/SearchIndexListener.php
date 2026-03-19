<?php

namespace App\EventListener;

use App\Entity\Task;
use App\Entity\User;
use App\Service\MeilisearchService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * EventListener для автоматической индексации в Meilisearch
 */
#[AsDoctrineListener(event: Events::postFlush)]
#[AsDoctrineListener(event: Events::postRemove)]
class SearchIndexListener
{
    private array $tasksToIndex = [];
    private array $tasksToRemove = [];
    private array $usersToIndex = [];
    private array $usersToRemove = [];

    public function __construct(
        #[Autowire(service: 'App\Service\MeilisearchService')]
        private MeilisearchService $searchService,
    ) {
    }

    /**
     * Собрать сущности для индексации после flush
     */
    public function postFlush(PostFlushEventArgs $args): void
    {
        // Индексация задач
        if (!empty($this->tasksToIndex)) {
            $tasks = [];
            foreach ($this->tasksToIndex as $task) {
                $tasks[] = $this->normalizeTask($task);
            }
            $this->searchService->reindexAllTasks($tasks);
            $this->tasksToIndex = [];
        }

        // Индексация пользователей
        if (!empty($this->usersToIndex)) {
            $users = [];
            foreach ($this->usersToIndex as $user) {
                $users[] = $this->normalizeUser($user);
            }
            $this->searchService->reindexAllUsers($users);
            $this->usersToIndex = [];
        }

        // Удаление задач
        foreach ($this->tasksToRemove as $taskId) {
            $this->searchService->removeTask($taskId);
        }
        $this->tasksToRemove = [];

        // Удаление пользователей
        foreach ($this->usersToRemove as $userId) {
            $this->searchService->removeUser($userId);
        }
        $this->usersToRemove = [];
    }

    /**
     * Обработка удаления сущностей
     */
    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Task) {
            $this->tasksToRemove[] = $entity->getId();
        } elseif ($entity instanceof User) {
            $this->usersToRemove[] = $entity->getId();
        }
    }

    /**
     * Обработка обновления задач
     */
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Task) {
            $this->tasksToIndex[] = $entity;
        } elseif ($entity instanceof User) {
            $this->usersToIndex[] = $entity;
        }
    }

    /**
     * Нормализовать задачу для индексации
     */
    private function normalizeTask(Task $task): array
    {
        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription() ?? '',
            'status' => $task->getStatus(),
            'priority' => $task->getPriority(),
            'user_id' => $task->getUser()?->getId(),
            'user_name' => $task->getUser()?->getFullName(),
            'created_at' => $task->getCreatedAt()?->format('Y-m-d H:i:s'),
            'due_date' => $task->getDueDate()?->format('Y-m-d'),
            'tags' => array_map(fn($tag) => $tag->getName(), $task->getTags()->toArray()),
        ];
    }

    /**
     * Нормализовать пользователя для индексации
     */
    private function normalizeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'first_name' => $user->getFirstName() ?? '',
            'last_name' => $user->getLastName() ?? '',
            'full_name' => $user->getFullName(),
            'position' => $user->getPosition() ?? '',
            'department' => $user->getDepartment() ?? '',
        ];
    }
}
