<?php

namespace App\EventListener;

use App\Service\InputValidationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Security validation listener to validate requests at the kernel level
 */
class SecurityValidationListener implements EventSubscriberInterface
{
    public function __construct(
        private InputValidationService $inputValidationService
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Skip validation for certain paths (e.g., API endpoints that handle their own validation)
        $path = $request->getPathInfo();
        if ($this->shouldSkipValidation($path)) {
            return;
        }

        // Validate and sanitize request parameters
        $this->validateAndSanitizeRequest($request);
    }

    /**
     * Validate and sanitize request parameters
     */
    private function validateAndSanitizeRequest($request): void
    {
        // Sanitize query parameters
        $queryParameters = $request->query->all();
        foreach ($queryParameters as $key => $value) {
            if (is_string($value)) {
                $sanitizedValue = $this->inputValidationService->validateString($value, 1000, true);
                if ($sanitizedValue !== $value) {
                    $request->query->set($key, $sanitizedValue);
                }
            }
        }

        // Sanitize request body parameters
        $requestContent = $request->request->all();
        foreach ($requestContent as $key => $value) {
            if (is_string($value)) {
                $sanitizedValue = $this->inputValidationService->validateString($value, 10000, true);
                if ($sanitizedValue !== $value) {
                    $request->request->set($key, $sanitizedValue);
                }
            }
        }

        // Sanitize route parameters
        $routeParams = $request->attributes->all();
        foreach ($routeParams as $key => $value) {
            if (is_string($value)) {
                $sanitizedValue = $this->inputValidationService->validateString($value, 1000, true);
                if ($sanitizedValue !== $value) {
                    $request->attributes->set($key, $sanitizedValue);
                }
            }
        }
    }

    /**
     * Determine if validation should be skipped for this path
     */
    private function shouldSkipValidation(string $path): bool
    {
        // Define paths that should skip validation (e.g., API endpoints that handle their own validation)
        $skipPaths = [
            '/api/',
            '/admin/',
            '/_wdt',
            '/_profiler',
        ];

        foreach ($skipPaths as $skipPath) {
            if (str_starts_with($path, $skipPath)) {
                return true;
            }
        }

        return false;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10], // Priority 10 to run early
        ];
    }
}