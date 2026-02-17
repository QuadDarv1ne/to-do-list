<?php

namespace App\EventSubscriber;

use App\Service\RateLimitingService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

class RateLimitSubscriber implements EventSubscriberInterface
{
    private RateLimitingService $rateLimitingService;

    public function __construct(RateLimitingService $rateLimitingService)
    {
        $this->rateLimitingService = $rateLimitingService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Skip rate limiting for static assets and health checks
        if ($this->shouldSkipRateLimiting($path)) {
            return;
        }

        // Check general request rate limiting
        if ($this->rateLimitingService->isRequestRateLimited($request)) {
            $this->handleRateLimitExceeded($event, 'Too many requests. Please try again later.');
            return;
        }

        // Check API rate limiting for API routes
        if (str_starts_with($path, '/api/')) {
            if ($this->rateLimitingService->isApiRateLimited($request)) {
                $this->handleRateLimitExceeded($event, 'API rate limit exceeded.', true);
                return;
            }
        }

        // Check login rate limiting for login attempts
        if ($path === '/login' && $request->isMethod('POST')) {
            $username = $request->request->get('_username') ?? '';
            if ($username && $this->rateLimitingService->isLoginRateLimited($username)) {
                $this->handleRateLimitExceeded($event, 'Too many login attempts. Please try again later.');
                return;
            }
        }
    }

    private function shouldSkipRateLimiting(string $path): bool
    {
        $skipPaths = [
            '/assets/',
            '/build/',
            '/favicon.ico',
            '/robots.txt',
            '/_profiler/',
            '/_wdt/',
        ];

        foreach ($skipPaths as $skipPath) {
            if (str_starts_with($path, $skipPath)) {
                return true;
            }
        }

        return false;
    }

    private function handleRateLimitExceeded(RequestEvent $event, string $message, bool $isApi = false): void
    {
        if ($isApi) {
            $response = new JsonResponse([
                'error' => 'rate_limit_exceeded',
                'message' => $message,
                'retry_after' => 3600, // 1 hour in seconds
            ], 429);
        } else {
            $response = new Response(
                $this->getRateLimitTemplate($message),
                429,
                ['Content-Type' => 'text/html']
            );
        }

        $event->setResponse($response);
    }

    private function getRateLimitTemplate(string $message): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Limit Exceeded</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            margin: 0;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            text-align: center;
            max-width: 500px;
        }
        .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dc3545;
        }
        h1 {
            color: #333;
            margin-bottom: 1rem;
        }
        p {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .btn {
            background-color: #007bff;
            color: white;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">⚠️</div>
        <h1>Rate Limit Exceeded</h1>
        <p>{$message}</p>
        <p>Please wait a while before trying again.</p>
        <a href="/" class="btn">Go to Homepage</a>
    </div>
</body>
</html>
HTML;
    }
}
