<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Автоматическое добавление заголовков кэширования
 */
class CacheHeadersListener implements EventSubscriberInterface
{
    private array $cacheableExtensions = [
        'css' => 31536000,  // 1 год
        'js' => 31536000,   // 1 год
        'jpg' => 2592000,   // 30 дней
        'jpeg' => 2592000,  // 30 дней
        'png' => 2592000,   // 30 дней
        'gif' => 2592000,   // 30 дней
        'webp' => 2592000,  // 30 дней
        'svg' => 2592000,   // 30 дней
        'ico' => 2592000,   // 30 дней
        'woff' => 31536000, // 1 год
        'woff2' => 31536000,// 1 год
        'ttf' => 31536000,  // 1 год
        'eot' => 31536000,  // 1 год
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Добавляем заголовки безопасности
        $this->addSecurityHeaders($response);

        // Добавляем заголовки кэширования для статических ресурсов
        $path = $request->getPathInfo();
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        if (isset($this->cacheableExtensions[$extension])) {
            $maxAge = $this->cacheableExtensions[$extension];
            
            $response->headers->set('Cache-Control', "public, max-age={$maxAge}, immutable");
            $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
            
            // ETag для валидации кэша
            if (!$response->headers->has('ETag')) {
                $etag = md5($response->getContent());
                $response->headers->set('ETag', '"' . $etag . '"');
            }
        }

        // Сжатие для текстовых ресурсов
        if ($this->isCompressible($response->headers->get('Content-Type'))) {
            $response->headers->set('Vary', 'Accept-Encoding');
        }
    }

    /**
     * Добавление заголовков безопасности
     */
    private function addSecurityHeaders($response): void
    {
        // Защита от XSS
        if (!$response->headers->has('X-Content-Type-Options')) {
            $response->headers->set('X-Content-Type-Options', 'nosniff');
        }

        // Защита от clickjacking
        if (!$response->headers->has('X-Frame-Options')) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        // XSS Protection
        if (!$response->headers->has('X-XSS-Protection')) {
            $response->headers->set('X-XSS-Protection', '1; mode=block');
        }

        // Referrer Policy
        if (!$response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
    }

    /**
     * Проверка, можно ли сжать контент
     */
    private function isCompressible(?string $contentType): bool
    {
        if (!$contentType) {
            return false;
        }

        $compressibleTypes = [
            'text/html',
            'text/css',
            'text/javascript',
            'application/javascript',
            'application/json',
            'application/xml',
            'text/xml',
            'image/svg+xml'
        ];

        foreach ($compressibleTypes as $type) {
            if (str_contains($contentType, $type)) {
                return true;
            }
        }

        return false;
    }
}
