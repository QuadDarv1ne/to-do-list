<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:performance-check',
    description: 'Проверка производительности приложения',
)]
class PerformanceCheckCommand extends Command
{
    private string $projectRoot;

    public function __construct(string $projectDir)
    {
        parent::__construct();
        $this->projectRoot = $projectDir;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🚀 Проверка производительности');

        $checks = [
            'CSS оптимизация' => $this->checkCSS(),
            'JS оптимизация' => $this->checkJavaScript(),
            'Кэширование' => $this->checkCache(),
            'Изображения' => $this->checkImages(),
            'Конфигурация' => $this->checkConfig(),
        ];

        $this->displayResults($io, $checks);

        return Command::SUCCESS;
    }

    private function checkCSS(): array
    {
        $results = [];
        $cssDir = $this->projectRoot . '/public/css';

        // Проверка critical.css
        $criticalCss = $cssDir . '/critical.css';
        if (file_exists($criticalCss)) {
            $size = filesize($criticalCss);
            $results['Critical CSS'] = [
                'status' => $size < 15000 ? '✅' : '⚠️',
                'size' => $this->formatSize($size),
                'note' => $size < 15000 ? 'Оптимально' : 'Рекомендуется уменьшить',
            ];
        } else {
            $results['Critical CSS'] = [
                'status' => '❌',
                'size' => 'Не найден',
                'note' => 'Создайте public/css/critical.css',
            ];
        }

        // Подсчёт количества CSS файлов
        $cssFiles = glob($cssDir . '/*.css');
        $totalSize = array_sum(array_map('filesize', $cssFiles));

        $results['Всего CSS файлов'] = [
            'status' => '📊',
            'size' => \count($cssFiles) . ' файлов',
            'note' => 'Общий размер: ' . $this->formatSize($totalSize),
        ];

        // Проверка наличия min версий
        $minFiles = glob($cssDir . '/*.min.css');
        $results['Minified CSS'] = [
            'status' => \count($minFiles) > 0 ? '✅' : '⚠️',
            'size' => \count($minFiles) . ' файлов',
            'note' => \count($minFiles) > 0 ? 'Есть min версии' : 'Рекомендуется минификация',
        ];

        return $results;
    }

    private function checkJavaScript(): array
    {
        $results = [];
        $jsDir = $this->projectRoot . '/public/js';

        // Проверка performance-optimizer.js
        $optimizer = $jsDir . '/performance-optimizer.js';
        if (file_exists($optimizer)) {
            $results['Performance Optimizer'] = [
                'status' => '✅',
                'size' => $this->formatSize(filesize($optimizer)),
                'note' => 'Code splitting настроен',
            ];
        } else {
            $results['Performance Optimizer'] = [
                'status' => '❌',
                'size' => 'Не найден',
                'note' => 'Требуется для оптимизации',
            ];
        }

        // Подсчёт JS файлов
        $jsFiles = glob($jsDir . '/*.js');
        $totalSize = array_sum(array_map('filesize', $jsFiles));

        $results['Всего JS файлов'] = [
            'status' => '📊',
            'size' => \count($jsFiles) . ' файлов',
            'note' => 'Общий размер: ' . $this->formatSize($totalSize),
        ];

        // Lazy loader
        $lazyLoader = $jsDir . '/lazy-load.js';
        $results['Lazy Load Images'] = [
            'status' => file_exists($lazyLoader) ? '✅' : '❌',
            'size' => file_exists($lazyLoader) ? $this->formatSize(filesize($lazyLoader)) : 'Не найден',
            'note' => file_exists($lazyLoader) ? 'Оптимизация изображений' : 'Рекомендуется добавить',
        ];

        return $results;
    }

