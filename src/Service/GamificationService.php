<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TaskRepository;

class GamificationService
{
    public function __construct(
        private TaskRepository $taskRepository
    ) {}

    /**
     * Get user level and XP
     */
    public function getUserLevel(User $user): array
    {
        $xp = $this->calculateTotalXP($user);
        $level = $this->calculateLevel($xp);
        $nextLevelXP = $this->getXPForLevel($level + 1);
        $currentLevelXP = $this->getXPForLevel($level);
        
        return [
            'level' => $level,
            'xp' => $xp,
            'xp_to_next_level' => $nextLevelXP - $xp,
            'xp_current_level' => $xp - $currentLevelXP,
            'xp_needed_for_level' => $nextLevelXP - $currentLevelXP,
            'progress_percent' => round((($xp - $currentLevelXP) / ($nextLevelXP - $currentLevelXP)) * 100, 2)
        ];
    }

    /**
     * Calculate total XP
     */
    private function calculateTotalXP(User $user): int
    {
        $xp = 0;
        
        // XP from completed tasks
        $completedTasks = $this->taskRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.assignedUser = :user')
            ->andWhere('t.status = :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getSingleScalarResult();
        
        $xp += $completedTasks * 10; // 10 XP per task
        
        // Bonus XP for on-time completion
        // TODO: Calculate from database
        
        // Bonus XP for quality (no reopened tasks)
        // TODO: Calculate from database
        
        return $xp;
    }

    /**
     * Calculate level from XP
     */
    private function calculateLevel(int $xp): int
    {
        // Level formula: level = floor(sqrt(xp / 100))
        return (int)floor(sqrt($xp / 100));
    }

    /**
     * Get XP required for level
     */
    private function getXPForLevel(int $level): int
    {
        // XP formula: xp = level^2 * 100
        return $level * $level * 100;
    }

    /**
     * Award XP for action
     */
    public function awardXP(User $user, string $action, ?int $amount = null): int
    {
        $xpAmount = $amount ?? match($action) {
            'task_completed' => 10,
            'task_completed_on_time' => 15,
            'task_completed_early' => 20,
            'comment_added' => 2,
            'task_created' => 5,
            'helped_teammate' => 25,
            'streak_day' => 5,
            'streak_week' => 50,
            'streak_month' => 200,
            default => 1
        };

        // TODO: Save to database
        
        return $xpAmount;
    }

    /**
     * Get user achievements
     */
    public function getUserAchievements(User $user): array
    {
        $achievements = $this->getAllAchievements();
        $userProgress = [];

        foreach ($achievements as $key => $achievement) {
            $progress = $this->checkAchievementProgress($user, $key);
            $userProgress[$key] = [
                'achievement' => $achievement,
                'unlocked' => $progress['current'] >= $progress['required'],
                'progress' => $progress['current'],
                'required' => $progress['required'],
                'percent' => round(($progress['current'] / $progress['required']) * 100, 2)
            ];
        }

        return $userProgress;
    }

    /**
     * Get all achievements
     */
    private function getAllAchievements(): array
    {
        return [
            'first_task' => [
                'name' => 'Первый шаг',
                'description' => 'Завершите первую задачу',
                'icon' => 'fa-star',
                'rarity' => 'common',
                'xp_reward' => 50
            ],
            'task_master_10' => [
                'name' => 'Мастер задач',
                'description' => 'Завершите 10 задач',
                'icon' => 'fa-trophy',
                'rarity' => 'common',
                'xp_reward' => 100
            ],
            'task_master_50' => [
                'name' => 'Эксперт задач',
                'description' => 'Завершите 50 задач',
                'icon' => 'fa-trophy',
                'rarity' => 'rare',
                'xp_reward' => 500
            ],
            'task_master_100' => [
                'name' => 'Легенда задач',
                'description' => 'Завершите 100 задач',
                'icon' => 'fa-crown',
                'rarity' => 'epic',
                'xp_reward' => 1000
            ],
            'speed_demon' => [
                'name' => 'Скоростной демон',
                'description' => 'Завершите 5 задач за один день',
                'icon' => 'fa-bolt',
                'rarity' => 'rare',
                'xp_reward' => 200
            ],
            'early_bird' => [
                'name' => 'Ранняя пташка',
                'description' => 'Завершите 10 задач до дедлайна',
                'icon' => 'fa-clock',
                'rarity' => 'rare',
                'xp_reward' => 300
            ],
            'perfectionist' => [
                'name' => 'Перфекционист',
                'description' => 'Завершите 20 задач без переоткрытия',
                'icon' => 'fa-gem',
                'rarity' => 'epic',
                'xp_reward' => 500
            ],
            'team_player' => [
                'name' => 'Командный игрок',
                'description' => 'Помогите 10 коллегам',
                'icon' => 'fa-users',
                'rarity' => 'rare',
                'xp_reward' => 400
            ],
            'streak_7' => [
                'name' => 'Неделя продуктивности',
                'description' => 'Завершайте задачи 7 дней подряд',
                'icon' => 'fa-fire',
                'rarity' => 'rare',
                'xp_reward' => 350
            ],
            'streak_30' => [
                'name' => 'Месяц продуктивности',
                'description' => 'Завершайте задачи 30 дней подряд',
                'icon' => 'fa-fire',
                'rarity' => 'legendary',
                'xp_reward' => 2000
            ],
            'commenter' => [
                'name' => 'Коммуникатор',
                'description' => 'Оставьте 50 комментариев',
                'icon' => 'fa-comment',
                'rarity' => 'common',
                'xp_reward' => 150
            ],
            'organizer' => [
                'name' => 'Организатор',
                'description' => 'Создайте 25 задач',
                'icon' => 'fa-tasks',
                'rarity' => 'common',
                'xp_reward' => 200
            ],
            'night_owl' => [
                'name' => 'Ночная сова',
                'description' => 'Завершите 10 задач после 22:00',
                'icon' => 'fa-moon',
                'rarity' => 'rare',
                'xp_reward' => 250
            ],
            'priority_master' => [
                'name' => 'Мастер приоритетов',
                'description' => 'Завершите 20 срочных задач',
                'icon' => 'fa-exclamation-circle',
                'rarity' => 'epic',
                'xp_reward' => 600
            ],
            'mentor' => [
                'name' => 'Наставник',
                'description' => 'Обучите 5 новых пользователей',
                'icon' => 'fa-graduation-cap',
                'rarity' => 'legendary',
                'xp_reward' => 1500
            ]
        ];
    }

    /**
     * Check achievement progress
     */
    private function checkAchievementProgress(User $user, string $achievementKey): array
    {
        return match($achievementKey) {
            'first_task' => [
                'current' => $this->getCompletedTasksCount($user),
                'required' => 1
            ],
            'task_master_10' => [
                'current' => $this->getCompletedTasksCount($user),
                'required' => 10
            ],
            'task_master_50' => [
                'current' => $this->getCompletedTasksCount($user),
                'required' => 50
            ],
            'task_master_100' => [
                'current' => $this->getCompletedTasksCount($user),
                'required' => 100
            ],
            default => [
                'current' => 0,
                'required' => 1
            ]
        };
    }

    /**
     * Get completed tasks count
     */
    private function getCompletedTasksCount(User $user): int
    {
        return (int)$this->taskRepository->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.assignedUser = :user')
            ->andWhere('t.status = :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get user streak
     */
    public function getUserStreak(User $user): array
    {
        // TODO: Calculate from database
        return [
            'current_streak' => 0,
            'longest_streak' => 0,
            'last_activity' => new \DateTime()
        ];
    }

    /**
     * Get leaderboard
     */
    public function getLeaderboard(int $limit = 10): array
    {
        // TODO: Get top users by XP
        return [];
    }

    /**
     * Get user rank
     */
    public function getUserRank(User $user): array
    {
        $level = $this->getUserLevel($user);
        
        $rank = match(true) {
            $level['level'] >= 50 => ['name' => 'Легенда', 'color' => 'gold'],
            $level['level'] >= 30 => ['name' => 'Мастер', 'color' => 'purple'],
            $level['level'] >= 20 => ['name' => 'Эксперт', 'color' => 'blue'],
            $level['level'] >= 10 => ['name' => 'Профессионал', 'color' => 'green'],
            $level['level'] >= 5 => ['name' => 'Опытный', 'color' => 'teal'],
            default => ['name' => 'Новичок', 'color' => 'gray']
        };

        return array_merge($level, $rank);
    }

    /**
     * Get daily challenge
     */
    public function getDailyChallenge(User $user): array
    {
        $challenges = [
            [
                'title' => 'Завершите 5 задач',
                'description' => 'Завершите 5 задач сегодня',
                'reward_xp' => 50,
                'progress' => 0,
                'required' => 5
            ],
            [
                'title' => 'Помогите коллеге',
                'description' => 'Оставьте полезный комментарий',
                'reward_xp' => 30,
                'progress' => 0,
                'required' => 1
            ],
            [
                'title' => 'Срочная задача',
                'description' => 'Завершите срочную задачу',
                'reward_xp' => 40,
                'progress' => 0,
                'required' => 1
            ]
        ];

        // Return random challenge
        return $challenges[array_rand($challenges)];
    }

    /**
     * Get rewards shop items
     */
    public function getShopItems(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Аватар: Золотая звезда',
                'description' => 'Эксклюзивный аватар',
                'cost' => 500,
                'type' => 'avatar',
                'icon' => 'fa-star'
            ],
            [
                'id' => 2,
                'name' => 'Тема: Темный режим Pro',
                'description' => 'Премиум темная тема',
                'cost' => 300,
                'type' => 'theme',
                'icon' => 'fa-palette'
            ],
            [
                'id' => 3,
                'name' => 'Бейдж: Легенда',
                'description' => 'Показывайте свой статус',
                'cost' => 1000,
                'type' => 'badge',
                'icon' => 'fa-crown'
            ]
        ];
    }
}
