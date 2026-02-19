<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Export Service
 * Экспорт данных в PDF, Excel, CSV
 */
class ExportService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    /**
     * Экспорт задач в CSV
     */
    public function tasksToCsv(array $tasks, string $filename = 'tasks.csv'): string
    {
        $headers = [
            'ID',
            'Название',
            'Описание',
            'Статус',
            'Приоритет',
            'Срок выполнения',
            'Дата создания',
            'Дата обновления'
        ];

        $rows = [];
        foreach ($tasks as $task) {
            $rows[] = [
                $task->getId(),
                $this->escapeCsv($task->getTitle()),
                $this->escapeCsv($task->getDescription() ?? ''),
                $task->getStatus(),
                $task->getPriority(),
                $task->getDueDate()?->format('Y-m-d') ?? '',
                $task->getCreatedAt()->format('Y-m-d H:i:s'),
                $task->getUpdatedAt()->format('Y-m-d H:i:s')
            ];
        }

        return $this->generateCsv($headers, $rows, $filename);
    }

    /**
     * Экспорт задач в Excel (XLSX)
     */
    public function tasksToExcel(array $tasks, string $filename = 'tasks.xlsx'): string
    {
        // Проверка наличия библиотеки PhpSpreadsheet
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            throw new \RuntimeException('PhpSpreadsheet not installed. Run: composer require phpoffice/phpspreadsheet');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Заголовки
        $headers = [
            'ID',
            'Название',
            'Описание',
            'Статус',
            'Приоритет',
            'Срок выполнения',
            'Дата создания'
        ];

        $sheet->fromArray([$headers], null, 'A1');

        // Стили заголовков
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF667eea']
            ],
            'alignment' => ['horizontal' => 'center']
        ]);

        // Данные
        $rowIndex = 2;
        foreach ($tasks as $task) {
            $sheet->fromArray([[
                $task->getId(),
                $task->getTitle(),
                $task->getDescription() ?? '',
                $task->getStatus(),
                $task->getPriority(),
                $task->getDueDate()?->format('Y-m-d'),
                $task->getCreatedAt()->format('Y-m-d')
            ]], null, 'A' . $rowIndex);
            $rowIndex++;
        }

        // Авто-ширина колонок
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Сохранение
        $filepath = $this->getExportPath($filename);
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filepath);

        return $filepath;
    }

    /**
     * Экспорт задач в PDF
     */
    public function tasksToPdf(array $tasks, string $filename = 'tasks.pdf'): string
    {
        // Проверка наличия TCPDF
        if (!class_exists('\TCPDF')) {
            throw new \RuntimeException('TCPDF not installed. Run: composer require tecnickcom/tcpdf');
        }

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');

        // Метаданные
        $pdf->SetCreator('CRM Tasks');
        $pdf->SetAuthor('CRM System');
        $pdf->SetTitle('Export Tasks');
        $pdf->SetSubject('Tasks Export');

        // Удаляем стандартные колонтитулы
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Добавляем страницу
        $pdf->AddPage();

        // Заголовок
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 15, 'Задачи', 0, 1, 'C');

        // Дата экспорта
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 10, 'Дата экспорта: ' . date('d.m.Y H:i'), 0, 1, 'R');

        $pdf->Ln(5);

        // Таблица
        $pdf->SetFont('helvetica', 'B', 10);
        
        // Заголовки таблицы
        $headers = ['ID', 'Название', 'Статус', 'Приоритет', 'Срок'];
        $widths = [15, 80, 30, 30, 35];
        
        $pdf->SetFillColor(102, 126, 234);
        $pdf->SetTextColor(255);
        
        foreach ($headers as $i => $header) {
            $pdf->Cell($widths[$i], 7, $header, 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Данные
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(0);
        $pdf->SetFillColor(245, 246, 250);

        $fill = false;
        foreach ($tasks as $task) {
            $pdf->Cell($widths[0], 6, $task->getId(), 1, 0, 'C', $fill);
            $pdf->Cell($widths[1], 6, $this->truncate($task->getTitle(), 40), 1, 0, 'L', $fill);
            $pdf->Cell($widths[2], 6, $task->getStatus(), 1, 0, 'C', $fill);
            $pdf->Cell($widths[3], 6, $task->getPriority(), 1, 0, 'C', $fill);
            $pdf->Cell($widths[4], 6, $task->getDueDate()?->format('d.m.Y') ?? '-', 1, 0, 'C', $fill);
            $pdf->Ln();
            $fill = !$fill;
        }

        // Итоговая статистика
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 7, 'Всего задач: ' . count($tasks), 0, 1, 'L');

        // Сохранение
        $filepath = $this->getExportPath($filename);
        $pdf->Output($filepath, 'F');

        return $filepath;
    }

    /**
     * Экспорт статистики в JSON
     */
    public function statisticsToJson(array $statistics, string $filename = 'statistics.json'): string
    {
        $data = [
            'exported_at' => date('Y-m-d H:i:s'),
            'statistics' => $statistics
        ];

        $filepath = $this->getExportPath($filename);
        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $filepath;
    }

    /**
     * Генерация CSV файла
     */
    private function generateCsv(array $headers, array $rows, string $filename): string
    {
        $filepath = $this->getExportPath($filename);
        $file = fopen($filepath, 'w');

        // Добавляем BOM для UTF-8
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

        // Заголовки
        fputcsv($file, $headers);

        // Данные
        foreach ($rows as $row) {
            fputcsv($file, $row);
        }

        fclose($file);

        return $filepath;
    }

    /**
     * Экранирование CSV значений
     */
    private function escapeCsv(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        // Удаляем специальные символы
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        
        // Экранируем кавычки
        return str_replace('"', '""', $value);
    }

    /**
     * Усечение строки
     */
    private function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 3) . '...';
    }

    /**
     * Получение пути для экспорта
     */
    private function getExportPath(string $filename): string
    {
        $exportDir = dirname(__DIR__) . '/../var/exports';
        
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $timestamp = date('Ymd_His');
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        return $exportDir . "/{$name}_{$timestamp}.{$ext}";
    }

    /**
     * Очистка старых экспортов
     */
    public function cleanupOldExports(int $daysToKeep = 7): int
    {
        $exportDir = dirname(__DIR__) . '/../var/exports';
        
        if (!is_dir($exportDir)) {
            return 0;
        }

        $deleted = 0;
        $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);

        foreach (scandir($exportDir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filepath = $exportDir . '/' . $file;
            
            if (filemtime($filepath) < $cutoffTime) {
                unlink($filepath);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Получить список доступных экспортов
     */
    public function getAvailableExports(): array
    {
        $exportDir = dirname(__DIR__) . '/../var/exports';
        
        if (!is_dir($exportDir)) {
            return [];
        }

        $files = [];
        foreach (scandir($exportDir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filepath = $exportDir . '/' . $file;
            
            $files[] = [
                'filename' => $file,
                'size' => filesize($filepath),
                'created' => date('Y-m-d H:i:s', filemtime($filepath)),
                'type' => pathinfo($file, PATHINFO_EXTENSION)
            ];
        }

        // Сортировка по дате (новые первые)
        usort($files, fn($a, $b) => strtotime($b['created']) - strtotime($a['created']));

        return $files;
    }
}