    private function checkCache(): array
    {
        $results = [];
        $cacheDir = $this->projectRoot . '/var/cache';

        // Проверка existence кэш директории
        if (is_dir($cacheDir)) {
            $results['Cache directory'] = [
                'status' => '✅',
                'size' => 'Существует',
                'note' => $this->getDirectorySize($cacheDir),
            ];
        } else {
            $results['Cache directory'] = [
                'status' => '❌',
                'size' => 'Не найден',
                'note' => 'Создайте директорию var/cache',
            ];
        }

        // Проверка .env
        $envFile = $this->projectRoot . '/.env';
        if (file_exists($envFile)) {
            $content = file_get_contents($envFile);
            $isProd = strpos($content, 'APP_ENV=prod') !== false;
            $isDebug = strpos($content, 'APP_DEBUG=1') !== false || strpos($content, 'APP_DEBUG=true') !== false;

            $results['APP_ENV'] = [
                'status' => $isProd ? '✅' : '⚠️',
                'size' => $isProd ? 'prod' : 'dev',
                'note' => $isProd ? 'Production режим' : 'Development режим',
            ];

            $results['APP_DEBUG'] = [
                'status' => !$isDebug ? '✅' : '⚠️',
                'size' => !$isDebug ? 'off' : 'on',
                'note' => !$isDebug ? 'Отключён (хорошо)' : 'Включён (только для dev)',
            ];
        }

        return $results;
    }

    private function checkImages(): array
    {
        $results = [];
        $uploadsDir = $this->projectRoot . '/public/uploads';

        if (!is_dir($uploadsDir)) {
            $results['Uploads directory'] = [
                'status' => '❌',
                'size' => 'Не найдена',
                'note' => 'Директория отсутствует',
            ];

            return $results;
        }

        // Поиск изображений
        $images = array_merge(
            glob($uploadsDir . '/*.jpg') ?: [],
            glob($uploadsDir . '/*.jpeg') ?: [],
            glob($uploadsDir . '/*.png') ?: [],
            glob($uploadsDir . '/*.gif') ?: [],
            glob($uploadsDir . '/*.webp') ?: [],
            glob($uploadsDir . '/*.svg') ?: [],
        );

        $webpCount = \count(glob($uploadsDir . '/*.webp') ?: []);
        $totalImages = \count($images);

        $results['Всего изображений'] = [
            'status' => '📊',
            'size' => $totalImages,
            'note' => 'WebP: ' . $webpCount . ' (' . ($totalImages > 0 ? round($webpCount / $totalImages * 100) : 0) . '%)',
        ];

        $results['WebP оптимизация'] = [
            'status' => $webpCount > 0 ? '✅' : '⚠️',
            'size' => $webpCount . ' WebP файлов',
            'note' => $webpCount > 0 ? 'Есть WebP версии' : 'Рекомендуется конвертация',
        ];

        return $results;
    }

    private function checkConfig(): array
    {
        $results = [];

        // Проверка .htaccess
        $htaccess = $this->projectRoot . '/public/.htaccess';
        if (file_exists($htaccess)) {
            $content = file_get_contents($htaccess);
            $hasExpires = strpos($content, 'ExpiresActive') !== false;
            $hasCacheControl = strpos($content, 'Cache-Control') !== false;

            $results['.htaccess кэширование'] = [
                'status' => ($hasExpires || $hasCacheControl) ? '✅' : '⚠️',
                'size' => 'Найдено',
                'note' => ($hasExpires || $hasCacheControl) ? 'Правила кэширования есть' : 'Добавьте правила кэширования',
            ];
        } else {
            $results['.htaccess'] = [
                'status' => '❌',
                'size' => 'Не найден',
                'note' => 'Создайте public/.htaccess для Apache',
            ];
        }

        // Проверка composer.json на оптимизацию
        $composer = $this->projectRoot . '/composer.json';
        if (file_exists($composer)) {
            $content = json_decode(file_get_contents($composer), true);
            $hasAutoload = isset($content['autoload']['psr-4']);

            $results['Composer autoload'] = [
                'status' => $hasAutoload ? '✅' : '⚠️',
                'size' => 'PSR-4',
                'note' => $hasAutoload ? 'Настроен' : 'Требуется настройка',
            ];
        }

        return $results;
    }

    private function displayResults(SymfonyStyle $io, array $checks): void
    {
        foreach ($checks as $category => $items) {
            $io->section($category);

            $rows = [];
            foreach ($items as $name => $data) {
                $rows[] = [
                    $data['status'],
                    $name,
                    $data['size'],
                    $data['note'],
                ];
            }

            $io->table(['', 'Параметр', 'Значение', 'Примечание'], $rows);
        }

        $io->newLine();
        $io->note('Для production рекомендуется: APP_ENV=prod, APP_DEBUG=0');
        $io->note('Минифицируйте CSS/JS и конвертируйте изображения в WebP');
    }

    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, \count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function getDirectorySize(string $path): string
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $this->formatSize($size);
    }
}
