<?php

namespace App\Security;

use App\Entity\TaskCategory;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class TaskCategoryVoter extends Voter
{
    public const VIEW = 'TASK_CATEGORY_VIEW';
    public const EDIT = 'TASK_CATEGORY_EDIT';
    public const DELETE = 'TASK_CATEGORY_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])) {
            return false;
        }

        if (!$subject instanceof TaskCategory) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        /** @var TaskCategory $taskCategory */
        $taskCategory = $subject;
        /** @var User $user */

        return $this->canAccessCategory($taskCategory, $user, $attribute);
    }

    private function canAccessCategory(TaskCategory $category, User $user, string $attribute): bool
    {
        // Only allow access if the category belongs to the user
        return $category->getUser() === $user;
    }
}