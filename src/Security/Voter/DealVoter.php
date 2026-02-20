<?php

namespace App\Security\Voter;

use App\Entity\Deal;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class DealVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Deal;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Deal $deal */
        $deal = $subject;

        // Админы имеют полный доступ
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // Менеджер может управлять только своими сделками
        return match($attribute) {
            self::VIEW => $deal->getManager() === $user,
            self::EDIT => $deal->getManager() === $user,
            self::DELETE => $deal->getManager() === $user,
            default => false,
        };
    }
}
