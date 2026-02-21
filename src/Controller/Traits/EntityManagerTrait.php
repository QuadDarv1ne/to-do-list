<?php

namespace App\Controller\Traits;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Trait for EntityManager operations
 */
trait EntityManagerTrait
{
    protected function persistAndFlush(object $entity): void
    {
        if (!property_exists($this, 'entityManager')) {
            throw new \LogicException('EntityManager property not found. Inject EntityManagerInterface in constructor.');
        }
        
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    protected function removeAndFlush(object $entity): void
    {
        if (!property_exists($this, 'entityManager')) {
            throw new \LogicException('EntityManager property not found. Inject EntityManagerInterface in constructor.');
        }
        
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    protected function flush(): void
    {
        if (!property_exists($this, 'entityManager')) {
            throw new \LogicException('EntityManager property not found. Inject EntityManagerInterface in constructor.');
        }
        
        $this->entityManager->flush();
    }
}
