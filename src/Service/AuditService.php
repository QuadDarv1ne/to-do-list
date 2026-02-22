<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class AuditService
{
    private array $buffer = [];
    private const BUFFER_SIZE = 10;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {}

    /**
     * Записать действие в журнал аудита
     */
    public function log(string $action, string $entityType, int $entityId, array $data = []): void
    {
        $user = $this->security->getUser();
        
        $entry = [
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $user?->getId(),
            'user_email' => $user?->getEmail(),
            'data' => $data,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];

        $this->buffer[] = $entry;

        // Сбрасываем буфер если достигли лимита
        if (count($this->buffer) >= self::BUFFER_SIZE) {
            $this->flush();
        }
    }

    /**
     * Записать создание сущности
     */
    public function logCreate(string $entityType, int $entityId, array $data = []): void
    {
        $this->log('create', $entityType, $entityId, $data);
    }

    /**
     * Записать обновление сущности
     */
    public function logUpdate(string $entityType, int $entityId, array $data = []): void
    {
        $this->log('update', $entityType, $entityId, $data);
    }

    /**
     * Записать удаление сущности
     */
    public function logDelete(string $entityType, int $entityId, array $data = []): void
    {
        $this->log('delete', $entityType, $entityId, $data);
    }

    /**
     * Записать просмотр сущности
     */
    public function logView(string $entityType, int $entityId): void
    {
        $this->log('view', $entityType, $entityId);
    }

    /**
     * Записать ошибку
     */
    public function logError(string $message, array $context = []): void
    {
        $this->log('error', 'system', 0, ['message' => $message, 'context' => $context]);
    }

    /**
     * Принудительно сбросить буфер в БД
     */
    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        // В реальном приложении здесь был бы INSERT в таблицу audit_log
        // Для примера просто логируем
        foreach ($this->buffer as $entry) {
            // Логирование в файл или БД
            error_log(sprintf(
                '[AUDIT] %s | %s | %s | %s | %s',
                $entry['timestamp'],
                $entry['action'],
                $entry['entity_type'],
                $entry['entity_id'],
                $entry['user_email'] ?? 'anonymous'
            ));
        }

        $this->buffer = [];
    }

    /**
     * Получить историю действий для сущности
     */
    public function getEntityHistory(string $entityType, int $entityId): array
    {
        // В реальном приложении - запрос к БД
        return [];
    }

    /**
     * Получить историю действий пользователя
     */
    public function getUserHistory(int $userId, int $limit = 50): array
    {
        // В реальном приложении - запрос к БД
        return [];
    }

    /**
     * Очистка старых записей аудита
     */
    public function cleanup(int $daysToKeep = 90): int
    {
        // В реальном приложении - DELETE запрос
        // DELETE FROM audit_log WHERE timestamp < DATE_SUB(NOW(), INTERVAL :days DAY)
        return 0;
    }
}
