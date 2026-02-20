<?php

namespace App\Repository;

use App\Entity\TaskTemplate;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskTemplate>
 *
 * @method TaskTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method TaskTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method TaskTemplate[]    findAll()
 * @method TaskTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TaskTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskTemplate::class);
    }

    /**
     * @return TaskTemplate[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.isActive = true')
            ->setParameter('user', $user)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TaskTemplate[]
     */
    public function findGlobalTemplates(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user IS NULL')
            ->andWhere('t.isActive = true')
            ->orderBy('t.category', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return TaskTemplate[]
     */
    public function findPopular(int $limit = 5): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.isActive = true')
            ->orderBy('t.usageCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndId(User $user, int $id): ?TaskTemplate
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.id = :id')
            ->setParameter('user', $user)
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(TaskTemplate $template, bool $flush = true): void
    {
        $this->getEntityManager()->persist($template);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TaskTemplate $template, bool $flush = true): void
    {
        $this->getEntityManager()->remove($template);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
