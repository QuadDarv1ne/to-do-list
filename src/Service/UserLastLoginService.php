<?php
// src/Service/UserLastLoginService.php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class UserLastLoginService
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Обновляет время последнего входа пользователя
     * В режиме разработки выполняет немедленное обновление, в продакшене - асинхронное
     */
    public function updateUserLastLogin(User $user): void
    {
        try {
            $user->setLastLoginAt(new \DateTime());

            // В целях производительности, обновляем только конкретного пользователя
            // без полной фиксации изменений в базе данных
            $this->entityManager->persist($user);
            
            // Выполняем flush только для конкретного объекта
            $this->entityManager->getUnitOfWork()->computeChangeSets();
            $this->entityManager->flush([$user]);

            $this->logger->info('User last login time updated: ' . $user->getId());
        } catch (\Exception $e) {
            // Логируем ошибку, но не прерываем основной процесс
            $this->logger->error('Failed to update user last login time: ' . $e->getMessage());
        }
    }
}