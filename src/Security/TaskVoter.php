<?php

namespace App\Security;

use App\Entity\Task;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TaskVoter extends Voter
{
    const VIEW = 'TASK_VIEW';
    const EDIT = 'TASK_EDIT';
    const DELETE = 'TASK_DELETE';
    const ASSIGN = 'TASK_ASSIGN';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::ASSIGN])
            && $subject instanceof Task;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Task $task */
        $task = $subject;

        return match($attribute) {
            self::VIEW => $this->canView($task, $user),
            self::EDIT => $this->canEdit($task, $user),
            self::DELETE => $this->canDelete($task, $user),
            self::ASSIGN => $this->canAssign($task, $user),
            default => false
        };
    }

    private function canView(Task $task, User $user): bool
    {
        // Admins and managers can view all tasks
        if ($user->isAdmin() || $user->isManager()) {
            return true;
        }

        // Users can view their own tasks or tasks assigned to them
        return $task->getUser() === $user || $task->getAssignedUser() === $user;
    }

    private function canEdit(Task $task, User $user): bool
    {
        // Admins can edit all tasks
        if ($user->isAdmin()) {
            return true;
        }

        // Managers can edit tasks in their department
        if ($user->isManager()) {
            return true;
        }

        // Users can edit their own tasks or tasks assigned to them
        return $task->getUser() === $user || $task->getAssignedUser() === $user;
    }

    private function canDelete(Task $task, User $user): bool
    {
        // Only admins and task creators can delete
        if ($user->isAdmin()) {
            return true;
        }

        return $task->getUser() === $user;
    }

    private function canAssign(Task $task, User $user): bool
    {
        // Admins and managers can assign tasks
        if ($user->isAdmin() || $user->isManager()) {
            return true;
        }

        // Task creator can assign
        return $task->getUser() === $user;
    }
}
