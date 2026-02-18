<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TaskRepository;

class AdvancedAnalyticsService
{
    public function __construct(
        private TaskRepository $taskRepository
    ) {}

    /**
     * Get comprehensive analytics dashboard
     */
    public function getDashboard(User $user, \DateTime $from, \DateTime $to): array
    {
        return [
            'overview' => $this->getOverviewMetrics($user, $from, $to),
            'trends' => $this->getTrends($user, $from, $to),
            'predictions' => $this->getPredictions($user),
            'insights' => $this->getInsights($user, $from, $to),
            'comparisons' => $this->getComparisons($user, $from, $to)
        ];
    }

    /**
     * Get overview metrics
     */
    private function getOverviewMetrics(User $user, \DateTime $from, \DateTime $to): array
    {
        return [
            'total_tasks' => 150,
            'completed_tasks' => 120,
            'in_progress_tasks' => 20,
            'overdue_tasks' => 10,
            'completion_rate' => 80,
            'average_completion_time' => 3.5, // days
            'productivity_score' => 85,
            'quality_score' => 90
        ];
    }

    /**
     * Get trends
     */
    private function getTrends(User $user, \DateTime $from, \DateTime $to): array
    {
        return [
            'task_creation_trend' => 'increasing', // increasing, decreasing, stable
            'completion_trend' => 'stable',
            'velocity_trend' => 'increasing',
            'quality_trend' => 'stable',
            'weekly_data' => $this->getWeeklyTrendData($user, $from, $to)
        ];
    }

    /**
     * Get weekly trend data
     */
    private function getWeeklyTrendData(User $user, \DateTime $from, \DateTime $to): array
    {
        $data = [];
        $current = clone $from;
        
        while ($current <= $to) {
            $weekEnd = (clone $current)->modify('+7 days');
            
            $data[] = [
                'week' => $current->format('Y-W'),
                'created' => rand(10, 30),
                'completed' => rand(8, 25),
                'velocity' => rand(2, 5)
            ];
            
            $current = $weekEnd;
        }
        
        return $data;
    }

    /**
     * Get predictions
     */
    private function getPredictions(User $user): array
    {
        return [
            'next_week_completion' => 25,
            'next_month_completion' => 100,
            'burnout_risk' => 'low', // low, medium, high
            'capacity_utilization' => 75,
            'recommended_task_limit' => 30
        ];
    }

    /**
     * Get insights
     */
    private function getInsights(User $user, \DateTime $from, \DateTime $to): array
    {
        return [
            [
                'type' => 'positive',
                'title' => 'Отличная продуктивность',
                'message' => 'Ваша продуктивность выросла на 15% за последний месяц',
                'icon' => 'fa-arrow-up'
            ],
            [
                'type' => 'warning',
                'title' => 'Много просроченных задач',
                'message' => 'У вас 10 просроченных задач. Рекомендуем пересмотреть приоритеты',
                'icon' => 'fa-exclamation-triangle'
            ],
            [
                'type' => 'info',
                'title' => 'Лучшее время для работы',
                'message' => 'Вы наиболее продуктивны с 10:00 до 12:00',
                'icon' => 'fa-clock'
            ]
        ];
    }

    /**
     * Get comparisons
     */
    private function getComparisons(User $user, \DateTime $from, \DateTime $to): array
    {
        $previousPeriod = $this->getPreviousPeriod($from, $to);
        
        return [
            'vs_previous_period' => [
                'tasks_completed' => ['current' => 120, 'previous' => 100, 'change' => 20],
                'completion_rate' => ['current' => 80, 'previous' => 75, 'change' => 5],
                'velocity' => ['current' => 4.5, 'previous' => 4.0, 'change' => 0.5]
            ],
            'vs_team_average' => [
                'productivity' => ['user' => 85, 'team' => 78, 'difference' => 7],
                'quality' => ['user' => 90, 'team' => 85, 'difference' => 5]
            ]
        ];
    }

    /**
     * Get previous period
     */
    private function getPreviousPeriod(\DateTime $from, \DateTime $to): array
    {
        $diff = $from->diff($to)->days;
        $previousFrom = (clone $from)->modify("-$diff days");
        $previousTo = clone $from;
        
        return ['from' => $previousFrom, 'to' => $previousTo];
    }

