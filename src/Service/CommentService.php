<?php

namespace App\Service;

use App\Entity\Comment;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;

class CommentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommentRepository $commentRepository,
        private RealTimeNotificationService $notificationService,
    ) {
    }

    /**
     * Add comment to task
     */
    public function addComment(Task $task, User $author, string $content): Comment
    {
        $comment = new Comment();
        $comment->setTask($task);
        $comment->setAuthor($author);
        $comment->setContent($content);

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        // Notify task owner and assigned user
        $this->notifyRelevantUsers($task, $author, $comment);

        return $comment;
    }

    /**
     * Update comment
     */
    public function updateComment(Comment $comment, string $content): Comment
    {
        $comment->setContent($content);
        $this->entityManager->flush();

        return $comment;
    }

    /**
     * Delete comment
     */
    public function deleteComment(Comment $comment): void
    {
        $this->entityManager->remove($comment);
        $this->entityManager->flush();
    }

    /**
     * Get comments for task
     */
    public function getTaskComments(Task $task, int $limit = 50, int $offset = 0): array
    {
        return $this->commentRepository->findBy(
            ['task' => $task],
            ['createdAt' => 'DESC'],
            $limit,
            $offset,
        );
    }

    /**
     * Get comment count for task
     */
    public function getCommentCount(Task $task): int
    {
        return $this->commentRepository->count(['task' => $task]);
    }

    /**
     * Get recent comments by user
     */
    public function getUserRecentComments(User $user, int $limit = 10): array
    {
        return $this->commentRepository->findBy(
            ['author' => $user],
            ['createdAt' => 'DESC'],
            $limit,
        );
    }

    /**
     * Search comments
     */
    public function searchComments(string $query, ?User $user = null): array
    {
        $qb = $this->commentRepository->createQueryBuilder('c')
            ->where('c.content LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(50);

        if ($user) {
            $qb->andWhere('c.author = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get comment statistics
     */
    public function getCommentStatistics(User $user): array
    {
        $qb = $this->commentRepository->createQueryBuilder('c');

        // Total comments by user
        $totalComments = $qb->select('COUNT(c.id)')
            ->where('c.author = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        // Comments this week
        $weekAgo = new \DateTime('-7 days');
        $commentsThisWeek = $this->commentRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.author = :user')
            ->andWhere('c.createdAt >= :weekAgo')
            ->setParameter('user', $user)
            ->setParameter('weekAgo', $weekAgo)
            ->getQuery()
            ->getSingleScalarResult();

        // Average comments per task
        $tasksWithComments = $this->commentRepository->createQueryBuilder('c')
            ->select('COUNT(DISTINCT c.task)')
            ->where('c.author = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $avgCommentsPerTask = $tasksWithComments > 0
            ? round($totalComments / $tasksWithComments, 2)
            : 0;

        return [
            'total_comments' => (int)$totalComments,
            'comments_this_week' => (int)$commentsThisWeek,
            'tasks_with_comments' => (int)$tasksWithComments,
            'avg_comments_per_task' => $avgCommentsPerTask,
        ];
    }

    /**
     * Notify relevant users about new comment
     */
    private function notifyRelevantUsers(Task $task, User $author, Comment $comment): void
    {
        $usersToNotify = [];

        // Notify task owner if not the author
        if ($task->getUser() && $task->getUser()->getId() !== $author->getId()) {
            $usersToNotify[] = $task->getUser();
        }

        // Notify assigned user if not the author
        if ($task->getAssignedUser() && $task->getAssignedUser()->getId() !== $author->getId()) {
            $usersToNotify[] = $task->getAssignedUser();
        }

        // Send notifications
        foreach ($usersToNotify as $user) {
            $this->notificationService->notifyNewComment($user, $task, $comment);
        }
    }

    /**
     * Get tasks with most comments
     */
    public function getTasksWithMostComments(int $limit = 10): array
    {
        return $this->commentRepository->createQueryBuilder('c')
            ->select('t.id, t.title, COUNT(c.id) as comment_count')
            ->join('c.task', 't')
            ->groupBy('t.id, t.title')
            ->orderBy('comment_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get most active commenters
     */
    public function getMostActiveCommenters(int $limit = 10): array
    {
        return $this->commentRepository->createQueryBuilder('c')
            ->select('u.id, u.fullName, u.email, COUNT(c.id) as comment_count')
            ->join('c.author', 'u')
            ->groupBy('u.id, u.fullName, u.email')
            ->orderBy('comment_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
