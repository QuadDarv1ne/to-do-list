<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Сервис для создания скриншотов страниц сайта
 */
class ScreenshotService
{
    private const SCREENSHOT_DIR = 'screenshots';
    
    public function __construct(
        private readonly string $projectRoot,
        private readonly SluggerInterface $slugger,
        private readonly ?string $screenshotApiUrl = null,
        private readonly ?string $screenshotApiKey = null,
    ) {
    }

    /**
     * Сделать скриншот страницы
     * 
     * @param string $url URL страницы (полный или относительный)
     * @param string|null $filename Имя файла (если null, генерируется автоматически)
     * @param array $options Опции скриншота
     * 
     * @return array{success: bool, file?: string, error?: string, url?: string}
     */
    public function takeScreenshot(
        string $url,
        ?string $filename = null,
        array $options = []
    ): array {
        // Нормализация URL
        $normalizedUrl = $this->normalizeUrl($url);
        
        // Генерация имени файла
        $filename = $filename ?? $this->generateFilename($normalizedUrl);
        $filepath = $this->getScreenshotPath($filename);
        
        // Создаём директорию если не существует
        $filesystem = new Filesystem();
        $dir = dirname($filepath);
        if (!$filesystem->exists($dir)) {
            $filesystem->mkdir($dir, 0755);
        }
        
        // Если есть внешний API - используем его
        if ($this->screenshotApiUrl && $this->screenshotApiUrl !== 'null') {
            return $this->takeScreenshotViaApi($normalizedUrl, $filepath, $options);
        }
        
        // Иначе генерируем HTML-превью
        return $this->generateHtmlPreview($normalizedUrl, $filepath, $options);
    }

    /**
     * Сделать скриншоты нескольких страниц
     */
    public function takeMultipleScreenshots(
        array $urls,
        array $options = []
    ): array {
        $results = [];
        
        foreach ($urls as $url => $filename) {
            $key = is_numeric($url) ? $filename : $url;
            $file = is_numeric($url) ? null : $filename;
            
            $results[$key] = $this->takeScreenshot(
                is_numeric($url) ? $filename : $url,
                $file,
                $options
            );
        }
        
        return $results;
    }

    /**
     * Получить список всех скриншотов
     */
    public function getScreenshotList(): array
    {
        $dir = $this->projectRoot . '/public/' . self::SCREENSHOT_DIR;
        
        if (!is_dir($dir)) {
            return [];
        }
        
        $files = scandir($dir);
        $screenshots = [];
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filepath = $dir . '/' . $file;
            if (is_file($filepath)) {
                $screenshots[] = [
                    'filename' => $file,
                    'url' => '/screenshots/' . $file,
                    'size' => filesize($filepath),
                    'created' => filemtime($filepath),
                ];
            }
        }
        
        // Сортировка по дате создания (новые первыми)
        usort($screenshots, fn($a, $b) => $b['created'] - $a['created']);
        
