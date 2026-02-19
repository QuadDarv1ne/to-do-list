<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gamification Service
 * Система достижений, уровней и наград
 */
class GamificationService
{
    // Список всех достижений
    private const ACHIEVEMENTS = [
        'first_task' => [
            'name' => 'Первый шаг',
            'description' => 'Создайте первую задачу',
            'icon' => 'fa-seedling',
            'points' => 10,
            'color' => 'success',
        ],
        'task_master_10' => [
            'name' => 'Мастер задач',
            'description' => 'Выполните 10 задач',
            'icon' => 'fa-medal',
            'points' => 50,
            'color' => 'primary',
        ],
        'task_master_50' => [
            'name' => 'Гуру продуктивности',
            'description' => 'Выполните 50 задач',
            'icon' => 'fa-trophy',
            'points' => 150,
            'color' => 'warning',
        ],
        'task_master_100' => [
            'name' => 'Легенда',
            'description' => 'Выполните 100 задач',
            'icon' => 'fa-crown',
            'points' => 300,
            'color' => 'danger',
        ],
        'streak_3' => [
            'name' => 'Трёхдневный воин',
            'description' => 'Выполняйте задачи 3 дня подряд',
            'icon' => 'fa-fire',
            'points' => 30,
            'color' => 'info',
        ],
        'streak_7' => [
            'name' => 'Недельный чемпион',
            'description' => 'Выполняйте задачи 7 дней подряд',
            'icon' => 'fa-fire-alt',
            'points' => 100,
            'color' => 'warning',
        ],
        'streak_30' => [
            'name' => 'Месяц славы',
            'description' => 'Выполняйте задачи 30 дней подряд',
            'icon' => 'fa-fire-flame-curved',
            'points' => 500,
            'color' => 'danger',
        ],
        'early_bird' => [
            'name' => 'Жаворонок',
            'description' => 'Выполните задачу до 8 утра',
            'icon' => 'fa-sun',
            'points' => 25,
            'color' => 'warning',
        ],
        'night_owl' => [
            'name' => 'Сова',
            'description' => 'Выполните задачу после 23:00',
            'icon' => 'fa-moon',
            'points' => 25,
            'color' => 'info',
        ],
        'deadline_hero' => [
            'name' => 'Герой дедлайнов',
            'description' => 'Выполните 5 задач в день дедлайна',
            'icon' => 'fa-stopwatch',
            'points' => 75,
            'color' => 'primary',
        ],
        'priority_master' => [
            'name' => 'Приоритетный',
            'description' => 'Выполните 10 задач с высоким приоритетом',
            'icon' => 'fa-exclamation-circle',
            'points' => 100,
            'color' => 'danger',
        ],
        'organizer' => [
            'name' => 'Организатор',
            'description' => 'Создайте 5 категорий для задач',
            'icon' => 'fa-folder',
            'points' => 40,
            'color' => 'info',
        ],
        'team_player' => [
            'name' => 'Командный игрок',
            'description' => 'Назначьте 10 задач другим пользователям',
            'icon' => 'fa-users',
            'points' => 60,
            'color' => 'primary',
        ],
        'completer' => [
            'name' => 'Завершитель',
            'description' => 'Достигните 100% выполнения задач',
            'icon' => 'fa-percentage',
            'points' => 200,
            'color' => 'success',
        ],
        'comeback_king' => [
            'name' => 'Король возвращения',
            'description' => 'Выполните 5 просроченных задач',
            'icon' => 'fa-undo',
            'points' => 80,
            'color' => 'warning',
        ],
    ];

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Проверка и разблокировка достижений
     */
    public function checkAndUnlockAchievements(User $user): array
    {
        $unlocked = [];
        $userAchievements = $user->getAchievements() ?? [];

        foreach (self::ACHIEVEMENTS as $key => $achievement) {
            if (\in_array($key, $userAchievements)) {
                continue; // Уже разблокировано
            }

            if ($this->checkAchievement($user, $key)) {
                $userAchievements[] = $key;
                $unlocked[] = $achievement;
            }
        }

        $user->setAchievements($userAchievements);
        $this->em->flush();

        return $unlocked;
    }

