<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;

class TaskVersioningService
{
    /**
     * Create version snapshot
     */
    public function createSnapshot(Task $task, User $user, string $reason = ''): array
    {
        $snapshot = [
            'id' => uniqid(),
            'task_id' => $task->getId(),
            'version' => $this->getNextVersion($task),
            'data' => $this->serializeTask($task),
            'created_by' => $user->getId(),
            'created_at' => new \DateTime(),
            'reason' => $reason
        ];

        // TODO: Save to database

        return $snapshot;
    }

    /**
     * Get task versions
     */
    public function getVersions(Task $task): array
    {
        // TODO: Get from database
        return [];
    }

    /**
     * Get specific version
     */
    public function getVersion(int $versionId): ?array
    {
        // TODO: Get from database
        return null;
    }

    /**
     * Restore version
     */
    public function restoreVersion(Task $task, int $versionId, User $user): Task
    {
        $version = $this->getVersion($versionId);
        if (!$version) {
            throw new \Exception('Version not found');
        }

        // Create snapshot before restore
        $this->createSnapshot($task, $user, "Restore to version {$version['version']}");

        // Restore data
        $this->deserializeTask($task, $version['data']);

        // TODO: Save to database

        return $task;
    }

    /**
     * Compare versions
     */
    public function compareVersions(int $version1Id, int $version2Id): array
    {
        $v1 = $this->getVersion($version1Id);
        $v2 = $this->getVersion($version2Id);

        if (!$v1 || !$v2) {
            throw new \Exception('Version not found');
        }

        $changes = [];

        foreach ($v1['data'] as $field => $value1) {
            $value2 = $v2['data'][$field] ?? null;
            
            if ($value1 !== $value2) {
                $changes[$field] = [
                    'old' => $value1,
                    'new' => $value2,
                    'changed' => true
                ];
            }
        }

        return [
            'version1' => $v1,
            'version2' => $v2,
            'changes' => $changes,
            'total_changes' => count($changes)
        ];
    }

    /**
     * Get version diff
     */
    public function getVersionDiff(int $versionId): array
    {
        $version = $this->getVersion($versionId);
        if (!$version) {
            throw new \Exception('Version not found');
        }

        $previousVersion = $this->getPreviousVersion($version['task_id'], $version['version']);
        
        if (!$previousVersion) {
            return [
                'changes' => [],
                'is_first_version' => true
            ];
        }

        return $this->compareVersions($previousVersion['id'], $versionId);
    }

    /**
     * Get previous version
     */
    private function getPreviousVersion(int $taskId, int $currentVersion): ?array
    {
        // TODO: Get from database
        return null;
    }

    /**
     * Get next version number
     */
    private function getNextVersion(Task $task): int
    {
        $versions = $this->getVersions($task);
        return count($versions) + 1;
    }

    /**
     * Serialize task
     */
    private function serializeTask(Task $task): array
    {
        return [
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus(),
            'priority' => $task->getPriority(),
            'deadline' => $task->getDeadline()?->format('Y-m-d H:i:s'),
            'category_id' => $task->getCategory()?->getId(),
            'assigned_user_id' => $task->getAssignedUser()?->getId()
        ];
    }

    /**
     * Deserialize task
     */
    private function deserializeTask(Task $task, array $data): void
    {
        $task->setTitle($data['title']);
        $task->setDescription($data['description']);
        $task->setStatus($data['status']);
        $task->setPriority($data['priority']);
        
        if ($data['deadline']) {
            $task->setDeadline(new \DateTime($data['deadline']));
        }

        // TODO: Set category and assigned user
    }

    /**
     * Auto-create version on significant changes
     */
    public function autoVersion(Task $task, User $user, array $changes): void
    {
        $significantFields = ['status', 'priority', 'deadline', 'assigned_user_id'];
        
        $hasSignificantChange = false;
        foreach ($changes as $field => $change) {
            if (in_array($field, $significantFields)) {
                $hasSignificantChange = true;
                break;
            }
        }

        if ($hasSignificantChange) {
            $this->createSnapshot($task, $user, 'Auto-version on significant change');
        }
    }

    /**
     * Get version timeline
     */
    public function getVersionTimeline(Task $task): array
    {
        $versions = $this->getVersions($task);
        $timeline = [];

        foreach ($versions as $version) {
            $diff = $this->getVersionDiff($version['id']);
            
            $timeline[] = [
                'version' => $version,
                'changes' => $diff['changes'],
                'change_count' => count($diff['changes'])
            ];
        }

        return $timeline;
    }

    /**
     * Export version history
     */
    public function exportVersionHistory(Task $task, string $format = 'json'): string
    {
        $timeline = $this->getVersionTimeline($task);
        
        return match($format) {
            'json' => json_encode($timeline, JSON_PRETTY_PRINT),
            'csv' => $this->exportToCSV($timeline),
            default => ''
        };
    }

    /**
     * Export to CSV
     */
    private function exportToCSV(array $timeline): string
    {
        $csv = "Версия,Дата,Автор,Изменений\n";
        
        foreach ($timeline as $item) {
            $version = $item['version'];
            $csv .= sprintf(
                "%d,%s,%s,%d\n",
                $version['version'],
                $version['created_at']->format('Y-m-d H:i:s'),
                $version['created_by'],
                $item['change_count']
            );
        }

        return $csv;
    }

    /**
     * Clean old versions
     */
    public function cleanOldVersions(Task $task, int $keepLast = 10): int
    {
        $versions = $this->getVersions($task);
        
        if (count($versions) <= $keepLast) {
            return 0;
        }

        $toDelete = array_slice($versions, 0, -$keepLast);
        
        // TODO: Delete from database
        
        return count($toDelete);
    }

    /**
     * Get version statistics
     */
    public function getVersionStats(Task $task): array
    {
        $versions = $this->getVersions($task);
        
        return [
            'total_versions' => count($versions),
            'first_version' => $versions[0] ?? null,
            'latest_version' => end($versions) ?: null,
            'most_active_user' => null, // TODO: Calculate
            'average_changes_per_version' => 0 // TODO: Calculate
        ];
    }

    /**
     * Check if can restore
     */
    public function canRestore(Task $task, int $versionId, User $user): bool
    {
        // Check permissions
        // Check if version exists
        // Check if task is not locked
        
        return true;
    }

    /**
     * Get restore preview
     */
    public function getRestorePreview(Task $task, int $versionId): array
    {
        $version = $this->getVersion($versionId);
        if (!$version) {
            throw new \Exception('Version not found');
        }

        $currentData = $this->serializeTask($task);
        $versionData = $version['data'];

        $changes = [];
        foreach ($versionData as $field => $newValue) {
            $currentValue = $currentData[$field] ?? null;
            
            if ($currentValue !== $newValue) {
                $changes[$field] = [
                    'current' => $currentValue,
                    'will_be' => $newValue
                ];
            }
        }

        return [
            'version' => $version,
            'changes' => $changes,
            'warning' => count($changes) > 0 ? 'Эти изменения будут применены' : 'Нет изменений'
        ];
    }
}
