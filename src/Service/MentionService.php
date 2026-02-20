<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;

class MentionService
{
    public function __construct(
        private UserRepository $userRepository,
        private NotificationService $notificationService,
    ) {
    }

    /**
     * Extract mentioned users from text (@username)
     * Returns array of User objects
     */
    public function extractMentions(string $text): array
    {
        $mentions = [];

        // Find all @mentions (alphanumeric and underscore)
        preg_match_all('/@([a-zA-Z0-9_]+)/', $text, $matches);

        if (!empty($matches[1])) {
            $uniqueUsernames = array_unique($matches[1]);
            
            // Batch load users for better performance
            if (!empty($uniqueUsernames)) {
                $users = $this->userRepository->createQueryBuilder('u')
                    ->where('u.username IN (:usernames)')
                    ->andWhere('u.isActive = :active')
                    ->setParameter('usernames', $uniqueUsernames)
                    ->setParameter('active', true)
                    ->getQuery()
                    ->getResult();

                foreach ($users as $user) {
                    $mentions[] = $user;
                }
            }
        }

        return $mentions;
    }

    /**
     * Parse mentions from text (@username) - alias for backward compatibility
     */
    public function parseMentions(string $text): array
    {
        return $this->extractMentions($text);
    }

    /**
     * Convert mentions to links with XSS protection
     */
    public function convertMentionsToLinks(string $text): string
    {
        // First escape HTML to prevent XSS
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        
        // Then convert mentions to links
        return preg_replace_callback('/@([a-zA-Z0-9_]+)/', function ($matches) {
            $username = $matches[1];
            $user = $this->userRepository->findOneBy(['username' => $username]);

            if ($user) {
                return sprintf(
                    '<a href="/users/%d" class="mention" data-user-id="%d">@%s</a>',
                    $user->getId(),
                    $user->getId(),
                    htmlspecialchars($username, ENT_QUOTES, 'UTF-8')
                );
            }

            return '@' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
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
            ->where('u.username LIKE :query OR CONCAT(u.firstName, \' \', u.lastName) LIKE :query')
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
