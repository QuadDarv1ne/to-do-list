<?php

namespace App\Controller;

use App\Repository\TaskRepository;
use App\Service\ExportService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Контроллер для экспорта данных
 */
#[Route('/export')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Export')]
class ExportController extends AbstractController
{
    #[Route('', name: 'export_index', methods: ['GET'])]
    #[OA\Get(
        path: '/export',
        summary: 'Страница экспорта',
        description: 'Выбор формата и параметров экспорта',
        tags: ['Export'],
    )]
    public function index(): Response
    {
        return $this->render('export/index.html.twig', [
            'formats' => [
                ['id' => 'csv', 'name' => 'CSV', 'icon' => '📄', 'description' => 'Текстовый формат, подходит для импорта в Excel'],
                ['id' => 'excel', 'name' => 'Excel', 'icon' => '📊', 'description' => 'Полноценный XLSX с форматированием'],
                ['id' => 'json', 'name' => 'JSON', 'icon' => '🔧', 'description' => 'Для интеграции с другими системами'],
                ['id' => 'pdf', 'name' => 'PDF', 'icon' => '📑', 'description' => 'Статистика и отчёты в печатном виде'],
            ],
        ]);
    }

    #[Route('/tasks/csv', name: 'export_tasks_csv', methods: ['GET'])]
    #[OA\Get(
        path: '/export/tasks/csv',
        summary: 'Экспорт задач в CSV',
        tags: ['Export'],
    )]
    #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'in_progress', 'completed', 'cancelled']))]
    #[OA\Parameter(name: 'priority', in: 'query', schema: new OA\Schema(type: 'string', enum: ['low', 'medium', 'high', 'urgent']))]
    #[OA\Response(response: 200, description: 'CSV файл', content: new OA\MediaType(mediaType: 'text/csv'))]
    public function tasksCsv(
        Request $request,
        ExportService $exportService,
        TaskRepository $taskRepo,
    ): Response {
        $user = $this->getUser();

        $filters = [
            'status' => $request->query->get('status'),
            'priority' => $request->query->get('priority'),
            'search' => $request->query->get('search'),
        ];

        return $exportService->exportTasksToCsv($user, $filters);
    }

    #[Route('/tasks/excel', name: 'export_tasks_excel', methods: ['GET'])]
    #[OA\Get(
        path: '/export/tasks/excel',
        summary: 'Экспорт задач в Excel',
        tags: ['Export'],
    )]
    #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'priority', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'XLSX файл', content: new OA\MediaType(mediaType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'))]
    public function tasksExcel(
        Request $request,
        ExportService $exportService,
    ): Response {
        $user = $this->getUser();

        $filters = [
            'status' => $request->query->get('status'),
            'priority' => $request->query->get('priority'),
            'search' => $request->query->get('search'),
        ];

        return $exportService->exportTasksToExcel($user, $filters);
    }

    #[Route('/tasks/json', name: 'export_tasks_json', methods: ['GET'])]
    #[OA\Get(
        path: '/export/tasks/json',
        summary: 'Экспорт задач в JSON',
        tags: ['Export'],
    )]
    #[OA\Response(response: 200, description: 'JSON файл', content: new OA\MediaType(mediaType: 'application/json'))]
    public function tasksJson(
        Request $request,
        ExportService $exportService,
    ): Response {
        $user = $this->getUser();

        $filters = [
            'status' => $request->query->get('status'),
            'priority' => $request->query->get('priority'),
        ];

        return $exportService->exportTasksToJson($user, $filters);
    }

    #[Route('/tasks/pdf', name: 'export_tasks_pdf', methods: ['GET'])]
    #[OA\Get(
        path: '/export/tasks/pdf',
        summary: 'Экспорт статистики в PDF',
        tags: ['Export'],
    )]
    #[OA\Response(response: 200, description: 'PDF файл', content: new OA\MediaType(mediaType: 'application/pdf'))]
    public function tasksPdf(
        ExportService $exportService,
    ): Response {
        $user = $this->getUser();

        return $exportService->exportStatisticsToPdf($user);
    }

    #[Route('/users/csv', name: 'export_users_csv', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    #[OA\Get(
        path: '/export/users/csv',
        summary: 'Экспорт пользователей в CSV',
        description: 'Доступно только администраторам',
        tags: ['Export'],
    )]
    #[OA\Response(response: 200, description: 'CSV файл с пользователями')]
    public function usersCsv(
        Request $request,
        ExportService $exportService,
    ): Response {
        $filters = [
            'department' => $request->query->get('department'),
            'isActive' => $request->query->getBoolean('active'),
        ];

        return $exportService->exportUsersToCsv($filters);
    }

    #[Route('/deals/excel', name: 'export_deals_excel', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    #[OA\Get(
        path: '/export/deals/excel',
        summary: 'Экспорт сделок в Excel',
        description: 'Доступно менеджерам и администраторам',
        tags: ['Export'],
    )]
    #[OA\Response(response: 200, description: 'XLSX файл со сделками')]
    public function dealsExcel(
        Request $request,
        ExportService $exportService,
    ): Response {
        $filters = [
            'status' => $request->query->get('status'),
            'minAmount' => $request->query->get('min_amount'),
            'maxAmount' => $request->query->get('max_amount'),
        ];

        return $exportService->exportDealsToExcel($filters);
    }

    #[Route('/statistics', name: 'export_statistics', methods: ['GET'])]
    #[OA\Get(
        path: '/export/statistics',
        summary: 'Статистика экспорта',
        tags: ['Export'],
    )]
    #[OA\Response(response: 200, description: 'Статистика в JSON')]
    public function statistics(TaskRepository $taskRepo): JsonResponse
    {
        $user = $this->getUser();

        $stats = [
            'total_tasks' => $taskRepo->count(['user' => $user]),
            'completed' => $taskRepo->count(['user' => $user, 'status' => 'completed']),
            'pending' => $taskRepo->count(['user' => $user, 'status' => 'pending']),
            'in_progress' => $taskRepo->count(['user' => $user, 'status' => 'in_progress']),
            'overdue' => $taskRepo->countOverdue($user),
        ];

        $stats['completion_rate'] = $stats['total_tasks'] > 0
            ? round($stats['completed'] / $stats['total_tasks'] * 100, 1)
            : 0;

        return $this->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
