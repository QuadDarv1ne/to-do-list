<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class IconController extends AbstractController
{
    #[Route('/icons/{filename}', name: 'app_icon', requirements: ['filename' => '.+\\.(png|ico|svg)$'])]
    public function serveIcon(string $filename): Response
    {
        // Создаем простую PNG иконку программно
        $size = $this->extractSizeFromFilename($filename);

        // Создаем изображение
        $image = imagecreate($size, $size);

        // Цвета
        $blue = imagecolorallocate($image, 13, 110, 253); // #0d6efd
        $white = imagecolorallocate($image, 255, 255, 255);

        // Заливаем фон синим
        imagefill($image, 0, 0, $blue);

        // Рисуем простую галочку
        $thickness = max(2, $size / 32);
        imagesetthickness($image, $thickness);

        // Координаты галочки (пропорционально размеру)
        $x1 = $size * 0.25;
        $y1 = $size * 0.5;
        $x2 = $size * 0.4;
        $y2 = $size * 0.65;
        $x3 = $size * 0.75;
        $y3 = $size * 0.35;

        // Рисуем галочку
        imageline($image, $x1, $y1, $x2, $y2, $white);
        imageline($image, $x2, $y2, $x3, $y3, $white);

        // Выводим изображение
        ob_start();
        imagepng($image);
        $imageData = ob_get_contents();
        ob_end_clean();

        imagedestroy($image);

        $response = new Response($imageData);
        $response->headers->set('Content-Type', 'image/png');
        $response->headers->set('Cache-Control', 'public, max-age=31536000'); // Кэш на год

        return $response;
    }

    private function extractSizeFromFilename(string $filename): int
    {
        // Извлекаем размер из имени файла (например, icon-512x512.png)
        if (preg_match('/(\\d+)x\\d+/', $filename, $matches)) {
            return (int) $matches[1];
        }

        // Размер по умолчанию
        return 512;
    }
}
