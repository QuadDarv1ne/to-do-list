<?php

namespace App\EventListener;

use App\Service\QueryPerformanceMonitor;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Слушатель для автоматического мониторинга производительности
 */
class QueryPerformanceListener implements EventSubscriberInterface
{
    private bool $enabled = false;

    public function __construct(
        private QueryPerformanceMonitor $monitor,
        string $environment = 'prod'
    ) {
        // Включаем только в dev окружении
        $this->enabled = $environment === 'dev';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        // Сбрасываем статистику для нового запроса
        $this->monitor->reset();
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        // Получаем статистику и добавляем в заголовки ответа (только для dev)
        $stats = $this->monitor->getStatistics();
        $response = $event->getResponse();
        
        $response->headers->set('X-Query-Count', (string)$stats['total_queries']);
        $response->headers->set('X-Query-Time', $stats['total_duration']);
        $response->headers->set('X-Slow-Queries', (string)$stats['slow_queries']);
    }
}
