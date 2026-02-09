<?php

namespace App\Service;

class SanitizationService
{
    /**
     * Sanitize user input to prevent XSS attacks
     */
    public function sanitizeInput(string $input): string
    {
        // Remove HTML tags
        $sanitized = strip_tags($input);
        
        // Convert special characters to HTML entities
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove potentially dangerous characters
        $sanitized = preg_replace('/[^\p{L}\p{N}\s\-_\.@]/u', '', $sanitized);
        
        return trim($sanitized);
    }

    /**
     * Sanitize rich text content (allows safe HTML)
     */
    public function sanitizeRichText(string $html): string
    {
        // Use HTML Purifier or similar library in production
        // This is a basic example
        $allowedTags = '<p><br><strong><em><u><ol><ul><li><h1><h2><h3><h4><h5><h6>';
        $sanitized = strip_tags($html, $allowedTags);
        
        // Remove dangerous attributes
        $sanitized = preg_replace('/(<[^>]+) on[a-z]+\s*=\s*"[^"]*"/i', '$1', $sanitized);
        $sanitized = preg_replace("/(<[^>]+) on[a-z]+\s*=\s*'[^']*'/i", '$1', $sanitized);
        $sanitized = preg_replace('/(<[^>]+) javascript:/i', '$1', $sanitized);
        
        return $sanitized;
    }

    /**
     * Validate and sanitize email addresses
     */
    public function sanitizeEmail(string $email): string
    {
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    /**
     * Sanitize URLs
     */
    public function sanitizeUrl(string $url): string
    {
        $url = filter_var(trim($url), FILTER_SANITIZE_URL);
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }

    /**
     * Sanitize numeric input
     */
    public function sanitizeNumber($input): ?float
    {
        if (is_numeric($input)) {
            return (float) $input;
        }
        return null;
    }

    /**
     * Sanitize array of inputs
     */
    public function sanitizeArray(array $inputs): array
    {
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
    }
}