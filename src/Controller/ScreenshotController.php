<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ScreenshotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/screenshots')]
#[IsGranted('ROLE_USER')]
class ScreenshotController extends AbstractController
{
    public function __construct(
        private readonly ScreenshotService $screenshotService,
    ) {
    }

    /**
     * Страница управления скриншотами
     */
    #[Route('', name: 'app_screenshots', methods: ['GET'])]
    public function index(): Response
    {
        $screenshots = $this->screenshotService->getScreenshotList();
        
        return $this->render('screenshots/index.html.twig', [
            'screenshots' => $screenshots,
        ]);
    }

    /**
     * API: Создать скриншот
     */
    #[Route('/api/capture', name: 'app_screenshots_api_capture', methods: ['POST'])]
    public function capture(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (empty($data['url'])) {
            return $this->json([
                'success' => false,
                'error' => 'Необходимо указать URL',
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $url = $data['url'];
        $filename = $data['filename'] ?? null;
        $options = $data['options'] ?? [];
        
        $result = $this->screenshotService->takeScreenshot($url, $filename, $options);
        
        if ($result['success']) {
            return $this->json([
                'success' => true,
                'file' => $result['file'],
                'url' => $result['url'] ?? null,
                'note' => $result['note'] ?? null,
            ]);
        }
        
        return $this->json([
            'success' => false,
            'error' => $result['error'] ?? 'Неизвестная ошибка',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * API: Создать несколько скриншотов
     */
    #[Route('/api/capture-batch', name: 'app_screenshots_api_batch', methods: ['POST'])]
    public function captureBatch(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (empty($data['urls']) || !is_array($data['urls'])) {
            return $this->json([
                'success' => false,
                'error' => 'Необходимо указать массив URL',
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $options = $data['options'] ?? [];
        
        $results = $this->screenshotService->takeMultipleScreenshots($data['urls'], $options);
        
        return $this->json([
            'success' => true,
            'results' => $results,
            'total' => count($results),
        ]);
    }

    /**
     * API: Список скриншотов
     */
    #[Route('/api/list', name: 'app_screenshots_api_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $screenshots = $this->screenshotService->getScreenshotList();
        
        return $this->json([
            'success' => true,
            'screenshots' => $screenshots,
            'total' => count($screenshots),
        ]);
    }

    /**
     * API: Удалить скриншот
     */
    #[Route('/api/delete/{filename}', name: 'app_screenshots_api_delete', methods: ['DELETE'])]
    public function delete(string $filename): JsonResponse
    {
        // Защита от path traversal
        $filename = basename($filename);
        
        if ($this->screenshotService->deleteScreenshot($filename)) {
            return $this->json([
                'success' => true,
                'message' => 'Скриншот удалён',
            ]);
        }
        
        return $this->json([
            'success' => false,
            'error' => 'Скриншот не найден',
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * API: Очистить старые скриншоты
     */
    #[Route('/api/cleanup', name: 'app_screenshots_api_cleanup', methods: ['POST'])]
    public function cleanup(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $days = $data['days'] ?? 30;
        
        $deleted = $this->screenshotService->cleanupOldScreenshots((int) $days);
        
        return $this->json([
            'success' => true,
            'deleted' => $deleted,
            'message' => "Удалено $deleted скриншотов старше $days дней",
        ]);
    }

    /**
     * Быстрый скриншот текущей страницы (через referer)
     */
    #[Route('/quick', name: 'app_screenshots_quick', methods: ['POST'])]
    public function quick(Request $request): JsonResponse
    {
        $referer = $request->headers->get('referer');
        
        if (!$referer) {
            return $this->json([
                'success' => false,
                'error' => 'Не удалось определить текущую страницу',
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $result = $this->screenshotService->takeScreenshot($referer);
        
        if ($result['success']) {
            return $this->json([
                'success' => true,
                'file' => $result['file'],
                'note' => $result['note'] ?? null,
            ]);
        }
        
        return $this->json([
            'success' => false,
            'error' => $result['error'] ?? 'Ошибка создания скриншота',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
