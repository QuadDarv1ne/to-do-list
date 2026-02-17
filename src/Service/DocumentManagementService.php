<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;

class DocumentManagementService
{
    /**
     * Get document templates
     */
    public function getTemplates(): array
    {
        return [
            'project_charter' => [
                'name' => 'Устав проекта',
                'description' => 'Официальный документ инициации проекта',
                'category' => 'Управление проектами',
                'fields' => ['project_name', 'objectives', 'scope', 'stakeholders', 'budget']
            ],
            'requirements_doc' => [
                'name' => 'Документ требований',
                'description' => 'Спецификация требований к системе',
                'category' => 'Разработка',
                'fields' => ['functional_requirements', 'non_functional_requirements', 'constraints']
            ],
            'test_plan' => [
                'name' => 'План тестирования',
                'description' => 'Стратегия и план тестирования',
                'category' => 'QA',
                'fields' => ['scope', 'approach', 'resources', 'schedule', 'deliverables']
            ],
            'meeting_minutes' => [
                'name' => 'Протокол встречи',
                'description' => 'Запись обсуждений и решений',
                'category' => 'Общее',
                'fields' => ['date', 'attendees', 'agenda', 'discussions', 'action_items']
            ],
            'status_report' => [
                'name' => 'Отчет о статусе',
                'description' => 'Периодический отчет о прогрессе',
                'category' => 'Отчетность',
                'fields' => ['period', 'accomplishments', 'issues', 'next_steps', 'metrics']
            ],
            'risk_register' => [
                'name' => 'Реестр рисков',
                'description' => 'Документ управления рисками',
                'category' => 'Управление рисками',
                'fields' => ['risk_id', 'description', 'probability', 'impact', 'mitigation']
            ],
            'change_request' => [
                'name' => 'Запрос на изменение',
                'description' => 'Формальный запрос изменений',
                'category' => 'Управление изменениями',
                'fields' => ['change_description', 'justification', 'impact_analysis', 'approval']
            ],
            'lessons_learned' => [
                'name' => 'Извлеченные уроки',
                'description' => 'Документ анализа опыта',
                'category' => 'Управление знаниями',
                'fields' => ['project_name', 'what_went_well', 'what_went_wrong', 'recommendations']
            ]
        ];
    }

    /**
     * Generate document from template
     */
    public function generateDocument(string $templateKey, array $data, User $user): array
    {
        $templates = $this->getTemplates();
        
        if (!isset($templates[$templateKey])) {
            throw new \Exception('Template not found');
        }

        $template = $templates[$templateKey];
        
        return [
            'id' => uniqid(),
            'template' => $templateKey,
            'title' => $data['title'] ?? $template['name'],
            'content' => $this->renderTemplate($template, $data),
            'created_by' => $user->getId(),
            'created_at' => new \DateTime(),
            'version' => 1
        ];
    }

    /**
     * Render template
     */
    private function renderTemplate(array $template, array $data): string
    {
        $content = "# {$template['name']}\n\n";
        
        foreach ($template['fields'] as $field) {
            $value = $data[$field] ?? '';
            $fieldName = ucfirst(str_replace('_', ' ', $field));
            $content .= "## $fieldName\n\n$value\n\n";
        }
        
        return $content;
    }

    /**
     * Generate task report
     */
    public function generateTaskReport(Task $task): string
    {
        $report = "# Отчет по задаче: {$task->getTitle()}\n\n";
        $report .= "**ID:** {$task->getId()}\n";
        $report .= "**Статус:** {$task->getStatus()}\n";
        $report .= "**Приоритет:** {$task->getPriority()}\n";
        $report .= "**Создана:** {$task->getCreatedAt()->format('d.m.Y H:i')}\n\n";
        
        if ($task->getDescription()) {
            $report .= "## Описание\n\n{$task->getDescription()}\n\n";
        }
        
        return $report;
    }

    /**
     * Generate project summary
     */
    public function generateProjectSummary(array $tasks): string
    {
        $total = count($tasks);
        $completed = count(array_filter($tasks, fn($t) => $t->getStatus() === 'completed'));
        
        $summary = "# Сводка по проекту\n\n";
        $summary .= "**Всего задач:** $total\n";
        $summary .= "**Завершено:** $completed\n";
        $summary .= "**Процент завершения:** " . round(($completed / $total) * 100, 2) . "%\n\n";
        
        return $summary;
    }

