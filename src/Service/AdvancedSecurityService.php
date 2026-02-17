<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Advanced security service with additional security measures
 */
class AdvancedSecurityService
{
    private LoggerInterface $logger;
    private RequestStack $requestStack;
    private TokenStorageInterface $tokenStorage;
    private AuthorizationCheckerInterface $authorizationChecker;

    // Security configuration
    private array $securityConfig = [
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes
        'session_timeout' => 3600, // 1 hour
        'password_reset_timeout' => 86400, // 24 hours
        'csrf_tokens_expire_after' => 3600, // 1 hour
    ];

    // Dangerous patterns for input validation
    private array $dangerousPatterns = [
        // SQL Injection
        '/(union\s+select|select.*from|insert\s+into|drop\s+table|exec\s*\(|execute\s+)/i',
        // XSS
        '/<script[^>]*>.*<\/script>|javascript:|on\w+\s*=|<iframe/i',
        // Command injection
        '/\|\s*rm\s|;\s*rm\s|&&\s*rm\s|>\s*\/etc\/|<\s*\/etc\//i',
        // Path traversal
        '/\.\.\/|\.\.\\\\|%2e%2e%2f|%2e%2e%5c/i',
    ];

    public function __construct(
        LoggerInterface $logger,
        RequestStack $requestStack,
        TokenStorageInterface $tokenStorage,
        AuthorizationCheckerInterface $authorizationChecker
    ) {
        $this->logger = $logger;
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * Validate user input against dangerous patterns
     */
    public function validateInput(string $input, string $context = ''): array
    {
        $violations = [];

        foreach ($this->dangerousPatterns as $index => $pattern) {
            if (preg_match($pattern, $input)) {
                $violations[] = [
                    'type' => 'dangerous_pattern',
                    'pattern_id' => $index,
                    'context' => $context,
                    'input_sample' => substr($input, 0, 50) . (strlen($input) > 50 ? '...' : '')
                ];
                
                $this->logger->warning("Dangerous pattern detected in input", [
                    'context' => $context,
                    'pattern_id' => $index,
                    'input_sample' => substr($input, 0, 100)
                ]);
            }
        }

        // Check for excessively long inputs (potential buffer overflow)
        if (strlen($input) > 10000) {
            $violations[] = [
                'type' => 'excessive_length',
                'length' => strlen($input),
                'context' => $context
            ];
            
            $this->logger->warning("Excessively long input detected", [
                'context' => $context,
                'length' => strlen($input)
            ]);
        }

        return $violations;
    }

    /**
     * Sanitize user input
     */
    public function sanitizeInput(string $input, array $allowedTags = []): string
    {
        // First, validate the input
        $violations = $this->validateInput($input);
        
        if (!empty($violations)) {
            $this->logger->info("Input sanitization required", [
                'violations' => $violations,
                'original_length' => strlen($input)
            ]);
        }

        // Remove dangerous patterns
        $cleanInput = $input;
        
        // Remove script tags and JavaScript
        $cleanInput = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $cleanInput);
        $cleanInput = preg_replace('/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi', '', $cleanInput);
        $cleanInput = preg_replace('/javascript:/mi', '', $cleanInput);
        $cleanInput = preg_replace('/vbscript:/mi', '', $cleanInput);
        
        // Remove event handlers
        $cleanInput = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/mi', '', $cleanInput);
        
        // Sanitize based on allowed tags
        if (!empty($allowedTags)) {
            $allowedTagsStr = '<' . implode('><', $allowedTags) . '>';
            $cleanInput = strip_tags($cleanInput, $allowedTagsStr);
        } else {
            // If no tags allowed, escape all HTML
            $cleanInput = htmlspecialchars($cleanInput, ENT_QUOTES, 'UTF-8');
        }

        if ($cleanInput !== $input) {
            $this->logger->info("Input sanitized", [
                'original_length' => strlen($input),
                'sanitized_length' => strlen($cleanInput),
                'difference' => strlen($input) - strlen($cleanInput)
            ]);
        }

        return $cleanInput;
    }

    /**
     * Check if current request is from a suspicious source
     */
    public function isSuspiciousRequest(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return false;
        }

        $clientIp = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent', '');
        $requestUri = $request->getRequestUri();

        // Check for suspicious user agents
        $suspiciousAgents = [
            '/sqlmap/i',
            '/nikto/i',
            '/nessus/i',
            '/acunetix/i',
            '/wget/i',
            '/curl/i'
        ];

        foreach ($suspiciousAgents as $agentPattern) {
            if (preg_match($agentPattern, $userAgent)) {
                $this->logger->warning("Request with suspicious user agent", [
                    'user_agent' => $userAgent,
                    'ip' => $clientIp,
                    'uri' => $requestUri
                ]);
                return true;
            }
        }

