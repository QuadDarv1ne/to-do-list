#!/usr/bin/env php
<?php

$dir = __DIR__ . '/../public/screenshots';

if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$screenshots = [
    ['dashboard.png', 1280, 720],
    ['tasks.png', 750, 1334]
];

foreach ($screenshots as $data) {
    list($name, $width, $height) = $data;
    $img = imagecreate($width, $height);
    $bg = imagecolorallocate($img, 248, 250, 252);
    $blue = imagecolorallocate($img, 102, 126, 234);
    $white = imagecolorallocate($img, 255, 255, 255);
    
    imagefill($img, 0, 0, $bg);
    imagefilledrectangle($img, 50, 50, $width-50, $height-50, $blue);
    
    // Добавляем текст
    $text = str_replace('.png', '', $name);
    $text = ucfirst($text);
    imagestring($img, 5, $width/2 - 50, $height/2, $text, $white);
    
    imagepng($img, $dir . '/' . $name);
    echo "✓ Создан скриншот: $name\n";
}

echo "\n✅ Скриншоты созданы!\n";
