<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthCheckController extends AbstractController
{
    #[Route('/health', name: 'app_health_check', methods: ['GET'])]
    public function check(Connection $connection): JsonResponse
    {
        $status = 'healthy';
        $checks = [];
        
        // Check database connection
        try {
            $connection->executeQuery('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Exception $e) {
            $checks['database'] = 'error';
            $status = 'unhealthy';
        }
        
        // Check cache directory
        $cacheDir = $this->getParameter('kernel.cache_dir');
        $checks['cache'] = is_writable($cacheDir) ? 'ok' : 'error';
        if ($checks['cache'] === 'error') {
            $status = 'unhealthy';
        }
        
        // Check log directory
        $logDir = $this->getParameter('kernel.logs_dir');
        $checks['logs'] = is_writable($logDir) ? 'ok' : 'error';
        if ($checks['logs'] === 'error') {
            $status = 'unhealthy';
        }
        
        return new JsonResponse([
            'status' => $status,
            'timestamp' => time(),
            'checks' => $checks,
            'version' => $this->getParameter('kernel.environment')
        ], $status === 'healthy' ? 200 : 503);
    }
}
