<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * Обработчик ошибок для кастомных страниц ошибок
 */
class ErrorSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Получаем статус только для HTTP исключений
        if (!$exception instanceof HttpExceptionInterface) {
            return;
        }

        $statusCode = $exception->getStatusCode();

        // Обрабатываем только 404 ошибки
        if ($statusCode === 404) {
            $response = new Response(
                $this->twig->render('errors/404.html.twig'),
                404,
            );
            $event->setResponse($response);
        }
    }
}
