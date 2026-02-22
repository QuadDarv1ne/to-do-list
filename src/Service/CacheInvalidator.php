<?php

namespace App\Service;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class CacheInvalidator implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $request = $event->getRequest();

        // Не кэшировать API запросы
        if (str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        // Добавить заголовки кэширования для GET запросов
        if ($request->isMethod('GET') && $response->isSuccessful()) {
            // Статические страницы - долгое кэширование
            if ($this->isStaticPage($request)) {
                $response->setMaxAge(3600);
                $response->setSharedMaxAge(3600);
                $response->headers->addCacheControlDirective('public');
            } 
            // Динамические страницы - короткое кэширование
            else {
                $response->setMaxAge(60);
                $response->setSharedMaxAge(60);
                $response->headers->addCacheControlDirective('public');
            }

            // Добавить ETag для валидации
            if (!$response->headers->has('ETag')) {
                $response->setETag(md5($response->getContent()));
            }

            // Vary: Accept-Encoding для сжатия
            $response->setVary(['Accept-Encoding', 'Accept-Language']);
        }
    }

    private function isStaticPage($request): bool
    {
        $staticRoutes = [
            'app_dashboard',
            'app_kanban_board',
            'app_calendar',
        ];

        return \in_array($request->attributes->get('_route'), $staticRoutes, true);
    }
}
