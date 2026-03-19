<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:garbage-cleanup',
    description: 'Очистка мусора: временные файлы, логи, сессии',
)]
class GarbageCleanupCommand extends Command
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('temp', null, InputOption::VALUE_NONE, 'Очистить временные файлы')
            ->addOption('logs', null, InputOption::VALUE_NONE, 'Очистить старые логи')
            ->addOption('sessions', null, InputOption::VALUE_NONE, 'Очистить старые сессии')
            ->addOption('uploads', null, InputOption::VALUE_NONE, 'Очистить неиспользуемые файлы загрузок')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Очистить весь мусор (по умолчанию)')
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Количество дней для хранения файлов', '7')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Показать что будет очищено без фактической очистки')
            ->addOption('stats', 's', InputOption::VALUE_NONE, 'Показать статистику перед очисткой');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Очистка мусора приложения');

        $all = $input->getOption('all');
        $temp = $input->getOption('temp');
        $logs = $input->getOption('logs');
        $sessions = $input->getOption('sessions');
        $uploads = $input->getOption('uploads');
        $dryRun = $input->getOption('dry-run');
        $days = (int) $input->getOption('days');
        $stats = $input->getOption('stats');

        // Показываем статистику если запрошено
        if ($stats) {
            $this->showGarbageStats($io, $days);
        }

        if ($dryRun) {
            $io->warning('Режим сухой проверки - данные не будут удалены');
        }

        // Определяем что очищать
        $tasks = [];
        
        if ($all || (!($temp || $logs || $sessions || $uploads))) {
            $tasks = ['temp', 'logs', 'sessions', 'uploads'];
        } else {
            if ($temp) $tasks[] = 'temp';
            if ($logs) $tasks[] = 'logs';
            if ($sessions) $tasks[] = 'sessions';
            if ($uploads) $tasks[] = 'uploads';
        }

        $results = [
            'files_deleted' => 0,
            'bytes_freed' => 0,
            'details' => [],
        ];

        try {
            foreach ($tasks as $task) {
                $result = match($task) {
                    'temp' => $this->cleanupTempFiles($io, $days, $dryRun),
                    'logs' => $this->cleanupOldLogs($io, $days, $dryRun),
                    'sessions' => $this->cleanupOldSessions($io, $days, $dryRun),
                    'uploads' => $this->cleanupUnusedUploads($io, $days, $dryRun),
                };

                $results['files_deleted'] += $result['files_deleted'];
                $results['bytes_freed'] += $result['bytes_freed'];
                $results['details'][$task] = $result;
            }

            $io->success('Очистка мусора завершена!');
            $io->table(
                ['Параметр', 'Значение'],
                [
                    ['Всего файлов удалено', $results['files_deleted']],
                    ['Освобождено места', $this->formatBytes($results['bytes_freed'])],
                ],
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Ошибка при очистке мусора: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function showGarbageStats(SymfonyStyle $io, int $days): void
    {
        $io->section('Статистика мусора');

        $stats = [
            'Временные файлы' => $this->countOldFiles($this->projectDir . '/var/temp', $days),
            'Старые логи (> ' . $days . ' дней)' => $this->countOldFiles($this->projectDir . '/var/log', $days),
            'Старые сессии' => $this->countOldFiles($this->projectDir . '/var/sessions', $days),
            'Неиспользуемые загрузки' => $this->countOldFiles($this->projectDir . '/public/uploads', $days),
        ];

        $io->table(
            ['Тип мусора', 'Количество файлов'],
            array_map(fn($k, $v) => [$k, $v], array_keys($stats), array_values($stats)),
        );
    }

    private function cleanupTempFiles(SymfonyStyle $io, int $days, bool $dryRun): array
    {
        $io->section('Очистка временных файлов');

        $tempDirs = [
            $this->projectDir . '/var/temp',
            $this->projectDir . '/var/cache',
            $this->projectDir . '/var/tmp',
        ];

        $result = ['files_deleted' => 0, 'bytes_freed' => 0];

        foreach ($tempDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $cleaned = $this->cleanDirectory($dir, $days, $dryRun);
            $result['files_deleted'] += $cleaned['files_deleted'];
            $result['bytes_freed'] += $cleaned['bytes_freed'];
        }

        $io->writeln("✓ Временные файлы: удалено {$result['files_deleted']} файлов, освобождено " . $this->formatBytes($result['bytes_freed']));

        return $result;
    }

    private function cleanupOldLogs(SymfonyStyle $io, int $days, bool $dryRun): array
    {
        $io->section('Очистка старых логов');

        $logDir = $this->projectDir . '/var/log';
        
        if (!is_dir($logDir)) {
            $io->info('Директория логов не найдена');
            return ['files_deleted' => 0, 'bytes_freed' => 0];
        }

        // Удаляем старые файлы логов (кроме текущих)
        $result = $this->cleanDirectory($logDir, $days, $dryRun, ['dev.log', 'prod.log', 'test.log']);

        $io->writeln("✓ Логи: удалено {$result['files_deleted']} файлов, освобождено " . $this->formatBytes($result['bytes_freed']));

        return $result;
    }

    private function cleanupOldSessions(SymfonyStyle $io, int $days, bool $dryRun): array
    {
        $io->section('Очистка старых сессий');

        $sessionDir = $this->projectDir . '/var/sessions';
        
        if (!is_dir($sessionDir)) {
            $io->info('Директория сессий не найдена');
            return ['files_deleted' => 0, 'bytes_freed' => 0];
        }

        $result = $this->cleanDirectory($sessionDir, $days, $dryRun);

        $io->writeln("✓ Сессии: удалено {$result['files_deleted']} файлов, освобождено " . $this->formatBytes($result['bytes_freed']));

        return $result;
    }

    private function cleanupUnusedUploads(SymfonyStyle $io, int $days, bool $dryRun): array
    {
        $io->section('Очистка неиспользуемых загрузок');

        $uploadDir = $this->projectDir . '/public/uploads';
        
        if (!is_dir($uploadDir)) {
            $io->info('Директория загрузок не найдена');
            return ['files_deleted' => 0, 'bytes_freed' => 0];
        }

        $result = $this->cleanDirectory($uploadDir, $days, $dryRun, [], true);

        $io->writeln("✓ Загрузки: удалено {$result['files_deleted']} файлов, освобождено " . $this->formatBytes($result['bytes_freed']));

        return $result;
    }

    private function cleanDirectory(
        string $directory,
        int $days,
        bool $dryRun,
        array $excludeFiles = [],
        bool $removeEmptyDirs = false
    ): array {
        if (!is_dir($directory)) {
            return ['files_deleted' => 0, 'bytes_freed' => 0];
        }

        $now = time();
        $maxAge = $days * 24 * 3600;
        $filesDeleted = 0;
        $bytesFreed = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->isWritable()) {
                // Проверяем исключённые файлы
                if (in_array($file->getFilename(), $excludeFiles)) {
                    continue;
                }

                $fileAge = $now - $file->getMTime();

                // Удаляем файлы старше указанного периода
                if ($fileAge > $maxAge) {
                    $fileSize = $file->getSize();
                    
                    if ($dryRun) {
                        $filesDeleted++;
                        $bytesFreed += $fileSize;
                    } else {
                        if (unlink($file->getPathname())) {
                            $filesDeleted++;
                            $bytesFreed += $fileSize;
                        }
                    }
                }
            }
        }

        // Удаляем пустые директории если запрошено
        if ($removeEmptyDirs && !$dryRun) {
            foreach ($iterator as $dir) {
                if ($dir->isDir() && $dir->isWritable()) {
                    $files = scandir($dir->getPathname());
                    if ($files === array('.', '..')) {
                        rmdir($dir->getPathname());
                    }
                }
            }
        }

        return [
            'files_deleted' => $filesDeleted,
            'bytes_freed' => $bytesFreed,
        ];
    }

    private function countOldFiles(string $directory, int $days): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $now = time();
        $maxAge = $days * 24 * 3600;
        $count = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $fileAge = $now - $file->getMTime();
                if ($fileAge > $maxAge) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, \count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
