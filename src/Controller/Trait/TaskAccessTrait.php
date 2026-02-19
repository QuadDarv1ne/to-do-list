<?php

namespace App\Controller\Trait;

use App\Entity\Task;
use Symfony\Component\HttpFoundation\JsonResponse;

trait TaskAccessTrait
{
    /**
     * Check if current user can access the task
     */
    private function canAccessTask(Task $task): bool
    {
        $user = $this->getUser();
        
        return $task->getAssignedTo() === $user || 
               $task->getCreatedBy() === $user ||
               $this->isGranted('ROLE_ADMIN');
    }

    /**
     * Check access and return error response if denied
     */
    private function checkTaskAccess(Task $task): ?JsonResponse
    {
        if (!$this->canAccessTask($task)) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }
        
        return null;
    }
}
