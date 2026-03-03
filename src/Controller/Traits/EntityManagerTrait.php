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
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    protected function removeAndFlush(object $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }

    protected function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        if (!$this instanceof \Symfony\Bundle\FrameworkBundle\Controller\AbstractController) {
            throw new \LogicException('EntityManagerTrait requires AbstractController');
        }

        $em = $this->container?->get('doctrine.orm.entity_manager');
        if (!$em instanceof EntityManagerInterface) {
            throw new \LogicException('EntityManager not available in container');
        }

        return $em;
    }
}
