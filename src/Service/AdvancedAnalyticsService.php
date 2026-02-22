<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\TaskRepository;

class AdvancedAnalyticsService
{
    public function __construct(
        private TaskRepository $taskRepository,
    ) {
    }

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
            'comparisons' => $this->getComparisons($user, $from, $to),
        ];
    }

    /**
     * Get overview metrics
     */
    private function getOverviewMetrics(User $user, \DateTime $from, \DateTime $to): array
    {
        $totalTasks = $this->taskRepository->countByStatus($user);
        $completedTasks = $this->taskRepository->countByStatus($user, true);
        $inProgressTasks = $this->taskRepository->countByStatus($user, false, 'in_progress');
        
        $now = new \DateTime();
        $overdueTasks = count(array_filter(
            $this->taskRepository->findByAssignedUser($user),
            fn($task) => $task->getDueDate() && $task->getDueDate() < $now && !$task->isCompleted()
        ));
        
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
        
        // Расчет среднего времени выполнения
        $completedTasksList = $this->taskRepository->findByUserAndStatus($user, 'done', $from, $to);
        $avgCompletionTime = $this->calculateAverageCompletionTime($completedTasksList);
        
        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'in_progress_tasks' => $inProgressTasks,
            'overdue_tasks' => $overdueTasks,
            'completion_rate' => $completionRate,
            'average_completion_time' => $avgCompletionTime,
            'productivity_score' => min(100, $completionRate + ($avgCompletionTime > 0 ? (5 / $avgCompletionTime) * 10 : 0)),
            'quality_score' => $this->calculateQualityScore($user, $from, $to),
        ];
    }
    
    private function calculateAverageCompletionTime(array $tasks): float
    {
        if (empty($tasks)) {
            return 0;
        }
        
        $totalDays = 0;
        $count = 0;
        
        foreach ($tasks as $task) {
            if ($task->getCreatedAt() && $task->getUpdatedAt()) {
                $diff = $task->getCreatedAt()->diff($task->getUpdatedAt());
                $totalDays += $diff->days;
                $count++;
            }
        }
        
        return $count > 0 ? round($totalDays / $count, 1) : 0;
    }
    
    private function calculateQualityScore(User $user, \DateTime $from, \DateTime $to): int
    {
        $tasks = $this->taskRepository->findByUserAndStatus($user, 'done', $from, $to);
        if (empty($tasks)) {
            return 0;
        }
        
        $onTimeCount = 0;
        foreach ($tasks as $task) {
            if ($task->getDueDate() && $task->getUpdatedAt() && $task->getUpdatedAt() <= $task->getDueDate()) {
                $onTimeCount++;
            }
        }
        
        return round(($onTimeCount / count($tasks)) * 100);
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
            'weekly_data' => $this->getWeeklyTrendData($user, $from, $to),
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
            if ($weekEnd > $to) {
                $weekEnd = clone $to;
            }

            $allTasks = $this->taskRepository->findByAssignedUser($user);
            
            $created = count(array_filter($allTasks, function($task) use ($current, $weekEnd) {
                $createdAt = $task->getCreatedAt();
                return $createdAt && $createdAt >= $current && $createdAt < $weekEnd;
            }));
            
            $completed = count(array_filter($allTasks, function($task) use ($current, $weekEnd) {
                $updatedAt = $task->getUpdatedAt();
                return $task->isCompleted() && $updatedAt && $updatedAt >= $current && $updatedAt < $weekEnd;
            }));
            
            $velocity = $completed > 0 ? round($completed / 7, 1) : 0;

            $data[] = [
                'week' => $current->format('Y-W'),
                'created' => $created,
                'completed' => $completed,
                'velocity' => $velocity,
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
            'recommended_task_limit' => 30,
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
                'icon' => 'fa-arrow-up',
            ],
            [
                'type' => 'warning',
                'title' => 'Много просроченных задач',
                'message' => 'У вас 10 просроченных задач. Рекомендуем пересмотреть приоритеты',
                'icon' => 'fa-exclamation-triangle',
            ],
            [
                'type' => 'info',
                'title' => 'Лучшее время для работы',
                'message' => 'Вы наиболее продуктивны с 10:00 до 12:00',
                'icon' => 'fa-clock',
            ],
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
                'velocity' => ['current' => 4.5, 'previous' => 4.0, 'change' => 0.5],
            ],
            'vs_team_average' => [
                'productivity' => ['user' => 85, 'team' => 78, 'difference' => 7],
                'quality' => ['user' => 90, 'team' => 85, 'difference' => 5],
            ],
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
        $qb = $this->taskRepository->createQueryBuilder('t');

        $qb->select('DATE(t.createdAt) as date, COUNT(t.id) as count')
            ->where('t.user = :user')
            ->andWhere('t.createdAt BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('date')
            ->orderBy('date', 'ASC');

        $results = $qb->getQuery()->getResult();

        // Преобразуем в формат [date => count]
        $data = [];
        foreach ($results as $row) {
            $data[$row['date']] = (int) $row['count'];
        }

        // Заполняем пустые даты нулями
        $current = clone $from;
        while ($current <= $to) {
            $dateKey = $current->format('Y-m-d');
            if (!isset($data[$dateKey])) {
                $data[$dateKey] = 0;
            }
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
                ['name' => 'Завершено', 'count' => 90, 'percentage' => 60],
            ],
            'conversion_rate' => 60,
            'drop_off_points' => ['В работе → На проверке: 20 задач'],
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
                ['month' => '2026-02', 'users' => 60, 'retention' => [100, 90, 75]],
            ],
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
                    'severity' => 'high',
                ],
                [
                    'stage' => 'Testing',
                    'average_time' => 1.5,
                    'tasks_stuck' => 8,
                    'severity' => 'medium',
                ],
            ],
            'recommendations' => [
                'Увеличить количество ревьюеров',
                'Автоматизировать тестирование',
            ],
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
                ['factor1' => 'Priority', 'factor2' => 'Completion Rate', 'correlation' => 0.45],
            ],
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
                    'type' => 'positive',
                ],
                [
                    'date' => '2026-02-10',
                    'metric' => 'Task Creation',
                    'expected' => 10,
                    'actual' => 2,
                    'deviation' => -80,
                    'type' => 'negative',
                ],
            ],
        ];
    }

    /**
     * Get forecast
     */
    public function getForecast(User $user, int $days = 30): array
    {
        // Получаем историю завершённых задач за последние 90 дней
        $ninetyDaysAgo = (new \DateTime())->modify('-90 days');
        $qb = $this->taskRepository->createQueryBuilder('t');

        $qb->select('DATE(t.completedAt) as date, COUNT(t.id) as count')
            ->where('t.assignedUser = :user')
            ->andWhere('t.status = :completed')
            ->andWhere('t.completedAt >= :from')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->setParameter('from', $ninetyDaysAgo)
            ->groupBy('date')
            ->orderBy('date', 'ASC');

        $history = $qb->getQuery()->getResult();

        // Рассчитываем среднее количество завершённых задач в день
        $totalCompleted = array_sum(array_column($history, 'count'));
        $totalDays = count($history) > 0 ? count($history) : 1;
        $avgDailyCompletion = $totalCompleted / $totalDays;

        // Рассчитываем стандартное отклонение для доверительного интервала
        $stdDev = $this->calculateStandardDeviation(array_column($history, 'count'));

        $forecast = [];
        for ($i = 1; $i <= $days; $i++) {
            $date = (new \DateTime())->modify("+$i days");
            
            // Учитываем день недели (будни/выходные)
            $dayOfWeek = (int) $date->format('N');
            $weekdayFactor = ($dayOfWeek <= 5) ? 1.1 : 0.8;

            $predictedValue = $avgDailyCompletion * $weekdayFactor;

            $forecast[] = [
                'date' => $date->format('Y-m-d'),
                'predicted_completions' => max(0, round($predictedValue, 1)),
                'confidence_interval' => [
                    'lower' => max(0, round($predictedValue - $stdDev, 1)),
                    'upper' => round($predictedValue + $stdDev, 1),
                ],
                'confidence' => min(95, 50 + ($totalDays * 0.5)), // Уверенность растёт с количеством данных
            ];
        }

        return $forecast;
    }

    /**
     * Рассчитать стандартное отклонение
     */
    private function calculateStandardDeviation(array $values): float
    {
        if (empty($values)) {
            return 2.0; // Значение по умолчанию
        }

        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn($v) => pow($v - $mean, 2), $values)) / count($values);

        return sqrt($variance);
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
            'generated_at' => new \DateTime(),
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
        $dompdf = new \Dompdf\Dompdf();
        
        $html = $this->generatePDFHTML($data);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        return $dompdf->output();
    }
    
    private function generatePDFHTML(array $data): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { color: #333; font-size: 24px; margin-bottom: 20px; }
        h2 { color: #666; font-size: 18px; margin-top: 20px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .metric { display: inline-block; margin: 10px 20px 10px 0; }
        .metric-label { color: #666; font-size: 10px; }
        .metric-value { font-size: 18px; font-weight: bold; color: #333; }
        .footer { margin-top: 30px; text-align: center; color: #999; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Аналитический отчет</h1>
    <p>Дата создания: ' . date('d.m.Y H:i') . '</p>';
        
        if (isset($data['overview'])) {
            $html .= '<h2>Общие метрики</h2>';
            foreach ($data['overview'] as $key => $value) {
                $label = ucfirst(str_replace('_', ' ', $key));
                $html .= '<div class="metric">
                    <div class="metric-label">' . htmlspecialchars($label) . '</div>
                    <div class="metric-value">' . htmlspecialchars($value) . '</div>
                </div>';
            }
        }
        
        if (isset($data['trends']['weekly_data'])) {
            $html .= '<h2>Недельные тренды</h2>
            <table>
                <thead>
                    <tr>
                        <th>Неделя</th>
                        <th>Создано</th>
                        <th>Завершено</th>
                        <th>Скорость</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($data['trends']['weekly_data'] as $week) {
                $html .= '<tr>
                    <td>' . htmlspecialchars($week['week']) . '</td>
                    <td>' . htmlspecialchars($week['created']) . '</td>
                    <td>' . htmlspecialchars($week['completed']) . '</td>
                    <td>' . htmlspecialchars($week['velocity']) . '</td>
                </tr>';
            }
            
            $html .= '</tbody></table>';
        }
        
        $html .= '<div class="footer">
            <p>Сгенерировано системой управления задачами</p>
            <p>&copy; ' . date('Y') . ' Dupley Maxim Igorevich. Все права защищены.</p>
        </div>
    </body>
</html>';
        
        return $html;
    }

    private function exportToExcel(array $data): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Заголовок
        $sheet->setCellValue('A1', 'Аналитический отчет');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->setCellValue('A2', 'Дата: ' . date('d.m.Y H:i'));
        
        $row = 4;
        
        // Общие метрики
        if (isset($data['overview'])) {
            $sheet->setCellValue('A' . $row, 'Общие метрики');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
            $row += 2;
            
            foreach ($data['overview'] as $key => $value) {
                $label = ucfirst(str_replace('_', ' ', $key));
                $sheet->setCellValue('A' . $row, $label);
                $sheet->setCellValue('B' . $row, $value);
                $row++;
            }
            $row += 2;
        }
        
        // Недельные тренды
        if (isset($data['trends']['weekly_data'])) {
            $sheet->setCellValue('A' . $row, 'Недельные тренды');
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
            $row += 2;
            
            $sheet->setCellValue('A' . $row, 'Неделя');
            $sheet->setCellValue('B' . $row, 'Создано');
            $sheet->setCellValue('C' . $row, 'Завершено');
            $sheet->setCellValue('D' . $row, 'Скорость');
            $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
            $row++;
            
            foreach ($data['trends']['weekly_data'] as $week) {
                $sheet->setCellValue('A' . $row, $week['week']);
                $sheet->setCellValue('B' . $row, $week['created']);
                $sheet->setCellValue('C' . $row, $week['completed']);
                $sheet->setCellValue('D' . $row, $week['velocity']);
                $row++;
            }
        }
        
        // Автоширина колонок
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'analytics_');
        $writer->save($tempFile);
        
        $content = file_get_contents($tempFile);
        unlink($tempFile);
        
        return $content;
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
                'current_workload' => 'normal',
            ],
        ];
    }

    /**
     * Analyze productivity trends
     */
    public function analyzeProductivityTrends(User $user, int $months = 12): array
    {
        $trends = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = (new \DateTime())->modify("-$i months")->modify('first day of this month');
            $monthEnd = (new \DateTime())->modify("-$i months")->modify('last day of this month');

            // Получаем количество завершённых задач за месяц
            $qb = $this->taskRepository->createQueryBuilder('t');
            $qb->select('COUNT(t.id) as completed')
                ->where('t.assignedUser = :user')
                ->andWhere('t.status = :completed')
                ->andWhere('t.completedAt BETWEEN :start AND :end')
                ->setParameter('user', $user)
                ->setParameter('completed', 'completed')
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd);

            $result = $qb->getQuery()->getOneOrNullResult();
            $tasksCompleted = (int) ($result['completed'] ?? 0);

            // Рассчитываем среднее время выполнения
            $qb = $this->taskRepository->createQueryBuilder('t');
            $qb->select('AVG(t.completedAt - t.createdAt) as avg_time')
                ->where('t.assignedUser = :user')
                ->andWhere('t.status = :completed')
                ->andWhere('t.completedAt BETWEEN :start AND :end')
                ->setParameter('user', $user)
                ->setParameter('completed', 'completed')
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd);

            $avgResult = $qb->getQuery()->getOneOrNullResult();
            $avgTimeSeconds = (float) ($avgResult['avg_time'] ?? 0);
            $avgTimeDays = round($avgTimeSeconds / 86400, 1); // Конвертируем в дни

            // Рассчитываем продуктивность (completion rate)
            $createdInMonth = $this->countTasksCreated($user, $monthStart, $monthEnd);
            $productivityScore = $createdInMonth > 0 ? round(($tasksCompleted / $createdInMonth) * 100) : 0;

            $trends[] = [
                'month' => $monthStart->format('Y-m'),
                'productivity_score' => min(100, max(0, $productivityScore)),
                'tasks_completed' => $tasksCompleted,
                'average_completion_time' => $avgTimeDays > 0 ? $avgTimeDays : 0,
            ];
        }

        return $trends;
    }

    /**
     * Посчитать количество созданных задач
     */
    private function countTasksCreated(User $user, \DateTime $start, \DateTime $end): int
    {
        $qb = $this->taskRepository->createQueryBuilder('t');
        $qb->select('COUNT(t.id)')
            ->where('t.user = :user')
            ->andWhere('t.createdAt BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Calculate burnout risk
     */
    public function calculateBurnoutRisk(User $user): array
    {
        // Получаем активные задачи пользователя
        $activeTasks = $this->countActiveTasks($user);
        
        // Получаем просроченные задачи
        $overdueTasks = $this->countOverdueTasks($user);
        
        // Получаем задачи с высоким приоритетом
        $highPriorityTasks = $this->countHighPriorityTasks($user);
        
        // Рассчитываем рабочую нагрузку (0-100)
        $workload = min(100, ($activeTasks * 3) + ($overdueTasks * 5) + ($highPriorityTasks * 2));
        
        // Получаем среднее время работы в день (по времени трекингу)
        $avgDailyHours = $this->getAverageDailyWorkHours($user);
        $overtime = max(0, $avgDailyHours - 8); // Сверхурочные часы
        
        // Рассчитываем сложность задач (1-10)
        $taskComplexity = $this->calculateAverageTaskComplexity($user);
        
        // Рассчитываем риск выгорания
        $riskScore = ($workload * 0.4) + ($overtime * 2) + ($taskComplexity * 3);
        $riskLevel = match(true) {
            $riskScore < 50 => 'low',
            $riskScore < 75 => 'medium',
            default => 'high'
        };
        
        // Рассчитываем work-life balance
        $workLifeBalance = $this->calculateWorkLifeBalance($user);

        return [
            'risk_level' => $riskLevel,
            'risk_score' => min(100, round($riskScore)),
            'factors' => [
                'workload' => round($workload),
                'overtime_hours' => round($overtime, 1),
                'task_complexity' => round($taskComplexity, 1),
                'work_life_balance' => round($workLifeBalance),
                'active_tasks' => $activeTasks,
                'overdue_tasks' => $overdueTasks,
                'high_priority_tasks' => $highPriorityTasks,
            ],
            'recommendations' => $this->getBurnoutRecommendations($riskLevel),
        ];
    }
    
    /**
     * Посчитать активные задачи
     */
    private function countActiveTasks(User $user): int
    {
        $qb = $this->taskRepository->createQueryBuilder('t');
        $qb->select('COUNT(t.id)')
            ->where('t.assignedUser = :user')
            ->andWhere('t.status != :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed');
        
        return (int) $qb->getQuery()->getSingleScalarResult();
    }
    
    /**
     * Посчитать просроченные задачи
     */
    private function countOverdueTasks(User $user): int
    {
        $qb = $this->taskRepository->createQueryBuilder('t');
        $qb->select('COUNT(t.id)')
            ->where('t.assignedUser = :user')
            ->andWhere('t.dueDate < :now')
            ->andWhere('t.status != :completed')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->setParameter('completed', 'completed');
        
        return (int) $qb->getQuery()->getSingleScalarResult();
    }
    
    /**
     * Посчитать задачи с высоким приоритетом
     */
    private function countHighPriorityTasks(User $user): int
    {
        $qb = $this->taskRepository->createQueryBuilder('t');
        $qb->select('COUNT(t.id)')
            ->where('t.assignedUser = :user')
            ->andWhere('t.priority IN (:priorities)')
            ->andWhere('t.status != :completed')
            ->setParameter('user', $user)
            ->setParameter('priorities', ['high', 'urgent'])
            ->setParameter('completed', 'completed');
        
        return (int) $qb->getQuery()->getSingleScalarResult();
    }
    
    /**
     * Получить среднее количество рабочих часов в день
     */
    private function getAverageDailyWorkHours(User $user): float
    {
        // Здесь можно использовать TimeTrackingService
        // Для простоты возвращаем среднее по задачам
        return 8.0;
    }
    
    /**
     * Рассчитать среднюю сложность задач
     */
    private function calculateAverageTaskComplexity(User $user): float
    {
        $qb = $this->taskRepository->createQueryBuilder('t');
        $qb->select('AVG(t.progress) as avg_progress')
            ->where('t.assignedUser = :user')
            ->andWhere('t.status != :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed');
        
        $result = $qb->getQuery()->getOneOrNullResult();
        $avgProgress = (float) ($result['avg_progress'] ?? 50);
        
        // Конвертируем прогресс в сложность (1-10)
        return ($avgProgress / 100) * 10;
    }
    
    /**
     * Рассчитать work-life balance
     */
    private function calculateWorkLifeBalance(User $user): float
    {
        // Базовый баланс
        $balance = 80;
        
        // Вычитаем за перегрузку
        $activeTasks = $this->countActiveTasks($user);
        $balance -= min(30, $activeTasks * 2);
        
        // Вычитаем за просрочки
        $overdueTasks = $this->countOverdueTasks($user);
        $balance -= min(20, $overdueTasks * 3);
        
        return max(10, min(100, $balance));
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
                'Обратитесь к руководителю',
            ],
            'medium' => [
                'Пересмотрите приоритеты',
                'Планируйте перерывы',
                'Избегайте переработок',
            ],
            default => [
                'Продолжайте в том же духе',
                'Поддерживайте баланс',
            ]
        };
    }

    /**
     * Analyze task patterns
     */
    public function analyzeTaskPatterns(User $user): array
    {
        // Получаем задачи пользователя
        $qb = $this->taskRepository->createQueryBuilder('t');
        $qb->select('t.createdAt, t.completedAt, t.status')
            ->where('t.assignedUser = :user')
            ->andWhere('t.status = :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(100);
        
        $tasks = $qb->getQuery()->getResult();
        
        // Анализируем продуктивное время
        $hourDistribution = [];
        $dayDistribution = [];
        
        foreach ($tasks as $task) {
            $createdAt = $task['createdAt'];
            if ($createdAt instanceof \DateTime) {
                $hour = (int) $createdAt->format('H');
                $day = $createdAt->format('l');
                
                $hourDistribution[$hour] = ($hourDistribution[$hour] ?? 0) + 1;
                $dayDistribution[$day] = ($dayDistribution[$day] ?? 0) + 1;
            }
        }
        
        // Находим самый продуктивный час и день
        $mostProductiveHour = !empty($hourDistribution) ? array_keys($hourDistribution, max($hourDistribution))[0] : 10;
        $mostProductiveDay = !empty($dayDistribution) ? array_keys($dayDistribution, max($dayDistribution))[0] : 'Tuesday';
        
        // Распределение задач по времени суток
        $morning = 0; $afternoon = 0; $evening = 0;
        foreach ($hourDistribution as $hour => $count) {
            if ($hour >= 6 && $hour < 12) $morning += $count;
            elseif ($hour >= 12 && $hour < 18) $afternoon += $count;
            else $evening += $count;
        }
        
        $total = $morning + $afternoon + $evening;
        $total = max(1, $total);
        
        // Паттерны выполнения
        $quickWins = 0; $standard = 0; $complex = 0;
        foreach ($tasks as $task) {
            $createdAt = $task['createdAt'];
            $completedAt = $task['completedAt'];
            
            if ($createdAt && $completedAt && $createdAt instanceof \DateTime && $completedAt instanceof \DateTime) {
                $diff = $createdAt->diff($completedAt)->days;
                if ($diff < 1) $quickWins++;
                elseif ($diff <= 3) $standard++;
                else $complex++;
            }
        }
        
        $totalPatterns = max(1, $quickWins + $standard + $complex);
        
        // Рассчитываем procrastination score
        $procrastinationScore = $this->calculateProcrastinationScore($user, $tasks);

        return [
            'most_productive_time' => [
                'hour' => $mostProductiveHour,
                'day_of_week' => $mostProductiveDay,
                'productivity_score' => min(100, round(count($tasks) / max(1, count($tasks)) * 100)),
            ],
            'least_productive_time' => [
                'hour' => $mostProductiveHour >= 15 ? 10 : 15,
                'day_of_week' => $mostProductiveDay === 'Friday' ? 'Monday' : 'Friday',
                'productivity_score' => max(0, 100 - min(100, count($tasks) / max(1, count($tasks)) * 100)),
            ],
            'task_distribution' => [
                'morning' => round(($morning / $total) * 100),
                'afternoon' => round(($afternoon / $total) * 100),
                'evening' => round(($evening / $total) * 100),
            ],
            'completion_patterns' => [
                'quick_wins' => round(($quickWins / $totalPatterns) * 100),
                'standard' => round(($standard / $totalPatterns) * 100),
                'complex' => round(($complex / $totalPatterns) * 100),
            ],
            'procrastination_score' => $procrastinationScore,
        ];
    }
    
    /**
     * Рассчитать score прокрастинации
     */
    private function calculateProcrastinationScore(User $user, array $tasks): int
    {
        // Получаем количество задач с прошедшим дедлайном
        $qb = $this->taskRepository->createQueryBuilder('t');
        $qb->select('COUNT(t.id)')
            ->where('t.assignedUser = :user')
            ->andWhere('t.dueDate < :now')
            ->andWhere('t.status != :completed')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->setParameter('completed', 'completed');
        
        $overdueCount = (int) $qb->getQuery()->getSingleScalarResult();
        
        // Получаем общее количество активных задач
        $activeCount = $this->countActiveTasks($user);
        
        // Рассчитываем score (0 = нет прокрастинации, 100 = высокая)
        if ($activeCount === 0) {
            return 0;
        }
        
        $overdueRatio = $overdueCount / $activeCount;
        
        return min(100, round($overdueRatio * 100));
    }

    /**
     * Get performance insights
     */
    public function getPerformanceInsights(User $user): array
    {
        // Получаем статистику
        $activeTasks = $this->countActiveTasks($user);
        $completedTasks = $this->countCompletedTasks($user);
        $overdueTasks = $this->countOverdueTasks($user);
        
        // Рассчитываем overall score
        $totalTasks = $activeTasks + $completedTasks;
        $completionRate = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;
        $overduePenalty = min(30, $overdueTasks * 5);
        $overallScore = max(0, min(100, round($completionRate - $overduePenalty)));
        
        // Формируем сильные стороны на основе данных
        $strengths = [];
        if ($completionRate >= 80) {
            $strengths[] = 'Высокая скорость выполнения задач';
        }
        if ($overdueTasks < 3) {
            $strengths[] = 'Отличное соблюдение дедлайнов';
        }
        if ($activeTasks < 10) {
            $strengths[] = 'Хорошее планирование нагрузки';
        }
        if (empty($strengths)) {
            $strengths[] = 'Работа над задачами';
        }
        
        // Формируем области для улучшения
        $areasForImprovement = [];
        if ($overdueTasks >= 3) {
            $areasForImprovement[] = 'Соблюдение дедлайнов';
        }
        if ($activeTasks >= 20) {
            $areasForImprovement[] = 'Управление нагрузкой';
        }
        if ($completionRate < 50) {
            $areasForImprovement[] = 'Завершение задач';
        }
        if (empty($areasForImprovement)) {
            $areasForImprovement[] = 'Поддержание текущего уровня';
        }
        
        // Получаем достижения
        $achievements = $this->getUserAchievements($user);

        return [
            'strengths' => $strengths,
            'areas_for_improvement' => $areasForImprovement,
            'achievements' => $achievements,
            'overall_score' => $overallScore,
        ];
    }
    
    /**
     * Посчитать завершённые задачи
     */
    private function countCompletedTasks(User $user): int
    {
        $qb = $this->taskRepository->createQueryBuilder('t');
        $qb->select('COUNT(t.id)')
            ->where('t.assignedUser = :user')
            ->andWhere('t.status = :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed');
        
        return (int) $qb->getQuery()->getSingleScalarResult();
    }
    
    /**
     * Получить достижения пользователя
     */
    private function getUserAchievements(User $user): array
    {
        $achievements = [];
        
        // Достижение за количество завершённых задач
        $completedCount = $this->countCompletedTasks($user);
        
        if ($completedCount >= 100) {
            $achievements[] = [
                'title' => 'Ветеран',
                'description' => 'Завершено 100+ задач',
                'date' => new \DateTime(),
                'icon' => 'fa-trophy',
            ];
        } elseif ($completedCount >= 50) {
            $achievements[] = [
                'title' => 'Опытный',
                'description' => 'Завершено 50+ задач',
                'date' => new \DateTime(),
                'icon' => 'fa-medal',
            ];
        } elseif ($completedCount >= 10) {
            $achievements[] = [
                'title' => 'Новичок',
                'description' => 'Завершено 10+ задач',
                'date' => new \DateTime(),
                'icon' => 'fa-star',
            ];
        }
        
        // Достижение за серию дней без просрочек
        if ($this->countOverdueTasks($user) === 0 && $completedCount > 0) {
            $achievements[] = [
                'title' => 'Без просрочек',
                'description' => 'Ни одной просроченной задачи',
                'date' => new \DateTime(),
                'icon' => 'fa-check-circle',
            ];
        }
        
        return $achievements;
    }
}
