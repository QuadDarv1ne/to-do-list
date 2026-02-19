<?php

namespace App\Security;

use App\Entity\Goal;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class GoalVoter extends Voter
{
    public const EDIT = 'edit';

    public const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::DELETE])
            && $subject instanceof Goal;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Goal $goal */
        $goal = $subject;

        return match($attribute) {
            self::EDIT, self::DELETE => $this->canEdit($goal, $user),
            default => false,
        };
    }

    private function canEdit(Goal $goal, User $user): bool
    {
        return $goal->getOwner() === $user;
    }
}
