<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Глобальный обработчик исключений
 */
class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Логируем исключение
        $this->logger->error('Exception caught', [
            'exception' => \get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'url' => $request->getUri(),
            'method' => $request->getMethod(),
        ]);

        // Определяем код ответа
        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        // Для API запросов возвращаем JSON
        if ($this->isApiRequest($request)) {
            $response = new JsonResponse([
                'error' => $this->environment === 'prod'
                    ? 'Произошла ошибка на сервере'
                    : $exception->getMessage(),
                'code' => $statusCode,
            ], $statusCode);

            $event->setResponse($response);

            return;
        }

        // Для обычных запросов показываем страницу ошибки
        // Symfony автоматически обработает это
    }

    private function isApiRequest($request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/') ||
               $request->headers->get('Accept') === 'application/json' ||
               $request->isXmlHttpRequest();
    }
}
