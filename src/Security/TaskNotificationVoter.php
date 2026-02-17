<?php

namespace App\Security;

use App\Entity\TaskNotification;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class TaskNotificationVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';
    public const CREATE = 'create';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])) {
            return false;
        }

        if (!$subject instanceof TaskNotification) {
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

        /** @var TaskNotification $notification */
        $notification = $subject;
        /** @var User $user */

        return $this->canAccessNotification($notification, $user, $attribute);
    }

    private function canAccessNotification(TaskNotification $notification, User $user, string $attribute): bool
    {
        // Only allow access if the notification is for the user
        return $notification->getRecipient() === $user;
    }
}
