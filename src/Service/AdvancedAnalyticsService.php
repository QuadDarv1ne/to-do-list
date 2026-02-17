<?php

namespace App\Service;

use App\Repository\TaskRepository;
use App\Entity\User;

class AdvancedAnalyticsService
{
    public function __construct(
        private TaskRepository $taskRepository
    ) {}
    
    /**
     * Predict task completion time based on historical data
     */
    public function predictCompletionTime(User $user, string $priority = 'medium'): array
    {
        $completedTasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->andWhere('t.status = :completed')
            ->andWhere('t.priority = :priority')
            ->andWhere('t.createdAt IS NOT NULL')
            ->andWhere('t.completedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->setParameter('priority', $priority)
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
        
        if (empty($completedTasks)) {
            return [
                'predicted_days' => 3,
                'confidence' => 'low',
                'sample_size' => 0
            ];
        }
        
        $completionTimes = [];
        foreach ($completedTasks as $task) {
            $diff = $task->getCompletedAt()->getTimestamp() - $task->getCreatedAt()->getTimestamp();
            $completionTimes[] = $diff / 86400; // Convert to days
        }
        
        $avgTime = array_sum($completionTimes) / count($completionTimes);
        $stdDev = $this->calculateStandardDeviation($completionTimes);
        
        return [
            'predicted_days' => round($avgTime, 1),
            'min_days' => round(max(0, $avgTime - $stdDev), 1),
            'max_days' => round($avgTime + $stdDev, 1),
            'confidence' => $this->calculateConfidence(count($completedTasks)),
            'sample_size' => count($completedTasks)
        ];
    }
    
    /**
     * Analyze productivity trends
     */
    public function analyzeProductivityTrends(User $user, int $weeks = 12): array
    {
        $weeklyData = [];
        
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $weekStart = new \DateTime("-{$i} weeks");
            $weekStart->modify('monday this week');
            $weekEnd = clone $weekStart;
            $weekEnd->modify('+6 days');
            
            $tasks = $this->taskRepository->createQueryBuilder('t')
                ->where('t.user = :user OR t.assignedUser = :user')
                ->andWhere('t.createdAt BETWEEN :start AND :end')
                ->setParameter('user', $user)
                ->setParameter('start', $weekStart)
                ->setParameter('end', $weekEnd)
                ->getQuery()
                ->getResult();
            
            $completed = array_filter($tasks, fn($t) => $t->getStatus() === 'completed');
            
            $weeklyData[] = [
                'week' => $weekStart->format('Y-m-d'),
                'total' => count($tasks),
                'completed' => count($completed),
                'completion_rate' => count($tasks) > 0 ? round((count($completed) / count($tasks)) * 100, 1) : 0
            ];
        }
        
        // Calculate trend
        $trend = $this->calculateTrend($weeklyData);
        
        return [
            'weekly_data' => $weeklyData,
            'trend' => $trend,
            'average_completion_rate' => $this->calculateAverageCompletionRate($weeklyData),
            'best_week' => $this->findBestWeek($weeklyData),
            'worst_week' => $this->findWorstWeek($weeklyData)
        ];
    }
    
