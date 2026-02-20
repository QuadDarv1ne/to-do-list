<?php

namespace App\Security\Voter;

use App\Entity\Client;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ClientVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE])
            && $subject instanceof Client;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Client $client */
        $client = $subject;

        // Админы имеют полный доступ
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // Менеджер может управлять только своими клиентами
        return match($attribute) {
            self::VIEW => $client->getManager() === $user,
            self::EDIT => $client->getManager() === $user,
            self::DELETE => $client->getManager() === $user,
            default => false,
        };
    }
}
