<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class CollaborationService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Get shared tasks between users
     */
    public function getSharedTasks(User $user1, User $user2): array
    {
        return $this->taskRepository->createQueryBuilder('t')
            ->where('(t.user = :user1 AND t.assignedUser = :user2)')
            ->orWhere('(t.user = :user2 AND t.assignedUser = :user1)')
            ->setParameter('user1', $user1)
            ->setParameter('user2', $user2)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get collaboration statistics
     */
    public function getCollaborationStats(User $user): array
    {
        // Tasks assigned to others
        $assignedToOthers = $this->taskRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.user = :user')
            ->andWhere('t.assignedUser IS NOT NULL')
            ->andWhere('t.assignedUser != :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        // Tasks assigned from others
        $assignedFromOthers = $this->taskRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.assignedUser = :user')
            ->andWhere('t.user != :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        // Most frequent collaborators
        $collaborators = $this->getMostFrequentCollaborators($user, 5);

        return [
            'assigned_to_others' => (int)$assignedToOthers,
            'assigned_from_others' => (int)$assignedFromOthers,
            'total_collaborations' => (int)$assignedToOthers + (int)$assignedFromOthers,
            'top_collaborators' => $collaborators
        ];
    }

    /**
     * Get most frequent collaborators
     */
    public function getMostFrequentCollaborators(User $user, int $limit = 10): array
    {
        // Get users who assigned tasks to this user
        $assignedFrom = $this->taskRepository->createQueryBuilder('t')
            ->select('IDENTITY(t.user) as user_id, COUNT(t.id) as count')
            ->where('t.assignedUser = :user')
            ->andWhere('t.user != :user')
            ->groupBy('t.user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        // Get users who received tasks from this user
        $assignedTo = $this->taskRepository->createQueryBuilder('t')
            ->select('IDENTITY(t.assignedUser) as user_id, COUNT(t.id) as count')
            ->where('t.user = :user')
            ->andWhere('t.assignedUser IS NOT NULL')
            ->andWhere('t.assignedUser != :user')
            ->groupBy('t.assignedUser')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        // Merge and sum counts
        $collaborators = [];
        foreach (array_merge($assignedFrom, $assignedTo) as $item) {
            $userId = $item['user_id'];
            if (!isset($collaborators[$userId])) {
                $collaborators[$userId] = 0;
            }
            $collaborators[$userId] += (int)$item['count'];
        }

        // Sort by count
        arsort($collaborators);

        // Оптимизация: загружаем пользователей одним запросом
        $topUserIds = array_keys(array_slice($collaborators, 0, $limit, true));
        
        if (empty($topUserIds)) {
            return [];
        }
        
        $users = $this->userRepository->createQueryBuilder('u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $topUserIds)
            ->getQuery()
            ->getResult();
        
        $usersById = [];
        foreach ($users as $user) {
            $usersById[$user->getId()] = $user;
        }
        
        $result = [];
        foreach ($topUserIds as $userId) {
            if (isset($usersById[$userId])) {
                $result[] = [
                    'user' => $usersById[$userId],
                    'collaboration_count' => $collaborators[$userId]
                ];
            }
        }

        return $result;
    }

    /**
     * Get team workload
     */
    public function getTeamWorkload(): array
    {
        // Оптимизация: один запрос вместо N+1
        $qb = $this->userRepository->createQueryBuilder('u')
            ->select('u.id, u.fullName, u.email, COUNT(t.id) as activeTasksCount')
            ->leftJoin('u.assignedTasks', 't', 'WITH', 't.status != :completed')
            ->where('u.isActive = :active')
            ->setParameter('active', true)
            ->setParameter('completed', 'completed')
            ->groupBy('u.id')
            ->setMaxResults(100);
        
        $results = $qb->getQuery()->getResult();
        $workload = [];

        foreach ($results as $result) {
            $activeTasks = (int)($result['activeTasksCount'] ?? 0);

            $urgentTasks = $this->taskRepository->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->where('t.assignedUser = :user OR t.user = :user')
                ->andWhere('t.status != :completed')
                ->andWhere('t.priority = :urgent')
                ->setParameter('user', $user)
                ->setParameter('completed', 'completed')
                ->setParameter('urgent', 'urgent')
                ->getQuery()
                ->getSingleScalarResult();

            $workload[] = [
                'user' => $user,
                'active_tasks' => (int)$activeTasks,
                'urgent_tasks' => (int)$urgentTasks,
                'workload_level' => $this->calculateWorkloadLevel((int)$activeTasks)
            ];
        }

        // Sort by active tasks
        usort($workload, fn($a, $b) => $b['active_tasks'] <=> $a['active_tasks']);

        return $workload;
    }

    /**
     * Calculate workload level
     */
    private function calculateWorkloadLevel(int $taskCount): string
    {
        if ($taskCount >= 20) return 'overloaded';
        if ($taskCount >= 10) return 'high';
        if ($taskCount >= 5) return 'medium';
        return 'low';
    }

    /**
     * Suggest task assignment
     */
    public function suggestAssignment(Task $task): ?User
    {
        // Get all users except task creator
        $users = $this->userRepository->createQueryBuilder('u')
            ->where('u != :creator')
            ->andWhere('u.isActive = :active')
            ->setParameter('creator', $task->getUser())
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        if (empty($users)) {
            return null;
        }

        // Calculate scores for each user
        $scores = [];
        foreach ($users as $user) {
            $score = 0;

            // Lower workload = higher score
            $workload = $this->taskRepository->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->where('t.assignedUser = :user OR t.user = :user')
                ->andWhere('t.status != :completed')
                ->setParameter('user', $user)
                ->setParameter('completed', 'completed')
                ->getQuery()
                ->getSingleScalarResult();

            $score += max(0, 20 - (int)$workload);

            // Previous collaboration = higher score
            $collaborations = $this->taskRepository->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->where('t.user = :creator AND t.assignedUser = :user')
                ->orWhere('t.user = :user AND t.assignedUser = :creator')
                ->setParameter('creator', $task->getUser())
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult();

            $score += (int)$collaborations * 2;

            // Same category experience = higher score
            if ($task->getCategory()) {
                $categoryTasks = $this->taskRepository->createQueryBuilder('t')
                    ->select('COUNT(t.id)')
                    ->where('t.assignedUser = :user OR t.user = :user')
                    ->andWhere('t.category = :category')
                    ->andWhere('t.status = :completed')
                    ->setParameter('user', $user)
                    ->setParameter('category', $task->getCategory())
                    ->setParameter('completed', 'completed')
                    ->getQuery()
                    ->getSingleScalarResult();

                $score += (int)$categoryTasks * 3;
            }

            $scores[$user->getId()] = [
                'user' => $user,
                'score' => $score
            ];
        }

        // Sort by score
        uasort($scores, fn($a, $b) => $b['score'] <=> $a['score']);

        return reset($scores)['user'] ?? null;
    }

    /**
     * Get collaboration network
     */
    public function getCollaborationNetwork(): array
    {
        $users = $this->userRepository->findAll();
        $network = [];

        foreach ($users as $user) {
            $collaborators = $this->getMostFrequentCollaborators($user, 5);
            
            $network[] = [
                'user' => $user,
                'collaborators' => $collaborators,
                'total_collaborations' => array_sum(array_column($collaborators, 'collaboration_count'))
            ];
        }

        return $network;
    }
}