        // Check for suspicious URI patterns
        $suspiciousUriPatterns = [
            '/\.\.\/|\.\.\\\\/',  // Directory traversal
            '/(union|select|insert|delete|update|drop|create|exec|execute).*from/i',  // SQL injection
            '/<script|javascript:|vbscript:/i',  // XSS
        ];

        foreach ($suspiciousUriPatterns as $pattern) {
            if (preg_match($pattern, $requestUri)) {
                $this->logger->warning("Request with suspicious URI pattern", [
                    'uri' => $requestUri,
                    'ip' => $clientIp,
                    'user_agent' => $userAgent
                ]);
                return true;
            }
        }

        return false;
    }

    /**
     * Enhanced access control check
     */
    public function isAuthorized(string $attribute, $subject = null): bool
    {
        $isAuthorized = $this->authorizationChecker->isGranted($attribute, $subject);
        
        $this->logger->info("Access control check", [
            'attribute' => $attribute,
            'subject' => $subject ? get_class($subject) : 'null',
            'result' => $isAuthorized ? 'GRANTED' : 'DENIED',
            'user' => $this->getCurrentUserIdentifier()
        ]);

        return $isAuthorized;
    }

    /**
     * Get current user identifier
     */
    public function getCurrentUserIdentifier(): ?string
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return null;
        }

        $user = $token->getUser();
        if (!is_object($user)) {
            return null;
        }

        // Safely get user identifier
        if (method_exists($user, 'getUserIdentifier')) {
            return $user->getUserIdentifier();
        } elseif (method_exists($user, '__toString')) {
            return (string) $user;
        } else {
            return null;
        }
    }

    /**
     * Check session security
     */
    public function checkSessionSecurity(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return ['secure' => false, 'reason' => 'No request available'];
        }

        $session = $request->getSession();
        if (!$session || !$session->isStarted()) {
            return ['secure' => false, 'reason' => 'Session not started'];
        }

        $securityIssues = [];

        // Check session timeout
        $lastActivity = $session->get('last_activity');
        if ($lastActivity) {
            $timeDiff = time() - $lastActivity;
            if ($timeDiff > $this->securityConfig['session_timeout']) {
                $securityIssues[] = [
                    'type' => 'session_timeout',
                    'timeout_seconds' => $this->securityConfig['session_timeout'],
                    'actual_seconds' => $timeDiff
                ];
            }
        }

        // Check for session fixation attempts
        $currentIp = $request->getClientIp();
        $previousIp = $session->get('ip_address');
        
        if ($previousIp && $previousIp !== $currentIp) {
            $securityIssues[] = [
                'type' => 'ip_change',
                'previous_ip' => $previousIp,
                'current_ip' => $currentIp
            ];
        }

        // Check user agent consistency
        $currentUserAgent = $request->headers->get('User-Agent');
        $previousUserAgent = $session->get('user_agent');
        
        if ($previousUserAgent && $previousUserAgent !== $currentUserAgent) {
            $securityIssues[] = [
                'type' => 'user_agent_change',
                'previous_ua' => $previousUserAgent,
                'current_ua' => $currentUserAgent
            ];
        }

        $isSecure = empty($securityIssues);
        
        return [
            'secure' => $isSecure,
            'issues' => $securityIssues,
            'session_id' => $session->getId()
        ];
    }

    /**
     * Update session security info
     */
    public function updateSessionSecurity(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $session = $request->getSession();
        if (!$session || !$session->isStarted()) {
            return;
        }

        // Update last activity timestamp
        $session->set('last_activity', time());

        // Store IP address and user agent for security checks
        $session->set('ip_address', $request->getClientIp());
        $session->set('user_agent', $request->headers->get('User-Agent'));
    }

    /**
     * Generate security report
     */
    public function getSecurityReport(): array
    {
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'config' => [
                'max_login_attempts' => $this->securityConfig['max_login_attempts'],
                'lockout_duration' => $this->securityConfig['lockout_duration'],
                'session_timeout' => $this->securityConfig['session_timeout']
            ],
            'current_user' => $this->getCurrentUserIdentifier(),
            'suspicious_request' => $this->isSuspiciousRequest(),
            'session_security' => $this->checkSessionSecurity(),
            'pattern_count' => count($this->dangerousPatterns)
        ];
    }

    /**
     * Log security event
     */
    public function logSecurityEvent(string $eventType, array $details = []): void
    {
        $context = array_merge($details, [
            'event_type' => $eventType,
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => $this->getCurrentUserIdentifier(),
            'ip' => $this->requestStack->getCurrentRequest()?->getClientIp(),
            'user_agent' => $this->requestStack->getCurrentRequest()?->headers?->get('User-Agent')
        ]);

        $this->logger->info("Security Event: {$eventType}", $context);
    }
}
