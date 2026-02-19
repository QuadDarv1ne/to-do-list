<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * HTTP Cache Subscriber - Spotify-style static resource caching
 */
class HttpCacheSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Don't cache if already has cache headers
        if ($response->headers->has('Cache-Control')) {
            return;
        }

        $path = $request->getPathInfo();

        // Cache static assets (CSS, JS, images) for 1 year
        if ($this->isStaticAsset($path)) {
            $response->setPublic();
            $response->setMaxAge(31536000); // 1 year
            $response->headers->addCacheControlDirective('immutable');

            return;
        }

        // Cache API responses for 5 minutes
        if (str_starts_with($path, '/api/')) {
            $response->setPublic();
            $response->setMaxAge(300); // 5 minutes
            $response->setSharedMaxAge(300);

            return;
        }

        // Cache public pages for 10 minutes
        if ($this->isPublicPage($path) && $response->isSuccessful()) {
            $response->setPublic();
            $response->setMaxAge(600); // 10 minutes
            $response->setSharedMaxAge(600);

            return;
        }

        // Private pages - no cache
        if ($this->isPrivatePage($path)) {
            $response->setPrivate();
            $response->setMaxAge(0);
            $response->headers->addCacheControlDirective('no-cache');
            $response->headers->addCacheControlDirective('must-revalidate');
        }
    }

    private function isStaticAsset(string $path): bool
    {
        return preg_match('/\\.(css|js|jpg|jpeg|png|gif|svg|woff|woff2|ttf|eot|ico|webp)$/i', $path);
    }

    private function isPublicPage(string $path): bool
    {
        $publicPaths = ['/about', '/login', '/register'];

        foreach ($publicPaths as $publicPath) {
            if (str_starts_with($path, $publicPath)) {
                return true;
            }
        }

        return false;
    }

    private function isPrivatePage(string $path): bool
    {
        $privatePaths = ['/dashboard', '/tasks', '/profile', '/settings'];

        foreach ($privatePaths as $privatePath) {
            if (str_starts_with($path, $privatePath)) {
                return true;
            }
        }

        return false;
    }
}
