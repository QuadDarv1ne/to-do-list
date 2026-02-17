<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Entity\User;

/**
 * Comprehensive logging service for application events
 */
class LoggingService
{
    private LoggerInterface $logger;
    private RequestStack $requestStack;
    private ?TokenStorageInterface $tokenStorage;

    public function __construct(LoggerInterface $logger, RequestStack $requestStack, ?TokenStorageInterface $tokenStorage = null)
    {
        $this->logger = $logger;
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * Log general application events
     */
    public function logInfo(string $message, array $context = []): void
    {
        $context = $this->addCommonContext($context);
        $this->logger->info($message, $context);
    }

    /**
     * Log warnings
     */
    public function logWarning(string $message, array $context = []): void
    {
        $context = $this->addCommonContext($context);
        $this->logger->warning($message, $context);
    }

    /**
     * Log errors
     */
    public function logError(string $message, array $context = []): void
    {
        $context = $this->addCommonContext($context);
        $this->logger->error($message, $context);
    }

    /**
     * Log critical events
     */
    public function logCritical(string $message, array $context = []): void
    {
        $context = $this->addCommonContext($context);
        $this->logger->critical($message, $context);
    }

    /**
     * Log debug information
     */
    public function logDebug(string $message, array $context = []): void
    {
        $context = $this->addCommonContext($context);
        $this->logger->debug($message, $context);
    }

    /**
     * Log security-related events
     */
    public function logSecurityEvent(string $event, string $level = 'info', array $context = []): void
    {
        $context['event_type'] = 'security';
        $context['security_event'] = $event;
        
        switch ($level) {
            case 'warning':
                $this->logWarning("Security event: {$event}", $context);
                break;
            case 'error':
                $this->logError("Security event: {$event}", $context);
                break;
            case 'critical':
                $this->logCritical("Security event: {$event}", $context);
                break;
            default:
                $this->logInfo("Security event: {$event}", $context);
        }
    }

    /**
     * Log user activity
     */
    public function logUserActivity(string $action, array $context = []): void
    {
        $context['event_type'] = 'user_activity';
        $context['action'] = $action;
        
        // Add user information if available
        if ($this->tokenStorage && $token = $this->tokenStorage->getToken()) {
            $user = $token->getUser();
            if ($user instanceof UserInterface) {
                $context['username'] = $user->getUserIdentifier();
                // If the user is our User entity, we can get the ID
                if ($user instanceof User) {
                    $context['user_id'] = $user->getId();
                }
            }
        }
        
        $this->logInfo("User activity: {$action}", $context);
    }

    /**
     * Log performance-related events
     */
    public function logPerformance(string $operation, float $executionTime, array $context = []): void
    {
        $context['event_type'] = 'performance';
        $context['operation'] = $operation;
        $context['execution_time_ms'] = round($executionTime * 1000, 2);
        
        $this->logInfo("Performance: {$operation} took {$context['execution_time_ms']}ms", $context);
    }

    /**
     * Log database query events
     */
    public function logDatabaseQuery(string $sql, array $params = [], float $executionTime = 0, array $context = []): void
    {
        $context['event_type'] = 'database_query';
        $context['sql'] = $sql;
        $context['execution_time_ms'] = round($executionTime * 1000, 2);
        $context['parameters'] = $params;
        
        $this->logDebug("Database query executed", $context);
    }

    /**
     * Add common context information to log entries
     */
    private function addCommonContext(array $context): array
    {
        // Add request information if available
        if ($request = $this->requestStack->getCurrentRequest()) {
            $context['request_uri'] = $request->getRequestUri();
            $context['request_method'] = $request->getMethod();
            $context['client_ip'] = $request->getClientIp();
            $context['user_agent'] = $request->headers->get('User-Agent');
        }
        
        // Add user information if available
        if ($this->tokenStorage && $token = $this->tokenStorage->getToken()) {
            $user = $token->getUser();
            if ($user instanceof UserInterface) {
                $context['username'] = $user->getUserIdentifier();
                // If the user is our User entity, we can get the ID
                if ($user instanceof User) {
                    $context['user_id'] = $user->getId();
                }
            }
        }
        
        // Add timestamp
        $context['logged_at'] = date('Y-m-d H:i:s');
        
        return $context;
    }
}
