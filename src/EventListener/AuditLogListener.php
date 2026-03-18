<?php

namespace App\EventListener;

use App\Entity\AuditLog;
use App\Service\AuditLogService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * EventListener для автоматического логирования изменений сущностей
 *
 * Автоматически создаёт записи в audit_log при:
 * - Создании сущности (postPersist)
 * - Обновлении сущности (postUpdate)
 * - Удалении сущности (postRemove)
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class AuditLogListener
{
    public function __construct(
        private AuditLogService $auditLogService,
    ) {
    }

    /**
     * Обработка события postPersist (создание сущности)
     */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        // Пропускаем AuditLog чтобы избежать бесконечного цикла
        if ($entity instanceof AuditLog) {
            return;
        }

        // Пропускаем сущности без getId
        if (!method_exists($entity, 'getId')) {
            return;
        }

        // Получаем данные сущности для логирования
        $data = $this->getEntityData($entity);

        $this->auditLogService->logCreate($entity, $data);
    }

    /**
     * Обработка события preUpdate (накапливаем изменения)
     * Note: Для отслеживания изменений используем postUpdate с UnitOfWork
     */
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        // Пропускаем AuditLog чтобы избежать бесконечного цикла
        if ($entity instanceof AuditLog) {
            return;
        }

        // Пропускаем сущности без getId
        if (!method_exists($entity, 'getId')) {
            return;
        }

        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $meta = $em->getClassMetadata($entity::class);

        // Получаем изменения из UnitOfWork
        $changes = $uow->getEntityChangeSet($entity);

        if (empty($changes)) {
            return;
        }

        // Формируем старые и новые значения
        $oldValues = [];
        $newValues = [];

        foreach ($changes as $field => $change) {
            // Пропускаем поля updated_at, created_at и т.п.
            if (\in_array($field, ['updatedAt', 'createdAt', 'updated_at', 'created_at'])) {
                continue;
            }

            $oldValues[$field] = $change[0];
            $newValues[$field] = $change[1];
        }

        if (!empty($oldValues) && !empty($newValues)) {
            $this->auditLogService->logUpdate($entity, $oldValues, $newValues);
        }
    }

    /**
     * Обработка события postRemove (удаление сущности)
     */
    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        // Пропускаем AuditLog чтобы избежать бесконечного цикла
        if ($entity instanceof AuditLog) {
            return;
        }

        // Пропускаем сущности без getId
        if (!method_exists($entity, 'getId')) {
            return;
        }

        // Получаем данные сущности перед удалением
        $data = $this->getEntityData($entity);

        $this->auditLogService->logDelete($entity, $data);
    }

    /**
     * Получить данные сущности для логирования
     */
    private function getEntityData(object $entity): array
    {
        $data = [];

        // Получаем доступные поля сущности
        $methods = get_class_methods($entity);

        foreach ($methods as $method) {
            // Ищем геттеры
            if (str_starts_with($method, 'get') && $method !== 'getId') {
                $field = lcfirst(substr($method, 3));

                // Пропускаем чувствительные данные
                if (\in_array(strtolower($field), ['password', 'token', 'secret', 'apiKey'])) {
                    continue;
                }

                try {
                    $value = $entity->$method();
                    // Сериализуем объекты в массивы
                    if (\is_object($value)) {
                        if (method_exists($value, 'getId')) {
                            $value = $value::class . '#' . $value->getId();
                        } else {
                            $value = $value::class;
                        }
                    }
                    $data[$field] = $value;
                } catch (\Throwable) {
                    // Игнорируем ошибки при получении данных
                }
            }
        }

        return $data;
    }
}
