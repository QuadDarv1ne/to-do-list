<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private string $environment
    ) {
    }

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

        // Отключаем строгие заголовки в dev окружении
        if ($this->environment === 'dev') {
            return;
        }

        $response = $event->getResponse();
        $headers = $response->headers;

        // CSP теперь обрабатывается через NelmioSecurityBundle
        // Здесь добавляем только дополнительные заголовки безопасности

        // OWASP recommended security headers
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-XSS-Protection', '0'); // Modern browsers use CSP instead
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy (formerly Feature-Policy)
        $headers->set('Permissions-Policy', implode(', ', [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=()',
            'usb=()',
            'magnetometer=()',
            'gyroscope=()',
            'accelerometer=()'
        ]));

        // HSTS (only in production with HTTPS)
        if ($event->getRequest()->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Cross-Origin policies
        $headers->set('Cross-Origin-Embedder-Policy', 'require-corp');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        // Remove server information
        $headers->remove('X-Powered-By');
        $headers->remove('Server');
    }
}
