<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Filesystem\Filesystem;

class BackupService
{
    private string $backupDir;

    public function __construct(
        private Connection $connection,
        private Filesystem $filesystem,
        string $projectDir,
    ) {
        $this->backupDir = $projectDir . '/var/backups';

        if (!$this->filesystem->exists($this->backupDir)) {
            $this->filesystem->mkdir($this->backupDir);
        }
    }

    /**
     * Создать бэкап (алиас для createFullBackup)
     */
    public function createBackup(): array
    {
        return $this->createFullBackup();
    }

    /**
     * Получить список бэкапов (алиас для listBackups)
     */
    public function getBackups(): array
    {
        return $this->listBackups();
    }

    /**
     * Восстановить из бэкапа (алиас для restore)
     */
    public function restoreFromBackup(string $filename): array
    {
        return $this->restore($filename);
    }

    /**
     * Очистить старые бэкапы (алиас для cleanOldBackups)
     */
    public function cleanupOldBackups(int $keepDays = 30): int
    {
        return $this->cleanOldBackups($keepDays);
    }

    /**
     * Создать полный бэкап базы данных
     */
    public function createFullBackup(): array
    {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "backup_full_{$timestamp}.sql";
        $filepath = $this->backupDir . '/' . $filename;

        try {
            $tables = $this->getAllTables();
            $sql = $this->generateBackupSQL($tables);

            file_put_contents($filepath, $sql);

            // Сжать файл
            $this->compressBackup($filepath);

            return [
                'success' => true,
                'filename' => $filename . '.gz',
                'size' => filesize($filepath . '.gz'),
                'tables' => \count($tables),
                'created_at' => $timestamp,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Создать инкрементальный бэкап (только изменения)
     */
    public function createIncrementalBackup(\DateTime $since): array
    {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "backup_incremental_{$timestamp}.sql";
        $filepath = $this->backupDir . '/' . $filename;

        try {
            $tables = ['task', 'task_history', 'comment', 'notification'];
            $sql = $this->generateIncrementalSQL($tables, $since);

            file_put_contents($filepath, $sql);
            $this->compressBackup($filepath);

            return [
                'success' => true,
                'filename' => $filename . '.gz',
                'size' => filesize($filepath . '.gz'),
                'since' => $since->format('Y-m-d H:i:s'),
                'created_at' => $timestamp,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Получить список всех бэкапов
     */
    public function listBackups(): array
    {
        $files = glob($this->backupDir . '/backup_*.sql.gz');
        $backups = [];

        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'size' => filesize($file),
                'created_at' => date('Y-m-d H:i:s', filemtime($file)),
                'type' => str_contains($file, 'incremental') ? 'incremental' : 'full',
            ];
        }

        usort($backups, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $backups;
    }

    /**
     * Удалить старые бэкапы
     */
    public function cleanOldBackups(int $keepDays = 30): int
    {
        $files = glob($this->backupDir . '/backup_*.sql.gz');
        $deleted = 0;
        $threshold = time() - ($keepDays * 86400);

        foreach ($files as $file) {
            if (filemtime($file) < $threshold) {
                $this->filesystem->remove($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Восстановить из бэкапа
     */
    public function restore(string $filename): array
    {
        $filepath = $this->backupDir . '/' . $filename;

        if (!file_exists($filepath)) {
            return ['success' => false, 'error' => 'Файл не найден'];
        }

        try {
            // Распаковать
            $sqlFile = str_replace('.gz', '', $filepath);
            $this->decompressBackup($filepath);

            // Выполнить SQL
            $sql = file_get_contents($sqlFile);
            $this->connection->executeStatement($sql);

            // Удалить временный файл
            $this->filesystem->remove($sqlFile);

            return ['success' => true, 'message' => 'Данные восстановлены'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Получить все таблицы
     */
    private function getAllTables(): array
    {
        $schemaManager = $this->connection->createSchemaManager();

        return $schemaManager->listTableNames();
    }

    /**
     * Генерировать SQL для полного бэкапа
     */
    private function generateBackupSQL(array $tables): string
    {
        $sql = '-- Backup created at ' . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $sql .= "-- Table: {$table}\n";
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";

            // Структура таблицы
            $createTable = $this->connection->fetchAssociative(
                "SHOW CREATE TABLE `{$table}`",
            );
            $sql .= $createTable['Create Table'] . ";\n\n";

            // Данные таблицы
            $rows = $this->connection->fetchAllAssociative("SELECT * FROM `{$table}`");

            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $sql .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES\n";

                $values = [];
                foreach ($rows as $row) {
                    $escapedValues = array_map(
                        fn ($v) => $v === null ? 'NULL' : $this->connection->quote($v),
                        array_values($row),
                    );
                    $values[] = '(' . implode(', ', $escapedValues) . ')';
                }

                $sql .= implode(",\n", $values) . ";\n\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return $sql;
    }

    /**
     * Генерировать SQL для инкрементального бэкапа
     */
    private function generateIncrementalSQL(array $tables, \DateTime $since): string
    {
        $sql = '-- Incremental backup from ' . $since->format('Y-m-d H:i:s') . "\n\n";

        foreach ($tables as $table) {
            // Проверяем наличие поля updated_at или created_at
            $columns = $this->connection->fetchAllAssociative(
                "SHOW COLUMNS FROM `{$table}`",
            );

            $dateColumn = null;
            foreach ($columns as $column) {
                if (\in_array($column['Field'], ['updated_at', 'created_at'])) {
                    $dateColumn = $column['Field'];

                    break;
                }
            }

            if ($dateColumn) {
                $rows = $this->connection->fetchAllAssociative(
                    "SELECT * FROM `{$table}` WHERE `{$dateColumn}` >= ?",
                    [$since->format('Y-m-d H:i:s')],
                );

                if (!empty($rows)) {
                    $sql .= "-- Table: {$table} (modified records)\n";
                    $columns = array_keys($rows[0]);

                    foreach ($rows as $row) {
                        $escapedValues = array_map(
                            fn ($v) => $v === null ? 'NULL' : $this->connection->quote($v),
                            array_values($row),
                        );

                        $sql .= "REPLACE INTO `{$table}` (`" . implode('`, `', $columns) . '`) ';
                        $sql .= 'VALUES (' . implode(', ', $escapedValues) . ");\n";
                    }

                    $sql .= "\n";
                }
            }
        }

        return $sql;
    }

    /**
     * Сжать бэкап
     */
    private function compressBackup(string $filepath): void
    {
        $gzFile = $filepath . '.gz';
        $fp = gzopen($gzFile, 'w9');
        gzwrite($fp, file_get_contents($filepath));
        gzclose($fp);

        // Удалить несжатый файл
        $this->filesystem->remove($filepath);
    }

    /**
     * Распаковать бэкап
     */
    private function decompressBackup(string $filepath): void
    {
        $sqlFile = str_replace('.gz', '', $filepath);
        $fp = gzopen($filepath, 'r');
        $content = '';

        while (!gzeof($fp)) {
            $content .= gzread($fp, 4096);
        }

        gzclose($fp);
        file_put_contents($sqlFile, $content);
    }

    /**
     * Получить размер всех бэкапов
     */
    public function getTotalBackupSize(): int
    {
        $files = glob($this->backupDir . '/backup_*.sql.gz');
        $totalSize = 0;

        foreach ($files as $file) {
            $totalSize += filesize($file);
        }

        return $totalSize;
    }

    /**
     * Экспортировать данные пользователя
     */
    public function exportUserData(int $userId): string
    {
        $data = [
            'user' => $this->connection->fetchAssociative(
                'SELECT * FROM user WHERE id = ?',
                [$userId],
            ),
            'tasks' => $this->connection->fetchAllAssociative(
                'SELECT * FROM task WHERE assigned_user_id = ?',
                [$userId],
            ),
            'comments' => $this->connection->fetchAllAssociative(
                'SELECT c.* FROM comment c JOIN task t ON c.task_id = t.id WHERE c.user_id = ?',
                [$userId],
            ),
            'notifications' => $this->connection->fetchAllAssociative(
                'SELECT * FROM notification WHERE user_id = ?',
                [$userId],
            ),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
