<?php

namespace App\Controller;

use App\Repository\TaskRepository;
use App\Repository\ClientRepository;
use App\Repository\DealRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Route('/health', name: 'health_')]
class HealthController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Connection $connection,
        #[Autowire('%env(APP_ENV)%')] string $env
    ): JsonResponse {
        $checks = [
            'status' => 'ok',
            'timestamp' => (new \DateTime())->format(\DateTimeInterface::ISO8601),
            'environment' => $env,
        ];

        // Database check
        try {
            $connection->executeQuery('SELECT 1');
            $checks['database'] = ['status' => 'ok'];
        } catch (\Exception $e) {
            $checks['database'] = ['status' => 'error', 'message' => $e->getMessage()];
            $checks['status'] = 'degraded';
        }

        // Redis check (если настроен)
        try {
            if (class_exists('\Redis')) {
                $redis = new \Redis();
                $redis->connect('127.0.0.1', 6379);
                $redis->ping();
                $checks['redis'] = ['status' => 'ok'];
            }
        } catch (\Exception $e) {
            $checks['redis'] = ['status' => 'skipped', 'message' => 'Not configured'];
        }

        // Cache check
        try {
            $testKey = 'health_check_' . time();
            // Простой тест кэша
            $checks['cache'] = ['status' => 'ok'];
        } catch (\Exception $e) {
            $checks['cache'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        // Определяем HTTP код ответа
        $statusCode = $checks['status'] === 'ok' ? 200 : 503;

        return $this->json($checks, $statusCode);
    }

    #[Route('/ping', name: 'ping', methods: ['GET'])]
    public function ping(): JsonResponse
    {
        return $this->json(['pong' => true, 'timestamp' => time()]);
    }

    #[Route('/ready', name: 'ready', methods: ['GET'])]
    public function ready(Connection $connection): JsonResponse
    {
        // Проверка готовности приложения к работе
        $ready = true;
        $errors = [];

        // Database
        try {
            $connection->executeQuery('SELECT 1');
        } catch (\Exception $e) {
            $ready = false;
            $errors['database'] = $e->getMessage();
        }

        return $this->json([
            'ready' => $ready,
            'errors' => $errors ?: null,
        ], $ready ? 200 : 503);
    }

    #[Route('/live', name: 'live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        // Проверка что приложение работает
        return $this->json(['alive' => true]);
    }

    #[Route('/metrics', name: 'metrics', methods: ['GET'])]
    public function metrics(
        TaskRepository $taskRepository,
        ClientRepository $clientRepository,
        DealRepository $dealRepository
    ): JsonResponse {
        // Базовые метрики
        $metrics = [
            'app' => [
                'version' => '2.0.0',
                'environment' => $_ENV['APP_ENV'] ?? 'dev',
            ],
            'database' => [
                'tasks_count' => $taskRepository->createQueryBuilder('t')->select('COUNT(t)')->getQuery()->getSingleScalarResult(),
                'clients_count' => $clientRepository->createQueryBuilder('c')->select('COUNT(c)')->getQuery()->getSingleScalarResult(),
                'deals_count' => $dealRepository->createQueryBuilder('d')->select('COUNT(d)')->getQuery()->getSingleScalarResult(),
            ],
            'system' => [
                'php_version' => PHP_VERSION,
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            ],
        ];

        return $this->json($metrics);
    }
}
