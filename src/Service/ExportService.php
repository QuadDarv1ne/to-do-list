<?php

namespace App\Service;

use App\Entity\Task;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * Export tasks to CSV format
     */
    public function exportTasksToCSV(array $tasks): StreamedResponse
    {
        $response = new StreamedResponse();
        $response->setCallback(function () use ($tasks) {
            $handle = fopen('php://output', 'w');
            
            // Add BOM for UTF-8
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // CSV Headers
            fputcsv($handle, [
                'ID',
                'Название',
                'Описание',
                'Статус',
                'Приоритет',
                'Категория',
                'Назначено',
                'Создано',
                'Дедлайн',
                'Обновлено'
            ], ';');
            
            // Data rows
            foreach ($tasks as $task) {
                fputcsv($handle, [
                    $task->getId(),
                    $task->getTitle(),
                    $task->getDescription() ?? '',
                    $this->translateStatus($task->getStatus()),
                    $this->translatePriority($task->getPriority()),
                    $task->getCategory() ? $task->getCategory()->getName() : '',
                    $task->getAssignedUser() ? $task->getAssignedUser()->getFullName() : '',
                    $task->getCreatedAt() ? $task->getCreatedAt()->format('d.m.Y H:i') : '',
                    $task->getDeadline() ? $task->getDeadline()->format('d.m.Y H:i') : '',
                    $task->getUpdatedAt() ? $task->getUpdatedAt()->format('d.m.Y H:i') : ''
                ], ';');
            }
            
            fclose($handle);
        });
        
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="tasks_export_' . date('Y-m-d_H-i-s') . '.csv"');
        
        return $response;
    }
    
    /**
     * Export tasks to Excel-compatible format (HTML table)
     */
    public function exportTasksToExcel(array $tasks): Response
    {
        $html = '
        <html xmlns:x="urn:schemas-microsoft-com:office:excel">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <style>
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #4CAF50; color: white; font-weight: bold; }
                tr:nth-child(even) { background-color: #f2f2f2; }
            </style>
        </head>
        <body>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Описание</th>
                        <th>Статус</th>
                        <th>Приоритет</th>
                        <th>Категория</th>
                        <th>Назначено</th>
                        <th>Создано</th>
                        <th>Дедлайн</th>
                        <th>Обновлено</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($tasks as $task) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($task->getId()) . '</td>';
            $html .= '<td>' . htmlspecialchars($task->getTitle()) . '</td>';
            $html .= '<td>' . htmlspecialchars($task->getDescription() ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($this->translateStatus($task->getStatus())) . '</td>';
            $html .= '<td>' . htmlspecialchars($this->translatePriority($task->getPriority())) . '</td>';
            $html .= '<td>' . htmlspecialchars($task->getCategory() ? $task->getCategory()->getName() : '') . '</td>';
            $html .= '<td>' . htmlspecialchars($task->getAssignedUser() ? $task->getAssignedUser()->getFullName() : '') . '</td>';
            $html .= '<td>' . ($task->getCreatedAt() ? $task->getCreatedAt()->format('d.m.Y H:i') : '') . '</td>';
            $html .= '<td>' . ($task->getDeadline() ? $task->getDeadline()->format('d.m.Y H:i') : '') . '</td>';
            $html .= '<td>' . ($task->getUpdatedAt() ? $task->getUpdatedAt()->format('d.m.Y H:i') : '') . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table></body></html>';
        
        $response = new Response($html);
        $response->headers->set('Content-Type', 'application/vnd.ms-excel; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="tasks_export_' . date('Y-m-d_H-i-s') . '.xls"');
        
        return $response;
    }
    
    private function translateStatus(string $status): string
    {
        return match($status) {
            'pending' => 'В ожидании',
            'in_progress' => 'В процессе',
            'completed' => 'Завершено',
            'cancelled' => 'Отменено',
            default => $status
        };
    }
    
    private function translatePriority(string $priority): string
    {
        return match($priority) {
            'low' => 'Низкий',
            'medium' => 'Средний',
            'high' => 'Высокий',
            'urgent' => 'Срочный',
            default => $priority
        };
    }
}
