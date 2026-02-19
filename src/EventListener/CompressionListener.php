<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Автоматическое сжатие HTTP ответов
 */
class CompressionListener implements EventSubscriberInterface
{
    private int $minSize = 1024; // Минимальный размер для сжатия (1KB)
    private array $compressibleTypes = [
        'text/html',
        'text/css',
        'text/javascript',
        'application/javascript',
        'application/json',
        'application/xml',
        'text/xml',
        'image/svg+xml',
        'application/x-javascript'
    ];

    public function __construct(
        private bool $enabled = true
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -256],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Проверяем, поддерживает ли клиент сжатие
        $acceptEncoding = $request->headers->get('Accept-Encoding', '');
        
        if (!str_contains($acceptEncoding, 'gzip')) {
            return;
        }

        // Проверяем, не сжат ли уже контент
        if ($response->headers->has('Content-Encoding')) {
            return;
        }

        // Проверяем тип контента
        $contentType = $response->headers->get('Content-Type', '');
        if (!$this->isCompressible($contentType)) {
            return;
        }

        $content = $response->getContent();
        
        // Проверяем минимальный размер
        if (strlen($content) < $this->minSize) {
            return;
        }

        // Сжимаем контент
        $compressed = gzencode($content, 6); // Уровень сжатия 6 (баланс скорость/размер)
        
        if ($compressed === false) {
            return;
        }

        // Проверяем, что сжатие дало результат
        $originalSize = strlen($content);
        $compressedSize = strlen($compressed);
        
        if ($compressedSize >= $originalSize) {
            return; // Сжатие не эффективно
        }

        // Применяем сжатый контент
        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string)$compressedSize);
        $response->headers->set('X-Compression-Ratio', round(($originalSize - $compressedSize) / $originalSize * 100, 2) . '%');
    }

    /**
     * Проверка, можно ли сжать тип контента
     */
    private function isCompressible(string $contentType): bool
    {
        foreach ($this->compressibleTypes as $type) {
            if (str_contains($contentType, $type)) {
                return true;
            }
        }
        return false;
    }
}
