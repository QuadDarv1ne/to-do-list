<?php

namespace App\Controller;

use App\Service\DashboardWidgetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/settings')]
#[IsGranted('ROLE_USER')]
class SettingsController extends AbstractController
{
    public function __construct(
        private DashboardWidgetService $widgetService
    ) {}
    
    /**
     * Settings page
     */
    #[Route('', name: 'app_settings', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        
        return $this->render('settings/index.html.twig', [
            'available_widgets' => $this->widgetService->getAvailableWidgets(),
            'user_widgets' => $this->widgetService->getUserWidgets($user)
        ]);
    }
    
    /**
     * Update dashboard widgets
     */
    #[Route('/widgets', name: 'app_settings_widgets', methods: ['POST'])]
    public function updateWidgets(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        
        $widgets = $data['widgets'] ?? [];
        
        $this->widgetService->saveUserWidgets($user, $widgets);
        
        return $this->json([
            'success' => true,
            'message' => 'Настройки виджетов сохранены'
        ]);
    }
    
    /**
     * Update theme preference
     */
    #[Route('/theme', name: 'app_settings_theme', methods: ['POST'])]
    public function updateTheme(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $theme = $data['theme'] ?? 'light';
        
        // Save to session for now
        $request->getSession()->set('theme', $theme);
        
        return $this->json([
            'success' => true,
            'theme' => $theme
        ]);
    }
    
    /**
     * Update notification preferences
     */
    #[Route('/notifications', name: 'app_settings_notifications', methods: ['POST'])]
    public function updateNotifications(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // TODO: Save to user preferences
        $preferences = [
            'email_notifications' => $data['email_notifications'] ?? true,
            'push_notifications' => $data['push_notifications'] ?? true,
            'deadline_reminders' => $data['deadline_reminders'] ?? true,
            'task_assignments' => $data['task_assignments'] ?? true
        ];
        
        $request->getSession()->set('notification_preferences', $preferences);
        
        return $this->json([
            'success' => true,
            'message' => 'Настройки уведомлений сохранены'
        ]);
    }
    
    /**
     * Export user data
     */
    #[Route('/export-data', name: 'app_settings_export_data', methods: ['GET'])]
    public function exportData(): Response
    {
        $user = $this->getUser();
        
        $data = [
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'full_name' => $user->getFullName(),
                'created_at' => $user->getCreatedAt()?->format('c')
            ],
            'exported_at' => (new \DateTime())->format('c')
        ];
        
        $response = new Response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 'attachment; filename="user_data_' . $user->getId() . '.json"');
        
        return $response;
    }
    
    /**
     * Get keyboard shortcuts
     */
    #[Route('/shortcuts', name: 'app_settings_shortcuts', methods: ['GET'])]
    public function shortcuts(): Response
    {
        $shortcuts = [
            'global' => [
                ['keys' => 'Ctrl + K', 'description' => 'Быстрый поиск'],
                ['keys' => 'Ctrl + N', 'description' => 'Новая задача'],
                ['keys' => 'Ctrl + /', 'description' => 'Показать горячие клавиши'],
                ['keys' => 'Esc', 'description' => 'Закрыть модальное окно']
            ],
            'tasks' => [
                ['keys' => 'E', 'description' => 'Редактировать задачу'],
                ['keys' => 'D', 'description' => 'Удалить задачу'],
                ['keys' => 'C', 'description' => 'Отметить как завершенную'],
                ['keys' => 'A', 'description' => 'Назначить пользователю']
            ],
            'navigation' => [
                ['keys' => 'G + D', 'description' => 'Перейти к дашборду'],
                ['keys' => 'G + T', 'description' => 'Перейти к задачам'],
                ['keys' => 'G + K', 'description' => 'Перейти к канбану'],
                ['keys' => 'G + R', 'description' => 'Перейти к отчетам']
            ]
        ];
        
        return $this->render('settings/shortcuts.html.twig', [
            'shortcuts' => $shortcuts
        ]);
    }
}
