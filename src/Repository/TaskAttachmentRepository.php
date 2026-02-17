<?php

namespace App\Repository;

use App\Entity\TaskAttachment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TaskAttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskAttachment::class);
    }

    public function save(TaskAttachment $attachment, bool $flush = false): void
    {
        $this->getEntityManager()->persist($attachment);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TaskAttachment $attachment, bool $flush = false): void
    {
        $this->getEntityManager()->remove($attachment);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByTask($task): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.task = :task')
            ->setParameter('task', $task)
            ->orderBy('a.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
