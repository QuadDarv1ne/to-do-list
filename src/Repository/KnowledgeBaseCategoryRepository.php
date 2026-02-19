<?php

namespace App\Repository;

use App\Entity\KnowledgeBaseCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KnowledgeBaseCategory>
 */
class KnowledgeBaseCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KnowledgeBaseCategory::class);
    }

    // Add custom methods here as needed
}
