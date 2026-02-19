<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

#[AsEventListener(event: 'kernel.exception', priority: 0)]
class ApiExceptionListener
{
    public function __construct(
        private LoggerInterface $logger,
        private string $environment,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        // Применяем только к API запросам
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();

        // Логируем ошибку
        $this->logger->error('API Exception', [
            'exception' => \get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'path' => $request->getPathInfo(),
            'method' => $request->getMethod(),
        ]);

        // Формируем ответ
        $statusCode = 500;
        $message = 'Внутренняя ошибка сервера';

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage();
        }

        // В production не показываем детали ошибок
        $response = [
            'error' => true,
            'message' => $message,
            'status' => $statusCode,
        ];

        // В dev режиме добавляем детали
        if ($this->environment === 'dev') {
            $response['debug'] = [
                'exception' => \get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        $event->setResponse(new JsonResponse($response, $statusCode));
    }
}
