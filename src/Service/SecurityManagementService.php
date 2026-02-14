<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Security management service with advanced security features
 */
class SecurityManagementService
{
    private LoggerInterface $logger;
    private RequestStack $requestStack;
    private TokenStorageInterface $tokenStorage;
    private array $suspiciousPatterns = [
        // SQL injection patterns
        '/(union|select|insert|delete|update|drop|create|alter|exec|execute)/i',
        '/(\'|\\\')[\s]*?(or|and)[\s]*?.*?(=|like)/i',
        '/(\/\*|\*\/|@@|;)/',
        
        // XSS patterns
        '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i',
        '/javascript:/i',
        '/on\w+\s*=/i',
        '/<iframe\b[^>]*>.*?<\/iframe>/i',
        
        // Command injection patterns
        '/(&&|\|\||;|`|\$\(|\(|\)|\{|\}|\[|\])/i',
        
        // Path traversal patterns
        '/(\.\.\/|~\/|\/etc\/|\/proc\/|\/var\/)/i',
    ];

    public function __construct(
        LoggerInterface $logger,
        RequestStack $requestStack,
        TokenStorageInterface $tokenStorage
    ) {
        $this->logger = $logger;
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * Check if input contains suspicious patterns
     */
    public function isSuspiciousInput(string $input): bool
    {
        foreach ($this->suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $this->logSuspiciousActivity('suspicious_input_detected', [
                    'pattern' => $pattern,
                    'input' => $input
                ]);
                return true;
            }
        }
        return false;
    }

    /**
     * Validate and sanitize user input
     */
    public function sanitizeInput(string $input, array $allowedTags = []): string
    {
        // Remove suspicious patterns
        $cleaned = $input;
        
        // Remove script tags and javascript
        $cleaned = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $cleaned);
        $cleaned = preg_replace('/javascript:/i', '', $cleaned);
        $cleaned = preg_replace('/on\w+\s*=/i', '', $cleaned);
        
        // Allow only specific HTML tags if provided
        if (!empty($allowedTags)) {
            $allowedTagsString = implode('', array_map(function($tag) {
                return "<{$tag}>";
            }, $allowedTags));
            $cleaned = strip_tags($cleaned, $allowedTagsString);
        } else {
            $cleaned = htmlspecialchars($cleaned, ENT_QUOTES, 'UTF-8');
        }
        
        // Log if input was modified
        if ($cleaned !== $input) {
            $this->logSuspiciousActivity('input_sanitized', [
                'original' => $input,
                'sanitized' => $cleaned
            ]);
        }
        
        return $cleaned;
    }

    /**
     * Check if user has excessive failed login attempts
     */
    public function isAccountLocked(UserInterface $user): bool
    {
        // This would check user entity for failed login attempts
        // Implementation depends on User entity structure
        return false;
    }

    /**
     * Get current user's IP address
     */
    public function getClientIp(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }
        
        return $request->getClientIp();
    }

    /**
     * Get current user agent
     */
    public function getUserAgent(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }
        
        return $request->headers->get('User-Agent');
    }

    /**
     * Log security-related events
     */
    public function logSecurityEvent(string $eventType, array $details = []): void
    {
        $context = array_merge($details, [
            'event_type' => $eventType,
            'ip' => $this->getClientIp(),
            'user_agent' => $this->getUserAgent(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        // Add user information if available
        $token = $this->tokenStorage->getToken();
        if ($token && $token->getUser() instanceof UserInterface) {
            $context['user_id'] = $token->getUser()->getUserIdentifier();
        }

        $this->logger->info("Security Event: {$eventType}", $context);
    }

    /**
     * Log suspicious activity
     */
    public function logSuspiciousActivity(string $activityType, array $details = []): void
    {
        $this->logSecurityEvent("suspicious_{$activityType}", $details);
    }

    /**
     * Check if request is from known malicious IP
     */
    public function isMaliciousIp(string $ip): bool
    {
        // This would check against known malicious IP lists
        // In production, this would integrate with threat intelligence services
        $knownMaliciousIps = [
            // Example malicious IPs - in production, use real threat intelligence
        ];
        
        return in_array($ip, $knownMaliciousIps);
    }

    /**
     * Rate limiting check
     */
    public function checkRateLimit(string $identifier, int $maxAttempts = 10, int $timeWindow = 3600): bool
    {
        // This would implement rate limiting logic
        // In production, use Redis or similar for persistent storage
        $key = "rate_limit_{$identifier}";
        
        // Simple in-memory implementation for demonstration
        static $attempts = [];
        
        if (!isset($attempts[$key])) {
            $attempts[$key] = [
                'count' => 0,
                'timestamp' => time()
            ];
        }
        
        $now = time();
        if ($now - $attempts[$key]['timestamp'] > $timeWindow) {
            $attempts[$key] = [
                'count' => 1,
                'timestamp' => $now
            ];
            return true;
        }
        
        $attempts[$key]['count']++;
        
        if ($attempts[$key]['count'] > $maxAttempts) {
            $this->logSecurityEvent('rate_limit_exceeded', [
                'identifier' => $identifier,
                'attempts' => $attempts[$key]['count']
            ]);
            return false;
        }
        
        return true;
    }

    /**
     * Get security statistics
     */
    public function getSecurityStats(): array
    {
        return [
            'suspicious_patterns_count' => count($this->suspiciousPatterns),
            'current_ip' => $this->getClientIp(),
            'current_user_agent' => $this->getUserAgent(),
            'rate_limiting_enabled' => true
        ];
    }
}