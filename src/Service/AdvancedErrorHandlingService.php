<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Advanced error handling service with comprehensive logging and monitoring
 */
class AdvancedErrorHandlingService
{
    private LoggerInterface $logger;
    private PerformanceMonitoringService $performanceMonitor;

    public function __construct(
        LoggerInterface $logger,
        PerformanceMonitoringService $performanceMonitor
    ) {
        $this->logger = $logger;
        $this->performanceMonitor = $performanceMonitor;
    }

    /**
     * Handle application exception with detailed logging
     */
    public function handleException(Throwable $exception, ?Request $request = null): void
    {
        $context = $this->buildExceptionContext($exception, $request);
        
        // Log based on exception severity
        $this->logException($exception, $context);
        
        // Track in performance monitoring
        $this->trackExceptionInPerformance($exception, $context);
        
        // Send notifications for critical errors
        if ($this->isCriticalException($exception)) {
            $this->sendCriticalErrorNotification($exception, $context);
        }
    }

    /**
     * Handle HTTP errors with response tracking
     */
    public function handleHttpError(int $statusCode, string $message, ?Request $request = null): void
    {
        $context = [
            'status_code' => $statusCode,
            'message' => $message,
            'url' => $request ? $request->getRequestUri() : 'unknown',
            'method' => $request ? $request->getMethod() : 'unknown',
            'user_agent' => $request ? $request->headers->get('User-Agent') : 'unknown',
            'ip' => $request ? $request->getClientIp() : 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->logger->warning("HTTP Error {$statusCode}: {$message}", $context);
        
        // Track 4xx and 5xx errors in performance monitoring
        if ($statusCode >= 400) {
            $this->performanceMonitor->logError([
                'type' => 'http_error',
                'status_code' => $statusCode,
                'message' => $message,
                'context' => $context
            ]);
        }
    }

    /**
     * Log security-related events
     */
    public function logSecurityEvent(string $eventType, array $details, ?Request $request = null): void
    {
        $context = array_merge($details, [
            'event_type' => $eventType,
            'url' => $request ? $request->getRequestUri() : 'unknown',
            'method' => $request ? $request->getMethod() : 'unknown',
            'ip' => $request ? $request->getClientIp() : 'unknown',
            'user_agent' => $request ? $request->headers->get('User-Agent') : 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        $this->logger->info("Security Event: {$eventType}", $context);
        
        // Track security events in performance monitoring
        $this->performanceMonitor->logSecurityEvent($eventType, $context);
    }

    /**
     * Log performance warnings
     */
    public function logPerformanceWarning(string $operation, float $executionTime, array $details = []): void
    {
        $context = array_merge($details, [
            'operation' => $operation,
            'execution_time_ms' => $executionTime,
            'threshold_exceeded' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        $this->logger->warning("Performance Warning: {$operation} took {$executionTime}ms", $context);
        
        // Track in performance monitoring
        $this->performanceMonitor->logPerformanceWarning($operation, $executionTime, $context);
    }

    /**
     * Log cache operations
     */
    public function logCacheOperation(string $operation, string $cacheKey, bool $hit, float $executionTime = 0): void
    {
        $context = [
            'operation' => $operation,
            'cache_key' => $cacheKey,
            'hit' => $hit,
            'execution_time_ms' => $executionTime,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        if ($hit) {
            $this->logger->info("Cache HIT: {$cacheKey}", $context);
        } else {
            $this->logger->info("Cache MISS: {$cacheKey}", $context);
        }
        
        // Track cache performance
        $this->performanceMonitor->logCacheOperation($operation, $cacheKey, $hit, $executionTime);
    }

    /**
     * Get error statistics for reporting
     */
    public function getErrorStatistics(): array
    {
        // This would integrate with log analysis or monitoring systems
        return [
            'total_errors' => 0, // Would be populated from logs/monitoring
            'critical_errors' => 0,
            'warning_errors' => 0,
            'http_errors' => 0,
            'security_events' => 0,
            'performance_warnings' => 0
        ];
    }

    /**
     * Build comprehensive exception context
     */
    private function buildExceptionContext(Throwable $exception, ?Request $request): array
    {
        $context = [
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        if ($request) {
            $context = array_merge($context, [
                'url' => $request->getRequestUri(),
                'method' => $request->getMethod(),
                'user_agent' => $request->headers->get('User-Agent'),
                'ip' => $request->getClientIp(),
                'referer' => $request->headers->get('Referer'),
                'session_id' => $request->hasSession() ? $request->getSession()->getId() : null
            ]);
        }

        // Add user context if available
        if ($request && $request->hasSession() && $request->getSession()->has('user_id')) {
            $context['user_id'] = $request->getSession()->get('user_id');
        }

        return $context;
    }

    /**
     * Log exception based on its severity
     */
    private function logException(Throwable $exception, array $context): void
    {
        if ($exception instanceof HttpExceptionInterface) {
            $this->logger->warning("HTTP Exception: " . $exception->getMessage(), $context);
        } elseif ($this->isCriticalException($exception)) {
            $this->logger->critical("Critical Exception: " . $exception->getMessage(), $context);
        } else {
            $this->logger->error("Application Exception: " . $exception->getMessage(), $context);
        }
    }

    /**
     * Track exception in performance monitoring
     */
    private function trackExceptionInPerformance(Throwable $exception, array $context): void
    {
        $this->performanceMonitor->logError([
            'type' => 'exception',
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'context' => $context
        ]);
    }

    /**
     * Determine if exception is critical
     */
    private function isCriticalException(Throwable $exception): bool
    {
        $criticalTypes = [
            'Symfony\Component\Security\Core\Exception\AuthenticationException',
            'Doctrine\DBAL\Exception\ConnectionException',
            'Symfony\Component\Mailer\Exception\TransportException'
        ];

        return in_array(get_class($exception), $criticalTypes);
    }

    /**
     * Send notification for critical errors (placeholder)
     */
    private function sendCriticalErrorNotification(Throwable $exception, array $context): void
    {
        // This would integrate with notification systems (email, Slack, etc.)
        $this->logger->alert("Critical Error Notification Sent: " . $exception->getMessage(), $context);
    }
}
