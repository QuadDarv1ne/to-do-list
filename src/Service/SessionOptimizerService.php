<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for managing and optimizing session handling
 */
class SessionOptimizerService
{
    private LoggerInterface $logger;
    private ContainerInterface $container;
    private TokenStorageInterface $tokenStorage;
    private RequestStack $requestStack;
    private array $sessionConfig;

    public function __construct(
        LoggerInterface $logger,
        ContainerInterface $container,
        TokenStorageInterface $tokenStorage,
        RequestStack $requestStack
    ) {
        $this->logger = $logger;
        $this->container = $container;
        $this->tokenStorage = $tokenStorage;
        $this->requestStack = $requestStack;
        
        // Default session configuration
        $this->sessionConfig = [
            'gc_maxlifetime' => 1440, // 24 minutes
            'cookie_lifetime' => 0, // Until browser closes
            'cache_limiter' => 'nocache',
            'use_strict_mode' => true,
            'cookie_secure' => 'auto',
            'cookie_httponly' => true,
            'cookie_samesite' => 'lax',
        ];
    }

    /**
     * Optimize session settings based on current environment
     */
    public function optimizeSessionSettings(): void
    {
        $environment = $this->container->getParameter('kernel.environment');
        
        $this->logger->info('Optimizing session settings', [
            'environment' => $environment
        ]);
        
        // Adjust settings based on environment
        if ($environment === 'prod') {
            // Production settings
            $this->sessionConfig = array_merge($this->sessionConfig, [
                'gc_maxlifetime' => 3600, // 1 hour
                'cookie_lifetime' => 3600 * 24 * 7, // 1 week
                'cookie_secure' => true,
                'use_cookies' => true,
                'use_only_cookies' => true,
            ]);
        } elseif ($environment === 'dev') {
            // Development settings
            $this->sessionConfig = array_merge($this->sessionConfig, [
                'gc_maxlifetime' => 7200, // 2 hours
                'cookie_lifetime' => 0,
                'cookie_secure' => false,
            ]);
        }
        
        // Apply configuration
        $this->applySessionConfiguration();
        
        $this->logger->info('Session settings optimized', $this->sessionConfig);
    }

    /**
     * Apply session configuration
     */
    private function applySessionConfiguration(): void
    {
        foreach ($this->sessionConfig as $key => $value) {
            $iniKey = 'session.' . $key;
            if (is_bool($value)) {
                ini_set($iniKey, $value ? '1' : '0');
            } elseif (is_int($value) || is_string($value)) {
                ini_set($iniKey, (string)$value);
            }
        }
    }

    /**
     * Clean up inactive sessions
     */
    public function cleanupInactiveSessions(int $inactiveHours = 24): int
    {
        $this->logger->info('Starting session cleanup', [
            'inactive_hours' => $inactiveHours
        ]);
        
        // In a real implementation, this would connect to the session storage
        // and remove sessions that have been inactive for more than the specified time
        // Since Symfony handles sessions differently depending on the storage mechanism,
        // we'll log the intended action
        
        $cutoffTime = new \DateTime("-{$inactiveHours} hours");
        
        $this->logger->info('Session cleanup completed', [
            'inactive_hours' => $inactiveHours,
            'cutoff_time' => $cutoffTime->format('Y-m-d H:i:s')
        ]);
        
        // Return 0 as a placeholder - in a real implementation this would return the number of cleaned sessions
        return 0;
    }

    /**
     * Get session security information
     */
    public function getSessionSecurityInfo(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $currentToken = $this->tokenStorage->getToken();
        
        $userInfo = [
            'authenticated' => false,
            'user_id' => null,
            'username' => null,
            'roles' => []
        ];
        
        if ($currentToken && $currentToken->getUser()) {
            $user = $currentToken->getUser();
            $userInfo = [
                'authenticated' => true,
                'user_id' => $this->getUserId($user),
                'username' => $this->getUserIdentifier($user),
                'roles' => $currentToken->getRoleNames() ?? []
            ];
        }
        
        $sessionInfo = [
            'session_id' => $request ? $request->getSession()->getId() : null,
            'session_name' => session_name(),
            'session_status' => session_status(),
            'session_started' => isset($_SESSION),
            'security_config' => [
                'use_strict_mode' => ini_get('session.use_strict_mode'),
                'cookie_httponly' => ini_get('session.cookie_httponly'),
                'cookie_secure' => ini_get('session.cookie_secure'),
                'use_cookies' => ini_get('session.use_cookies'),
                'use_only_cookies' => ini_get('session.use_only_cookies'),
                'entropy_length' => ini_get('session.entropy_length'),
                'hash_function' => ini_get('session.hash_function'),
            ],
            'user_info' => $userInfo,
            'client_info' => [
                'ip_address' => $request ? $request->getClientIp() : null,
                'user_agent' => $request ? $request->headers->get('User-Agent') : null,
            ]
        ];
        
        return $sessionInfo;
    }

