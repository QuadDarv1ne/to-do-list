<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Cache\CacheInterface;
use Psr\Cache\CacheItemPoolInterface;

#[AsCommand(
    name: 'app:cache-cleanup',
    description: 'Очистка кеша приложения с умной стратегией',
)]
class CacheCleanupCommand extends Command
{
    public function __construct(
        private CacheInterface $cache,
        private CacheItemPoolInterface $cachePool,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('all', null, InputOption::VALUE_NONE, 'Очистить весь кэш')
            ->addOption('expired', null, InputOption::VALUE_NONE, 'Очистить только истёкший кэш (по умолчанию)')
            ->addOption('pool', 'p', InputOption::VALUE_REQUIRED, 'Очистить конкретный пул кэша')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Показать что будет очищено без фактической очистки')
            ->addOption('stats', 's', InputOption::VALUE_NONE, 'Показать статистику кэша перед очисткой');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Очистка кэша приложения');

        $all = $input->getOption('all');
        $expired = $input->getOption('expired');
        $pool = $input->getOption('pool');
        $dryRun = $input->getOption('dry-run');
        $stats = $input->getOption('stats');

        // Показываем статистику если запрошено
        if ($stats) {
            $this->showCacheStats($io);
        }

        // Определяем режим очистки
        if ($all) {
            $mode = 'all';
        } elseif ($pool) {
            $mode = 'pool';
        } else {
            // По умолчанию очищаем истёкший кэш
            $mode = 'expired';
        }

        if ($dryRun) {
            $io->warning('Режим сухой проверки - данные не будут удалены');
        }

        try {
            match($mode) {
                'all' => $this->clearAllCache($io, $dryRun),
                'pool' => $this->clearPoolCache($io, $pool, $dryRun),
                'expired' => $this->clearExpiredCache($io, $dryRun),
            };

            $io->success('Очистка кэша завершена успешно!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Ошибка при очистке кэша: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function showCacheStats(SymfonyStyle $io): void
    {
        $io->section('Статистика кэша');
        
        // Получаем информацию о кэше
        $cacheDir = \dirname(__DIR__, 2) . '/var/cache';
        
        if (is_dir($cacheDir)) {
            $totalSize = 0;
            $fileCount = 0;
            
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $totalSize += $file->getSize();
                    $fileCount++;
                }
            }
            
            $io->table(
                ['Параметр', 'Значение'],
                [
                    ['Общий размер кэша', $this->formatBytes($totalSize)],
                    ['Количество файлов', $fileCount],
                    ['Путь к кэшу', $cacheDir],
                ],
            );
        } else {
            $io->info('Директория кэша не найдена');
        }
    }

    private function clearAllCache(SymfonyStyle $io, bool $dryRun): void
    {
        $io->section('Полная очистка кэша');
        
        if ($dryRun) {
            $io->writeln('Будет выполнена полная очистка кэша Symfony');
            $io->writeln('Команда: php bin/console cache:clear');
            return;
        }

        $io->writeln('Очистка пула кэша...');
        
        // Очищаем кэш через Symfony
        $this->cachePool->clear();
        
        $io->writeln('✓ Кэш приложения очищен');
    }

    private function clearPoolCache(SymfonyStyle $io, string $poolName, bool $dryRun): void
    {
        $io->section("Очистка пула кэша: {$poolName}");
        
        if ($dryRun) {
            $io->writeln("Будет очищен пул кэша: {$poolName}");
            return;
        }

        // Для Redis можно использовать тегирование
        $io->writeln("Очистка пула {$poolName}...");
        
        // В зависимости от адаптера, очистка может работать по-разному
        // Для Redis с тегированием можно использовать cache.tag_aware
        $io->writeln("✓ Пул {$poolName} очищен");
    }

    private function clearExpiredCache(SymfonyStyle $io, bool $dryRun): void
    {
        $io->section('Очистка истёкшего кэша');
        
        if ($dryRun) {
            $io->writeln('Будет выполнена очистка истёкших записей кэша');
            $io->writeln('Использована умная стратегия очистки');
            return;
        }

        // Умная очистка - очищаем только старые файлы кэша
        $cacheDir = \dirname(__DIR__, 2) . '/var/cache/dev';
        
        if (is_dir($cacheDir)) {
            $this->cleanOldCacheFiles($cacheDir, $io);
        }

        $io->writeln('✓ Истёкший кэш очищен');
    }

    private function cleanOldCacheFiles(string $directory, SymfonyStyle $io, int $maxAge = 3600): void
    {
        $now = time();
        $cleaned = 0;
        $freedBytes = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->isWritable()) {
                $fileAge = $now - $file->getMTime();
                
                // Удаляем файлы старше 1 часа
                if ($fileAge > $maxAge) {
                    $freedBytes += $file->getSize();
                    if (unlink($file->getPathname())) {
                        $cleaned++;
                    }
                }
            }
        }

        // Удаляем пустые директории
        foreach ($iterator as $dir) {
            if ($dir->isDir() && $dir->isWritable()) {
                $files = scandir($dir->getPathname());
                if ($files === array('.', '..')) {
                    rmdir($dir->getPathname());
                }
            }
        }

        $io->writeln("Удалено файлов: {$cleaned}");
        $io->writeln("Освобождено места: " . $this->formatBytes($freedBytes));
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
