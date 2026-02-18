<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;

class TaskFilterService
{
    public function __construct(
        private TaskRepository $taskRepository
    ) {}

    public function createFilteredQuery(User $user, array $filters = []): QueryBuilder
    {
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->leftJoin('t.assignedUser', 'au')->addSelect('au')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->leftJoin('t.tags', 'tags')->addSelect('tags')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user);

        $this->applyFilters($qb, $filters);

        return $qb;
    }

    public function applyFilters(QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['search'])) {
            $qb->andWhere('t.title LIKE :search OR t.description LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $qb->andWhere('t.priority = :priority')
               ->setParameter('priority', $filters['priority']);
        }

        if (!empty($filters['category'])) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $filters['category']);
        }

        if (!empty($filters['tag'])) {
            $qb->join('t.tags', 'jt')
               ->andWhere('jt.id = :tagId')
               ->setParameter('tagId', $filters['tag']);
        }

        if (!empty($filters['hideCompleted'])) {
            $qb->andWhere('t.status != :completedStatus')
               ->setParameter('completedStatus', 'completed');
        }
    }

    public function getFiltersFromRequest(Request $request): array
    {
        return [
            'search' => $request->query->get('search'),
            'status' => $request->query->get('status'),
            'priority' => $request->query->get('priority'),
            'category' => $request->query->get('category'),
            'tag' => $request->query->get('tag'),
            'hideCompleted' => $request->query->get('hide_completed', false),
            'sort' => $request->query->get('sort', 'createdAt'),
            'direction' => $request->query->get('direction', 'DESC'),
        ];
    }
}
