<?php

namespace App\EventListener;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
class AuditLogSubscriber implements EventSubscriber
{
    private array $pendingAuditLogs = [];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [Events::postPersist, Events::postUpdate, Events::postFlush];
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if ($entity instanceof AuditLog) {
            return;
        }

        $entityManager = $args->getObjectManager();
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $auditLog = new AuditLog();
        $auditLog->setEntityClass(get_class($entity));
        $auditLog->setEntityId((string) $this->getEntityId($entityManager, $entity));
        $auditLog->setAction('CREATE');
        $auditLog->setNewValues($this->getEntityData($entity));
        
        if ($user) {
            $auditLog->setUser($user);
            $auditLog->setUserName($user->getUserIdentifier());
            if (method_exists($user, 'getEmail')) {
                $auditLog->setUserEmail($user->getEmail());
            }
        }

        if ($request) {
            $auditLog->setIpAddress($request->getClientIp());
            $auditLog->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->pendingAuditLogs[] = $auditLog;
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if ($entity instanceof AuditLog) {
            return;
        }

        $entityManager = $args->getObjectManager();
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        $request = $this->requestStack->getCurrentRequest();
        $unitOfWork = $entityManager->getUnitOfWork();
        $changeSet = $unitOfWork->getEntityChangeSet($entity);

        if (empty($changeSet)) {
            return;
        }

        $auditLog = new AuditLog();
        $auditLog->setEntityClass(get_class($entity));
        $auditLog->setEntityId((string) $this->getEntityId($entityManager, $entity));
        $auditLog->setAction('UPDATE');
        $auditLog->setChanges($this->formatChanges($changeSet));
        $auditLog->setOldValues($this->getOldValues($changeSet));
        $auditLog->setNewValues($this->getNewValues($changeSet));
        
        if ($user) {
            $auditLog->setUser($user);
            $auditLog->setUserName($user->getUserIdentifier());
            if (method_exists($user, 'getEmail')) {
                $auditLog->setUserEmail($user->getEmail());
            }
        }

        if ($request) {
            $auditLog->setIpAddress($request->getClientIp());
            $auditLog->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->pendingAuditLogs[] = $auditLog;
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();

        foreach ($this->pendingAuditLogs as $auditLog) {
            $entityManager->persist($auditLog);
        }

        if (!empty($this->pendingAuditLogs)) {
            $entityManager->flush();
        }

        $this->pendingAuditLogs = [];
    }

    private function getEntityId($entityManager, object $entity): mixed
    {
        $metadata = $entityManager->getClassMetadata(get_class($entity));
        $identifiers = $metadata->getIdentifierValues($entity);
        
        return reset($identifiers) ?: null;
    }

    private function getEntityData(object $entity): array
    {
        $data = [];
        $reflection = new \ReflectionClass($entity);
        
        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($entity);
            
            if (!$value instanceof \DateTimeInterface && !is_object($value)) {
                $data[$property->getName()] = $value;
            }
        }
        
        return $data;
    }

    private function formatChanges(array $changeSet): array
    {
        $changes = [];
        
        foreach ($changeSet as $field => $values) {
            $changes[$field] = [
                'old' => $this->normalizeValue($values[0]),
                'new' => $this->normalizeValue($values[1]),
            ];
        }
        
        return $changes;
    }

    private function getOldValues(array $changeSet): array
    {
        $oldValues = [];
        
        foreach ($changeSet as $field => $values) {
            $oldValues[$field] = $this->normalizeValue($values[0]);
        }
        
        return $oldValues;
    }

    private function getNewValues(array $changeSet): array
    {
        $newValues = [];
        
        foreach ($changeSet as $field => $values) {
            $newValues[$field] = $this->normalizeValue($values[1]);
        }
        
        return $newValues;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }
        
        if (is_object($value)) {
            return get_class($value);
        }
        
        return $value;
    }
}
