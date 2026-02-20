<?php

namespace App\Security;

use App\Entity\TaskRecurrence;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TaskRecurrenceVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!\in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])) {
            return false;
        }

        return $subject instanceof TaskRecurrence;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        $recurrence = $subject;

        switch ($attribute) {
            case self::VIEW:
                return $this->canView($recurrence, $user);
            case self::EDIT:
                return $this->canEdit($recurrence, $user);
            case self::DELETE:
                return $this->canDelete($recurrence, $user);
        }

        return false;
    }

    private function canView(TaskRecurrence $recurrence, User $user): bool
    {
        return $recurrence->getUser() === $user;
    }

    private function canEdit(TaskRecurrence $recurrence, User $user): bool
    {
        return $recurrence->getUser() === $user;
    }

    private function canDelete(TaskRecurrence $recurrence, User $user): bool
    {
        return $recurrence->getUser() === $user;
    }
}
