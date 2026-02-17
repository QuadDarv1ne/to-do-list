<?php

namespace App\Controller;

use App\Repository\TaskRepository;
use App\Repository\TaskCategoryRepository;
use App\Service\PerformanceMonitorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/calendar')]
class CalendarController extends AbstractController
{
    #[Route('/', name: 'app_calendar_index')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(
        TaskCategoryRepository $categoryRepository,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('calendar_controller_index');
        }
        
        $user = $this->getUser();
        $categories = $user->getTaskCategories();
        
        try {
            return $this->render('calendar/index.html.twig', [
                'categories' => $categories,
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('calendar_controller_index');
            }
        }
    }
    
    #[Route('/api/events', name: 'app_api_calendar_events', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getEvents(
        Request $request,
        TaskRepository $taskRepository,
        ?PerformanceMonitorService $performanceMonitor = null
    ): JsonResponse {
        if ($performanceMonitor) {
            $performanceMonitor->startTiming('calendar_controller_get_events');
        }
        
        $user = $this->getUser();
        $start = $request->query->get('start');
        $end = $request->query->get('end');
        $status = $request->query->get('status');
        $priority = $request->query->get('priority');
        $category = $request->query->get('category');
        $showOverdue = $request->query->get('showOverdue', '1') === '1';
        $showCompleted = $request->query->get('showCompleted', '0') === '1';
        
        // Build search criteria
        $criteria = [
            'user' => $user,
        ];
        
        if ($status) {
            $criteria['status'] = $status;
        }
        
        if ($priority) {
            $criteria['priority'] = $priority;
        }
        
        if ($category) {
            $criteria['category'] = $category;
        }
        
        if (!$showOverdue) {
            $criteria['hideOverdue'] = true;
        }
        
        if (!$showCompleted) {
            $criteria['hideCompleted'] = true;
        }
        
        if ($start) {
            $criteria['startDate'] = new \DateTime($start);
        }
        
        if ($end) {
            $criteria['endDate'] = new \DateTime($end);
        }
        
        $tasks = $taskRepository->searchTasks($criteria);
        
        // Convert tasks to calendar events format
        $events = [];
        foreach ($tasks as $task) {
            $event = [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'start' => $task->getDueDate() ? $task->getDueDate()->format('Y-m-d\TH:i:s') : $task->getCreatedAt()->format('Y-m-d\TH:i:s'),
                'allDay' => true,
                'extendedProps' => [
                    'description' => $task->getDescription(),
                    'status' => $task->getStatus(),
                    'priority' => $task->getPriority(),
                    'category' => $task->getCategory() ? $task->getCategory()->getName() : null,
                    'isOverdue' => $task->isOverdue(),
                    'isCompleted' => $task->isCompleted(),
                ]
            ];
            
            // Add color based on priority and status
            if ($task->isCompleted()) {
                $event['backgroundColor'] = '#28a745'; // green
                $event['borderColor'] = '#28a745';
            } elseif ($task->isOverdue()) {
                $event['backgroundColor'] = '#dc3545'; // red
                $event['borderColor'] = '#dc3545';
            } elseif ($task->getPriority() === 'high' || $task->getPriority() === 'urgent') {
                $event['backgroundColor'] = '#fd7e14'; // orange
                $event['borderColor'] = '#fd7e14';
            } elseif ($task->getPriority() === 'low') {
                $event['backgroundColor'] = '#6c757d'; // gray
                $event['borderColor'] = '#6c757d';
            } else {
                $event['backgroundColor'] = '#0d6efd'; // blue (medium priority)
                $event['borderColor'] = '#0d6efd';
            }
            
            $events[] = $event;
        }
        
        try {
            return $this->json($events);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTiming('calendar_controller_get_events');
            }
        }
    }
}
