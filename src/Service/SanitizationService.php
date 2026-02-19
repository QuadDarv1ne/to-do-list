<?php

namespace App\Service;

use App\Service\PerformanceMonitorService;

class SanitizationService
{
    private ?PerformanceMonitorService $performanceMonitor;

    public function __construct(?PerformanceMonitorService $performanceMonitor = null)
    {
        $this->performanceMonitor = $performanceMonitor;
    }

    /**
     * Sanitize user input to prevent XSS attacks
     */
    public function sanitizeInput(string $input): string
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('sanitization_service_sanitize_input');
        }
        try {
            // Remove HTML tags
            $sanitized = strip_tags($input);
            
            // Convert special characters to HTML entities
            $sanitized = htmlspecialchars($sanitized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            // Remove potentially dangerous characters
            $sanitized = preg_replace('/[^\p{L}\p{N}\s\-_\.@]/u', '', $sanitized);
            
            return trim($sanitized);
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('sanitization_service_sanitize_input');
            }
        }
    }

    /**
     * Sanitize rich text content (allows safe HTML)
     */
    public function sanitizeRichText(string $html): string
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('sanitization_service_sanitize_rich_text');
        }
        try {
            // Use HTML Purifier or similar library in production
            // This is an improved example with more comprehensive sanitization
            $allowedTags = '<p><br><strong><em><u><ol><ul><li><h1><h2><h3><h4><h5><h6><blockquote><code><pre><small><sub><sup><ins><del><mark>';
            $sanitized = strip_tags($html, $allowedTags);
            
            // Remove dangerous attributes and patterns
            $patterns = [
                '/(<[^>]+) on[a-z]+\s*=\s*["\'][^"\']*["\']/i',  // Event handlers
                '/(<[^>]+) href\s*=\s*["\']javascript:[^"\']*["\']/i',  // JavaScript in href
                '/(<[^>]+) src\s*=\s*["\']javascript:[^"\']*["\']/i',  // JavaScript in src
                '/(<[^>]+) data\s*=\s*["\']javascript:[^"\']*["\']/i',  // JavaScript in data
                '/(<[^>]+) formaction\s*=\s*["\']javascript:[^"\']*["\']/i',  // Form actions
                '/javascript:/i',  // Direct javascript: protocol
                '/vbscript:/i',  // VBScript
                '/data:/i',  // Data URIs
                '/about:/i',  // About URIs
                '/(<[^>]+) style\s*=\s*["\'][^"\']*["\']/i',  // Inline styles (potential CSS injection)
            ];
            
            foreach ($patterns as $pattern) {
                $sanitized = preg_replace($pattern, '$1', $sanitized);
            }
            
            // Additional sanitization: remove any remaining script tags (case insensitive)
            $sanitized = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $sanitized);
            
            return $sanitized;
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('sanitization_service_sanitize_rich_text');
            }
        }
    }

    /**
     * Validate and sanitize email addresses
     */
    public function sanitizeEmail(string $email): string
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('sanitization_service_sanitize_email');
        }
        try {
            $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('sanitization_service_sanitize_email');
            }
        }
    }

    /**
     * Sanitize URLs
     */
    public function sanitizeUrl(string $url): string
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('sanitization_service_sanitize_url');
        }
        try {
            $url = filter_var(trim($url), FILTER_SANITIZE_URL);
            return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('sanitization_service_sanitize_url');
            }
        }
    }

    /**
     * Sanitize numeric input
     */
    public function sanitizeNumber($input): ?float
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('sanitization_service_sanitize_number');
        }
        try {
            if (is_numeric($input)) {
                return (float) $input;
            }
            return null;
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('sanitization_service_sanitize_number');
            }
        }
    }

    /**
     * Sanitize array of inputs
     */
    public function sanitizeArray(array $inputs): array
    {
        if ($this->performanceMonitor) {
            $this->performanceMonitor->startTiming('sanitization_service_sanitize_array');
        }
        try {
            $sanitized = [];
            foreach ($inputs as $key => $value) {
                if (is_array($value)) {
                    $sanitized[$key] = $this->sanitizeArray($value);
                } elseif (is_string($value)) {
                    $sanitized[$key] = $this->sanitizeInput($value);
                } else {
                    $sanitized[$key] = $value;
                }
            }
            return $sanitized;
        } finally {
            if ($this->performanceMonitor) {
                $this->performanceMonitor->stopTiming('sanitization_service_sanitize_array');
            }
        }
    }
}
