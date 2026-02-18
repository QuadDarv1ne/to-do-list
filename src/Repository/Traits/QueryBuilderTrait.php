<?php

namespace App\Repository\Traits;

use Doctrine\ORM\QueryBuilder;

/**
 * Trait with common QueryBuilder methods to reduce duplication
 */
trait QueryBuilderTrait
{
    /**
     * Add user filter to query
     */
    protected function addUserFilter(QueryBuilder $qb, $user, string $alias = 't'): QueryBuilder
    {
        return $qb
            ->andWhere("{$alias}.user = :user")
            ->setParameter('user', $user);
    }
    
    /**
     * Add date range filter
     */
    protected function addDateRangeFilter(
        QueryBuilder $qb, 
        ?\DateTimeInterface $from, 
        ?\DateTimeInterface $to, 
        string $field = 'createdAt',
        string $alias = 't'
    ): QueryBuilder {
        if ($from) {
            $qb->andWhere("{$alias}.{$field} >= :dateFrom")
               ->setParameter('dateFrom', $from);
        }
        
        if ($to) {
            $qb->andWhere("{$alias}.{$field} <= :dateTo")
               ->setParameter('dateTo', $to);
        }
        
        return $qb;
    }
    
    /**
     * Add status filter
     */
    protected function addStatusFilter(QueryBuilder $qb, ?string $status, string $alias = 't'): QueryBuilder
    {
        if ($status !== null) {
            $qb->andWhere("{$alias}.status = :status")
               ->setParameter('status', $status);
        }
        
        return $qb;
    }
    
    /**
     * Add active filter
     */
    protected function addActiveFilter(QueryBuilder $qb, bool $active = true, string $alias = 't'): QueryBuilder
    {
        return $qb
            ->andWhere("{$alias}.isActive = :active")
            ->setParameter('active', $active);
    }
    
    /**
     * Add ordering
     */
    protected function addOrdering(
        QueryBuilder $qb, 
        string $field = 'createdAt', 
        string $direction = 'DESC',
        string $alias = 't'
    ): QueryBuilder {
        return $qb->orderBy("{$alias}.{$field}", $direction);
    }
    
    /**
     * Add pagination
     */
    protected function addPagination(QueryBuilder $qb, int $page = 1, int $limit = 20): QueryBuilder
    {
        return $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
    }
}
