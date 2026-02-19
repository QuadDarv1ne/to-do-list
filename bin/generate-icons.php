#!/usr/bin/env php
<?php

/**
 * Генератор иконок для PWA
 */

$sizes = [72, 96, 128, 144, 152, 192, 384, 512];
$iconsDir = __DIR__ . '/../public/icons';

// Создаем директорию если не существует
if (!is_dir($iconsDir)) {
    mkdir($iconsDir, 0755, true);
}

foreach ($sizes as $size) {
    $filename = sprintf('icon-%dx%d.png', $size, $size);
    $filepath = $iconsDir . '/' . $filename;
    
    // Создаем изображение
    $image = imagecreate($size, $size);
    
    // Цвета
    $blue = imagecolorallocate($image, 102, 126, 234); // #667eea
    $white = imagecolorallocate($image, 255, 255, 255);
    
    // Заливаем фон синим
    imagefill($image, 0, 0, $blue);
    
    // Рисуем галочку
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
    
    // Сохраняем
    imagepng($image, $filepath);
    imagedestroy($image);
    
    echo "✓ Создана иконка: {$filename}\n";
}

// Создаем shortcuts иконки
$shortcuts = ['new', 'tasks', 'calendar', 'kanban'];
foreach ($shortcuts as $shortcut) {
    $filename = "shortcut-{$shortcut}.png";
    $filepath = $iconsDir . '/' . $filename;
    
    $image = imagecreate(96, 96);
    $blue = imagecolorallocate($image, 102, 126, 234);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefill($image, 0, 0, $blue);
    
    // Простой квадрат в центре
    imagefilledrectangle($image, 30, 30, 66, 66, $white);
    
    imagepng($image, $filepath);
    imagedestroy($image);
    
    echo "✓ Создана иконка: {$filename}\n";
}

echo "\n✅ Все иконки успешно созданы!\n";
