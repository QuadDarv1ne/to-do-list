<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Сервис для экспорта данных в различные форматы
 *
 * Поддерживаемые форматы:
 * - CSV
 * - Excel (XLSX)
 * - PDF
 * - JSON
 */
class ExportService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SerializerInterface $serializer,
        private TaskRepository $taskRepo,
    ) {
    }

    /**
     * Экспорт задач в CSV
     *
     * @param User $user Пользователь
     * @param array $filters Фильтры
     */
    public function exportTasksToCsv(User $user, array $filters = []): StreamedResponse
    {
        $tasks = $this->taskRepo->findByUserWithFilters($user, $filters);

        $response = new StreamedResponse(function () use ($tasks) {
            $handle = fopen('php://output', 'w');

            // Заголовки
            fputcsv($handle, [
                'ID',
                'Название',
                'Описание',
                'Статус',
                'Приоритет',
                'Дедлайн',
                'Дата создания',
                'Теги',
            ], ';');

            // Данные
            foreach ($tasks as $task) {
                fputcsv($handle, [
                    $task->getId(),
                    $this->escapeCsv($task->getTitle()),
                    $this->escapeCsv($task->getDescription() ?? ''),
                    $task->getStatus(),
                    $task->getPriority(),
                    $task->getDueDate()?->format('d.m.Y'),
                    $task->getCreatedAt()->format('d.m.Y H:i'),
                    $this->getTagsString($task),
                ], ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="tasks_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    /**
     * Экспорт задач в Excel (XLSX)
     */
    public function exportTasksToExcel(User $user, array $filters = []): StreamedResponse
    {
        $tasks = $this->taskRepo->findByUserWithFilters($user, $filters);

        $response = new StreamedResponse(function () use ($tasks) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Заголовки
            $headers = [
                'ID',
                'Название',
                'Описание',
                'Статус',
                'Приоритет',
                'Дедлайн',
                'Дата создания',
                'Теги',
            ];

            $col = 1;
            foreach ($headers as $header) {
                $sheet->setCellValueByColumnAndRow($col, 1, $header);
                $col++;
            }

            // Стили заголовков
            $sheet->getStyle('A1:H1')->getFont()->setBold(true);
            $sheet->getStyle('A1:H1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('4F81BD');
            $sheet->getStyle('A1:H1')->getFont()->getColor()->setRGB('FFFFFF');

            // Авто-ширина колонок
            foreach (range('A', 'H') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Данные
            $row = 2;
            foreach ($tasks as $task) {
                $sheet->setCellValueByColumnAndRow(1, $row, $task->getId());
                $sheet->setCellValueByColumnAndRow(2, $row, $task->getTitle());
                $sheet->setCellValueByColumnAndRow(3, $row, $task->getDescription() ?? '');
                $sheet->setCellValueByColumnAndRow(4, $row, $this->getStatusText($task->getStatus()));
                $sheet->setCellValueByColumnAndRow(5, $row, $this->getPriorityText($task->getPriority()));
                $sheet->setCellValueByColumnAndRow(6, $row, $task->getDueDate()?->format('d.m.Y'));
                $sheet->setCellValueByColumnAndRow(7, $row, $task->getCreatedAt()->format('d.m.Y H:i'));
                $sheet->setCellValueByColumnAndRow(8, $row, $this->getTagsString($task));

                // Цвета для статусов
                $this->applyStatusColor($sheet, $row, $task->getStatus());

                $row++;
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="tasks_' . date('Y-m-d') . '.xlsx"');

        return $response;
    }

    /**
     * Экспорт задач в JSON
     */
    public function exportTasksToJson(User $user, array $filters = []): StreamedResponse
    {
        $tasks = $this->taskRepo->findByUserWithFilters($user, $filters);

        $json = $this->serializer->serialize($tasks, 'json', [
            'groups' => ['task:read'],
            'json_encode_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
        ]);

        $response = new StreamedResponse(function () use ($json) {
            echo $json;
        });

        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 'attachment; filename="tasks_' . date('Y-m-d') . '.json"');

        return $response;
    }

    /**
     * Экспорт статистики в PDF
     */
    public function exportStatisticsToPdf(User $user): StreamedResponse
    {
        $html = $this->generateStatisticsHtml($user);

        $response = new StreamedResponse(function () use ($html) {
            // Используем dompdf для генерации PDF
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            echo $dompdf->output();
        });

        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename="statistics_' . date('Y-m-d') . '.pdf"');

        return $response;
    }

    /**
     * Экспорт пользователей в CSV
     */
    public function exportUsersToCsv(array $filters = []): StreamedResponse
    {
        $users = $this->em->getRepository(User::class)->findBy($filters);

        $response = new StreamedResponse(function () use ($users) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID',
                'Email',
                'Имя',
                'Фамилия',
                'Телефон',
                'Должность',
                'Отдел',
                'Роль',
                'Дата регистрации',
            ], ';');

            foreach ($users as $user) {
                fputcsv($handle, [
                    $user->getId(),
                    $user->getEmail(),
                    $user->getFirstName(),
                    $user->getLastName(),
                    $user->getPhone(),
                    $user->getPosition(),
                    $user->getDepartment(),
                    implode(', ', $user->getRoles()),
                    $user->getCreatedAt()?->format('d.m.Y'),
                ], ';');
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="users_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    /**
     * Экспорт отчёта по сделкам в Excel
     */
    public function exportDealsToExcel(array $filters = []): StreamedResponse
    {
        $deals = $this->em->getRepository(\App\Entity\Deal::class)->findBy($filters, ['createdAt' => 'DESC']);

        $response = new StreamedResponse(function () use ($deals) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ['ID', 'Название', 'Клиент', 'Сумма', 'Статус', 'Вероятность', 'Менеджер', 'Дата создания'];
            $col = 1;
            foreach ($headers as $header) {
                $sheet->setCellValueByColumnAndRow($col, 1, $header);
                $col++;
            }

            $sheet->getStyle('A1:H1')->getFont()->setBold(true);

            $row = 2;
            foreach ($deals as $deal) {
                $sheet->setCellValueByColumnAndRow(1, $row, $deal->getId());
                $sheet->setCellValueByColumnAndRow(2, $row, $deal->getTitle());
                $sheet->setCellValueByColumnAndRow(3, $row, $deal->getClient()?->getName() ?? '-');
                $sheet->setCellValueByColumnAndRow(4, $row, $deal->getAmount());
                $sheet->setCellValueByColumnAndRow(5, $row, $deal->getStatus());
                $sheet->setCellValueByColumnAndRow(6, $row, $deal->getProbability() . '%');
                $sheet->setCellValueByColumnAndRow(7, $row, $deal->getOwner()?->getFullName() ?? '-');
                $sheet->setCellValueByColumnAndRow(8, $row, $deal->getCreatedAt()?->format('d.m.Y'));
                $row++;
            }

            foreach (range('A', 'H') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="deals_' . date('Y-m-d') . '.xlsx"');

        return $response;
    }

    /**
     * Escape CSV special characters
     */
    private function escapeCsv(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        // Удаляем символы, которые могут сломать CSV
        return str_replace(["\r", "\n", "\t"], ' ', $value);
    }

    /**
     * Получить строку тегов
     */
    private function getTagsString(Task $task): string
    {
        $tags = [];
        foreach ($task->getTags() as $tag) {
            $tags[] = $tag->getName();
        }
        return implode(', ', $tags);
    }

    /**
     * Текст статуса
     */
    private function getStatusText(string $status): string
    {
        $statuses = [
            'pending' => 'В ожидании',
            'in_progress' => 'В работе',
            'completed' => 'Завершено',
            'cancelled' => 'Отменено',
        ];
        return $statuses[$status] ?? $status;
    }

    /**
     * Текст приоритета
     */
    private function getPriorityText(string $priority): string
    {
        $priorities = [
            'low' => 'Низкий',
            'medium' => 'Средний',
            'high' => 'Высокий',
            'urgent' => 'Срочно',
        ];
        return $priorities[$priority] ?? $priority;
    }

    /**
     * Применить цвет статуса
     */
    private function applyStatusColor($sheet, int $row, string $status): void
    {
        $colors = [
            'pending' => 'FFFF00',
            'in_progress' => 'FFFF99',
            'completed' => '99FF99',
            'cancelled' => 'FF9999',
        ];

        if (isset($colors[$status])) {
            $sheet->getStyle("A$row:H$row")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB($colors[$status]);
        }
    }

    /**
     * Генерация HTML для статистики
     */
    private function generateStatisticsHtml(User $user): string
    {
        $total = $this->taskRepo->count(['user' => $user]);
        $completed = $this->taskRepo->count(['user' => $user, 'status' => 'completed']);
        $pending = $this->taskRepo->count(['user' => $user, 'status' => 'pending']);
        $inProgress = $this->taskRepo->count(['user' => $user, 'status' => 'in_progress']);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Статистика задач</title>
    <style>
        body { font-family: Arial, sans-serif; }
        h1 { color: #333; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0; }
        .stat-card { padding: 20px; border-radius: 8px; text-align: center; }
        .stat-value { font-size: 36px; font-weight: bold; color: #4F81BD; }
        .stat-label { color: #666; margin-top: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #4F81BD; color: white; }
    </style>
</head>
<body>
    <h1>📊 Статистика задач</h1>
    <p>Пользователь: {$user->getFullName()} ({$user->getEmail()})</p>
    <p>Дата отчёта: {date('d.m.Y H:i')}</p>
    
    <div class="stats">
        <div class="stat-card" style="background: #E8F4F8;">
            <div class="stat-value">$total</div>
            <div class="stat-label">Всего задач</div>
        </div>
        <div class="stat-card" style="background: #FFF4E6;">
            <div class="stat-value">$pending</div>
            <div class="stat-label">В ожидании</div>
        </div>
        <div class="stat-card" style="background: #FEF9E7;">
            <div class="stat-value">$inProgress</div>
            <div class="stat-label">В работе</div>
        </div>
        <div class="stat-card" style="background: #E8F8F5;">
            <div class="stat-value">$completed</div>
            <div class="stat-label">Завершено</div>
        </div>
    </div>
    
    <h2>Прогресс выполнения</h2>
    <table>
        <tr>
            <th>Метрика</th>
            <th>Значение</th>
        </tr>
        <tr>
            <td>Процент выполнения</td>
            <td>" . ($total > 0 ? round($completed / $total * 100, 1) : 0) . "%</td>
        </tr>
        <tr>
            <td>Осталось задач</td>
            <td>" . ($total - $completed) . "</td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}
