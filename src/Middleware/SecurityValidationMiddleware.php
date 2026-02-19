<?php

namespace App\Middleware;

use App\Service\InputValidationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * Security validation middleware to intercept and validate requests
 */
class SecurityValidationMiddleware implements HttpKernelInterface, TerminableInterface
{
    public function __construct(
        private HttpKernelInterface $httpKernel,
        private InputValidationService $inputValidationService
    ) {}

    public function handle(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST, bool $catch = true): Response
    {
        // Validate and sanitize request parameters
        $this->validateRequestParameters($request);
        
        // Continue with the request processing
        $response = $this->httpKernel->handle($request, $type, $catch);
        
        // Add security headers to the response
        $this->addSecurityHeaders($response);
        
        return $response;
    }
    
    public function terminate(Request $request, Response $response): void
    {
        if ($this->httpKernel instanceof TerminableInterface) {
            $this->httpKernel->terminate($request, $response);
        }
    }
    
    /**
     * Validate and sanitize request parameters
     */
    private function validateRequestParameters(Request $request): void
    {
        // Validate query parameters
        $queryParameters = $request->query->all();
        foreach ($queryParameters as $key => $value) {
            if (is_string($value)) {
                $sanitizedValue = $this->inputValidationService->validateString($value, 1000, true);
                if ($sanitizedValue !== $value) {
                    $request->query->set($key, $sanitizedValue);
                }
            }
        }
        
        // Validate request body parameters
        $requestContent = $request->request->all();
        foreach ($requestContent as $key => $value) {
            if (is_string($value)) {
                $sanitizedValue = $this->inputValidationService->validateString($value, 10000, true);
                if ($sanitizedValue !== $value) {
                    $request->request->set($key, $sanitizedValue);
                }
            }
        }
        
        // Validate content from JSON requests
        if ($request->headers->get('Content-Type') === 'application/json') {
            $content = $request->getContent();
            if (!empty($content)) {
                $decodedContent = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedContent)) {
                    $sanitizedContent = $this->sanitizeArray($decodedContent);
                    // We can't easily modify the raw content, but we can set sanitized values in request
                    foreach ($sanitizedContent as $key => $value) {
                        if (is_string($value)) {
                            $request->request->set($key, $value);
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Recursively sanitize an array
     */
    private function sanitizeArray(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = $this->inputValidationService->validateString($value, 10000, true);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Add security headers to response
     */
    private function addSecurityHeaders(Response $response): void
    {
        // Prevent MIME-type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // Enable XSS protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Referrer policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Content Security Policy
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https://api.example.com;");
    }
}