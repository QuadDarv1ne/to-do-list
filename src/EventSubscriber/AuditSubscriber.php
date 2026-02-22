<?php

namespace App\EventSubscriber;

use App\Service\AuditService;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

class AuditSubscriber implements EventSubscriber
{
    public function __construct(
        private AuditService $auditService
    ) {}

    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush,
        ];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        // Отслеживаем создание
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->auditService->logCreate(
                $this->getEntityType($entity),
                $this->getEntityId($entity),
                $this->getEntityData($entity)
            );
        }

        // Отслеживаем обновление
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->auditService->logUpdate(
                $this->getEntityType($entity),
                $this->getEntityId($entity),
                $this->getEntityChanges($entity, $uow)
            );
        }

        // Отслеживаем удаление
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->auditService->logDelete(
                $this->getEntityType($entity),
                $this->getEntityId($entity)
            );
        }

        // Сброс буфера
        $this->auditService->flush();
    }

    private function getEntityType(object $entity): string
    {
        return (new \ReflectionClass($entity))->getShortName();
    }

    private function getEntityId(object $entity): int
    {
        // Пробуем разные методы получения ID
        if (method_exists($entity, 'getId')) {
            return $entity->getId() ?? 0;
        }

        return 0;
    }

    private function getEntityData(object $entity): array
    {
        $data = [];
        
        // Получаем публичные свойства
        $reflection = new \ReflectionClass($entity);
        foreach ($reflection->getProperties() as $property) {
            if (!$property->isPublic()) {
                $property->setAccessible(true);
            }
            
            $value = $property->getValue($entity);
            
            // Исключаем sensitive данные
            if (in_array($property->getName(), ['password', 'token', 'secret'])) {
                $data[$property->getName()] = '***HIDDEN***';
                continue;
            }
            
            // Сериализуем объекты
            if (is_object($value)) {
                if (method_exists($value, '__toString')) {
                    $data[$property->getName()] = (string) $value;
                }
            } else {
                $data[$property->getName()] = $value;
            }
        }

        return $data;
    }

    private function getEntityChanges(object $entity, $uow): array
    {
        $changes = $uow->getEntityChangeSet($entity);
        return array_keys($changes);
    }
}
