<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Контроллер для обработки отчётов о нарушениях Content Security Policy
 */
class CspReportController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Обработка отчётов о нарушениях CSP
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP
     */
    public function report(Request $request): Response
    {
        $content = $request->getContent();
        $data = json_decode($content, true);

        if ($data && isset($data['csp-report'])) {
            $report = $data['csp-report'];

            // Логируем нарушение
            $this->logger->warning('CSP Violation', [
                'blocked_uri' => $report['blocked-uri'] ?? null,
                'document_uri' => $report['document-uri'] ?? null,
                'effective_directive' => $report['effective-directive'] ?? null,
                'original_policy' => $report['original-policy'] ?? null,
                'referrer' => $report['referrer'] ?? null,
                'script_sample' => $report['script-sample'] ?? null,
                'source_file' => $report['source-file'] ?? null,
                'status_code' => $report['status-code'] ?? null,
                'violated_directive' => $report['violated-directive'] ?? null,
                'disposition' => $report['disposition'] ?? 'unknown',
            ]);
        }

        // Возвращаем пустой ответ 204 No Content
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
