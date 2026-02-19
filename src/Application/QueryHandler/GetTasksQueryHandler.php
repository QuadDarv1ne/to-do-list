<?php

namespace App\Application\QueryHandler;

use App\Application\Query\GetTasksQuery;
use App\Repository\TaskRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetTasksQueryHandler
{
    public function __construct(
        private TaskRepository $taskRepository,
    ) {
    }

    public function __invoke(GetTasksQuery $query): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->leftJoin('t.assignedUser', 'au')->addSelect('au')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->leftJoin('t.tags', 'tags')->addSelect('tags')
            ->where('t.user = :userId')
            ->setParameter('userId', $query->getUserId());

        if ($query->getStatus()) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $query->getStatus());
        }

        if ($query->getPriority()) {
            $qb->andWhere('t.priority = :priority')
               ->setParameter('priority', $query->getPriority());
        }

        if ($query->getCategoryId()) {
            $qb->andWhere('t.category = :categoryId')
               ->setParameter('categoryId', $query->getCategoryId());
        }

        $qb->setFirstResult($query->getOffset())
           ->setMaxResults($query->getLimit())
           ->orderBy('t.createdAt', 'DESC');

        $tasks = $qb->getQuery()->getResult();

        return array_map(fn ($task) => [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'status' => $task->getStatus(),
            'priority' => $task->getPriority(),
            'due_date' => $task->getDueDate()?->format(\DateTimeInterface::ATOM),
        ], $tasks);
    }
}
