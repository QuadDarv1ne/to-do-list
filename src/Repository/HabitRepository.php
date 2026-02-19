<?php

namespace App\Repository;

use App\Entity\Habit;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class HabitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Habit::class);
    }

    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('h')
            ->leftJoin('h.logs', 'l')
            ->addSelect('l')
            ->where('h.user = :user')
            ->andWhere('h.active = :active')
            ->setParameter('user', $user)
            ->setParameter('active', true)
            ->orderBy('h.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByCategory(User $user, string $category): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.user = :user')
            ->andWhere('h.category = :category')
            ->andWhere('h.active = :active')
            ->setParameter('user', $user)
            ->setParameter('category', $category)
            ->setParameter('active', true)
            ->orderBy('h.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
