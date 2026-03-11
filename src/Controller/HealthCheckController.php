<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Контроллер для проверки здоровья системы (Health Check)
 * Используется для мониторинга и load balancer
 */
class HealthCheckController extends AbstractController
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * Базовая проверка здоровья (liveness probe)
     * Возвращает статус приложения без проверки зависимостей
     */
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'healthy',
            'timestamp' => (new \DateTime())->format('Y-m-d\\TH:i:sP'),
            'version' => '3.2.0',
            'environment' => $this->getParameter('kernel.environment'),
        ]);
    }

    /**
     * Полная проверка здоровья (readiness probe)
     * Проверяет подключение к базе данных и другим сервисам
     */
    #[Route('/health/ready', name: 'app_health_ready', methods: ['GET'])]
    public function healthReady(): JsonResponse
    {
        $checks = [];
        $isHealthy = true;

        // Проверка подключения к базе данных
        try {
            $this->connection->executeQuery('SELECT 1');
            $checks['database'] = [
                'status' => 'healthy',
                'message' => 'Database connection successful',
            ];
        } catch (\Exception $e) {
            $checks['database'] = [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
            $isHealthy = false;
        }

        // Проверка кэша
        try {
            $cacheDir = $this->getParameter('kernel.cache_dir');
            if (is_writable($cacheDir)) {
                $checks['cache'] = [
                    'status' => 'healthy',
                    'message' => 'Cache directory is writable',
                ];
            } else {
                throw new \Exception('Cache directory is not writable');
            }
        } catch (\Exception $e) {
            $checks['cache'] = [
                'status' => 'unhealthy',
                'message' => 'Cache check failed: ' . $e->getMessage(),
            ];
            $isHealthy = false;
        }

        // Проверка логов
        try {
            $logDir = $this->getParameter('kernel.logs_dir');
            if (is_writable($logDir)) {
                $checks['logs'] = [
                    'status' => 'healthy',
                    'message' => 'Log directory is writable',
                ];
            } else {
                throw new \Exception('Log directory is not writable');
            }
        } catch (\Exception $e) {
            $checks['logs'] = [
                'status' => 'unhealthy',
                'message' => 'Log check failed: ' . $e->getMessage(),
            ];
            $isHealthy = false;
        }

        $statusCode = $isHealthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return new JsonResponse([
            'status' => $isHealthy ? 'ready' : 'not_ready',
            'timestamp' => (new \DateTime())->format('Y-m-d\\TH:i:sP'),
            'version' => '3.2.0',
            'environment' => $this->getParameter('kernel.environment'),
            'checks' => $checks,
        ], $statusCode);
    }

    /**
     * Проверка живости (liveness probe)
     * Для Kubernetes и других оркестраторов
     */
    #[Route('/health/live', name: 'app_health_live', methods: ['GET'])]
    public function healthLive(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'alive',
            'timestamp' => (new \DateTime())->format('Y-m-d\\TH:i:sP'),
        ]);
    }

    /**
     * Проверка версии приложения
     */
    #[Route('/health/version', name: 'app_health_version', methods: ['GET'])]
    public function healthVersion(): JsonResponse
    {
        return new JsonResponse([
            'version' => '3.2.0',
            'php_version' => PHP_VERSION,
            'symfony_version' => \Symfony\Component\HttpKernel\Kernel::VERSION,
            'environment' => $this->getParameter('kernel.environment'),
            'debug' => $this->getParameter('kernel.debug'),
        ]);
    }
}
