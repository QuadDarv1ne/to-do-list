<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * Сервис для фильтрации и сортировки задач
 */
class TaskFilterService
{
    public function __construct(
        private TaskRepository $taskRepository
    ) {}

    /**
     * Создать QueryBuilder с базовыми фильтрами
     */
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

    /**
     * Применить фильтры к QueryBuilder
     */
    private function applyFilters(QueryBuilder $qb, array $filters): void
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

        if (!empty($filters['hide_completed'])) {
            $qb->andWhere('t.status != :completedStatus')
               ->setParameter('completedStatus', 'completed');
        }
    }

    /**
     * Применить сортировку
     */
    public function applySorting(QueryBuilder $qb, string $sort = 'createdAt', string $direction = 'DESC'): QueryBuilder
    {
        $allowedSorts = ['createdAt', 'priority', 'dueDate', 'title', 'tag_count'];
        $allowedDirections = ['ASC', 'DESC'];

        if (!in_array($direction, $allowedDirections)) {
            $direction = 'DESC';
        }

        if ($sort === 'tag_count') {
            $qb->select('t, au, c, tags, COUNT(tags.id) as HIDDEN tag_count')
               ->groupBy('t.id, au.id, c.id')
               ->orderBy('tag_count', $direction)
               ->addOrderBy('t.createdAt', 'DESC');
        } elseif (in_array($sort, $allowedSorts)) {
            $qb->orderBy('t.' . $sort, $direction);
        } else {
            $qb->orderBy('t.createdAt', 'DESC');
        }

        return $qb;
    }

    /**
     * Получить общее количество задач
     */
    public function getTotalCount(QueryBuilder $qb): int
    {
        $countQb = clone $qb;
        return (int) $countQb->select('COUNT(DISTINCT t.id)')
            ->setFirstResult(0)
            ->setMaxResults(null)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Применить пагинацию
     */
    public function applyPagination(QueryBuilder $qb, int $page = 1, int $limit = 10): QueryBuilder
    {
        $offset = ($page - 1) * $limit;
        
        return $qb->setFirstResult($offset)
                  ->setMaxResults($limit);
    }

    /**
     * Извлечь фильтры из Request
     */
    public function extractFilters(Request $request): array
    {
        return [
            'search' => $request->query->get('search'),
            'status' => $request->query->get('status'),
            'priority' => $request->query->get('priority'),
            'category' => $request->query->get('category'),
            'tag' => $request->query->get('tag'),
            'hide_completed' => $request->query->get('hide_completed', false),
        ];
    }

    /**
     * Получить параметры сортировки из Request
     */
    public function extractSorting(Request $request): array
    {
        return [
            'sort' => $request->query->get('sort', 'createdAt'),
            'direction' => $request->query->get('direction', 'DESC'),
        ];
    }

    /**
     * Получить параметры пагинации из Request
     */
    public function extractPagination(Request $request, int $defaultLimit = 10): array
    {
        return [
            'page' => max(1, (int)$request->query->get('page', 1)),
            'limit' => $defaultLimit,
        ];
    }
}
