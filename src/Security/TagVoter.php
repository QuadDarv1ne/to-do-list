<?php

namespace App\Security;

use App\Entity\Tag;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TagVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';
    public const CREATE = 'create';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::CREATE])) {
            return false;
        }

        // only vote on Tag objects inside this voter
        if (!$subject instanceof Tag) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            // the user must be logged in; if not, deny access
            return false;
        }

        $tag = $subject;

        switch ($attribute) {
            case self::VIEW:
                return $this->canView($tag, $user);
            case self::EDIT:
                return $this->canEdit($tag, $user);
            case self::DELETE:
                return $this->canDelete($tag, $user);
            case self::CREATE:
                return $this->canCreate($tag, $user);
        }

        return false;
    }

    private function canView(Tag $tag, User $user): bool
    {
        // Users can only view their own tags
        return $this->isOwner($tag, $user);
    }

    private function canEdit(Tag $tag, User $user): bool
    {
        // Users can only edit their own tags
        return $this->isOwner($tag, $user);
    }

    private function canDelete(Tag $tag, User $user): bool
    {
        // Users can only delete their own tags
        return $this->isOwner($tag, $user);
    }

    private function canCreate(Tag $tag, User $user): bool
    {
        // Users can create tags for themselves
        return true;
    }

    private function isOwner(Tag $tag, User $user): bool
    {
        // Check if the tag belongs to the user
        return $tag->getUser() && $tag->getUser()->getId() === $user->getId();
    }
}
