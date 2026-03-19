<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда для анализа и оптимизации N+1 запросов
 *
 * Использование:
 *   php bin/console app:n-plus-one-detect          # Анализ текущих проблем
 *   php bin/console app:n-plus-one-detect --fix    # Автоматическое исправление (где возможно)
 */
#[AsCommand(
    name: 'app:n-plus-one-detect',
    description: 'Анализ и оптимизация N+1 запросов в проекте',
)]
class NPlusOneDetectCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('fix', null, InputOption::VALUE_NONE, 'Автоматическое исправление проблем')
            ->addOption('report', null, InputOption::VALUE_NONE, 'Создать отчёт в Markdown')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Путь для анализа', 'src');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🔍 Анализ N+1 запросов');

        $srcPath = $input->getOption('path');
        $fixMode = $input->getOption('fix');
        $reportMode = $input->getOption('report');

        if (!file_exists($srcPath)) {
            $io->error("Путь не найден: $srcPath");
            return Command::FAILURE;
        }

        // Анализ файлов
        $problems = $this->analyzePath($srcPath);

        if (empty($problems)) {
            $io->success('N+1 запросы не обнаружены!');
            return Command::SUCCESS;
        }

        $io->warning("Найдено проблем: " . count($problems));

        // Вывод отчёта
        $this->printReport($io, $problems);

        // Создание Markdown отчёта
        if ($reportMode) {
            $this->generateMarkdownReport($problems);
            $io->info('Отчёт сохранён в docs/N_PLUS_ONE_REPORT.md');
        }

        // Автоматическое исправление
        if ($fixMode) {
            $io->warning('Автоматическое исправление не реализовано - требует ручного анализа');
        }

        $io->note([
            'Рекомендации:',
            '1. Используйте eager loading (JOIN) для связанных сущностей',
            '2. Применяйте fetch extra lazy для коллекций',
            '3. Кэшируйте часто используемые запросы',
            '4. Используйте индексацию для полей в WHERE/JOIN',
        ]);

        return Command::SUCCESS;
    }

    /**
     * Анализ директории на наличие N+1 проблем
     */
    private function analyzePath(string $path): array
    {
        $problems = [];
        $files = $this->findPhpFiles($path);

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $relativePath = str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $file);

            // Паттерн 1: Цикл с запросом внутри
            if (preg_match_all('/foreach\s*\([^)]*\)\s*\{[^}]*->\w+Repository->find\(/s', $content, $matches)) {
                $problems[] = [
                    'file' => $relativePath,
                    'type' => 'N+1 Query in Loop',
                    'severity' => 'HIGH',
                    'description' => 'Запрос find() внутри цикла foreach',
                    'line' => $this->findLineNumber($content, $matches[0][0]),
                    'suggestion' => 'Используйте IN() запрос или eager loading',
                ];
            }

            // Паттерн 2: Доступ к lazy-loaded коллекции в цикле
            if (preg_match_all('/foreach\s*\([^)]*\)\s*\{[^}]*->get\w+\(\)->/s', $content, $matches)) {
                $problems[] = [
                    'file' => $relativePath,
                    'type' => 'Lazy Loading in Loop',
                    'severity' => 'MEDIUM',
                    'description' => 'Возможен доступ к lazy-loaded коллекции в цикле',
                    'line' => $this->findLineNumber($content, $matches[0][0]),
                    'suggestion' => 'Используйте JOIN с fetch в основном запросе',
                ];
            }

            // Паттерн 3: Многократный вызов getOneOrNullResult без кэширования
            if (preg_match_all('/getOneOrNullResult\(\)/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                if (count($matches[0]) > 3) { // Больше 3 раз в файле
                    $problems[] = [
                        'file' => $relativePath,
                        'type' => 'Repeated Single Result Query',
                        'severity' => 'MEDIUM',
                        'description' => 'Многократные вызовы getOneOrNullResult()',
                        'line' => 'multiple',
                        'suggestion' => 'Кэшируйте результаты или используйте batch query',
                    ];
                }
            }

            // Паттерн 4: Отсутствие индексации в репозиториях
            if (str_contains($file, 'Repository.php')) {
                if (preg_match_all('/->where\([^)]*LIKE/s', $content, $matches)) {
                    $problems[] = [
                        'file' => $relativePath,
                        'type' => 'Potential Missing Index',
                        'severity' => 'LOW',
                        'description' => 'LIKE запрос без явного указания индекса',
                        'line' => $this->findLineNumber($content, $matches[0][0]),
                        'suggestion' => 'Добавьте индекс для поля в LIKE',
                    ];
                }
            }
        }

        return $problems;
    }

    /**
     * Поиск PHP файлов в директории
     */
    private function findPhpFiles(string $path): array
    {
        $files = [];
        $iterator = new \RecursiveDirectoryIterator($path);
        $recursiveIterator = new \RecursiveIteratorIterator($iterator);

        foreach ($recursiveIterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Поиск номера строки
     */
    private function findLineNumber(string $content, string $search): int|string
    {
        $pos = strpos($content, $search);
        if ($pos === false) {
            return 'unknown';
        }

        return substr_count($content, "\n", 0, $pos) + 1;
    }

    /**
     * Вывод отчёта в консоль
     */
    private function printReport(SymfonyStyle $io, array $problems): void
    {
        $rows = [];
        foreach ($problems as $problem) {
            $rows[] = [
                $problem['severity'],
                $problem['type'],
                basename($problem['file']),
                $problem['line'],
            ];
        }

        $io->table(
            ['Severity', 'Type', 'File', 'Line'],
            $rows
        );

        // Детали по каждой проблеме
        foreach ($problems as $i => $problem) {
            $io->section("Проблема #" . ($i + 1));
            $io->writeln([
                "  <comment>Файл:</comment> {$problem['file']}",
                "  <comment>Тип:</comment> {$problem['type']}",
                "  <comment>Описание:</comment> {$problem['description']}",
                "  <info>Решение:</info> {$problem['suggestion']}",
                "",
            ]);
        }
    }

    /**
     * Генерация Markdown отчёта
     */
    private function generateMarkdownReport(array $problems): void
    {
        $report = "# 🔍 Отчёт по N+1 запросам\n\n";
        $report .= "**Дата генерации:** " . date('d.m.Y H:i') . "\n\n";
        $report .= "## Статистика\n\n";
        $report .= "- **Всего проблем:** " . count($problems) . "\n";
        $report .= "- **HIGH:** " . count(array_filter($problems, fn($p) => $p['severity'] === 'HIGH')) . "\n";
        $report .= "- **MEDIUM:** " . count(array_filter($problems, fn($p) => $p['severity'] === 'MEDIUM')) . "\n";
        $report .= "- **LOW:** " . count(array_filter($problems, fn($p) => $p['severity'] === 'LOW')) . "\n\n";

        $report .= "## Проблемы\n\n";

        foreach ($problems as $i => $problem) {
            $report .= "### Проблема #" . ($i + 1) . "\n\n";
            $report .= "| Параметр | Значение |\n";
            $report .= "|----------|----------|\n";
            $report .= "| **Severity** | {$problem['severity']} |\n";
            $report .= "| **Type** | {$problem['type']} |\n";
            $report .= "| **File** | `{$problem['file']}` |\n";
            $report .= "| **Line** | {$problem['line']} |\n";
            $report .= "| **Description** | {$problem['description']} |\n";
            $report .= "| **Suggestion** | {$problem['suggestion']} |\n\n";
        }

        $report .= "## Рекомендации\n\n";
        $report .= "1. Используйте **eager loading** (JOIN) для связанных сущностей\n";
        $report .= "2. Применяйте **fetch extra lazy** для коллекций\n";
        $report .= "3. **Кэшируйте** часто используемые запросы\n";
        $report .= "4. Используйте **индексацию** для полей в WHERE/JOIN\n";
        $report .= "5. Применяйте **batch queries** для массовых операций\n";

        file_put_contents('docs/N_PLUS_ONE_REPORT.md', $report);
    }
}