    /**
     * Get burnout risk score
     */
    public function calculateBurnoutRisk(User $user): array
    {
        $now = new \DateTime();
        $twoWeeksAgo = (clone $now)->modify('-14 days');
        
        // Get recent tasks
        $recentTasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.assignedUser = :user')
            ->andWhere('t.createdAt >= :twoWeeksAgo')
            ->setParameter('user', $user)
            ->setParameter('twoWeeksAgo', $twoWeeksAgo)
            ->getQuery()
            ->getResult();
        
        $riskFactors = [];
        $riskScore = 0;
        
        // Factor 1: High workload (>20 tasks in 2 weeks)
        if (count($recentTasks) > 20) {
            $riskScore += 30;
            $riskFactors[] = 'Высокая нагрузка';
        }
        
        // Factor 2: Many overdue tasks
        $overdueTasks = array_filter($recentTasks, function($t) use ($now) {
            return $t->getDeadline() && $t->getDeadline() < $now && $t->getStatus() !== 'completed';
        });
        
        if (count($overdueTasks) > 5) {
            $riskScore += 25;
            $riskFactors[] = 'Много просроченных задач';
        }
        
        // Factor 3: Many urgent tasks
        $urgentTasks = array_filter($recentTasks, fn($t) => $t->getPriority() === 'urgent');
        
        if (count($urgentTasks) > 10) {
            $riskScore += 20;
            $riskFactors[] = 'Много срочных задач';
        }
        
        // Factor 4: Low completion rate
        $completed = array_filter($recentTasks, fn($t) => $t->getStatus() === 'completed');
        $completionRate = count($recentTasks) > 0 ? (count($completed) / count($recentTasks)) * 100 : 100;
        
        if ($completionRate < 50) {
            $riskScore += 25;
            $riskFactors[] = 'Низкий процент завершения';
        }
        
        return [
            'risk_score' => min(100, $riskScore),
            'risk_level' => $this->getRiskLevel($riskScore),
            'risk_factors' => $riskFactors,
            'recommendations' => $this->getRecommendations($riskScore, $riskFactors)
        ];
    }
    
    /**
     * Analyze task patterns
     */
    public function analyzeTaskPatterns(User $user): array
    {
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
        
        // Most productive day of week
        $dayOfWeekCounts = array_fill(1, 7, 0);
        foreach ($tasks as $task) {
            if ($task->getStatus() === 'completed' && $task->getCompletedAt()) {
                $dayOfWeek = (int)$task->getCompletedAt()->format('N');
                $dayOfWeekCounts[$dayOfWeek]++;
            }
        }
        
        $mostProductiveDay = array_search(max($dayOfWeekCounts), $dayOfWeekCounts);
        
        // Most common priority
        $priorityCounts = ['low' => 0, 'medium' => 0, 'high' => 0, 'urgent' => 0];
        foreach ($tasks as $task) {
            $priorityCounts[$task->getPriority()]++;
        }
        
        arsort($priorityCounts);
        $mostCommonPriority = array_key_first($priorityCounts);
        
        // Average tasks per week
        $oldestTask = $this->taskRepository->createQueryBuilder('t')
            ->where('t.user = :user OR t.assignedUser = :user')
            ->orderBy('t.createdAt', 'ASC')
            ->setMaxResults(1)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
        
        $weeks = 1;
        if ($oldestTask && $oldestTask->getCreatedAt()) {
            $weeks = max(1, (new \DateTime())->diff($oldestTask->getCreatedAt())->days / 7);
        }
        
        $avgTasksPerWeek = round(count($tasks) / $weeks, 1);
        
        return [
            'most_productive_day' => $this->getDayName($mostProductiveDay),
            'most_common_priority' => $mostCommonPriority,
            'avg_tasks_per_week' => $avgTasksPerWeek,
            'total_tasks' => count($tasks),
            'completion_rate' => $this->calculateOverallCompletionRate($tasks)
        ];
    }
    
    /**
     * Get performance insights
     */
    public function getPerformanceInsights(User $user): array
    {
        $insights = [];
        
        // Check completion rate
        $stats = $this->analyzeTaskPatterns($user);
        
        if ($stats['completion_rate'] > 80) {
            $insights[] = [
                'type' => 'positive',
                'message' => 'Отличный процент завершения задач!',
                'icon' => 'fa-trophy'
            ];
        } elseif ($stats['completion_rate'] < 50) {
            $insights[] = [
                'type' => 'warning',
                'message' => 'Низкий процент завершения. Попробуйте разбить задачи на более мелкие.',
                'icon' => 'fa-exclamation-triangle'
            ];
        }
        
        // Check burnout risk
        $burnout = $this->calculateBurnoutRisk($user);
        
        if ($burnout['risk_score'] > 70) {
            $insights[] = [
                'type' => 'danger',
                'message' => 'Высокий риск выгорания. Рекомендуем снизить нагрузку.',
                'icon' => 'fa-fire'
            ];
        }
        
        // Check productivity trend
        $trends = $this->analyzeProductivityTrends($user, 4);
        
        if ($trends['trend'] === 'improving') {
            $insights[] = [
                'type' => 'success',
                'message' => 'Ваша продуктивность растет!',
                'icon' => 'fa-chart-line'
            ];
        } elseif ($trends['trend'] === 'declining') {
            $insights[] = [
                'type' => 'info',
                'message' => 'Продуктивность снижается. Возможно, стоит пересмотреть приоритеты.',
                'icon' => 'fa-chart-line-down'
            ];
        }
        
        return $insights;
    }
    