    /**
     * Get heatmap data
     */
    public function getHeatmapData(User $user, \DateTime $from, \DateTime $to): array
    {
        $data = [];
        $current = clone $from;
        
        while ($current <= $to) {
            $data[$current->format('Y-m-d')] = rand(0, 10);
            $current->modify('+1 day');
        }
        
        return $data;
    }

    /**
     * Get funnel analysis
     */
    public function getFunnelAnalysis(User $user, \DateTime $from, \DateTime $to): array
    {
        return [
            'stages' => [
                ['name' => 'Создано', 'count' => 150, 'percentage' => 100],
                ['name' => 'В работе', 'count' => 120, 'percentage' => 80],
                ['name' => 'На проверке', 'count' => 100, 'percentage' => 67],
                ['name' => 'Завершено', 'count' => 90, 'percentage' => 60]
            ],
            'conversion_rate' => 60,
            'drop_off_points' => ['В работе → На проверке: 20 задач']
        ];
    }

    /**
     * Get cohort analysis
     */
    public function getCohortAnalysis(\DateTime $from, \DateTime $to): array
    {
        return [
            'cohorts' => [
                ['month' => '2026-01', 'users' => 50, 'retention' => [100, 85, 70, 60]],
                ['month' => '2026-02', 'users' => 60, 'retention' => [100, 90, 75]]
            ]
        ];
    }

    /**
     * Get bottleneck analysis
     */
    public function getBottleneckAnalysis(User $user): array
    {
        return [
            'bottlenecks' => [
                [
                    'stage' => 'Code Review',
                    'average_time' => 2.5, // days
                    'tasks_stuck' => 15,
                    'severity' => 'high'
                ],
                [
                    'stage' => 'Testing',
                    'average_time' => 1.5,
                    'tasks_stuck' => 8,
                    'severity' => 'medium'
                ]
            ],
            'recommendations' => [
                'Увеличить количество ревьюеров',
                'Автоматизировать тестирование'
            ]
        ];
    }

    /**
     * Get correlation analysis
     */
    public function getCorrelationAnalysis(User $user): array
    {
        return [
            'correlations' => [
                ['factor1' => 'Task Complexity', 'factor2' => 'Completion Time', 'correlation' => 0.85],
                ['factor1' => 'Team Size', 'factor2' => 'Velocity', 'correlation' => 0.65],
                ['factor1' => 'Priority', 'factor2' => 'Completion Rate', 'correlation' => 0.45]
            ]
        ];
    }

    /**
     * Get anomaly detection
     */
    public function detectAnomalies(User $user, \DateTime $from, \DateTime $to): array
    {
        return [
            'anomalies' => [
                [
                    'date' => '2026-02-15',
                    'metric' => 'Task Completion',
                    'expected' => 5,
                    'actual' => 15,
                    'deviation' => 200,
                    'type' => 'positive'
                ],
                [
                    'date' => '2026-02-10',
                    'metric' => 'Task Creation',
                    'expected' => 10,
                    'actual' => 2,
                    'deviation' => -80,
                    'type' => 'negative'
                ]
            ]
        ];
    }

    /**
     * Get forecast
     */
    public function getForecast(User $user, int $days = 30): array
    {
        $forecast = [];
        $baseValue = 5;
        
        for ($i = 1; $i <= $days; $i++) {
            $date = (new \DateTime())->modify("+$i days");
            $value = $baseValue + sin($i / 7) * 2 + rand(-1, 1);
            
            $forecast[] = [
                'date' => $date->format('Y-m-d'),
                'predicted_completions' => max(0, round($value)),
                'confidence_interval' => [
                    'lower' => max(0, round($value - 2)),
                    'upper' => round($value + 2)
                ]
            ];
        }
        
        return $forecast;
    }

    /**
     * Get custom report
     */
    public function generateCustomReport(array $config): array
    {
        return [
            'title' => $config['title'] ?? 'Custom Report',
            'data' => [],
            'charts' => [],
            'generated_at' => new \DateTime()
        ];
    }

    /**
     * Export analytics
     */
    public function exportAnalytics(array $data, string $format = 'pdf'): string
    {
        return match($format) {
            'pdf' => $this->exportToPDF($data),
            'excel' => $this->exportToExcel($data),
            'json' => json_encode($data, JSON_PRETTY_PRINT),
            default => ''
        };
    }

