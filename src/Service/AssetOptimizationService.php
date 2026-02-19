<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;

class AssetOptimizationService
{
    private Filesystem $filesystem;

    private string $publicDir;

    public function __construct(string $projectDir)
    {
        $this->filesystem = new Filesystem();
        $this->publicDir = $projectDir . '/public';
    }

    /**
     * Минификация CSS файлов
     */
    public function minifyCSS(): void
    {
        $cssDir = $this->publicDir . '/css';
        if (!is_dir($cssDir)) {
            return;
        }

        $files = glob($cssDir . '/*.css');
        foreach ($files as $file) {
            if (str_ends_with($file, '.min.css')) {
                continue; // Пропускаем уже минифицированные файлы
            }

            $content = file_get_contents($file);
            $minified = $this->minifyCSSContent($content);

            $minFile = str_replace('.css', '.min.css', $file);
            file_put_contents($minFile, $minified);
        }
    }

    /**
     * Минификация содержимого CSS
     */
    private function minifyCSSContent(string $css): string
    {
        // Удаляем комментарии
        $css = preg_replace('!/\\*[^*]*\\*+([^/][^*]*\\*+)*/!', '', $css);

        // Удаляем лишние пробелы и переносы строк
        $css = preg_replace('/\\s+/', ' ', $css);

        // Удаляем пробелы вокруг специальных символов
        $css = preg_replace('/\\s*([{}:;,>+~])\\s*/', '$1', $css);

        // Удаляем последнюю точку с запятой в блоке
        $css = preg_replace('/;(?=\\s*})/', '', $css);

        // Удаляем пробелы в начале и конце
        return trim($css);
    }

    /**
     * Объединение CSS файлов
     */
    public function combineCSS(array $files, string $outputFile): void
    {
        $combined = '';
        $cssDir = $this->publicDir . '/css';

        foreach ($files as $file) {
            $filePath = $cssDir . '/' . $file;
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                $combined .= "/* {$file} */\n" . $content . "\n\n";
            }
        }

        $outputPath = $cssDir . '/' . $outputFile;
        file_put_contents($outputPath, $this->minifyCSSContent($combined));
    }

    /**
     * Создание критического CSS
     */
    public function generateCriticalCSS(): void
    {
        $criticalRules = [
            // Базовые стили
            'body, html' => 'margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif',
            '.container-fluid' => 'width:100%;padding:0 15px',
            '.row' => 'display:flex;flex-wrap:wrap;margin:0 -15px',
            '.col-md-3, .col-md-4, .col-lg-4, .col-lg-6, .col-lg-8' => 'padding:0 15px;flex:1',

            // Карточки
            '.card' => 'background:#fff;border:1px solid #dee2e6;border-radius:0.375rem;margin-bottom:1rem',
            '.card-body' => 'padding:1rem',
            '.card-header' => 'padding:0.75rem 1rem;background:#f8f9fa;border-bottom:1px solid #dee2e6',

            // Кнопки
            '.btn' => 'display:inline-block;padding:0.375rem 0.75rem;border:1px solid transparent;border-radius:0.375rem;text-decoration:none;cursor:pointer',
            '.btn-primary' => 'background:#0d6efd;border-color:#0d6efd;color:#fff',
            '.btn-success' => 'background:#198754;border-color:#198754;color:#fff',

            // Утилиты
            '.text-center' => 'text-align:center',
            '.mb-3' => 'margin-bottom:1rem',
            '.d-flex' => 'display:flex',
            '.justify-content-between' => 'justify-content:space-between',
            '.align-items-center' => 'align-items:center',

            // Скелетон загрузки
            '.skeleton' => 'background:linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%);background-size:200% 100%;animation:loading 1.5s infinite',
            '@keyframes loading' => '0%{background-position:200% 0}100%{background-position:-200% 0}',
        ];

        $criticalCSS = '';
        foreach ($criticalRules as $selector => $rules) {
            if (str_starts_with($selector, '@keyframes')) {
                $criticalCSS .= "{$selector}{{$rules}}\n";
            } else {
                $criticalCSS .= "{$selector}{{$rules}}\n";
            }
        }

        file_put_contents($this->publicDir . '/css/critical.min.css', $criticalCSS);
    }

    /**
     * Оптимизация изображений (базовая)
     */
    public function optimizeImages(): void
    {
        $uploadsDir = $this->publicDir . '/uploads';
        if (!is_dir($uploadsDir)) {
            return;
        }

        $images = glob($uploadsDir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
        foreach ($images as $image) {
            $this->optimizeImage($image);
        }
    }

    /**
     * Оптимизация отдельного изображения
     */
    private function optimizeImage(string $imagePath): void
    {
        $info = getimagesize($imagePath);
        if (!$info) {
            return;
        }

        $mime = $info['mime'];
        $quality = 85; // Качество сжатия

        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($imagePath);
                if ($image) {
                    imagejpeg($image, $imagePath, $quality);
                    imagedestroy($image);
                }

                break;
            case 'image/png':
                $image = imagecreatefrompng($imagePath);
                if ($image) {
                    imagepng($image, $imagePath, 9); // Максимальное сжатие для PNG
                    imagedestroy($image);
                }

                break;
        }
    }

    /**
     * Создание WebP версий изображений
     */
    public function createWebPVersions(): void
    {
        if (!\function_exists('imagewebp')) {
            return; // WebP не поддерживается
        }

        $uploadsDir = $this->publicDir . '/uploads';
        if (!is_dir($uploadsDir)) {
            return;
        }

        $images = glob($uploadsDir . '/*.{jpg,jpeg,png}', GLOB_BRACE);
        foreach ($images as $image) {
            $webpPath = preg_replace('/\\.(jpg|jpeg|png)$/i', '.webp', $image);

            if (file_exists($webpPath)) {
                continue; // WebP версия уже существует
            }

            $this->convertToWebP($image, $webpPath);
        }
    }

    /**
     * Конвертация в WebP
     */
    private function convertToWebP(string $source, string $destination): void
    {
        $info = getimagesize($source);
        if (!$info) {
            return;
        }

        $image = match($info['mime']) {
            'image/jpeg' => imagecreatefromjpeg($source),
            'image/png' => imagecreatefrompng($source),
            default => null
        };

        if ($image) {
            imagewebp($image, $destination, 80);
            imagedestroy($image);
        }
    }

    /**
     * Генерация Service Worker для кэширования
     */
    public function generateServiceWorker(): void
    {
        $swContent = <<<JS
const CACHE_NAME = 'task-manager-v1';
const urlsToCache = [
    '/',
    '/css/critical.min.css',
    '/css/main.css',
    '/js/app.js',
    '/manifest.json'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(urlsToCache))
    );
});

self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                if (response) {
                    return response;
                }
                return fetch(event.request);
            })
    );
});
JS;

        file_put_contents($this->publicDir . '/sw.js', $swContent);
    }

    /**
     * Полная оптимизация всех ресурсов
     */
    public function optimizeAll(): void
    {
        $this->minifyCSS();
        $this->generateCriticalCSS();
        $this->optimizeImages();
        $this->createWebPVersions();
        $this->generateServiceWorker();

        // Объединяем основные CSS файлы
        $this->combineCSS([
            'main.css',
            'components.css',
            'dashboard-widgets.css',
        ], 'combined.min.css');
    }
}
