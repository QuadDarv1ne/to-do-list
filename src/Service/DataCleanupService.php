<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Сервис для автоматической очистки устаревших данных
 */
class DataCleanupService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    /**
     * Очистка старых логов активности
     */
    public function cleanupActivityLogs(int $daysToKeep = 90): int
    {
        $cutoffDate = new \DateTime("-{$daysToKeep} days");
        
        $qb = $this->entityManager->createQueryBuilder();
        $deleted = $qb->delete('App\Entity\ActivityLog', 'a')
            ->where('a.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();

        $this->logger->info('Activity logs cleaned up', [
            'deleted' => $deleted,
            'cutoff_date' => $cutoffDate->format('Y-m-d')
        ]);

        return $deleted;
    }

    /**
     * Очистка прочитанных уведомлений
     */
    public function cleanupReadNotifications(int $daysToKeep = 30): int
    {
        $cutoffDate = new \DateTime("-{$daysToKeep} days");
        
        $qb = $this->entityManager->createQueryBuilder();
        $deleted = $qb->delete('App\Entity\Notification', 'n')
            ->where('n.isRead = :read')
            ->andWhere('n.createdAt < :cutoff')
            ->setParameter('read', true)
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();

        $this->logger->info('Read notifications cleaned up', [
            'deleted' => $deleted,
            'cutoff_date' => $cutoffDate->format('Y-m-d')
        ]);

        return $deleted;
    }

    /**
     * Очистка завершенных задач
     */
    public function archiveCompletedTasks(int $daysToKeep = 365): int
    {
        $cutoffDate = new \DateTime("-{$daysToKeep} days");
        
        // Помечаем как архивные вместо удаления
        $qb = $this->entityManager->createQueryBuilder();
        $archived = $qb->update('App\Entity\Task', 't')
            ->set('t.isArchived', ':archived')
            ->where('t.status = :status')
            ->andWhere('t.completedAt < :cutoff')
            ->setParameter('archived', true)
            ->setParameter('status', 'completed')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();

        $this->logger->info('Completed tasks archived', [
            'archived' => $archived,
            'cutoff_date' => $cutoffDate->format('Y-m-d')
        ]);

        return $archived;
    }

    /**
     * Очистка временных файлов
     */
    public function cleanupTempFiles(string $tempDir): int
    {
        if (!is_dir($tempDir)) {
            return 0;
        }

        $deleted = 0;
        $cutoffTime = time() - (24 * 3600); // 24 часа

        $files = glob($tempDir . '/*');
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }

        $this->logger->info('Temp files cleaned up', ['deleted' => $deleted]);
        return $deleted;
    }

    /**
     * Очистка истории изменений задач
     */
    public function cleanupTaskHistory(int $daysToKeep = 180): int
    {
        $cutoffDate = new \DateTime("-{$daysToKeep} days");
        
        $qb = $this->entityManager->createQueryBuilder();
        $deleted = $qb->delete('App\Entity\TaskHistory', 'h')
            ->where('h.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();

        $this->logger->info('Task history cleaned up', [
            'deleted' => $deleted,
            'cutoff_date' => $cutoffDate->format('Y-m-d')
        ]);

        return $deleted;
    }

    /**
     * Полная очистка всех устаревших данных
     */
    public function cleanupAll(): array
    {
        $results = [
            'activity_logs' => $this->cleanupActivityLogs(),
            'notifications' => $this->cleanupReadNotifications(),
            'archived_tasks' => $this->archiveCompletedTasks(),
            'task_history' => $this->cleanupTaskHistory(),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->logger->info('Full cleanup completed', $results);
        return $results;
    }

    /**
     * Получение статистики по данным для очистки
     */
    public function getCleanupStats(): array
    {
        $stats = [];

        // Старые логи активности
        $qb = $this->entityManager->createQueryBuilder();
        $stats['old_activity_logs'] = $qb->select('COUNT(a.id)')
            ->from('App\Entity\ActivityLog', 'a')
            ->where('a.createdAt < :cutoff')
            ->setParameter('cutoff', new \DateTime('-90 days'))
            ->getQuery()
            ->getSingleScalarResult();

        // Прочитанные уведомления
        $qb = $this->entityManager->createQueryBuilder();
        $stats['old_notifications'] = $qb->select('COUNT(n.id)')
            ->from('App\Entity\Notification', 'n')
            ->where('n.isRead = :read')
            ->andWhere('n.createdAt < :cutoff')
            ->setParameter('read', true)
            ->setParameter('cutoff', new \DateTime('-30 days'))
            ->getQuery()
            ->getSingleScalarResult();

        // Старые завершенные задачи
        $qb = $this->entityManager->createQueryBuilder();
        $stats['old_completed_tasks'] = $qb->select('COUNT(t.id)')
            ->from('App\Entity\Task', 't')
            ->where('t.status = :status')
            ->andWhere('t.completedAt < :cutoff')
            ->setParameter('status', 'completed')
            ->setParameter('cutoff', new \DateTime('-365 days'))
            ->getQuery()
            ->getSingleScalarResult();

        return $stats;
    }
}
