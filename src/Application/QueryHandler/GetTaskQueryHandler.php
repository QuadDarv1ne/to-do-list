<?php

namespace App\Application\QueryHandler;

use App\Application\Query\GetTaskQuery;
use App\Repository\TaskRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetTaskQueryHandler
{
    public function __construct(
        private TaskRepository $taskRepository,
    ) {
    }

    public function __invoke(GetTaskQuery $query): ?array
    {
        $task = $this->taskRepository->find($query->getTaskId());

        if (!$task) {
            return null;
        }

        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus(),
            'priority' => $task->getPriority(),
            'created_at' => $task->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updated_at' => $task->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'due_date' => $task->getDueDate()?->format(\DateTimeInterface::ATOM),
            'completed_at' => $task->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'user' => [
                'id' => $task->getUser()->getId(),
                'username' => $task->getUser()->getUsername(),
                'email' => $task->getUser()->getEmail(),
            ],
            'assigned_user' => [
                'id' => $task->getAssignedUser()->getId(),
                'username' => $task->getAssignedUser()->getUsername(),
                'email' => $task->getAssignedUser()->getEmail(),
            ],
            'category' => $task->getCategory() ? [
                'id' => $task->getCategory()->getId(),
                'name' => $task->getCategory()->getName(),
            ] : null,
            'tags' => array_map(fn ($tag) => [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
            ], $task->getTags()->toArray()),
        ];
    }
}