    // Helper methods
    
    private function calculateStandardDeviation(array $values): float
    {
        $count = count($values);
        if ($count === 0) return 0;
        
        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / $count;
        
        return sqrt($variance);
    }
    
    private function calculateConfidence(int $sampleSize): string
    {
        if ($sampleSize >= 50) return 'high';
        if ($sampleSize >= 20) return 'medium';
        return 'low';
    }
    
    private function calculateTrend(array $weeklyData): string
    {
        if (count($weeklyData) < 2) return 'stable';
        
        $recentWeeks = array_slice($weeklyData, -4);
        $rates = array_column($recentWeeks, 'completion_rate');
        
        $firstHalf = array_slice($rates, 0, 2);
        $secondHalf = array_slice($rates, 2);
        
        $avgFirst = array_sum($firstHalf) / count($firstHalf);
        $avgSecond = array_sum($secondHalf) / count($secondHalf);
        
        if ($avgSecond > $avgFirst + 5) return 'improving';
        if ($avgSecond < $avgFirst - 5) return 'declining';
        return 'stable';
    }
    
    private function calculateAverageCompletionRate(array $weeklyData): float
    {
        $rates = array_column($weeklyData, 'completion_rate');
        return count($rates) > 0 ? round(array_sum($rates) / count($rates), 1) : 0;
    }
    
    private function findBestWeek(array $weeklyData): ?array
    {
        if (empty($weeklyData)) return null;
        
        usort($weeklyData, fn($a, $b) => $b['completion_rate'] <=> $a['completion_rate']);
        return $weeklyData[0];
    }
    
    private function findWorstWeek(array $weeklyData): ?array
    {
        if (empty($weeklyData)) return null;
        
        usort($weeklyData, fn($a, $b) => $a['completion_rate'] <=> $b['completion_rate']);
        return $weeklyData[0];
    }
    
    private function getRiskLevel(int $score): string
    {
        if ($score >= 70) return 'high';
        if ($score >= 40) return 'medium';
        return 'low';
    }
    
    private function getRecommendations(int $score, array $factors): array
    {
        $recommendations = [];
        
        if (in_array('Высокая нагрузка', $factors)) {
            $recommendations[] = 'Делегируйте часть задач коллегам';
            $recommendations[] = 'Установите реалистичные дедлайны';
        }
        
        if (in_array('Много просроченных задач', $factors)) {
            $recommendations[] = 'Пересмотрите приоритеты задач';
            $recommendations[] = 'Закройте или отмените неактуальные задачи';
        }
        
        if (in_array('Низкий процент завершения', $factors)) {
            $recommendations[] = 'Разбивайте большие задачи на подзадачи';
            $recommendations[] = 'Используйте технику Pomodoro';
        }
        
        if ($score >= 70) {
            $recommendations[] = 'Возьмите выходной или отпуск';
            $recommendations[] = 'Обсудите нагрузку с руководителем';
        }
        
        return $recommendations;
    }
    
    private function getDayName(int $dayNumber): string
    {
        $days = [
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
            7 => 'Воскресенье'
        ];
        
        return $days[$dayNumber] ?? 'Неизвестно';
    }
    
    private function calculateOverallCompletionRate(array $tasks): float
    {
        if (empty($tasks)) return 0;
        
        $completed = array_filter($tasks, fn($t) => $t->getStatus() === 'completed');
        return round((count($completed) / count($tasks)) * 100, 1);
    }
}