        return $screenshots;
    }

    /**
     * Удалить скриншот
     */
    public function deleteScreenshot(string $filename): bool
    {
        $filepath = $this->getScreenshotPath($filename);
        
        if (file_exists($filepath)) {
            unlink($filepath);
            return true;
        }
        
        return false;
    }

    /**
     * Очистить старые скриншоты
     * 
     * @param int $olderThanDays Удалять скриншоты старше N дней
     */
    public function cleanupOldScreenshots(int $olderThanDays = 30): int
    {
        $dir = $this->projectRoot . '/public/' . self::SCREENSHOT_DIR;
        
        if (!is_dir($dir)) {
            return 0;
        }
        
        $deleted = 0;
        $threshold = time() - ($olderThanDays * 24 * 60 * 60);
        
        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filepath = $dir . '/' . $file;
            if (is_file($filepath) && filemtime($filepath) < $threshold) {
                unlink($filepath);
                $deleted++;
            }
        }
        
        return $deleted;
    }

    /**
     * Нормализация URL
     */
    private function normalizeUrl(string $url): string
    {
        // Если URL относительный - добавляем базовый
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = ltrim($url, '/');
            $url = 'http://localhost:8080/' . $url;
        }
        
        return $url;
    }

    /**
     * Генерация имени файла
     */
    private function generateFilename(string $url): string
    {
        // Если URL пустой или null - используем дефолтное имя
        if (empty($url) || $url === 'null') {
            return 'page-' . time() . '.html';
        }
        
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? 'page';
        $host = $parsed['host'] ?? 'localhost';
        
        $slug = $this->slugger->slug(str_replace('/', '-', trim($path, '/')));
        
        return $host . '-' . $slug . '-' . time() . '.html';
    }

    /**
     * Получить полный путь к файлу скриншота
     */
    private function getScreenshotPath(string $filename): string
    {
        return $this->projectRoot . '/public/' . self::SCREENSHOT_DIR . '/' . $filename;
    }

    /**
     * Сделать скриншот через внешний API
     */
    private function takeScreenshotViaApi(
        string $url,
        string $filepath,
        array $options
    ): array {
        $httpClient = HttpClient::create();
        
        $defaultOptions = [
            'width' => 1920,
            'height' => 1080,
            'fullPage' => true,
            'format' => 'png',
        ];
        
        $apiOptions = array_merge($defaultOptions, $options);
        
        try {
            $response = $httpClient->request('POST', $this->screenshotApiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->screenshotApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'url' => $url,
                    ...$apiOptions,
                ],
            ]);
            
            $content = $response->getContent();
            
            // Если API возвращает бинарные данные
            if (str_starts_with($content, "\x89PNG") || str_starts_with($content, "\xFF\xD8")) {
                file_put_contents($filepath, $content);
                
                return [
                    'success' => true,
                    'file' => '/screenshots/' . basename($filepath),
                    'url' => $url,
                ];
            }
            
            // Если API возвращает JSON
            $data = json_decode($content, true);
            
            if (isset($data['screenshot'])) {
                // Base64_encoded изображение
                $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data['screenshot']));
                file_put_contents($filepath, $imageData);
                
                return [
                    'success' => true,
                    'file' => '/screenshots/' . basename($filepath),
                    'url' => $url,
                ];
            }
            
            if (isset($data['url'])) {
                // URL на готовый скриншот
                $imageResponse = $httpClient->request('GET', $data['url']);
                file_put_contents($filepath, $imageResponse->getContent());
                
                return [
                    'success' => true,
                    'file' => '/screenshots/' . basename($filepath),
                    'url' => $url,
                ];
            }
            
            return [
                'success' => false,
                'error' => 'Неизвестный формат ответа API',
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Сгенерировать HTML-превью (заглушка, если нет API)
     */
    private function generateHtmlPreview(
        string $url,
        string $filepath,
        array $options
    ): array {
        // Создаём HTML-файл с информацией о скриншоте
        $htmlPath = preg_replace('/\.png$/', '.html', $filepath);
        
        $html = <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Скриншот: $url</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 { color: #333; margin-top: 0; }
        .info { color: #666; margin: 16px 0; }
        .url { 
            background: #f0f0f0; 
            padding: 12px; 
            border-radius: 4px; 
            font-family: monospace;
            word-break: break-all;
        }
        .placeholder {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px;
            text-align: center;
            border-radius: 8px;
            margin: 20px 0;
        }
        .placeholder h2 { margin: 0 0 10px 0; }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            margin-top: 20px;
        }
        .btn:hover { background: #5a6fd6; }
    </style>
</head>
<body>
    <div class="card">
        <h1>📸 Скриншот страницы</h1>
        <div class="info">
            <strong>Дата создания:</strong> {{date}}<br>
            <strong>Страница:</strong>
        </div>
        <div class="url">$url</div>
        
        <div class="placeholder">
            <h2>Скриншот в разработке</h2>
            <p>Для генерации скриншотов необходимо настроить внешний API</p>
            <p>Например: <a href="https://api.screenshotmachine.com" target="_blank">ScreenshotMachine</a>, 
                       <a href="https://www.browserless.io" target="_blank">Browserless</a></p>
        </div>
        
        <a href="$url" target="_blank" class="btn">Открыть страницу →</a>
    </div>
</body>
</html>
HTML;
        
        $html = str_replace('{{date}}', date('d.m.Y H:i:s'), $html);
        file_put_contents($htmlPath, $html);
        
        return [
            'success' => true,
            'file' => '/screenshots/' . basename($htmlPath),
            'url' => $url,
            'note' => 'Создан HTML-файл. Для PNG-скриншотов настройте screenshot API в .env',
        ];
    }
}
