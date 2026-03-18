<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Сервис для логирования действий пользователей (Audit Log)
 *
 * Используется для отслеживания критических операций в системе:
 * - Изменения в сущностях (создание, обновление, удаление)
 * - Действия администраторов
 * - Изменения настроек безопасности
 * - Экспорт данных
 */
class AuditLogService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * Получить текущего пользователя
     */
    private function getUser(): ?User
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return null;
        }

        $user = $token->getUser();
        return $user instanceof User ? $user : null;
    }

    /**
     * Логирование действия
     *
     * @param string $entityClass Класс сущности (например, App\Entity\Task)
     * @param int|string $entityId ID сущности
     * @param string $action Действие (create, update, delete, export, login, etc.)
     * @param array $changes Изменения (oldValues и newValues)
     * @param string|null $reason Причина изменения (для административных действий)
     */
    public function log(
        string $entityClass,
        int|string $entityId,
        string $action,
        array $changes = [],
        ?string $reason = null,
    ): AuditLog {
        $user = $this->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $auditLog = new AuditLog();
        $auditLog->setEntityClass($entityClass);
        $auditLog->setEntityId((string) $entityId);
        $auditLog->setAction($action);

        // Изменения
        if (isset($changes['old'])) {
            $auditLog->setOldValues($changes['old']);
        }
        if (isset($changes['new'])) {
            $auditLog->setNewValues($changes['new']);
        }
        if (!empty($changes)) {
            $auditLog->setChanges($changes);
        }

        // Пользователь
        if ($user instanceof User) {
            $auditLog->setUser($user);
            $auditLog->setUserName($user->getFullName());
            $auditLog->setUserEmail($user->getEmail());
        }

        // IP адрес и User Agent
        if ($request) {
            $auditLog->setIpAddress($request->getClientIp());
            $auditLog->setUserAgent($request->headers->get('User-Agent'));
        }

        // Причина (для административных действий)
        if ($reason) {
            $auditLog->setReason($reason);
        }

        $this->em->persist($auditLog);
        $this->em->flush();

        return $auditLog;
    }

    /**
     * Логирование создания сущности
     */
    public function logCreate(object $entity, array $newValues = []): AuditLog
    {
        return $this->log(
            $entity::class,
            $this->getEntityId($entity),
            'create',
            ['new' => $newValues],
        );
    }

    /**
     * Логирование обновления сущности
     */
    public function logUpdate(object $entity, array $oldValues, array $newValues, ?string $reason = null): AuditLog
    {
        return $this->log(
            $entity::class,
            $this->getEntityId($entity),
            'update',
            [
                'old' => $oldValues,
                'new' => $newValues,
            ],
            $reason,
        );
    }

    /**
     * Логирование удаления сущности
     */
    public function logDelete(object $entity, array $oldValues = []): AuditLog
    {
        return $this->log(
            $entity::class,
            $this->getEntityId($entity),
            'delete',
            ['old' => $oldValues],
        );
    }

    /**
     * Логирование входа пользователя
     */
    public function logLogin(User $user): AuditLog
    {
        $request = $this->requestStack->getCurrentRequest();

        return $this->log(
            User::class,
            $user->getId(),
            'login',
            [
                'new' => [
                    'ip' => $request?->getClientIp(),
                    'user_agent' => $request?->headers->get('User-Agent'),
                ],
            ],
        );
    }

    /**
     * Логирование выхода пользователя
     */
    public function logLogout(User $user): AuditLog
    {
        return $this->log(
            User::class,
            $user->getId(),
            'logout',
        );
    }

    /**
     * Логирование экспорта данных
     */
    public function logExport(string $exportType, int $recordsCount): AuditLog
    {
        return $this->log(
            'Export',
            $exportType,
            'export',
            [
                'new' => [
                    'type' => $exportType,
                    'records' => $recordsCount,
                ],
            ],
        );
    }

    /**
     * Логирование изменения настроек
     */
    public function logSettingsChange(string $settingName, mixed $oldValue, mixed $newValue): AuditLog
    {
        return $this->log(
            'Settings',
            $settingName,
            'settings_change',
            [
                'old' => ['value' => $oldValue],
                'new' => ['value' => $newValue],
            ],
        );
    }

    /**
     * Логирование изменения прав доступа
     */
    public function logPermissionChange(User $targetUser, string $oldRole, string $newRole): AuditLog
    {
        return $this->log(
            User::class,
            $targetUser->getId(),
            'permission_change',
            [
                'old' => ['role' => $oldRole],
                'new' => ['role' => $newRole],
            ],
            sprintf('Изменение роли: %s → %s', $oldRole, $newRole),
        );
    }

    /**
     * Получить ID сущности
     */
    private function getEntityId(object $entity): int|string
    {
        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();
            return $id !== null ? $id : 'new';
        }

        return 'unknown';
    }

    /**
     * Массовое логирование (для пакетных операций)
     *
     * @param array<int, array> $operations
     */
    public function logBatch(array $operations): void
    {
        foreach ($operations as $op) {
            $this->log(
                $op['entityClass'],
                $op['entityId'],
                $op['action'],
                $op['changes'] ?? [],
                $op['reason'] ?? null,
            );
        }
    }
}