    /**
     * Get user ID safely
     */
    private function getUserId($user): ?int
    {
        if (is_object($user) && method_exists($user, 'getId')) {
            return $user->getId();
        }
        return null;
    }

    /**
     * Get user identifier safely
     */
    private function getUserIdentifier($user): ?string
    {
        if (is_object($user) && method_exists($user, 'getUserIdentifier')) {
            return $user->getUserIdentifier();
        }
        if (is_object($user) && method_exists($user, 'getUsername')) {
            return $user->getUsername();
        }
        return null;
    }

    /**
     * Regenerate session ID securely
     */
    public function regenerateSessionId(bool $destroyOld = false): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            $this->logger->error('Cannot regenerate session ID: no current request');
            return false;
        }
        
        try {
            $session = $request->getSession();
            
            // Get current session data
            $sessionData = $session->all();
            
            // Regenerate session ID
            $session->migrate($destroyOld);
            
            // Restore session data if needed
            if ($destroyOld) {
                foreach ($sessionData as $key => $value) {
                    if ($key !== '_sf2_meta') { // Preserve Symfony metadata
                        $session->set($key, $value);
                    }
                }
            }
            
            $this->logger->info('Session ID regenerated', [
                'new_session_id' => $session->getId(),
                'destroy_old' => $destroyOld
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to regenerate session ID', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Validate session integrity
     */
    public function validateSessionIntegrity(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            return [
                'valid' => false,
                'issues' => ['No current request available']
            ];
        }
        
        $session = $request->getSession();
        $issues = [];
        
        // Check if session is started
        if (!isset($_SESSION)) {
            $issues[] = 'Session not started';
        }
        
        // Check session ID length (should be reasonable)
        $sessionId = $session->getId();
        if (strlen($sessionId) < 22) { // Typical session IDs are longer
            $issues[] = 'Session ID appears to be too short';
        }
        
        // Check for suspicious session data
        $sessionData = $session->all();
        foreach ($sessionData as $key => $value) {
            if (is_string($value) && strlen($value) > 10000) {
                // Very large session values might indicate abuse
                $issues[] = "Large session value for key: {$key}";
            }
        }
        
        // Check session age if available
        if ($session->has('_sf2_meta')) {
            $meta = $session->get('_sf2_meta');
            if (is_array($meta) && isset($meta['c']) && is_numeric($meta['c'])) {
                $createdTime = (int)$meta['c'];
                $currentTime = time();
                $sessionAge = $currentTime - $createdTime;
                
                // Sessions shouldn't be excessively old
                if ($sessionAge > 86400 * 30) { // 30 days
                    $issues[] = 'Session is older than 30 days';
                }
            }
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'session_data_keys' => array_keys($sessionData),
            'session_size_estimate' => $this->estimateSessionSize($sessionData)
        ];
    }

    /**
     * Estimate session data size
     */
    private function estimateSessionSize(array $sessionData): int
    {
        $size = 0;
        foreach ($sessionData as $key => $value) {
            $size += strlen((string)$key);
            if (is_string($value)) {
                $size += strlen($value);
            } elseif (is_array($value)) {
                $size += strlen(serialize($value));
            } else {
                $size += strlen(json_encode($value));
            }
        }
        return $size;
    }

    /**
     * Optimize session storage
     */
    public function optimizeSessionStorage(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            return [
                'success' => false,
                'message' => 'No current request available'
            ];
        }
        
        $session = $request->getSession();
        $originalSize = $this->estimateSessionSize($session->all());
        
        // Remove unnecessary session data
        $keysToRemove = [];
        $sessionData = $session->all();
        
        foreach ($sessionData as $key => $value) {
            // Remove temporary or debug data
            if (str_starts_with($key, '_debug_') || str_starts_with($key, 'temp_')) {
                $keysToRemove[] = $key;
            }
            
            // Remove large objects that might not be needed
            if (is_string($value) && strlen($value) > 50000 && str_contains($key, 'cache')) {
                $keysToRemove[] = $key;
            }
        }
        
        foreach ($keysToRemove as $key) {
            $session->remove($key);
        }
        
        $newSize = $this->estimateSessionSize($session->all());
        $spaceSaved = $originalSize - $newSize;
        
        $this->logger->info('Session storage optimized', [
            'original_size' => $this->formatBytes($originalSize),
            'new_size' => $this->formatBytes($newSize),
            'space_saved' => $this->formatBytes($spaceSaved),
            'keys_removed' => count($keysToRemove)
        ]);
        
        return [
            'success' => true,
            'original_size' => $originalSize,
            'new_size' => $newSize,
            'space_saved' => $spaceSaved,
            'keys_removed' => $keysToRemove
        ];
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Force session garbage collection
     */
    public function forceGarbageCollection(): bool
    {
        try {
            // Trigger PHP's session garbage collection
            $result = session_gc();
            
            $this->logger->info('Session garbage collection executed', [
                'result' => $result
            ]);
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Session garbage collection failed', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
}
