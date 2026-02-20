<?php

namespace App\Controller;

use App\Repository\TaskRepository;
use App\Service\TaskExportService;
use App\Service\PerformanceOptimizerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Контроллер для экспорта данных
 */
#[Route('/export')]
#[IsGranted('ROLE_USER')]
class ExportController extends AbstractController
{
    public function __construct(
        private TaskExportService $exportService,
        private PerformanceOptimizerService $optimizer,
    ) {
    }

    /**
     * Экспорт задач в CSV
     */
    #[Route('/tasks/csv', name: 'app_export_tasks_csv', methods: ['GET'])]
    public function exportTasksCsv(TaskRepository $taskRepo): StreamedResponse
    {
        $user = $this->getUser();

        // Получаем задачи с кэшированием
        $cacheKey = 'export_tasks_csv_' . $user->getId();

        $tasks = $this->optimizer->cacheQuery($cacheKey, function () use ($taskRepo, $user) {
            return $taskRepo->findBy(
                ['user' => $user],
                ['createdAt' => 'DESC'],
                1000, // Лимит 1000 задач
            );
        }, 60);

        $filename = 'tasks_' . date('Y-m-d') . '.csv';

        return new StreamedResponse(function () use ($tasks) {
            $output = fopen('php://output', 'w');

            // BOM для UTF-8
            fprintf($output, \chr(0xEF).\chr(0xBB).\chr(0xBF));

            // Заголовки
            fputcsv($output, [
                'ID',
                'Название',
                'Описание',
                'Статус',
                'Приоритет',
                'Срок выполнения',
                'Дата создания',
                'Дата обновления',
            ]);

            // Данные
            foreach ($tasks as $task) {
                fputcsv($output, [
                    $task->getId(),
                    $task->getTitle(),
                    $task->getDescription() ?? '',
                    $task->getStatus(),
                    $task->getPriority(),
                    $task->getDueDate()?->format('Y-m-d') ?? '',
                    $task->getCreatedAt()->format('Y-m-d H:i:s'),
                    $task->getUpdatedAt()->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($output);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Экспорт задач в Excel
     */
    #[Route('/tasks/excel', name: 'app_export_tasks_excel', methods: ['GET'])]
    public function exportTasksExcel(TaskRepository $taskRepo): Response
    {
        $user = $this->getUser();

        $cacheKey = 'export_tasks_excel_' . $user->getId();

        $tasks = $this->optimizer->cacheQuery($cacheKey, function () use ($taskRepo, $user) {
            return $taskRepo->findBy(
                ['user' => $user],
                ['createdAt' => 'DESC'],
                1000,
            );
        }, 60);

        try {
            $filepath = $this->exportService->tasksToExcel($tasks);
            $filename = basename($filepath);

            return $this->file($filepath, $filename);
        } catch (\RuntimeException $e) {
            return $this->redirectToRoute('app_task_index', [], 302);
        }
    }

    /**
     * Экспорт задач в PDF
     */
    #[Route('/tasks/pdf', name: 'app_export_tasks_pdf', methods: ['GET'])]
    public function exportTasksPdf(TaskRepository $taskRepo): Response
    {
        $user = $this->getUser();

        $cacheKey = 'export_tasks_pdf_' . $user->getId();

        $tasks = $this->optimizer->cacheQuery($cacheKey, function () use ($taskRepo, $user) {
            return $taskRepo->findBy(
                ['user' => $user],
                ['createdAt' => 'DESC'],
                100,
            );
        }, 60);

        try {
            $filepath = $this->exportService->tasksToPdf($tasks);
            $filename = basename($filepath);

            return $this->file($filepath, $filename);
        } catch (\RuntimeException $e) {
            return $this->redirectToRoute('app_task_index', [], 302);
        }
    }

    /**
     * Страница выбора экспорта
     */
    #[Route('', name: 'app_export_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('export/index.html.twig', [
            'exports' => $this->exportService->getAvailableExports(),
        ]);
    }

    /**
     * Скачать готовый экспорт
     */
    #[Route('/download/{filename}', name: 'app_export_download', methods: ['GET'])]
    public function download(string $filename): Response
    {
        $exportDir = \dirname(__DIR__) . '/../var/exports';
        $filepath = $exportDir . '/' . $filename;

        if (!file_exists($filepath)) {
            throw $this->createNotFoundException('Файл не найден');
        }

        return $this->file($filepath, $filename);
    }

    /**
     * Очистить старые экспорты
     */
    #[Route('/cleanup', name: 'app_export_cleanup', methods: ['POST'])]
    public function cleanup(): Response
    {
        $deleted = $this->exportService->cleanupOldExports(7);

        $this->addFlash('success', "Удалено {$deleted} старых экспортов");

        return $this->redirectToRoute('app_export_index', [], 302);
    }
}
