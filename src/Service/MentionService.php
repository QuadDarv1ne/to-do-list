<?php

namespace App\Service;

use App\Entity\Mention;
use App\Entity\User;
use App\Repository\MentionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class MentionService
{
    public function __construct(
        private UserRepository $userRepository,
        private MentionRepository $mentionRepository,
        private NotificationService $notificationService,
        private EntityManagerInterface $em,
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
            // Создаём упоминание в БД
            $mention = new Mention();
            $mention->setMentionedUser($user);
            $mention->setMentionedByUser($mentionedBy);
            
            if (isset($context['entity_type'])) {
                $mention->setEntityType($context['entity_type']);
            }
            if (isset($context['entity_id'])) {
                $mention->setEntityId($context['entity_id']);
            }
            if (isset($context['content'])) {
                $mention->setContent($context['content']);
            }
            
            $this->mentionRepository->save($mention);
            
            // Отправляем уведомление
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
        $mentions = $this->mentionRepository->findByUser($user, $limit);
        
        return array_map(fn ($m) => [
            'id' => $m->getId(),
            'mentioned_by' => [
                'id' => $m->getMentionedByUser()->getId(),
                'username' => $m->getMentionedByUser()->getUsername(),
            ],
            'entity_type' => $m->getEntityType(),
            'entity_id' => $m->getEntityId(),
            'content' => $m->getContent(),
            'is_read' => $m->isRead(),
            'created_at' => $m->getCreatedAt()->format('Y-m-d H:i:s'),
        ], $mentions);
    }

    /**
     * Mark mention as read
     */
    public function markAsRead(int $mentionId, User $user): bool
    {
        $mention = $this->mentionRepository->findOneByIdAndUser($mentionId, $user);
        
        if (!$mention) {
            return false;
        }

        $mention->setIsRead(true);
        $this->mentionRepository->save($mention);

        return true;
    }
}