    /**
     * Проверка конкретного достижения
     */
    private function checkAchievement(User $user, string $key): bool
    {
        $conn = $this->em->getConnection();
        $userId = $user->getId();

        switch ($key) {
            case 'first_task':
                $sql = 'SELECT COUNT(*) FROM tasks WHERE user_id = ?';

                return $conn->fetchOne($sql, [$userId]) >= 1;

            case 'task_master_10':
                $sql = "SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'completed'";

                return $conn->fetchOne($sql, [$userId]) >= 10;

            case 'task_master_50':
                $sql = "SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'completed'";

                return $conn->fetchOne($sql, [$userId]) >= 50;

            case 'task_master_100':
                $sql = "SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'completed'";

                return $conn->fetchOne($sql, [$userId]) >= 100;

            case 'streak_3':
                return $this->calculateStreak($user) >= 3;

            case 'streak_7':
                return $this->calculateStreak($user) >= 7;

            case 'streak_30':
                return $this->calculateStreak($user) >= 30;

            case 'early_bird':
                $sql = "SELECT COUNT(*) FROM tasks
                        WHERE user_id = ?
                        AND status = 'completed'
                        AND strftime('%H', completed_at) < '08'";

                return $conn->fetchOne($sql, [$userId]) >= 1;

            case 'night_owl':
                $sql = "SELECT COUNT(*) FROM tasks
                        WHERE user_id = ?
                        AND status = 'completed'
                        AND strftime('%H', completed_at) >= '23'";

                return $conn->fetchOne($sql, [$userId]) >= 1;

            case 'deadline_hero':
                $sql = "SELECT COUNT(*) FROM tasks
                        WHERE user_id = ?
                        AND status = 'completed'
                        AND DATE(completed_at) = DATE(due_date)";

                return $conn->fetchOne($sql, [$userId]) >= 5;

            case 'priority_master':
                $sql = "SELECT COUNT(*) FROM tasks
                        WHERE user_id = ?
                        AND status = 'completed'
                        AND priority = 'high'";

                return $conn->fetchOne($sql, [$userId]) >= 10;

            case 'organizer':
                $sql = 'SELECT COUNT(*) FROM task_category WHERE user_id = ?';

                return $conn->fetchOne($sql, [$userId]) >= 5;

            case 'team_player':
                $sql = 'SELECT COUNT(*) FROM tasks
                        WHERE user_id = ?
                        AND assignee_id IS NOT NULL';

                return $conn->fetchOne($sql, [$userId]) >= 10;

            case 'completer':
                $sql = "SELECT
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(*) as rate
                        FROM tasks WHERE user_id = ?";
                $rate = (float)$conn->fetchOne($sql, [$userId]);

                return $rate >= 100;

            case 'comeback_king':
                $sql = "SELECT COUNT(*) FROM tasks
                        WHERE user_id = ?
                        AND status = 'completed'
                        AND completed_at > due_date";

                return $conn->fetchOne($sql, [$userId]) >= 5;

            default:
                return false;
        }
    }

    /**
     * Расчёт текущей серии
     */
    public function calculateStreak(User $user): int
    {
        $conn = $this->em->getConnection();

        $sql = "SELECT DATE(completed_at) as date
                FROM tasks
                WHERE user_id = ?
                AND status = 'completed'
                GROUP BY DATE(completed_at)
                ORDER BY date DESC
                LIMIT 30";

        $dates = $conn->fetchFirstColumn($sql, [$user->getId()]);

        if (empty($dates)) {
            return 0;
        }

        $streak = 0;
        $expectedDate = new \DateTime();

        foreach ($dates as $dateStr) {
            $expectedDateStr = $expectedDate->format('Y-m-d');

            if ($dateStr === $expectedDateStr) {
                $streak++;
                $expectedDate->modify('-1 day');
            } elseif ($dateStr < $expectedDateStr) {
                break;
            }
        }

        return $streak;
    }

    /**
     * Получить все достижения пользователя
     */
    public function getUserAchievements(User $user): array
    {
        $userAchievements = $user->getAchievements() ?? [];
        $allAchievements = self::ACHIEVEMENTS;

        $result = [
            'unlocked' => [],
            'locked' => [],
            'total' => \count($allAchievements),
            'progress' => round((\count($userAchievements) / \count($allAchievements)) * 100),
        ];

        foreach ($allAchievements as $key => $achievement) {
            $achievement['key'] = $key;

            if (\in_array($key, $userAchievements)) {
                $result['unlocked'][] = $achievement;
            } else {
                $result['locked'][] = $achievement;
            }
        }

        return $result;
    }

    /**
     * Расчёт уровня пользователя
     */
    public function calculateLevel(User $user): array
    {
        $totalPoints = $this->calculateTotalPoints($user);

        $level = floor($totalPoints / 500) + 1;
        $progress = $totalPoints % 500;
        $nextLevelPoints = 500;

        return [
            'level' => (int)$level,
            'points' => $totalPoints,
            'progress' => $progress,
            'next_level' => $nextLevelPoints,
            'progress_percent' => round(($progress / $nextLevelPoints) * 100),
        ];
    }

    /**
     * Подсчёт общего количества очков
     */
    private function calculateTotalPoints(User $user): int
    {
        $achievements = $user->getAchievements() ?? [];
        $totalPoints = 0;

        foreach ($achievements as $key) {
            if (isset(self::ACHIEVEMENTS[$key])) {
                $totalPoints += self::ACHIEVEMENTS[$key]['points'];
            }
        }

        return $totalPoints;
    }

    /**
     * Получить все доступные достижения
     */
    public function getAllAchievements(): array
    {
        return self::ACHIEVEMENTS;
    }

    /**
     * Получить leaderboard (топ пользователей)
     */
    public function getLeaderboard(int $limit = 10): array
    {
        $conn = $this->em->getConnection();

        $sql = "SELECT
                u.id,
                u.first_name,
                u.last_name,
                u.email,
                COUNT(t.id) as total_tasks,
                SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
                FROM users u
                LEFT JOIN tasks t ON u.id = t.user_id
                GROUP BY u.id
                ORDER BY completed_tasks DESC
                LIMIT ?";

        return $conn->fetchAllAssociative($sql, [$limit]);
    }
}
