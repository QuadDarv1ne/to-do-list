<?php

namespace App\EventSubscriber;

use App\Service\PerformanceMetricsCollector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Performance Metrics Subscriber - автоматический сбор метрик
 */
class PerformanceMetricsSubscriber implements EventSubscriberInterface
{
    private ?PerformanceMetricsCollector $collector = null;

    public function __construct(
        private PerformanceMetricsCollector $metricsCollector
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1024],
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
            KernelEvents::TERMINATE => ['onKernelTerminate', -1024],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->collector = $this->metricsCollector;
        $this->collector->startTimer('request_total');
        $this->collector->startTimer('controller_execution');
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->collector) {
            return;
        }

        $this->collector->stopTimer('controller_execution');
        
        $request = $event->getRequest();
        $response = $event->getResponse();

        // Record response metrics
        $this->collector->recordMetric('response_status', [
            'code' => $response->getStatusCode(),
            'size' => strlen($response->getContent())
        ]);

        // Add performance header in dev mode
        if ($_ENV['APP_ENV'] === 'dev') {
            $summary = $this->collector->getPerformanceSummary();
            $response->headers->set('X-Performance-Time', $summary['request_time']);
            $response->headers->set('X-Performance-Memory', $summary['memory_peak']);
            $response->headers->set('X-Performance-Status', $summary['status']);
        }
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (!$this->collector) {
            return;
        }

        $duration = $this->collector->stopTimer('request_total');
        
        $request = $event->getRequest();
        $endpoint = $request->getPathInfo();

        // Log slow requests
        $this->collector->logSlowOperation($endpoint, $duration, 2.0);

        // Store metrics for analysis
        $this->collector->storeMetrics($endpoint);
    }
}