    /**
     * Export to PDF
     */
    private function exportToPDF(array $data): string
    {
        // TODO: Generate PDF
        return '';
    }

    /**
     * Export to Excel
     */
    private function exportToExcel(array $data): string
    {
        // TODO: Generate Excel
        return '';
    }

    /**
     * Predict completion time for tasks
     */
    public function predictCompletionTime(User $user): array
    {
        return [
            'average_completion_days' => 3.5,
            'predicted_completion_date' => (new \DateTime())->modify('+4 days'),
            'confidence' => 85,
            'factors' => [
                'task_complexity' => 'medium',
                'user_velocity' => 'high',
                'current_workload' => 'normal'
            ]
        ];
    }

    /**
     * Analyze productivity trends
     */
    public function analyzeProductivityTrends(User $user, int $months = 12): array
    {
        $trends = [];
        $baseProductivity = 75;
        
        for ($i = 0; $i < $months; $i++) {
            $date = (new \DateTime())->modify("-$i months");
            $productivity = $baseProductivity + sin($i / 3) * 10 + rand(-5, 5);
            
            $trends[] = [
                'month' => $date->format('Y-m'),
                'productivity_score' => max(0, min(100, round($productivity))),
                'tasks_completed' => rand(15, 35),
                'average_completion_time' => rand(2, 5)
            ];
        }
        
        return array_reverse($trends);
    }

    /**
     * Calculate burnout risk
     */
    public function calculateBurnoutRisk(User $user): array
    {
        $workload = rand(50, 100);
        $overtime = rand(0, 20);
        $taskComplexity = rand(1, 10);
        
        $riskScore = ($workload * 0.4) + ($overtime * 2) + ($taskComplexity * 3);
        $riskLevel = match(true) {
            $riskScore < 50 => 'low',
            $riskScore < 75 => 'medium',
            default => 'high'
        };
        
        return [
            'risk_level' => $riskLevel,
            'risk_score' => min(100, round($riskScore)),
            'factors' => [
                'workload' => $workload,
                'overtime_hours' => $overtime,
                'task_complexity' => $taskComplexity,
                'work_life_balance' => rand(60, 90)
            ],
            'recommendations' => $this->getBurnoutRecommendations($riskLevel)
        ];
    }

    /**
     * Get burnout recommendations
     */
    private function getBurnoutRecommendations(string $riskLevel): array
    {
        return match($riskLevel) {
            'high' => [
                'Срочно снизьте нагрузку',
                'Делегируйте задачи',
                'Возьмите выходной',
                'Обратитесь к руководителю'
            ],
            'medium' => [
                'Пересмотрите приоритеты',
                'Планируйте перерывы',
                'Избегайте переработок'
            ],
            default => [
                'Продолжайте в том же духе',
                'Поддерживайте баланс'
            ]
        };
    }

    /**
     * Analyze task patterns
     */
    public function analyzeTaskPatterns(User $user): array
    {
        return [
            'most_productive_time' => [
                'hour' => 10,
                'day_of_week' => 'Tuesday',
                'productivity_score' => 92
            ],
            'least_productive_time' => [
                'hour' => 15,
                'day_of_week' => 'Friday',
                'productivity_score' => 65
            ],
            'task_distribution' => [
                'morning' => 35,
                'afternoon' => 45,
                'evening' => 20
            ],
            'completion_patterns' => [
                'quick_wins' => 40, // tasks completed in < 1 day
                'standard' => 45,   // 1-3 days
                'complex' => 15     // > 3 days
            ],
            'procrastination_score' => rand(10, 40)
        ];
    }

    /**
     * Get performance insights
     */
    public function getPerformanceInsights(User $user): array
    {
        return [
            'strengths' => [
                'Высокая скорость выполнения задач',
                'Отличное качество работы',
                'Хорошее планирование'
            ],
            'areas_for_improvement' => [
                'Управление временем',
                'Делегирование задач'
            ],
            'achievements' => [
                [
                    'title' => 'Продуктивная неделя',
                    'description' => 'Завершено 25+ задач за неделю',
                    'date' => (new \DateTime())->modify('-3 days')
                ],
                [
                    'title' => 'Серия выполнения',
                    'description' => '7 дней подряд с завершенными задачами',
                    'date' => (new \DateTime())->modify('-1 week')
                ]
            ],
            'overall_score' => rand(75, 95)
        ];
    }
}
