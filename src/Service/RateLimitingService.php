<?php

namespace App\Service;

use App\Service\PerformanceMonitorService;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class RateLimitingService
{
    private CacheItemPoolInterface $cache;
    private RequestStack $requestStack;
    private int $defaultLimit;
    private int $defaultWindow;
    private ?PerformanceMonitorService $performanceMonitor;

    public function __construct(
        CacheItemPoolInterface $cache,
        RequestStack $requestStack,
        int $defaultLimit = 60,
        int $defaultWindow = 3600,
        ?PerformanceMonitorService $performanceMonitor = null
    ) {
        $this->cache = $cache;
        $this->requestStack = $requestStack;
        $this->defaultLimit = $defaultLimit;
        $this->defaultWindow = $defaultWindow;
        $this->performanceMonitor = $performanceMonitor;
    }

    /**
     * Check if the request is rate limited
     */
    public function isRateLimited(
        string $identifier,
        ?int $limit = null,
        ?int $window = null
    ): bool {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('rate_limiting_service_is_rate_limited');
        }
        try {
            $limit = $limit ?? $this->defaultLimit;
            $window = $window ?? $this->defaultWindow;
            
            $key = $this->getCacheKey($identifier);
            $item = $this->cache->getItem($key);
            
            $requests = $item->isHit() ? $item->get() : [];
            $now = time();
            
            // Remove expired requests
            $requests = array_filter($requests, function($timestamp) use ($now, $window) {
                return ($now - $timestamp) < $window;
            });
            
            // Check if limit is exceeded
            if (count($requests) >= $limit) {
                return true;
            }
            
            // Add current request
            $requests[] = $now;
            $item->set($requests);
            $item->expiresAfter($window);
            $this->cache->save($item);
            
            return false;
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('rate_limiting_service_is_rate_limited');
            }
        }
    }

    /**
     * Get rate limit info for an identifier
     */
    public function getRateLimitInfo(
        string $identifier,
        ?int $limit = null,
        ?int $window = null
    ): array {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('rate_limiting_service_get_rate_limit_info');
        }
        try {
            $limit = $limit ?? $this->defaultLimit;
            $window = $window ?? $this->defaultWindow;
            
            $key = $this->getCacheKey($identifier);
            $item = $this->cache->getItem($key);
            
            $requests = $item->isHit() ? $item->get() : [];
            $now = time();
            
            // Remove expired requests
            $requests = array_filter($requests, function($timestamp) use ($now, $window) {
                return ($now - $timestamp) < $window;
            });
            
            $remaining = max(0, $limit - count($requests));
            $resetTime = $now + $window;
            
            if (!empty($requests)) {
                $oldestRequest = min($requests);
                $resetTime = $oldestRequest + $window;
            }
            
            return [
                'limit' => $limit,
                'remaining' => $remaining,
                'reset' => $resetTime,
                'used' => count($requests)
            ];
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('rate_limiting_service_get_rate_limit_info');
            }
        }
    }

    /**
     * Get client IP address
     */
    public function getClientIp(): string
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('rate_limiting_service_get_client_ip');
        }
        try {
            $request = $this->requestStack->getCurrentRequest();
            if (!$request) {
                return 'unknown';
            }
            
            // Check for forwarded headers
            $forwardedFor = $request->headers->get('X-Forwarded-For');
            if ($forwardedFor) {
                $ips = explode(',', $forwardedFor);
                return trim($ips[0]);
            }
            
            $forwarded = $request->headers->get('X-Forwarded');
            if ($forwarded) {
                $ips = explode(',', $forwarded);
                return trim($ips[0]);
            }
            
            $realIp = $request->headers->get('X-Real-IP');
            if ($realIp) {
                return $realIp;
            }
            
            return $request->getClientIp() ?? 'unknown';
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('rate_limiting_service_get_client_ip');
            }
        }
    }

    /**
     * Generate cache key for identifier
     */
    private function getCacheKey(string $identifier): string
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('rate_limiting_service_get_cache_key');
        }
        try {
            return 'rate_limit_' . md5($identifier);
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('rate_limiting_service_get_cache_key');
            }
        }
    }

    /**
     * Check login attempts for brute force protection
     */
    public function isLoginRateLimited(string $username): bool
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('rate_limiting_service_is_login_rate_limited');
        }
        try {
            $identifier = 'login_' . strtolower($username);
            return $this->isRateLimited($identifier, 5, 900); // 5 attempts per 15 minutes
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('rate_limiting_service_is_login_rate_limited');
            }
        }
    }

    /**
     * Check API request rate limiting
     */
    public function isApiRateLimited(Request $request): bool
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('rate_limiting_service_is_api_rate_limited');
        }
        try {
            $ip = $this->getClientIp();
            $apiKey = $request->headers->get('X-API-Key');
            
            if ($apiKey) {
                // API key based rate limiting (higher limits)
                return $this->isRateLimited('api_' . $apiKey, 1000, 3600); // 1000 requests per hour
            }
            
            // IP based rate limiting for API
            return $this->isRateLimited('api_ip_' . $ip, 100, 3600); // 100 requests per hour
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('rate_limiting_service_is_api_rate_limited');
            }
        }
    }

    /**
     * Check general request rate limiting
     */
    public function isRequestRateLimited(Request $request): bool
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('rate_limiting_service_is_request_rate_limited');
        }
        try {
            $ip = $this->getClientIp();
            $userAgent = $request->headers->get('User-Agent', 'unknown');
            
            // Create identifier based on IP and User-Agent
            $identifier = 'request_' . $ip . '_' . md5($userAgent);
            
            return $this->isRateLimited($identifier, 200, 3600); // 200 requests per hour
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('rate_limiting_service_is_request_rate_limited');
            }
        }
    }

    /**
     * Reset rate limit for an identifier
     */
    public function resetRateLimit(string $identifier): void
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('rate_limiting_service_reset_rate_limit');
        }
        try {
            $key = $this->getCacheKey($identifier);
            $this->cache->deleteItem($key);
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('rate_limiting_service_reset_rate_limit');
            }
        }
    }

    /**
     * Reset login attempts for a username
     */
    public function resetLoginAttempts(string $username): void
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('rate_limiting_service_reset_login_attempts');
        }
        try {
            $identifier = 'login_' . strtolower($username);
            $this->resetRateLimit($identifier);
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('rate_limiting_service_reset_login_attempts');
            }
        }
    }
}
