<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageOptimizationService
{
    private const MAX_WIDTH = 1920;
    private const MAX_HEIGHT = 1080;
    private const QUALITY = 85;

    /**
     * Оптимизировать изображение
     */
    public function optimize(UploadedFile $file): array
    {
        $filename = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());

        // Проверяем, является ли файл изображением
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            return ['error' => 'Неподдерживаемый формат'];
        }

        $result = [
            'original_name' => $filename,
            'original_size' => $file->getSize(),
            'optimized_name' => null,
            'optimized_size' => null,
            'saved_bytes' => 0,
            'saved_percent' => 0,
        ];

        try {
            // Создаём изображение из файла
            $image = $this->createImageFromFile($file);
            
            if (!$image) {
                return ['error' => 'Не удалось создать изображение'];
            }

            // Изменяем размер если нужно
            $image = $this->resizeIfNeeded($image);

            // Конвертируем в WebP для лучшего сжатия
            $optimizedFilename = $this->convertToWebP($image, $filename);
            
            $result['optimized_name'] = $optimizedFilename;
            $result['optimized_size'] = filesize($this->getUploadDir() . '/' . $optimizedFilename);
            $result['saved_bytes'] = $result['original_size'] - $result['optimized_size'];
            $result['saved_percent'] = round(($result['saved_bytes'] / $result['original_size']) * 100, 2);

            // Освобождаем память
            imagedestroy($image);

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }

        return $result;
    }

    /**
     * Создать GD изображение из файла
     */
    private function createImageFromFile(UploadedFile $file): \GdImage|false
    {
        $extension = strtolower($file->getClientOriginalExtension());
        
        $imageCreate = match($extension) {
            'jpg', 'jpeg' => 'imagecreatefromjpeg',
            'png' => 'imagecreatefrompng',
            'gif' => 'imagecreatefromgif',
            'webp' => 'imagecreatefromwebp',
            default => null,
        };

        if (!$imageCreate) {
            return false;
        }

        return $imageCreate($file->getPathname());
    }

    /**
     * Изменить размер если изображение слишком большое
     */
    private function resizeIfNeeded(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= self::MAX_WIDTH && $height <= self::MAX_HEIGHT) {
            return $image;
        }

        // Вычисляем новые размеры
        $ratio = min(self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height);
        $newWidth = (int) ($width * $ratio);
        $newHeight = (int) ($height * $ratio);

        // Создаём новое изображение
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        
        // Сохраняем прозрачность для PNG
        if (imageistruecolor($image)) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }

        // Изменяем размер
        imagecopyresampled(
            $resized, $image,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $width, $height
        );

        imagedestroy($image);

        return $resized;
    }

    /**
     * Конвертировать в WebP
     */
    private function convertToWebP(\GdImage $image, string $filename): string
    {
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $webpFilename = $baseName . '.webp';
        $filepath = $this->getUploadDir() . '/' . $webpFilename;

        imagewebp($image, $filepath, self::QUALITY);

        return $webpFilename;
    }

    /**
     * Получить директорию для загрузки
     */
    private function getUploadDir(): string
    {
        return 'public/uploads/images';
    }

    /**
     * Сгенерировать thumbnail
     */
    public function createThumbnail(UploadedFile $file, int $width = 200, int $height = 200): ?string
    {
        $image = $this->createImageFromFile($file);
        
        if (!$image) {
            return null;
        }

        // Создаём thumbnail
        $thumb = imagecreatetruecolor($width, $height);
        
        // Сохраняем прозрачность
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);

        // Вычисляем размеры для crop
        $srcWidth = imagesx($image);
        $srcHeight = imagesy($image);
        
        $ratio = max($width / $srcWidth, $height / $srcHeight);
        $srcX = ($srcWidth - $width / $ratio) / 2;
        $srcY = ($srcHeight - $height / $ratio) / 2;

        imagecopyresampled(
            $thumb, $image,
            0, 0, $srcX, $srcY,
            $width, $height,
            $width / $ratio, $height / $ratio
        );

        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $thumbFilename = $baseName . '_thumb.webp';
        $filepath = $this->getUploadDir() . '/' . $thumbFilename;

        imagewebp($thumb, $filepath, 80);

        imagedestroy($image);
        imagedestroy($thumb);

        return $thumbFilename;
    }
}
