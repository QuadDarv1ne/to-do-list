<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsEventListener(event: 'kernel.response', priority: 10)]
class RateLimitResponseListener
{
    public function __construct(
        private readonly RateLimiterFactory $apiGlobalLimiter,
    ) {
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        
        if (!$event->isMainRequest()) {
            return;
        }

        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $response = $event->getResponse();
        $identity = $this->getClientIdentity($request);
        
        $limiter = $this->apiGlobalLimiter->create($identity);
        $config = $limiter->getConfig();
        $limit = method_exists($config, 'getLimit') ? $config->getLimit() : 1000;
        $remaining = $limiter->getTokens();
        
        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $remaining));
        $response->headers->set('X-RateLimit-Reset', (string) (time() + 3600));
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
