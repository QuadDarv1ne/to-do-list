<?php

namespace App\Controller;

use App\Entity\Task;
use App\Repository\TaskRepository;
use App\Service\DocumentManagementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/documents')]
class DocumentController extends AbstractController
{
    public function __construct(
        private DocumentManagementService $documentManagementService,
        private TaskRepository $taskRepository
    ) {}

    #[Route('/', name: 'app_document_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        // For now, just render a basic template
        return $this->render('document/index.html.twig');
    }

    #[Route('/templates', name: 'app_document_templates', methods: ['GET'])]
    public function templates(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $templates = $this->documentManagementService->getTemplates();
        
        return $this->json([
            'templates' => $templates
        ]);
    }

    #[Route('/generate', name: 'app_document_generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $data = json_decode($request->getContent(), true);
        $templateKey = $data['template'] ?? '';
        $documentData = $data['data'] ?? [];
        
        if (empty($templateKey)) {
            return $this->json([
                'error' => 'Template key is required'
            ], 400);
        }
        
        $document = $this->documentManagementService->generateDocument($templateKey, $documentData, $this->getUser());
        
        return $this->json([
            'document' => $document
        ]);
    }

    #[Route('/task-report/{id}', name: 'app_document_task_report', methods: ['GET'])]
    public function taskReport(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        // Get task by ID
        $task = $this->taskRepository->find($id);
        
        if (!$task) {
            return $this->json(['error' => 'Task not found'], 404);
        }
        
        // Check access to task
        if ($task->getUser() !== $this->getUser() && $task->getAssignedUser() !== $this->getUser()) {
            return $this->json(['error' => 'Access denied'], 403);
        }
        
        $report = $this->documentManagementService->generateTaskReport($task);
        
        $response = new Response($report);
        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Content-Disposition', "attachment; filename=task_$id.txt");
        
        return $response;
    }

    #[Route('/sprint-report', name: 'app_document_sprint_report', methods: ['POST'])]
    public function sprintReport(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $data = json_decode($request->getContent(), true);
        
        if (empty($data['sprint_name']) || empty($data['start_date']) || empty($data['end_date'])) {
            return $this->json([
                'error' => 'Sprint name, start date, and end date are required'
            ], 400);
        }
        
        $report = $this->documentManagementService->generateSprintReport($data);
        
        return $this->json([
            'report' => $report
        ]);
    }

    #[Route('/invoice', name: 'app_document_invoice', methods: ['POST'])]
    public function invoice(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');
        
        $data = json_decode($request->getContent(), true);
        
        if (empty($data['invoice_number']) || empty($data['client_name']) || empty($data['items'])) {
            return $this->json([
                'error' => 'Invoice number, client name, and items are required'
            ], 400);
        }
        
        $invoice = $this->documentManagementService->generateInvoice($data);
        
        return $this->json([
            'invoice' => $invoice
        ]);
    }

    #[Route('/convert-pdf', name: 'app_document_convert_pdf', methods: ['POST'])]
    public function convertToPdf(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $data = json_decode($request->getContent(), true);
        $markdown = $data['markdown'] ?? '';
        
        if (empty($markdown)) {
            return $this->json([
                'error' => 'Markdown content is required'
            ], 400);
        }
        
        $pdf = $this->documentManagementService->convertToPDF($markdown);
        
        return $this->json([
            'pdf_url' => $pdf
        ]);
    }

    #[Route('/convert-docx', name: 'app_document_convert_docx', methods: ['POST'])]
    public function convertToDocx(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $data = json_decode($request->getContent(), true);
        $markdown = $data['markdown'] ?? '';
        
        if (empty($markdown)) {
            return $this->json([
                'error' => 'Markdown content is required'
            ], 400);
        }
        
        $docx = $this->documentManagementService->convertToDOCX($markdown);
        
        return $this->json([
            'docx_url' => $docx
        ]);
    }

    #[Route('/versions/{id}', name: 'app_document_versions', methods: ['GET'])]
    public function versions(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $versions = $this->documentManagementService->getDocumentVersions($id);
        
        return $this->json([
            'versions' => $versions
        ]);
    }

    #[Route('/version/create', name: 'app_document_version_create', methods: ['POST'])]
    public function createVersion(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $data = json_decode($request->getContent(), true);
        $documentId = $data['document_id'] ?? 0;
        $content = $data['content'] ?? '';
        
        if (!$documentId || empty($content)) {
            return $this->json([
                'error' => 'Document ID and content are required'
            ], 400);
        }
        
        $version = $this->documentManagementService->createVersion($documentId, $content, $this->getUser());
        
        return $this->json([
            'version' => $version
        ]);
    }

    #[Route('/search', name: 'app_document_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $query = $request->query->get('q', '');
        
        if (empty($query)) {
            return $this->json([
                'error' => 'Search query is required'
            ], 400);
        }
        
        $results = $this->documentManagementService->searchDocuments($query, $this->getUser());
        
        return $this->json([
            'results' => $results
        ]);
    }

    #[Route('/stats', name: 'app_document_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $stats = $this->documentManagementService->getDocumentStats($this->getUser());
        
        return $this->json([
            'stats' => $stats
        ]);
    }
}