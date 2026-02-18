<?php

namespace App\Repository;

use App\Entity\Goal;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GoalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Goal::class);
    }

    public function findActiveGoalsByUser(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.owner = :user')
            ->andWhere('g.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'active')
            ->orderBy('g.priority', 'DESC')
            ->addOrderBy('g.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findGoalsByStatus(User $user, string $status): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.owner = :user')
            ->andWhere('g.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
