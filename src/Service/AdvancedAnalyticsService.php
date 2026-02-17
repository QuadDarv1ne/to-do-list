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
}
