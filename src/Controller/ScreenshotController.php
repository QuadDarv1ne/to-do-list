<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ScreenshotController extends AbstractController
{
    #[Route('/screenshots/{filename}', name: 'app_screenshot', requirements: ['filename' => '.+\.png$'])]
    public function serveScreenshot(string $filename): Response
    {
        // Определяем размеры на основе имени файла
        $width = 1280;
        $height = 720;
        
        if (str_contains($filename, 'tasks')) {
            $width = 750;
            $height = 1334;
        }
        
        // Создаем изображение
        $image = imagecreate($width, $height);
        
        // Цвета
        $bg = imagecolorallocate($image, 249, 250, 251); // #f9fafb
        $blue = imagecolorallocate($image, 13, 110, 253); // #0d6efd
        $gray = imagecolorallocate($image, 107, 114, 128); // #6b7280
        
        // Заливаем фон
        imagefill($image, 0, 0, $bg);
        
        // Рисуем заголовок
        $title = str_contains($filename, 'dashboard') ? 'Dashboard' : 'Tasks';
        imagestring($image, 5, $width/2 - 50, 50, $title, $blue);
        
        // Рисуем несколько прямоугольников как элементы интерфейса
        for ($i = 0; $i < 3; $i++) {
            $y = 150 + $i * 100;
            imagerectangle($image, 50, $y, $width - 50, $y + 60, $gray);
        }
        
        // Выводим изображение
        ob_start();
        imagepng($image);
        $imageData = ob_get_contents();
        ob_end_clean();
        
        imagedestroy($image);
        
        $response = new Response($imageData);
        $response->headers->set('Content-Type', 'image/png');
        $response->headers->set('Cache-Control', 'public, max-age=31536000');
        
        return $response;
    }
}