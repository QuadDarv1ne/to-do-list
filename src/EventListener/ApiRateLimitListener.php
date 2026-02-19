<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsEventListener(event: 'kernel.request', priority: 10)]
class ApiRateLimitListener
{
    public function __construct(
        private RateLimiterFactory $apiRequestLimiter
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Применяем rate limiting только к API запросам
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }
        
        // Получаем идентификатор клиента (IP или user ID)
        $identifier = $request->getClientIp();
        
        // Создаем лимитер для этого клиента
        $limiter = $this->apiRequestLimiter->create($identifier);
        
        // Проверяем лимит
        $limit = $limiter->consume(1);
        
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                $limit->getRetryAfter()->getTimestamp() - time(),
                'Слишком много запросов. Попробуйте позже.'
            );
        }
        
        // Добавляем заголовки с информацией о лимитах
        $response = $event->getResponse();
        if ($response) {
            $response->headers->set('X-RateLimit-Limit', (string) $limit->getLimit());
            $response->headers->set('X-RateLimit-Remaining', (string) $limit->getRemainingTokens());
            $response->headers->set('X-RateLimit-Reset', (string) $limit->getRetryAfter()->getTimestamp());
        }
    }
}
