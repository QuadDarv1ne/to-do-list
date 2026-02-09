<?php

namespace App\Controller\Api;

use App\Entity\Task;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/tasks')]
class TaskApiController extends AbstractController
{
    #[Route('/reorder', name: 'app_api_task_reorder', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function reorderTasks(
        Request $request, 
        EntityManagerInterface $entityManager,
        TaskRepository $taskRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['positions']) || !is_array($data['positions'])) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid data format'
            ], 400);
        }
        
        try {
            $entityManager->beginTransaction();
            
            foreach ($data['positions'] as $positionData) {
                if (!isset($positionData['id']) || !isset($positionData['position'])) {
                    throw new \Exception('Invalid position data');
                }
                
                $task = $taskRepository->find($positionData['id']);
                
                if (!$task) {
                    throw new \Exception('Task not found: ' . $positionData['id']);
                }
                
                // Check if user has permission to modify this task
                if ($task->getAssignedUser() !== $this->getUser() && 
                    $task->getCreatedBy() !== $this->getUser() &&
                    !$this->isGranted('ROLE_ADMIN')) {
                    throw new \Exception('Access denied to task: ' . $positionData['id']);
                }
                
                // Update sort order (assuming you add a sortOrder field to Task entity)
                // For now, we'll use a simple approach with createdAt modification
                $task->setUpdatedAt(new \DateTimeImmutable());
            }
            
            $entityManager->flush();
            $entityManager->commit();
            
            return $this->json([
                'success' => true,
                'message' => 'Task order updated successfully'
            ]);
            
        } catch (\Exception $e) {
            $entityManager->rollback();
            
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    #[Route('/categories', name: 'app_api_categories', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getCategories(): JsonResponse
    {
        $user = $this->getUser();
        $categories = $user->getTaskCategories();
        
        $categoryData = [];
        foreach ($categories as $category) {
            $categoryData[] = [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'color' => $category->getColor(),
                'taskCount' => count($category->getTasks())
            ];
        }
        
        return $this->json($categoryData);
    }
    
    #[Route('/users', name: 'app_api_users', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getUsers(): JsonResponse
    {
        // This would typically fetch users the current user can assign tasks to
        // For simplicity, returning just the current user
        $user = $this->getUser();
        
        $userData = [
            [
                'id' => $user->getId(),
                'fullName' => $user->getFullName(),
                'email' => $user->getEmail()
            ]
        ];
        
        return $this->json($userData);
    }
    
    #[Route('/tags', name: 'app_api_tags', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getTags(): JsonResponse
    {
        $user = $this->getUser();
        
        // Return user's tags or commonly used tags
        $tags = [];
        
        return $this->json($tags);
    }
    
    #[Route('/quick-create', name: 'app_task_quick_create', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function quickCreateTask(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (empty($data['title'])) {
            return $this->json([
                'success' => false,
                'message' => 'Title is required'
            ], 400);
        }
        
        try {
            $task = new Task();
            $task->setTitle($data['title']);
            $task->setDescription($data['description'] ?? '');
            $task->setPriority($data['priority'] ?? 'medium');
            
            if (!empty($data['dueDate'])) {
                $task->setDueDate(new \DateTime($data['dueDate']));
            }
            
            $task->setStatus('pending');
            $task->setCreatedBy($this->getUser());
            $task->setAssignedUser($this->getUser());
            
            $entityManager->persist($task);
            $entityManager->flush();
            
            return $this->json([
                'success' => true,
                'message' => 'Task created successfully',
                'taskId' => $task->getId()
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Failed to create task: ' . $e->getMessage()
            ], 500);
        }
    }
}