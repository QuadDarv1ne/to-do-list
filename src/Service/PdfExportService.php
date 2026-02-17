<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;

class PdfExportService
{
    /**
     * Generate HTML for PDF export
     * Note: In production, use a library like TCPDF, mPDF, or Dompdf
     */
    public function generateTasksPdf(array $tasks, User $user): string
    {
        $html = $this->getHtmlHeader();
        $html .= $this->getTasksTable($tasks, $user);
        $html .= $this->getHtmlFooter();
        
        return $html;
    }
    
    /**
     * Generate productivity report PDF
     */
    public function generateProductivityReportPdf(array $reportData, User $user): string
    {
        $html = $this->getHtmlHeader();
        $html .= '<h1 style="text-align: center; color: #333;">Отчет о продуктивности</h1>';
        $html .= '<p style="text-align: center; color: #666;">Пользователь: ' . htmlspecialchars($user->getFullName()) . '</p>';
        $html .= '<p style="text-align: center; color: #666;">Дата: ' . date('d.m.Y H:i') . '</p>';
        $html .= '<hr>';
        
        // Summary section
        $html .= '<h2>Сводка</h2>';
        $html .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
        $html .= '<tr>';
        $html .= '<td style="padding: 10px; border: 1px solid #ddd;"><strong>Всего задач:</strong></td>';
        $html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . $reportData['summary']['total_tasks'] . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="padding: 10px; border: 1px solid #ddd;"><strong>Завершено:</strong></td>';
        $html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . $reportData['summary']['completed'] . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="padding: 10px; border: 1px solid #ddd;"><strong>Процент завершения:</strong></td>';
        $html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . $reportData['summary']['completion_rate'] . '%</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="padding: 10px; border: 1px solid #ddd;"><strong>Среднее время выполнения:</strong></td>';
        $html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . $reportData['summary']['avg_completion_time_days'] . ' дней</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="padding: 10px; border: 1px solid #ddd;"><strong>Оценка продуктивности:</strong></td>';
        $html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . $reportData['productivity_score'] . ' / 100</td>';
        $html .= '</tr>';
        $html .= '</table>';
        
        $html .= $this->getHtmlFooter();
        
        return $html;
    }
    
    /**
     * Get HTML header
     */
    private function getHtmlHeader(): string
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Экспорт PDF</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    line-height: 1.6;
                    color: #333;
                    margin: 20px;
                }
                h1 {
                    color: #007bff;
                    border-bottom: 2px solid #007bff;
                    padding-bottom: 10px;
                }
                h2 {
                    color: #495057;
                    margin-top: 20px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                th {
                    background-color: #007bff;
                    color: white;
                    padding: 10px;
                    text-align: left;
                    font-weight: bold;
                }
                td {
                    padding: 8px;
                    border: 1px solid #ddd;
                }
                tr:nth-child(even) {
                    background-color: #f8f9fa;
                }
                .badge {
                    display: inline-block;
                    padding: 3px 8px;
                    border-radius: 3px;
                    font-size: 10px;
                    font-weight: bold;
                }
                .badge-success { background-color: #28a745; color: white; }
                .badge-warning { background-color: #ffc107; color: #333; }
                .badge-danger { background-color: #dc3545; color: white; }
                .badge-info { background-color: #17a2b8; color: white; }
                .badge-secondary { background-color: #6c757d; color: white; }
                .footer {
                    margin-top: 30px;
                    padding-top: 10px;
                    border-top: 1px solid #ddd;
                    text-align: center;
                    color: #666;
                    font-size: 10px;
                }
            </style>
        </head>
        <body>';
    }
    
    /**
     * Get HTML footer
     */
    private function getHtmlFooter(): string
    {
        return '
            <div class="footer">
                <p>CRM система анализа продаж - ООО «Дальневосточный фермер»</p>
                <p>Сгенерировано: ' . date('d.m.Y H:i:s') . '</p>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Get tasks table HTML
     */
    private function getTasksTable(array $tasks, User $user): string
    {
        $html = '<h1>Список задач</h1>';
        $html .= '<p>Пользователь: ' . htmlspecialchars($user->getFullName()) . '</p>';
        $html .= '<p>Всего задач: ' . count($tasks) . '</p>';
        $html .= '<hr>';
        
        $html .= '<table>';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>ID</th>';
        $html .= '<th>Название</th>';
        $html .= '<th>Статус</th>';
        $html .= '<th>Приоритет</th>';
        $html .= '<th>Категория</th>';
        $html .= '<th>Дедлайн</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        foreach ($tasks as $task) {
            $html .= '<tr>';
            $html .= '<td>' . $task->getId() . '</td>';
            $html .= '<td>' . htmlspecialchars($task->getTitle()) . '</td>';
            $html .= '<td>' . $this->getStatusBadge($task->getStatus()) . '</td>';
            $html .= '<td>' . $this->getPriorityBadge($task->getPriority()) . '</td>';
            $html .= '<td>' . ($task->getCategory() ? htmlspecialchars($task->getCategory()->getName()) : '-') . '</td>';
            $html .= '<td>' . ($task->getDeadline() ? $task->getDeadline()->format('d.m.Y') : '-') . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        
        return $html;
    }
    
    /**
     * Get status badge HTML
     */
    private function getStatusBadge(string $status): string
    {
        $class = match($status) {
            'completed' => 'badge-success',
            'in_progress' => 'badge-warning',
            'pending' => 'badge-secondary',
            'cancelled' => 'badge-danger',
            default => 'badge-info'
        };
        
        $label = match($status) {
            'completed' => 'Завершено',
            'in_progress' => 'В процессе',
            'pending' => 'В ожидании',
            'cancelled' => 'Отменено',
            default => $status
        };
        
        return '<span class="badge ' . $class . '">' . $label . '</span>';
    }
    
    /**
     * Get priority badge HTML
     */
    private function getPriorityBadge(string $priority): string
    {
        $class = match($priority) {
            'urgent' => 'badge-danger',
            'high' => 'badge-warning',
            'medium' => 'badge-info',
            'low' => 'badge-secondary',
            default => 'badge-secondary'
        };
        
        $label = match($priority) {
            'urgent' => 'Срочный',
            'high' => 'Высокий',
            'medium' => 'Средний',
            'low' => 'Низкий',
            default => $priority
        };
        
        return '<span class="badge ' . $class . '">' . $label . '</span>';
    }
    
    /**
     * Convert HTML to PDF (placeholder for actual PDF generation)
     * In production, use: TCPDF, mPDF, Dompdf, or wkhtmltopdf
     */
    public function htmlToPdf(string $html): string
    {
        // This is a placeholder
        // In production, integrate with a PDF library:
        
        // Example with Dompdf:
        // $dompdf = new \Dompdf\Dompdf();
        // $dompdf->loadHtml($html);
        // $dompdf->setPaper('A4', 'portrait');
        // $dompdf->render();
        // return $dompdf->output();
        
        return $html; // For now, return HTML
    }
}
