<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class UserLastLoginService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function updateUserLastLogin(User $user): void
    {
        try {
            $user->setLastLoginAt(new \DateTime());

            $this->entityManager->persist($user);
            $this->entityManager->getUnitOfWork()->computeChangeSets();
            $this->entityManager->flush([$user]);

            $this->logger->info('User last login time updated', ['user_id' => $user->getId()]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to update user last login time', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
                'class' => \get_class($e),
            ]);
        }
    }
}
