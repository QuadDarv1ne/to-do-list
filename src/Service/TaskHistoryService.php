<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;

class TaskHistoryService
{
    /**
     * Record task change
     */
    public function recordChange(Task $task, string $field, $oldValue, $newValue, User $user): void
    {
        $change = [
            'task_id' => $task->getId(),
            'field' => $field,
            'old_value' => $this->serializeValue($oldValue),
            'new_value' => $this->serializeValue($newValue),
            'changed_by' => $user->getId(),
            'changed_at' => new \DateTime()
        ];

        // TODO: Save to database
    }

    /**
     * Get task history
     */
    public function getHistory(Task $task): array
    {
        // TODO: Get from database
        return [];
    }

    /**
     * Get field history
     */
    public function getFieldHistory(Task $task, string $field): array
    {
        // TODO: Get from database
        return [];
    }

    /**
     * Revert to previous version
     */
    public function revertToVersion(Task $task, int $versionId): bool
    {
        // TODO: Implement version revert
        return false;
    }

    /**
     * Compare versions
     */
    public function compareVersions(int $version1, int $version2): array
    {
        // TODO: Implement version comparison
        return [];
    }

    /**
     * Get change statistics
     */
    public function getStatistics(Task $task): array
    {
        return [
            'total_changes' => 0,
            'most_changed_field' => null,
            'last_changed_at' => null,
            'changed_by_users' => []
        ];
    }

    /**
     * Serialize value for storage
     */
    private function serializeValue($value): string
    {
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_object($value)) {
            return get_class($value) . '#' . $value->getId();
        }

        return (string)$value;
    }

    /**
     * Get recent changes
     */
    public function getRecentChanges(int $limit = 50): array
    {
        // TODO: Get from database
        return [];
    }

    /**
     * Get user's change history
     */
    public function getUserHistory(User $user, int $limit = 50): array
    {
        // TODO: Get from database
        return [];
    }
}
