<?php

namespace App\Security;

use App\Entity\TaskTimeTracking;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TaskTimeTrackingVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!\in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])) {
            return false;
        }

        return $subject instanceof TaskTimeTracking;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        $tracking = $subject;

        switch ($attribute) {
            case self::VIEW:
                return $this->canView($tracking, $user);
            case self::EDIT:
                return $this->canEdit($tracking, $user);
            case self::DELETE:
                return $this->canDelete($tracking, $user);
        }

        return false;
    }

    private function canView(TaskTimeTracking $tracking, User $user): bool
    {
        return $tracking->getUser() === $user;
    }

    private function canEdit(TaskTimeTracking $tracking, User $user): bool
    {
        return $tracking->getUser() === $user;
    }

    private function canDelete(TaskTimeTracking $tracking, User $user): bool
    {
        return $tracking->getUser() === $user;
    }
}
