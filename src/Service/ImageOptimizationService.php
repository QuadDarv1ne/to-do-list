<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Сервис для оптимизации изображений
 */
class ImageOptimizationService
{
    private string $uploadsDir;

    private array $supportedFormats = ['jpg', 'jpeg', 'png', 'gif'];

    public function __construct(
        string $projectDir,
        private LoggerInterface $logger,
    ) {
        $this->uploadsDir = $projectDir . '/public/uploads';
    }

    /**
     * Оптимизация всех изображений в директории
     */
    public function optimizeAll(): array
    {
        $results = [
            'processed' => 0,
            'optimized' => 0,
            'errors' => 0,
            'saved_bytes' => 0,
        ];

        if (!is_dir($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0755, true);

            return $results;
        }

        $pattern = $this->uploadsDir . '/*.{' . implode(',', $this->supportedFormats) . '}';
        $images = glob($pattern, GLOB_BRACE);

        foreach ($images as $imagePath) {
            $results['processed']++;

            try {
                $originalSize = filesize($imagePath);
                $optimized = $this->optimizeImage($imagePath);

                if ($optimized) {
                    $newSize = filesize($imagePath);
                    $saved = $originalSize - $newSize;

                    if ($saved > 0) {
                        $results['optimized']++;
                        $results['saved_bytes'] += $saved;
                    }
                }
            } catch (\Exception $e) {
                $results['errors']++;
                $this->logger->error('Image optimization failed', [
                    'file' => basename($imagePath),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Оптимизация отдельного изображения
     */
    public function optimizeImage(string $imagePath): bool
    {
        if (!file_exists($imagePath)) {
            return false;
        }

        $info = getimagesize($imagePath);
        if (!$info) {
            return false;
        }

        $mime = $info['mime'];

        try {
            switch ($mime) {
                case 'image/jpeg':
                    return $this->optimizeJpeg($imagePath);
                case 'image/png':
                    return $this->optimizePng($imagePath);
                case 'image/gif':
                    return $this->optimizeGif($imagePath);
                default:
                    return false;
            }
        } catch (\Exception $e) {
            $this->logger->error('Image optimization error', [
                'file' => basename($imagePath),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Оптимизация JPEG
     */
    private function optimizeJpeg(string $path): bool
    {
        $image = imagecreatefromjpeg($path);
        if (!$image) {
            return false;
        }

        // Сохраняем с качеством 85%
        $result = imagejpeg($image, $path, 85);
        imagedestroy($image);

        return $result;
    }

    /**
     * Оптимизация PNG
     */
    private function optimizePng(string $path): bool
    {
        $image = imagecreatefrompng($path);
        if (!$image) {
            return false;
        }

        // Включаем сжатие
        imagesavealpha($image, true);
        $result = imagepng($image, $path, 9);
        imagedestroy($image);

        return $result;
    }

    /**
     * Оптимизация GIF
     */
    private function optimizeGif(string $path): bool
    {
        $image = imagecreatefromgif($path);
        if (!$image) {
            return false;
        }

        $result = imagegif($image, $path);
        imagedestroy($image);

        return $result;
    }

    /**
     * Создание миниатюр
     */
    public function createThumbnail(string $sourcePath, string $destPath, int $maxWidth = 200, int $maxHeight = 200): bool
    {
        if (!file_exists($sourcePath)) {
            return false;
        }

        $info = getimagesize($sourcePath);
        if (!$info) {
            return false;
        }

        list($width, $height) = $info;
        $mime = $info['mime'];

        // Вычисляем новые размеры
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);

        // Создаем исходное изображение
        $source = match($mime) {
            'image/jpeg' => imagecreatefromjpeg($sourcePath),
            'image/png' => imagecreatefrompng($sourcePath),
            'image/gif' => imagecreatefromgif($sourcePath),
            default => null
        };

        if (!$source) {
            return false;
        }

        // Создаем миниатюру
        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);

        // Для PNG сохраняем прозрачность
        if ($mime === 'image/png') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Сохраняем миниатюру
        $result = match($mime) {
            'image/jpeg' => imagejpeg($thumbnail, $destPath, 85),
            'image/png' => imagepng($thumbnail, $destPath, 9),
            'image/gif' => imagegif($thumbnail, $destPath),
            default => false
        };

        imagedestroy($source);
        imagedestroy($thumbnail);

        return $result;
    }

    /**
     * Создание WebP версий
     */
    public function createWebPVersions(): array
    {
        $results = [
            'processed' => 0,
            'created' => 0,
            'errors' => 0,
        ];

        if (!\function_exists('imagewebp')) {
            $this->logger->warning('WebP support not available');

            return $results;
        }

        if (!is_dir($this->uploadsDir)) {
            return $results;
        }

        $pattern = $this->uploadsDir . '/*.{jpg,jpeg,png}';
        $images = glob($pattern, GLOB_BRACE);

        foreach ($images as $imagePath) {
            $results['processed']++;

            $webpPath = preg_replace('/\\.(jpg|jpeg|png)$/i', '.webp', $imagePath);

            if (file_exists($webpPath)) {
                continue;
            }

            try {
                if ($this->convertToWebP($imagePath, $webpPath)) {
                    $results['created']++;
                }
            } catch (\Exception $e) {
                $results['errors']++;
                $this->logger->error('WebP conversion failed', [
                    'file' => basename($imagePath),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Конвертация в WebP
     */
    private function convertToWebP(string $source, string $destination): bool
    {
        $info = getimagesize($source);
        if (!$info) {
            return false;
        }

        $image = match($info['mime']) {
            'image/jpeg' => imagecreatefromjpeg($source),
            'image/png' => imagecreatefrompng($source),
            default => null
        };

        if (!$image) {
            return false;
        }

        $result = imagewebp($image, $destination, 80);
        imagedestroy($image);

        return $result;
    }

    /**
     * Получение статистики по изображениям
     */
    public function getStatistics(): array
    {
        if (!is_dir($this->uploadsDir)) {
            return [
                'total_images' => 0,
                'total_size' => 0,
                'by_format' => [],
            ];
        }

        $stats = [
            'total_images' => 0,
            'total_size' => 0,
            'by_format' => [],
        ];

        $pattern = $this->uploadsDir . '/*.{' . implode(',', $this->supportedFormats) . ',webp}';
        $images = glob($pattern, GLOB_BRACE);

        foreach ($images as $imagePath) {
            $stats['total_images']++;
            $size = filesize($imagePath);
            $stats['total_size'] += $size;

            $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
            if (!isset($stats['by_format'][$ext])) {
                $stats['by_format'][$ext] = [
                    'count' => 0,
                    'size' => 0,
                ];
            }

            $stats['by_format'][$ext]['count']++;
            $stats['by_format'][$ext]['size'] += $size;
        }

        return $stats;
    }
}
