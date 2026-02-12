<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service to manage event streams efficiently and prevent performance issues
 */
class EventStreamManager
{
    private NotificationRepository $notificationRepository;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    
    // Store last notification check times per user to reduce database queries
    private array $lastCheckTimes = [];
    
    public function __construct(
        NotificationRepository $notificationRepository,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->notificationRepository = $notificationRepository;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }
    
    /**
     * Get new notifications for a user since last check
     */
    public function getNewNotifications(User $user, \DateTime $since): array
    {
        // Use a more efficient query with proper indexing
        $qb = $this->notificationRepository->createQueryBuilder('n');
        
        $result = $qb
            ->where('n.user = :user')
            ->andWhere('n.createdAt > :since')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->orderBy('n.createdAt', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
            
        // Update the last check time for this user
        $this->lastCheckTimes[$user->getId()] = new \DateTime();
        
        return $result;
    }
    
    /**
     * Check if user has new notifications without loading them all
     */
    public function hasNewNotifications(User $user, \DateTime $since): bool
    {
        $count = $this->notificationRepository->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :user')
            ->andWhere('n.createdAt > :since')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
            
        return $count > 0;
    }
    
    /**
     * Get last check time for a user
     */
    public function getLastCheckTime(int $userId): ?\DateTime
    {
        return $this->lastCheckTimes[$userId] ?? null;
    }
    
    /**
     * Set last check time for a user
     */
    public function setLastCheckTime(int $userId, \DateTime $dateTime): void
    {
        $this->lastCheckTimes[$userId] = $dateTime;
    }
    
    /**
     * Clean up old entries from memory
     */
    public function cleanup(): void
    {
        // Remove entries older than 1 hour from memory
        $cutoff = new \DateTime('-1 hour');
        foreach ($this->lastCheckTimes as $userId => $dateTime) {
            if ($dateTime < $cutoff) {
                unset($this->lastCheckTimes[$userId]);
            }
        }
    }
}