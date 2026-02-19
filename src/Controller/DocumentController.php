<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\User;
use App\Repository\DocumentRepository;
use App\Repository\TaskRepository;
use App\Service\DocumentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/document')]
class DocumentController extends AbstractController
{
    public function __construct(
        private DocumentService $documentService,
        private DocumentRepository $documentRepository,
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_document_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(#[CurrentUser] User $user, Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);
        $offset = ($page - 1) * $limit;

        $documents = $this->documentService->getDocumentsByUser($user, $limit, $offset);
        $total = $this->documentRepository->count(['createdBy' => $user->getId()]);

        return $this->render('document/index.html.twig', [
            'documents' => $documents,
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $limit),
        ]);
    }

    #[Route('/create', name: 'app_document_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request, #[CurrentUser] User $user): Response
    {
        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);

            $document = $this->documentService->createDocument([
                'title' => $data['title'],
                'content' => $data['content'] ?? '',
                'description' => $data['description'] ?? '',
                'status' => $data['status'] ?? 'draft',
                'content_type' => $data['content_type'] ?? 'text/markdown',
                'parent_id' => $data['parent_id'] ?? null,
                'tags' => $data['tags'] ?? [],
            ], $user);

            return $this->redirectToRoute('app_document_show', ['id' => $document->getId()]);
        }

        return $this->render('document/create.html.twig');
    }

    #[Route('/{id}', name: 'app_document_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function show(Document $document, #[CurrentUser] User $user): Response
    {
        // Check if user has access to this document
        if ($document->getCreatedBy() !== $user->getId() && !\in_array('ROLE_ADMIN', $user->getRoles())) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('document/show.html.twig', [
            'document' => $document,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_document_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function edit(Request $request, Document $document, #[CurrentUser] User $user): Response
    {
        // Check if user has access to edit this document
        if ($document->getCreatedBy() !== $user->getId() && !\in_array('ROLE_ADMIN', $user->getRoles())) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);

            $this->documentService->updateDocument($document, [
                'title' => $data['title'] ?? $document->getTitle(),
                'content' => $data['content'] ?? $document->getContent(),
                'description' => $data['description'] ?? $document->getDescription(),
                'status' => $data['status'] ?? $document->getStatus(),
                'content_type' => $data['content_type'] ?? $document->getContentType(),
                'parent_id' => $data['parent_id'] ?? $document->getParentId(),
                'tags' => $data['tags'] ?? [],
            ]);

            return $this->redirectToRoute('app_document_show', ['id' => $document->getId()]);
        }

        return $this->render('document/edit.html.twig', [
            'document' => $document,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_document_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Request $request, Document $document, #[CurrentUser] User $user): Response
    {
        // Check if user has access to delete this document
        if ($document->getCreatedBy() !== $user->getId() && !\in_array('ROLE_ADMIN', $user->getRoles())) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$document->getId(), $request->request->get('_token'))) {
            $this->documentService->deleteDocument($document);
        }

        return $this->redirectToRoute('app_document_index');
    }

    #[Route('/upload', name: 'app_document_upload', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function upload(Request $request, #[CurrentUser] User $user): Response
    {
        if ($request->isMethod('POST')) {
            /** @var UploadedFile|null $uploadedFile */
            $uploadedFile = $request->files->get('file');

            if (!$uploadedFile) {
                $this->addFlash('error', 'No file uploaded');

                return $this->redirectToRoute('app_document_upload');
            }

            try {
                $document = $this->documentService->uploadDocument($uploadedFile, $user, [
                    'title' => $request->request->get('title', $uploadedFile->getClientOriginalName()),
                    'description' => $request->request->get('description', ''),
                    'status' => $request->request->get('status', 'published'),
                    'parent_id' => $request->request->getInt('parent_id', 0) ?: null,
                    'tags' => explode(',', $request->request->get('tags', '')),
                ]);

                $this->addFlash('success', 'Document uploaded successfully');

                return $this->redirectToRoute('app_document_show', ['id' => $document->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Upload failed: ' . $e->getMessage());
            }
        }

        return $this->render('document/upload.html.twig');
    }

    #[Route('/download/{id}', name: 'app_document_download', methods: ['GET'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function download(Document $document, #[CurrentUser] User $user): BinaryFileResponse
    {
        // Check if user has access to this document
        if ($document->getCreatedBy() !== $user->getId() && !\in_array('ROLE_ADMIN', $user->getRoles())) {
            throw $this->createAccessDeniedException();
        }

        if ($document->getFileName()) {
            // Serve uploaded file
            $filePath = $this->documentService->getFilePath($document);

            if (!file_exists($filePath)) {
                throw $this->createNotFoundException('Document file not found');
            }

            $response = new BinaryFileResponse($filePath);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $document->getFileName(),
            );

            return $response;
        } else {
            // Serve content as file
            $content = $document->getContent();
            $response = new Response($content);
            $response->headers->set('Content-Type', $document->getContentType() ?: 'text/plain');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $document->getTitle() . '.txt"');

            return $response;
        }
    }

    #[Route('/search', name: 'app_document_search', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function search(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $query = $request->query->get('q', '');
        $limit = $request->query->getInt('limit', 10);
        $offset = $request->query->getInt('offset', 0);

        if (empty($query)) {
            return $this->json(['documents' => [], 'total' => 0]);
        }

        $documents = $this->documentService->searchDocuments($query, $user, $limit, $offset);
        $total = \count($this->documentService->searchDocuments($query, $user)); // This is a simplified count

        return $this->json([
            'documents' => array_map(function ($doc) {
                return [
                    'id' => $doc->getId(),
                    'title' => $doc->getTitle(),
                    'description' => $doc->getDescription(),
                    'status' => $doc->getStatus(),
                    'created_at' => $doc->getCreatedAt()->format('Y-m-d H:i:s'),
                    'preview' => $this->documentService->generatePreview($doc, 150),
                ];
            }, $documents),
            'total' => $total,
        ]);
    }

    #[Route('/statistics', name: 'app_document_statistics', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function statistics(#[CurrentUser] User $user): JsonResponse
    {
        $stats = $this->documentService->getDocumentStatistics($user);

        return $this->json($stats);
    }

    #[Route('/recent', name: 'app_document_recent', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function recent(#[CurrentUser] User $user): JsonResponse
    {
        $documents = $this->documentService->getRecentDocuments(10, $user);

        return $this->json(array_map(function ($doc) {
            return [
                'id' => $doc->getId(),
                'title' => $doc->getTitle(),
                'status' => $doc->getStatus(),
                'created_at' => $doc->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $documents));
    }

    #[Route('/duplicate/{id}', name: 'app_document_duplicate', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function duplicate(Request $request, Document $document, #[CurrentUser] User $user): Response
    {
        // Check if user has access to this document
        if ($document->getCreatedBy() !== $user->getId() && !\in_array('ROLE_ADMIN', $user->getRoles())) {
            throw $this->createAccessDeniedException();
        }

        $newTitle = $request->request->get('title', $document->getTitle() . ' (Copy)');

        $newDocument = $this->documentService->duplicateDocument($document, $user, $newTitle);

        return $this->redirectToRoute('app_document_show', ['id' => $newDocument->getId()]);
    }

    #[Route('/publish/{id}', name: 'app_document_publish', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function publish(Document $document, #[CurrentUser] User $user): JsonResponse
    {
        // Check if user has access to this document
        if ($document->getCreatedBy() !== $user->getId() && !\in_array('ROLE_ADMIN', $user->getRoles())) {
            throw $this->createAccessDeniedException();
        }

        $document = $this->documentService->publishDocument($document);

        return $this->json([
            'success' => true,
            'message' => 'Document published successfully',
            'status' => $document->getStatus(),
        ]);
    }

    #[Route('/unpublish/{id}', name: 'app_document_unpublish', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function unpublish(Document $document, #[CurrentUser] User $user): JsonResponse
    {
        // Check if user has access to this document
        if ($document->getCreatedBy() !== $user->getId() && !\in_array('ROLE_ADMIN', $user->getRoles())) {
            throw $this->createAccessDeniedException();
        }

        $document = $this->documentService->unpublishDocument($document);

        return $this->json([
            'success' => true,
            'message' => 'Document unpublished successfully',
            'status' => $document->getStatus(),
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
                'category' => 'project_management',
            ],
            [
                'id' => 2,
                'name' => 'Task Summary Template',
                'description' => 'Template for task summaries',
                'type' => 'summary',
                'category' => 'task_management',
            ],
            [
                'id' => 3,
                'name' => 'Meeting Minutes Template',
                'description' => 'Template for meeting minutes',
                'type' => 'minutes',
                'category' => 'meetings',
            ],
        ];

        return $this->json($templates);
    }

    #[Route('/generate', name: 'app_document_generate', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function generateDocument(Request $request, #[CurrentUser] User $user): JsonResponse
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

        // Optionally save as a new document
        if ($data['save_as_document'] ?? false) {
            $document = $this->documentService->createDocument([
                'title' => $data['title'] ?? $this->generateTemplateName($templateType),
                'content' => $documentContent,
                'description' => $data['description'] ?? 'Auto-generated document',
                'status' => 'draft',
                'content_type' => 'text/plain',
            ], $user);

            return $this->json([
                'success' => true,
                'content' => $documentContent,
                'document_id' => $document->getId(),
                'filename' => $this->generateFilename($templateType),
                'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);
        }

        return $this->json([
            'success' => true,
            'content' => $documentContent,
            'filename' => $this->generateFilename($templateType),
            'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
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
            'total_tasks' => \count($tasks),
            'completed_tasks' => \count(array_filter($tasks, fn ($task) => $task->isCompleted())),
            'pending_tasks' => \count(array_filter($tasks, fn ($task) => $task->isPending())),
            'in_progress_tasks' => \count(array_filter($tasks, fn ($task) => $task->isInProgress())),
            'tasks' => array_map(function ($task) {
                return [
                    'id' => $task->getId(),
                    'title' => $task->getTitle(),
                    'status' => $task->getStatus(),
                    'priority' => $task->getPriority(),
                    'dueDate' => $task->getDueDate()?->format('Y-m-d') ?? null,
                    'assignedUser' => $task->getAssignedUser()?->getFullName() ?? null,
                ];
            }, $tasks),
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

        if (!\in_array($targetFormat, ['pdf', 'docx', 'txt', 'html'])) {
            return $this->json(['error' => 'Invalid target format'], 400);
        }

        // Convert document based on target format
        $convertedContent = $this->convertDocumentContent($documentContent, $targetFormat);

        return $this->json([
            'success' => true,
            'content' => $convertedContent,
            'format' => $targetFormat,
        ]);
    }

    private function generateDocumentContent(string $templateType, ?array $taskData = null): string
    {
        switch ($templateType) {
            case 'report':
                $content = "Project Report\n\n";
                $content .= 'Generated on: ' . date('Y-m-d H:i:s') . "\n\n";

                if ($taskData) {
                    $content .= "Task Details:\n";
                    $content .= '- Title: ' . $taskData['title'] . "\n";
                    $content .= '- Status: ' . $taskData['status'] . "\n";
                    $content .= '- Priority: ' . $taskData['priority'] . "\n";
                    $content .= '- Due Date: ' . $taskData['dueDate'] . "\n";
                    $content .= '- Assigned to: ' . $taskData['assignedUser'] . "\n\n";
                }

                $content .= "Summary: This report provides an overview of the project status.\n";
                $content .= "Recommendations: Continue with current approach.\n";

                break;

            case 'summary':
                $content = "Task Summary\n\n";
                $content .= 'Date: ' . date('Y-m-d') . "\n\n";

                if ($taskData) {
                    $content .= 'Task: ' . $taskData['title'] . "\n";
                    $content .= 'Status: ' . $taskData['status'] . "\n";
                    $content .= 'Description: ' . $taskData['description'] . "\n\n";
                }

                $content .= "Next Steps: Follow up on pending items.\n";

                break;

            case 'minutes':
                $content = "Meeting Minutes\n\n";
                $content .= 'Date: ' . date('Y-m-d') . "\n";
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
                $content .= 'Content generated on: ' . date('Y-m-d H:i:s') . "\n";

                break;
        }

        return $content;
    }

    private function generateTemplateName(string $templateType): string
    {
        switch ($templateType) {
            case 'report':
                return 'Project Report';
            case 'summary':
                return 'Task Summary';
            case 'minutes':
                return 'Meeting Minutes';
            default:
                return 'Generated Document';
        }
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
