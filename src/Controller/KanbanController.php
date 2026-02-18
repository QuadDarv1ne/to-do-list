<?php

namespace App\Controller;

use App\Repository\TaskRepository;
use App\Service\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/kanban')]
#[IsGranted('ROLE_USER')]
class KanbanController extends AbstractController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager,
        private AuditLogService $auditLog
    ) {}
    
    /**
     * Kanban board view
     */
    #[Route('', name: 'app_kanban_board', methods: ['GET'])]
    public function board(Request $request): Response
    {
        $user = $this->getUser();
        $categoryId = $request->query->get('category');
        $assignedUserId = $request->query->get('assigned_user');
        
        // Get tasks grouped by status
        $qb = $this->taskRepository->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.tags', 'tg');
        
        // Access control
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            $qb->andWhere('t.user = :user OR t.assignedUser = :user')
               ->setParameter('user', $user);
        }
        
        // Apply filters
        if ($categoryId) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $categoryId);
        }
        
        if ($assignedUserId) {
            $qb->andWhere('t.assignedUser = :assignedUser')
               ->setParameter('assignedUser', $assignedUserId);
        }
        
        $qb->orderBy('t.priority', 'DESC')
           ->addOrderBy('t.createdAt', 'DESC');
        
        $allTasks = $qb->getQuery()->getResult();
        
        // Group tasks by status
        $tasksByStatus = [
            'pending' => [],
            'in_progress' => [],
            'completed' => [],
            'cancelled' => []
        ];
        
        foreach ($allTasks as $task) {
            $status = $task->getStatus();
            if (isset($tasksByStatus[$status])) {
                $tasksByStatus[$status][] = $task;
            }
        }
        
        // Get categories for filter
        $categories = $this->entityManager->getRepository('App\Entity\TaskCategory')
            ->findAll();
        
        // Get users for filter (if admin or manager)
        $users = [];
        if (in_array('ROLE_MANAGER', $user->getRoles()) || in_array('ROLE_ADMIN', $user->getRoles())) {
            $users = $this->entityManager->getRepository('App\Entity\User')
                ->findBy(['isActive' => true], ['firstName' => 'ASC', 'lastName' => 'ASC']);
        }
        
        return $this->render('kanban/board.html.twig', [
            'tasks_by_status' => $tasksByStatus,
            'categories' => $categories,
            'users' => $users,
            'selected_category' => $categoryId,
            'selected_user' => $assignedUserId
        ]);
    }
    
    /**
     * Update task status (drag and drop)
     */
    #[Route('/task/{id}/status', name: 'app_kanban_update_status', methods: ['POST'])]
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $task = $this->taskRepository->find($id);
        
        if (!$task) {
            return $this->json(['success' => false, 'message' => 'Task not found'], 404);
        }
        
        $this->denyAccessUnlessGranted('edit', $task);
        
        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;
        
        if (!in_array($newStatus, ['pending', 'in_progress', 'completed', 'cancelled'])) {
            return $this->json(['success' => false, 'message' => 'Invalid status'], 400);
        }
        
        $oldStatus = $task->getStatus();
        $task->setStatus($newStatus);
        
        // Update completion date if completed
        if ($newStatus === 'completed' && $oldStatus !== 'completed') {
            $task->setCompletedAt(new \DateTime());
        }
        
        $this->entityManager->flush();
        
        // Log the change
        $this->auditLog->logTaskStatusChanged($this->getUser(), $task, $oldStatus, $newStatus);
        
        return $this->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'task' => [
                'id' => $task->getId(),
                'status' => $task->getStatus(),
                'completed_at' => $task->getCompletedAt()?->format('Y-m-d H:i:s')
            ]
        ]);
    }
    
    /**
     * Update task position in column
     */
    #[Route('/task/{id}/position', name: 'app_kanban_update_position', methods: ['POST'])]
    public function updatePosition(int $id, Request $request): JsonResponse
    {
        $task = $this->taskRepository->find($id);
        
        if (!$task) {
            return $this->json(['success' => false, 'message' => 'Task not found'], 404);
        }
        
        $this->denyAccessUnlessGranted('edit', $task);
        
        $data = json_decode($request->getContent(), true);
        $position = $data['position'] ?? null;
        
        if ($position !== null) {
            $task->setPosition((int)$position);
            $this->entityManager->flush();
        }
        
        return $this->json([
            'success' => true,
            'message' => 'Position updated successfully'
        ]);
    }
    
    /**
     * Get kanban statistics
     */
    #[Route('/stats', name: 'app_kanban_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $user = $this->getUser();
        
        $qb = $this->taskRepository->createQueryBuilder('t');
        
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            $qb->andWhere('t.user = :user OR t.assignedUser = :user')
               ->setParameter('user', $user);
        }
        
        $tasks = $qb->getQuery()->getResult();
        
        $stats = [
            'total' => count($tasks),
            'by_status' => [
                'pending' => 0,
                'in_progress' => 0,
                'completed' => 0,
                'cancelled' => 0
            ],
            'by_priority' => [
                'low' => 0,
                'medium' => 0,
                'high' => 0,
                'urgent' => 0
            ]
        ];
        
        foreach ($tasks as $task) {
            $status = $task->getStatus();
            $priority = $task->getPriority();
            
            if (isset($stats['by_status'][$status])) {
                $stats['by_status'][$status]++;
            }
            
            if (isset($stats['by_priority'][$priority])) {
                $stats['by_priority'][$priority]++;
            }
        }
        
        return $this->json($stats);
    }
}