    /**
     * Generate sprint report
     */
    public function generateSprintReport(array $data): string
    {
        $report = "# Отчет по спринту\n\n";
        $report .= "**Спринт:** {$data['sprint_name']}\n";
        $report .= "**Период:** {$data['start_date']} - {$data['end_date']}\n\n";
        
        $report .= "## Цели спринта\n\n";
        foreach ($data['goals'] as $goal) {
            $report .= "- $goal\n";
        }
        
        $report .= "\n## Выполненные задачи\n\n";
        $report .= "Завершено: {$data['completed_tasks']} из {$data['planned_tasks']}\n\n";
        
        return $report;
    }

    /**
     * Generate invoice
     */
    public function generateInvoice(array $data): string
    {
        $invoice = "# Счет №{$data['invoice_number']}\n\n";
        $invoice .= "**Дата:** {$data['date']}\n";
        $invoice .= "**Клиент:** {$data['client_name']}\n\n";
        
        $invoice .= "## Позиции\n\n";
        $invoice .= "| Описание | Количество | Цена | Сумма |\n";
        $invoice .= "|----------|------------|------|-------|\n";
        
        $total = 0;
        foreach ($data['items'] as $item) {
            $sum = $item['quantity'] * $item['price'];
            $total += $sum;
            $invoice .= "| {$item['description']} | {$item['quantity']} | {$item['price']} | $sum |\n";
        }
        
        $invoice .= "\n**Итого:** $total руб.\n";
        
        return $invoice;
    }

    /**
     * Convert markdown to PDF
     */
    public function convertToPDF(string $markdown): string
    {
        // TODO: Use library like mPDF or TCPDF
        return '';
    }

    /**
     * Convert markdown to DOCX
     */
    public function convertToDOCX(string $markdown): string
    {
        // TODO: Use library like PHPWord
        return '';
    }

    /**
     * Get document versions
     */
    public function getDocumentVersions(int $documentId): array
    {
        // TODO: Get from database
        return [];
    }

    /**
     * Create document version
     */
    public function createVersion(int $documentId, string $content, User $user): array
    {
        // TODO: Save to database
        return [
            'id' => uniqid(),
            'document_id' => $documentId,
            'version' => 2,
            'content' => $content,
            'created_by' => $user->getId(),
            'created_at' => new \DateTime()
        ];
    }

    /**
     * Share document
     */
    public function shareDocument(int $documentId, array $userIds, string $permission = 'view'): void
    {
        // TODO: Save to database
    }

    /**
     * Get shared documents
     */
    public function getSharedDocuments(User $user): array
    {
        // TODO: Get from database
        return [];
    }

    /**
     * Search documents
     */
    public function searchDocuments(string $query, User $user): array
    {
        // TODO: Search in database
        return [];
    }

    /**
     * Get document statistics
     */
    public function getDocumentStats(User $user): array
    {
        return [
            'total_documents' => 0,
            'documents_created' => 0,
            'documents_shared' => 0,
            'most_used_template' => 'status_report',
            'recent_documents' => []
        ];
    }

    /**
     * Generate table of contents
     */
    public function generateTableOfContents(string $markdown): string
    {
        $toc = "# Содержание\n\n";
        
        preg_match_all('/^(#{1,6})\s+(.+)$/m', $markdown, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $level = strlen($match[1]) - 1;
            $title = $match[2];
            $indent = str_repeat('  ', $level);
            $toc .= "$indent- $title\n";
        }
        
        return $toc;
    }

    /**
     * Merge documents
     */
    public function mergeDocuments(array $documentIds): string
    {
        // TODO: Get documents and merge
        return '';
    }

    /**
     * Apply template to task
     */
    public function applyTemplateToTask(Task $task, string $templateKey): string
    {
        $templates = $this->getTemplates();
        
        if (!isset($templates[$templateKey])) {
            throw new \Exception('Template not found');
        }

        $data = [
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus(),
            'priority' => $task->getPriority()
        ];

        return $this->renderTemplate($templates[$templateKey], $data);
    }
}
