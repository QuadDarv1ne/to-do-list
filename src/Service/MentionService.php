<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;

class MentionService
{
    public function __construct(
        private UserRepository $userRepository,
        private NotificationService $notificationService
    ) {}

    /**
     * Parse mentions from text (@username)
     */
    public function parseMentions(string $text): array
    {
        $mentions = [];
        
        // Find all @mentions
        preg_match_all('/@(\w+)/', $text, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $username) {
                $user = $this->userRepository->findOneBy(['username' => $username]);
                if ($user) {
                    $mentions[] = $user;
                }
            }
        }

        return array_unique($mentions, SORT_REGULAR);
    }

    /**
     * Convert mentions to links
     */
    public function convertMentionsToLinks(string $text): string
    {
        return preg_replace_callback('/@(\w+)/', function($matches) {
            $username = $matches[1];
            $user = $this->userRepository->findOneBy(['username' => $username]);
            
            if ($user) {
                return sprintf(
                    '<a href="/users/%d" class="mention">@%s</a>',
                    $user->getId(),
                    $username
                );
            }
            
            return $matches[0];
        }, $text);
    }

    /**
     * Notify mentioned users
     */
    public function notifyMentionedUsers(array $users, $context, User $mentionedBy): void
    {
        foreach ($users as $user) {
            $this->notificationService->notifyMention($user, $context, $mentionedBy);
        }
    }

    /**
     * Get mention suggestions
     */
    public function getSuggestions(string $query, int $limit = 5): array
    {
        return $this->userRepository->createQueryBuilder('u')
            ->where('u.username LIKE :query OR u.fullName LIKE :query')
            ->andWhere('u.isActive = :active')
            ->setParameter('query', $query . '%')
            ->setParameter('active', true)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get user mentions
     */
    public function getUserMentions(User $user, int $limit = 20): array
    {
        // TODO: Get from database
        return [];
    }

    /**
     * Mark mention as read
     */
    public function markAsRead(int $mentionId): bool
    {
        // TODO: Update in database
        return true;
    }
}
