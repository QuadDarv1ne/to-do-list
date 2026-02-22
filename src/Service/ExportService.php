<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\Client;
use App\Entity\Deal;
use App\Repository\TaskRepository;
use App\Repository\ClientRepository;
use App\Repository\DealRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRepository $taskRepository,
        private ClientRepository $clientRepository,
        private DealRepository $dealRepository
    ) {}

    /**
     * Экспорт задач в Excel
     */
    public function exportTasks(array $filters = []): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Задачи');

        // Заголовки
        $headers = ['ID', 'Название', 'Описание', 'Статус', 'Приоритет', 'Срок', 'Категория', 'Создано', 'Обновлено'];
        $sheet->fromArray($headers, null, 'A1');

        // Данные
        $tasks = $this->getFilteredTasks($filters);
        $row = 2;

        foreach ($tasks as $task) {
            $sheet->setCellValue('A' . $row, $task->getId());
            $sheet->setCellValue('B' . $row, $task->getTitle());
            $sheet->setCellValue('C' . $row, $task->getDescription());
            $sheet->setCellValue('D' . $row, $task->getStatus());
            $sheet->setCellValue('E' . $row, $task->getPriority());
            $sheet->setCellValue('F' . $row, $task->getDueDate()?->format('d.m.Y'));
            $sheet->setCellValue('G' . $row, $task->getCategory()?->getName());
            $sheet->setCellValue('H' . $row, $task->getCreatedAt()?->format('d.m.Y H:i'));
            $sheet->setCellValue('I' . $row, $task->getUpdatedAt()?->format('d.m.Y H:i'));
            $row++;
        }

        // Автоширина
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->createStreamedResponse($spreadsheet, 'tasks_' . date('Ymd') . '.xlsx');
    }

    /**
     * Экспорт клиентов в Excel
     */
    public function exportClients(array $filters = []): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Клиенты');

        $headers = ['ID', 'Имя', 'Email', 'Телефон', 'Компания', 'Источник', 'Создано'];
        $sheet->fromArray($headers, null, 'A1');

        $clients = $this->getFilteredClients($filters);
        $row = 2;

        foreach ($clients as $client) {
            $sheet->setCellValue('A' . $row, $client->getId());
            $sheet->setCellValue('B' . $row, $client->getName());
            $sheet->setCellValue('C' . $row, $client->getEmail());
            $sheet->setCellValue('D' . $row, $client->getPhone());
            $sheet->setCellValue('E' . $row, $client->getCompany());
            $sheet->setCellValue('F' . $row, $client->getSource());
            $sheet->setCellValue('G' . $row, $client->getCreatedAt()?->format('d.m.Y H:i'));
            $row++;
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->createStreamedResponse($spreadsheet, 'clients_' . date('Ymd') . '.xlsx');
    }

    /**
     * Экспорт сделок в Excel
     */
    public function exportDeals(array $filters = []): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Сделки');

        $headers = ['ID', 'Название', 'Клиент', 'Сумма', 'Статус', 'Этап', 'Ожидаемая дата', 'Создано'];
        $sheet->fromArray($headers, null, 'A1');

        $deals = $this->getFilteredDeals($filters);
        $row = 2;

        foreach ($deals as $deal) {
            $sheet->setCellValue('A' . $row, $deal->getId());
            $sheet->setCellValue('B' . $row, $deal->getName());
            $sheet->setCellValue('C' . $row, $deal->getClient()?->getName());
            $sheet->setCellValue('D' . $row, $deal->getAmount());
            $sheet->setCellValue('E' . $row, $deal->getStatus());
            $sheet->setCellValue('F' . $row, $deal->getStage());
            $sheet->setCellValue('G' . $row, $deal->getExpectedCloseDate()?->format('d.m.Y'));
            $sheet->setCellValue('H' . $row, $deal->getCreatedAt()?->format('d.m.Y H:i'));
            $row++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->createStreamedResponse($spreadsheet, 'deals_' . date('Ymd') . '.xlsx');
    }

    /**
     * Экспорт в CSV
     */
    public function exportToCsv(string $entityType, array $filters = []): StreamedResponse
    {
        $callback = function() use ($entityType, $filters) {
            $handle = fopen('php://output', 'w');
            
            // Заголовки
            switch ($entityType) {
                case 'tasks':
                    fputcsv($handle, ['ID', 'Название', 'Статус', 'Приоритет', 'Срок']);
                    $entities = $this->getFilteredTasks($filters);
                    foreach ($entities as $task) {
                        fputcsv($handle, [
                            $task->getId(),
                            $task->getTitle(),
                            $task->getStatus(),
                            $task->getPriority(),
                            $task->getDueDate()?->format('d.m.Y')
                        ]);
                    }
                    break;
                    
                case 'clients':
                    fputcsv($handle, ['ID', 'Имя', 'Email', 'Телефон']);
                    $entities = $this->getFilteredClients($filters);
                    foreach ($entities as $client) {
                        fputcsv($handle, [
                            $client->getId(),
                            $client->getName(),
                            $client->getEmail(),
                            $client->getPhone()
                        ]);
                    }
                    break;
            }
            
            fclose($handle);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $entityType . '_' . date('Ymd') . '.csv"',
        ]);
    }

    private function getFilteredTasks(array $filters): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t');

        if (isset($filters['status'])) {
            $qb->andWhere('t.status = :status')->setParameter('status', $filters['status']);
        }

        if (isset($filters['priority'])) {
            $qb->andWhere('t.priority = :priority')->setParameter('priority', $filters['priority']);
        }

        if (isset($filters['user_id'])) {
            $qb->andWhere('t.user = :user')->setParameter('user', $filters['user_id']);
        }

        return $qb->getQuery()->getResult();
    }

    private function getFilteredClients(array $filters): array
    {
        $qb = $this->clientRepository->createQueryBuilder('c');

        if (isset($filters['source'])) {
            $qb->andWhere('c.source = :source')->setParameter('source', $filters['source']);
        }

        return $qb->getQuery()->getResult();
    }

    private function getFilteredDeals(array $filters): array
    {
        $qb = $this->dealRepository->createQueryBuilder('d');

        if (isset($filters['status'])) {
            $qb->andWhere('d.status = :status')->setParameter('status', $filters['status']);
        }

        return $qb->getQuery()->getResult();
    }

    private function createStreamedResponse(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = new Xlsx($spreadsheet);

        $response = new StreamedResponse(function() use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);

        return $response;
    }
}
