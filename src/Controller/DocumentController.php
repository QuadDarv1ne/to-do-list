<?php

namespace App\Controller;

use App\Repository\TaskRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\User;

#[Route('/document')]
class DocumentController extends AbstractController
{
    public function __construct(
        private TaskRepository $taskRepository
    ) {
    }

    #[Route('', name: 'app_document_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->render('document/index.html.twig', [
            'documents' => [], // Placeholder - would load actual documents
        ]);
    }

    #[Route('/templates', name: 'app_document_templates', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getTemplates(): JsonResponse
    {
        $templates = [
            [
                'id' => 1,
                'name' => 'Project Report Template',
                'description' => 'Standard template for project reports',
                'type' => 'report',
                'category' => 'project_management'
            ],
            [
                'id' => 2,
                'name' => 'Task Summary Template',
                'description' => 'Template for task summaries',
                'type' => 'summary',
                'category' => 'task_management'
            ],
            [
                'id' => 3,
                'name' => 'Meeting Minutes Template',
                'description' => 'Template for meeting minutes',
                'type' => 'minutes',
                'category' => 'meetings'
            ]
        ];

        return $this->json($templates);
    }

    #[Route('/generate', name: 'app_document_generate', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function generateDocument(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $templateType = $data['template_type'] ?? '';
        $taskId = $data['task_id'] ?? null;
        
        if (!$templateType) {
            return $this->json(['error' => 'Template type is required'], 400);
        }

        // Fetch task data if taskId is provided
        $taskData = null;
        if ($taskId) {
            $task = $this->taskRepository->find($taskId);
            if ($task) {
                $taskData = [
                    'id' => $task->getId(),
                    'title' => $task->getTitle(),
                    'description' => $task->getDescription(),
                    'status' => $task->getStatus(),
                    'priority' => $task->getPriority(),
                    'dueDate' => $task->getDueDate()?->format('Y-m-d') ?? null,
                    'createdAt' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
                    'assignedUser' => $task->getAssignedUser()?->getFullName() ?? null,
                ];
            }
        }

        // Generate document content based on template type
        $documentContent = $this->generateDocumentContent($templateType, $taskData);
        
        return $this->json([
            'success' => true,
            'content' => $documentContent,
            'filename' => $this->generateFilename($templateType),
            'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ]);
    }

    #[Route('/report', name: 'app_document_report', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function generateReport(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $reportType = $data['report_type'] ?? 'summary';
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;
        $userId = $data['user_id'] ?? null;
        
        // Get tasks based on criteria
        $criteria = [];
        if ($startDate && $endDate) {
            $criteria['date_range'] = [$startDate, $endDate];
        }
        if ($userId) {
            $criteria['user_id'] = $userId;
        }
        
        $tasks = $this->taskRepository->findWithCriteria($criteria);
        
        $reportData = [
            'report_type' => $reportType,
            'period_start' => $startDate,
            'period_end' => $endDate,
            'total_tasks' => count($tasks),
            'completed_tasks' => count(array_filter($tasks, fn($task) => $task->isCompleted())),
            'pending_tasks' => count(array_filter($tasks, fn($task) => $task->isPending())),
            'in_progress_tasks' => count(array_filter($tasks, fn($task) => $task->isInProgress())),
            'tasks' => array_map(function($task) {
                return [
                    'id' => $task->getId(),
                    'title' => $task->getTitle(),
                    'status' => $task->getStatus(),
                    'priority' => $task->getPriority(),
                    'dueDate' => $task->getDueDate()?->format('Y-m-d') ?? null,
                    'assignedUser' => $task->getAssignedUser()?->getFullName() ?? null,
                ];
            }, $tasks)
        ];
        
        return $this->json($reportData);
    }

    #[Route('/convert', name: 'app_document_convert', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function convertDocument(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $documentContent = $data['content'] ?? '';
        $targetFormat = $data['format'] ?? 'pdf';
        
        if (!$documentContent) {
            return $this->json(['error' => 'Document content is required'], 400);
        }

        if (!in_array($targetFormat, ['pdf', 'docx', 'txt', 'html'])) {
            return $this->json(['error' => 'Invalid target format'], 400);
        }

        // Convert document based on target format
        $convertedContent = $this->convertDocumentContent($documentContent, $targetFormat);
        
        return $this->json([
            'success' => true,
            'content' => $convertedContent,
            'format' => $targetFormat
        ]);
    }

    private function generateDocumentContent(string $templateType, ?array $taskData = null): string
    {
        switch ($templateType) {
            case 'report':
                $content = "Project Report\n\n";
                $content .= "Generated on: " . date('Y-m-d H:i:s') . "\n\n";
                
                if ($taskData) {
                    $content .= "Task Details:\n";
                    $content .= "- Title: " . $taskData['title'] . "\n";
                    $content .= "- Status: " . $taskData['status'] . "\n";
                    $content .= "- Priority: " . $taskData['priority'] . "\n";
                    $content .= "- Due Date: " . $taskData['dueDate'] . "\n";
                    $content .= "- Assigned to: " . $taskData['assignedUser'] . "\n\n";
                }
                
                $content .= "Summary: This report provides an overview of the project status.\n";
                $content .= "Recommendations: Continue with current approach.\n";
                break;
                
            case 'summary':
                $content = "Task Summary\n\n";
                $content .= "Date: " . date('Y-m-d') . "\n\n";
                
                if ($taskData) {
                    $content .= "Task: " . $taskData['title'] . "\n";
                    $content .= "Status: " . $taskData['status'] . "\n";
                    $content .= "Description: " . $taskData['description'] . "\n\n";
                }
                
                $content .= "Next Steps: Follow up on pending items.\n";
                break;
                
            case 'minutes':
                $content = "Meeting Minutes\n\n";
                $content .= "Date: " . date('Y-m-d') . "\n";
                $content .= "Attendees: [List attendees]\n\n";
                $content .= "Agenda Items:\n";
                $content .= "1. [Item 1]\n";
                $content .= "2. [Item 2]\n";
                $content .= "3. [Item 3]\n\n";
                $content .= "Action Items:\n";
                $content .= "- [Action 1]\n";
                $content .= "- [Action 2]\n";
                break;
                
            default:
                $content = "Generated Document\n\n";
                $content .= "Content generated on: " . date('Y-m-d H:i:s') . "\n";
                break;
        }
        
        return $content;
    }

    private function generateFilename(string $templateType): string
    {
        $timestamp = date('Y-m-d_H-i-s');
        $extension = '.docx';
        
        switch ($templateType) {
            case 'report':
                return "Project_Report_{$timestamp}{$extension}";
            case 'summary':
                return "Task_Summary_{$timestamp}{$extension}";
            case 'minutes':
                return "Meeting_Minutes_{$timestamp}{$extension}";
            default:
                return "Document_{$timestamp}{$extension}";
        }
    }

    private function convertDocumentContent(string $content, string $format): string
    {
        // In a real implementation, this would convert the content to the target format
        // For now, we'll just return the content with basic formatting
        
        switch ($format) {
            case 'pdf':
                // In a real implementation, we'd use a PDF library like TCPDF or DomPDF
                return "[PDF Conversion of: {$content}]";
                
            case 'docx':
                // In a real implementation, we'd use a library like PHPWord
                return "[DOCX Conversion of: {$content}]";
                
            case 'txt':
                return strip_tags($content);
                
            case 'html':
                return nl2br(htmlspecialchars($content));
                
            default:
                return $content;
        }
    }
}