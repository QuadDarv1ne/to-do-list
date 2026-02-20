<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskCategoryRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class TaskImportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskCategoryRepository $categoryRepository,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * Import tasks from CSV file
     */
    public function importFromCSV(string $filePath, User $creator): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception('File not found');
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception('Cannot open file');
        }

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        // Skip header row
        $header = fgetcsv($handle, 0, ';');
        if (!$header) {
            $header = fgetcsv($handle, 0, ',');
            rewind($handle);
            fgetcsv($handle, 0, ',');
        }

        $lineNumber = 1;

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            if (empty($data) || \count($data) < 2) {
                $data = fgetcsv($handle, 0, ',');
                if (empty($data) || \count($data) < 2) {
                    continue;
                }
            }

            $lineNumber++;

            try {
                $this->importTaskFromRow($data, $creator);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Строка {$lineNumber}: " . $e->getMessage();
            }
        }

        fclose($handle);

        // Flush all changes
        $this->entityManager->flush();

        return $results;
    }

    /**
     * Import single task from CSV row
     */
    private function importTaskFromRow(array $row, User $creator): Task
    {
        // Expected format: Title, Description, Status, Priority, Category, Assigned To, Deadline
        $title = trim($row[0] ?? '');
        $description = trim($row[1] ?? '');
        $status = trim($row[2] ?? 'pending');
        $priority = trim($row[3] ?? 'medium');
        $categoryName = trim($row[4] ?? '');
        $assignedToEmail = trim($row[5] ?? '');
        $deadline = trim($row[6] ?? '');

        if (empty($title)) {
            throw new \Exception('Title is required');
        }

        $task = new Task();
        $task->setTitle($title);
        $task->setDescription($description ?: null);
        $task->setUser($creator);

        // Validate and set status
        $validStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        if (\in_array($status, $validStatuses)) {
            $task->setStatus($status);
        } else {
            $task->setStatus('pending');
        }

        // Validate and set priority
        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        if (\in_array($priority, $validPriorities)) {
            $task->setPriority($priority);
        } else {
            $task->setPriority('medium');
        }

        // Set category
        if (!empty($categoryName)) {
            $category = $this->categoryRepository->findOneBy(['name' => $categoryName]);
            if ($category) {
                $task->setCategory($category);
            }
        }

        // Set assigned user
        if (!empty($assignedToEmail)) {
            $assignedUser = $this->userRepository->findOneBy(['email' => $assignedToEmail]);
            if ($assignedUser) {
                $task->setAssignedUser($assignedUser);
            }
        }

        // Set deadline
        if (!empty($deadline)) {
            try {
                $deadlineDate = new \DateTime($deadline);
                $task->setDeadline($deadlineDate);
            } catch (\Exception $e) {
                // Invalid date format, skip
            }
        }

        $this->entityManager->persist($task);

        return $task;
    }

    /**
     * Generate CSV template
     */
    public function generateTemplate(): string
    {
        $template = "Название;Описание;Статус;Приоритет;Категория;Назначено (email);Дедлайн\n";
        $template .= "Пример задачи;Описание задачи;pending;medium;Продажи;user@example.com;2024-12-31\n";
        $template .= "Срочная задача;Требует немедленного внимания;in_progress;urgent;Поддержка;manager@example.com;2024-01-15\n";

        return $template;
    }

    /**
     * Validate CSV file
     */
    public function validateCSV(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return ['valid' => false, 'error' => 'File not found'];
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['valid' => false, 'error' => 'Cannot open file'];
        }

        // Check header
        $header = fgetcsv($handle, 0, ';');
        if (!$header) {
            $header = fgetcsv($handle, 0, ',');
        }

        if (empty($header) || \count($header) < 2) {
            fclose($handle);

            return ['valid' => false, 'error' => 'Invalid CSV format'];
        }

        // Count rows
        $rowCount = 0;
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            $rowCount++;
        }

        fclose($handle);

        return [
            'valid' => true,
            'row_count' => $rowCount,
            'columns' => \count($header),
        ];
    }

    /**
     * Get import statistics
     * TODO: Реализовать статистику импорта
     * - Создать таблицу import_history (user_id, filename, rows_imported, rows_failed, created_at)
     * - Подсчет общего количества импортов
     * - Средний процент успешности
     * - История последних импортов
     * - График импортов по времени
     */
    public function getImportStatistics(): array
    {
        // This would typically come from a database table tracking imports
        // For now, return placeholder data
        return [
            'total_imports' => 0,
            'total_tasks_imported' => 0,
            'last_import_date' => null,
            'success_rate' => 0,
        ];
    }
}
