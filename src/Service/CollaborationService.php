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
        private EntityManagerInterface $entityManager,
    ) {
    }

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
            'top_collaborators' => $collaborators,
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
        $topUserIds = array_keys(\array_slice($collaborators, 0, $limit, true));

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
                    'collaboration_count' => $collaborators[$userId],
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
        // First get users with their active task counts (optimized query to avoid N+1)
        $qb = $this->userRepository->createQueryBuilder('u')
            ->select('u.id, u.firstName, u.lastName, u.email, COUNT(t.id) as activeTasksCount')
            ->leftJoin('u.assignedTasks', 't', 'WITH', 't.status != :completed')
            ->where('u.isActive = :active')
            ->setParameter('active', true)
            ->setParameter('completed', 'completed')
            ->groupBy('u.id, u.firstName, u.lastName, u.email')
            ->setMaxResults(100);

        $results = $qb->getQuery()->getResult();

        if (empty($results)) {
            return [];
        }

        // Get user IDs for the next query
        $userIds = array_column($results, 'id');

        // Now get urgent task counts for all users in one query
        $urgentTasksData = $this->taskRepository->createQueryBuilder('t')
            ->select('COALESCE(IDENTITY(t.assignedUser), IDENTITY(t.user)) as userId, COUNT(t.id) as urgentCount')
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.user', 'u')
            ->where('(t.assignedUser IN (:userIds) OR t.user IN (:userIds))')
            ->andWhere('t.status != :completed')
            ->andWhere('t.priority = :urgent')
            ->setParameter('userIds', $userIds)
            ->setParameter('completed', 'completed')
            ->setParameter('urgent', 'urgent')
            ->groupBy('userId')
            ->getQuery()
            ->getResult();

        // Convert urgent tasks data to associative array for quick lookup
        $urgentTasksMap = [];
        foreach ($urgentTasksData as $data) {
            $userId = $data['userId'];
            $urgentTasksMap[$userId] = (int)$data['urgentCount'];
        }

        // Get all users in one query
        $users = $this->userRepository->createQueryBuilder('u')
            ->where('u.id IN (:userIds)')
            ->setParameter('userIds', $userIds)
            ->getQuery()
            ->getResult();

        // Create user map for quick lookup
        $userMap = [];
        foreach ($users as $user) {
            $userMap[$user->getId()] = $user;
        }

        $workload = [];
        foreach ($results as $result) {
            $userId = $result['id'];
            $user = $userMap[$userId] ?? null;

            if (!$user) {
                continue; // Skip if user not found
            }

            $activeTasks = (int)($result['activeTasksCount'] ?? 0);
            $urgentTasks = $urgentTasksMap[$userId] ?? 0;

            $workload[] = [
                'user' => $user,
                'active_tasks' => $activeTasks,
                'urgent_tasks' => $urgentTasks,
                'workload_level' => $this->calculateWorkloadLevel($activeTasks),
            ];
        }

        // Sort by active tasks
        usort($workload, fn ($a, $b) => $b['active_tasks'] <=> $a['active_tasks']);

        return $workload;
    }

    /**
     * Calculate workload level
     */
    private function calculateWorkloadLevel(int $taskCount): string
    {
        if ($taskCount >= 20) {
            return 'overloaded';
        }
        if ($taskCount >= 10) {
            return 'high';
        }
        if ($taskCount >= 5) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Suggest task assignment based on workload, collaboration history, and expertise
     * Optimized: single query to calculate all scores
     */
    public function suggestAssignment(Task $task): ?User
    {
        $creator = $task->getUser();
        $category = $task->getCategory();

        // Build a single optimized query to get all scoring data
        $qb = $this->userRepository->createQueryBuilder('u')
            ->select('u.id as userId')
            ->where('u != :creator')
            ->andWhere('u.isActive = :active')
            ->setParameter('creator', $creator)
            ->setParameter('active', true);

        // Get workload (active tasks count)
        $qb->addSelect('(
            SELECT COUNT(t1.id) 
            FROM App\Entity\Task t1 
            WHERE (t1.assignedUser = u OR t1.user = u) 
            AND t1.status != :completed
        ) as workload');

        // Get collaboration count with task creator
        $qb->addSelect('(
            SELECT COUNT(t2.id) 
            FROM App\Entity\Task t2 
            WHERE (
                (t2.user = :creator AND t2.assignedUser = u) OR 
                (t2.user = u AND t2.assignedUser = :creator)
            )
        ) as collaborations');

        // Get category experience if category exists
        if ($category) {
            $qb->addSelect('(
                SELECT COUNT(t3.id) 
                FROM App\Entity\Task t3 
                WHERE (t3.assignedUser = u OR t3.user = u) 
                AND t3.category = :category 
                AND t3.status = :completed
            ) as categoryExperience')
            ->setParameter('category', $category);
        } else {
            $qb->addSelect('0 as categoryExperience');
        }

        $qb->setParameter('completed', 'completed');

        $results = $qb->getQuery()->getArrayResult();

        if (empty($results)) {
            return null;
        }

        // Calculate scores
        $scores = [];
        foreach ($results as $result) {
            $workload = (int)$result['workload'];
            $collaborations = (int)$result['collaborations'];
            $categoryExperience = (int)($result['categoryExperience'] ?? 0);

            // Calculate total score
            $score = max(0, 20 - $workload) + ($collaborations * 2) + ($categoryExperience * 3);

            $scores[$result['userId']] = $score;
        }

        // Sort by score descending
        arsort($scores);

        // Get the best user
        $bestUserId = array_key_first($scores);
        if ($bestUserId) {
            return $this->userRepository->find($bestUserId);
        }

        return null;
    }

    /**
     * Get collaboration network
     * Optimized: batch load collaboration data instead of per-user queries
     */
    public function getCollaborationNetwork(int $limit = 50): array
    {
        // Get active users with limit
        $users = $this->userRepository->createQueryBuilder('u')
            ->where('u.isActive = :active')
            ->setParameter('active', true)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        if (empty($users)) {
            return [];
        }

        $userIds = array_map(fn($u) => $u->getId(), $users);

        // Batch load all collaboration data in one query
        $collaborationData = $this->taskRepository->createQueryBuilder('t')
            ->select('
                IDENTITY(t.user) as userId,
                IDENTITY(t.assignedUser) as collaboratorId,
                COUNT(t.id) as count
            ')
            ->where('t.user IN (:userIds) AND t.assignedUser IS NOT NULL')
            ->orWhere('t.assignedUser IN (:userIds) AND t.user IS NOT NULL')
            ->setParameter('userIds', $userIds)
            ->groupBy('userId, collaboratorId')
            ->getQuery()
            ->getArrayResult();

        // Build collaboration map
        $collaborationMap = [];
        foreach ($collaborationData as $data) {
            $userId = $data['userId'];
            $collaboratorId = $data['collaboratorId'];
            $count = (int)$data['count'];

            if (!isset($collaborationMap[$userId])) {
                $collaborationMap[$userId] = [];
            }
            if (!isset($collaborationMap[$userId][$collaboratorId])) {
                $collaborationMap[$userId][$collaboratorId] = 0;
            }
            $collaborationMap[$userId][$collaboratorId] += $count;

            // Bidirectional
            if (!isset($collaborationMap[$collaboratorId])) {
                $collaborationMap[$collaboratorId] = [];
            }
            if (!isset($collaborationMap[$collaboratorId][$userId])) {
                $collaborationMap[$collaboratorId][$userId] = 0;
            }
            $collaborationMap[$collaboratorId][$userId] += $count;
        }

        // Build network
        $network = [];
        foreach ($users as $user) {
            $userId = $user->getId();
            $userCollaborations = $collaborationMap[$userId] ?? [];

            // Sort and limit to top 5 collaborators
            arsort($userCollaborations);
            $topCollaborators = array_slice($userCollaborations, 0, 5, true);

            $collaborators = [];
            foreach ($topCollaborators as $collaboratorId => $count) {
                $collaborators[] = [
                    'user_id' => $collaboratorId,
                    'collaboration_count' => $count,
                ];
            }

            $network[] = [
                'user' => $user,
                'collaborators' => $collaborators,
                'total_collaborations' => array_sum($userCollaborations),
            ];
        }

        return $network;
    }
}
