<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use App\Entity\ActivityLog;
use App\Entity\Comment;
use App\Entity\TaskNotification;
use App\Entity\TaskTimeTracking;

/**
 * Service for cleaning up old data
 */
class DataCleanupService
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
     * Clean up old activity logs
     */
    public function cleanOldActivityLogs(int $daysToKeep = 30): int
    {
        $cutoffDate = new \DateTime("-{$daysToKeep} days");
        
        $this->logger->info('Starting activity log cleanup', [
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
            'days_to_keep' => $daysToKeep
        ]);

        $qb = $this->entityManager->createQueryBuilder();
        
        $deletedCount = $qb
            ->delete(ActivityLog::class, 'al')
            ->where('al.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();

        $this->logger->info('Activity log cleanup completed', [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s')
        ]);

        return $deletedCount;
    }

    /**
     * Clean up old notifications
     */
    public function cleanOldNotifications(int $daysToKeep = 30): int
    {
        $cutoffDate = new \DateTime("-{$daysToKeep} days");
        
        $this->logger->info('Starting notification cleanup', [
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
            'days_to_keep' => $daysToKeep
        ]);

        $qb = $this->entityManager->createQueryBuilder();
        
        $deletedCount = $qb
            ->delete(TaskNotification::class, 'tn')
            ->where('tn.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();

        $this->logger->info('Notification cleanup completed', [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s')
        ]);

        return $deletedCount;
    }

    /**
     * Clean up old comments
     */
    public function cleanOldComments(int $daysToKeep = 60): int
    {
        $cutoffDate = new \DateTime("-{$daysToKeep} days");
        
        $this->logger->info('Starting comment cleanup', [
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
            'days_to_keep' => $daysToKeep
        ]);

        // First, find comments that are older than cutoff and not associated with active tasks
        $qb = $this->entityManager->createQueryBuilder();
        
        $deletedCount = $qb
            ->delete(Comment::class, 'c')
            ->where('c.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();

        $this->logger->info('Comment cleanup completed', [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s')
        ]);

        return $deletedCount;
    }

    /**
     * Clean up old time tracking records
     */
    public function cleanOldTimeTracking(int $daysToKeep = 90): int
    {
        $cutoffDate = new \DateTime("-{$daysToKeep} days");
        
        $this->logger->info('Starting time tracking cleanup', [
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
            'days_to_keep' => $daysToKeep
        ]);

        $qb = $this->entityManager->createQueryBuilder();
        
        $deletedCount = $qb
            ->delete(TaskTimeTracking::class, 'ttt')
            ->where('ttt.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();

        $this->logger->info('Time tracking cleanup completed', [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s')
        ]);

        return $deletedCount;
    }

    /**
     * Clean up old password reset requests
     */
    public function cleanOldPasswordResetRequests(int $daysToKeep = 7): int
    {
        $cutoffDate = new \DateTime("-{$daysToKeep} days");
        
        $this->logger->info('Starting password reset request cleanup', [
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
            'days_to_keep' => $daysToKeep
        ]);

        $resetRequestRepo = $this->entityManager->getRepository('App:ResetPasswordRequest');
        
        $qb = $this->entityManager->createQueryBuilder();
        
        $deletedCount = $qb
            ->delete('App:ResetPasswordRequest', 'rpr')
            ->where('rpr.expiresAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();

        $this->logger->info('Password reset request cleanup completed', [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s')
        ]);

        return $deletedCount;
    }

    /**
     * Perform comprehensive cleanup
     */
    public function performComprehensiveCleanup(array $options = []): array
    {
        $startTime = microtime(true);
        $this->logger->info('Starting comprehensive data cleanup');
        
        $defaults = [
            'activity_logs_days' => 30,
            'notifications_days' => 30,
            'comments_days' => 60,
            'time_tracking_days' => 90,
            'password_reset_days' => 7,
        ];
        
        $options = array_merge($defaults, $options);
        
        $results = [
            'activity_logs_deleted' => $this->cleanOldActivityLogs($options['activity_logs_days']),
            'notifications_deleted' => $this->cleanOldNotifications($options['notifications_days']),
            'comments_deleted' => $this->cleanOldComments($options['comments_days']),
            'time_tracking_deleted' => $this->cleanOldTimeTracking($options['time_tracking_days']),
            'password_reset_requests_deleted' => $this->cleanOldPasswordResetRequests($options['password_reset_days']),
        ];
        
        $results['total_deleted'] = array_sum($results);
        $results['duration'] = round(microtime(true) - $startTime, 2);
        
        $this->logger->info('Comprehensive data cleanup completed', [
            'total_deleted' => $results['total_deleted'],
            'duration' => $results['duration']
        ]);
        
        return $results;
    }

    /**
     * Get statistics about old data
     */
    public function getOldDataStatistics(array $options = []): array
    {
        $defaults = [
            'activity_logs_days' => 30,
            'notifications_days' => 30,
            'comments_days' => 60,
            'time_tracking_days' => 90,
            'password_reset_days' => 7,
        ];
        
        $options = array_merge($defaults, $options);
        
        $stats = [];
        
        // Count old activity logs
        $cutoffDate = new \DateTime("-{$options['activity_logs_days']} days");
        $qb = $this->entityManager->createQueryBuilder();
        $stats['activity_logs_old'] = $qb
            ->select('COUNT(al.id)')
            ->from(ActivityLog::class, 'al')
            ->where('al.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->getSingleScalarResult();
        
        // Count old notifications
        $cutoffDate = new \DateTime("-{$options['notifications_days']} days");
        $qb = $this->entityManager->createQueryBuilder();
        $stats['notifications_old'] = $qb
            ->select('COUNT(tn.id)')
            ->from(TaskNotification::class, 'tn')
            ->where('tn.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->getSingleScalarResult();
        
        // Count old comments
        $cutoffDate = new \DateTime("-{$options['comments_days']} days");
        $qb = $this->entityManager->createQueryBuilder();
        $stats['comments_old'] = $qb
            ->select('COUNT(c.id)')
            ->from(Comment::class, 'c')
            ->where('c.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->getSingleScalarResult();
        
        // Count old time tracking
        $cutoffDate = new \DateTime("-{$options['time_tracking_days']} days");
        $qb = $this->entityManager->createQueryBuilder();
        $stats['time_tracking_old'] = $qb
            ->select('COUNT(ttt.id)')
            ->from(TaskTimeTracking::class, 'ttt')
            ->where('ttt.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->getSingleScalarResult();
        
        // Count old password reset requests
        $cutoffDate = new \DateTime("-{$options['password_reset_days']} days");
        $qb = $this->entityManager->createQueryBuilder();
        $stats['password_reset_requests_old'] = $qb
            ->select('COUNT(rpr.id)')
            ->from('App:ResetPasswordRequest', 'rpr')
            ->where('rpr.expiresAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->getSingleScalarResult();
        
        return $stats;
    }
}