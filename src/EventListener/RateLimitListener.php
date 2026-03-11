<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsEventListener(event: 'kernel.request', priority: 10)]
class RateLimitListener
{
    public function __construct(
        private readonly RateLimiterFactory $apiGlobalLimiter,
        private readonly RateLimiterFactory $apiCreateLimiter,
        private readonly RateLimiterFactory $authenticationLimiter,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $request->getPathInfo();
        
        // Skip rate limiting for non-API routes
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        $identity = $this->getClientIdentity($request);

        // Check authentication endpoints
        if (str_contains($path, '/login') || str_contains($path, '/auth')) {
            $this->checkLimit($this->authenticationLimiter, $identity, 'authentication');
        }

        // Check write operations
        if (in_array($request->getMethod(), ['POST', 'PUT', 'DELETE'], true)) {
            $this->checkLimit($this->apiCreateLimiter, $identity, 'api_create');
        }

        // Check global limit
        $this->checkLimit($this->apiGlobalLimiter, $identity, 'api_global');
    }

    private function checkLimit(RateLimiterFactory $limiter, string $identity, string $type): void
    {
        $limiter = $limiter->create($identity);
        
        if (!$limiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(
                sprintf('Rate limit exceeded for %s. Please try again later.', $type)
            );
        }
    }

    private function getClientIdentity($request): string
    {
        $user = $request->attributes->get('_security_user');
        if ($user && method_exists($user, 'getIdentifier')) {
            return 'user_' . $user->getIdentifier();
        }

        return 'ip_' . $request->getClientIp();
    }
}
