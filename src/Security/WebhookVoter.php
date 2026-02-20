<?php

namespace App\Security;

use App\Entity\Webhook;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class WebhookVoter extends Voter
{
    public const VIEW = 'view';

    public const EDIT = 'edit';

    public const DELETE = 'delete';

    public const CREATE = 'create';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!\in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::CREATE])) {
            return false;
        }

        return $subject instanceof Webhook;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        $webhook = $subject;

        switch ($attribute) {
            case self::VIEW:
                return $this->canView($webhook, $user);
            case self::EDIT:
                return $this->canEdit($webhook, $user);
            case self::DELETE:
                return $this->canDelete($webhook, $user);
            case self::CREATE:
                return $this->canCreate($webhook, $user);
        }

        return false;
    }

    private function canView(Webhook $webhook, User $user): bool
    {
        return $this->isOwner($webhook, $user);
    }

    private function canEdit(Webhook $webhook, User $user): bool
    {
        return $this->isOwner($webhook, $user);
    }

    private function canDelete(Webhook $webhook, User $user): bool
    {
        return $this->isOwner($webhook, $user);
    }

    private function canCreate(Webhook $webhook, User $user): bool
    {
        return true;
    }

    private function isOwner(Webhook $webhook, User $user): bool
    {
        return $webhook->getUser() && $webhook->getUser()->getId() === $user->getId();
    }
}
