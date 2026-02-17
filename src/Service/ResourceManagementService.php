<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Task;

class ResourceManagementService
{
    /**
     * Get resource availability
     */
    public function getResourceAvailability(User $user, \DateTime $from, \DateTime $to): array
    {
        return [
            'total_hours' => 160, // 40 hours/week * 4 weeks
            'allocated_hours' => 120,
            'available_hours' => 40,
            'utilization_percentage' => 75,
            'status' => 'available', // available, busy, overloaded
            'calendar' => $this->getAvailabilityCalendar($user, $from, $to)
        ];
    }

    /**
     * Get availability calendar
     */
    private function getAvailabilityCalendar(User $user, \DateTime $from, \DateTime $to): array
    {
        $calendar = [];
        $current = clone $from;
        
        while ($current <= $to) {
            $calendar[$current->format('Y-m-d')] = [
                'available_hours' => 8,
                'allocated_hours' => rand(0, 8),
                'status' => 'available'
            ];
            $current->modify('+1 day');
        }
        
        return $calendar;
    }

    /**
     * Allocate resource to task
     */
    public function allocateResource(Task $task, User $user, float $hours, \DateTime $date): array
    {
        // TODO: Save to database
        return [
            'id' => uniqid(),
            'task_id' => $task->getId(),
            'user_id' => $user->getId(),
            'hours' => $hours,
            'date' => $date,
            'status' => 'allocated'
        ];
    }

    /**
     * Get resource workload
     */
    public function getResourceWorkload(User $user, \DateTime $from, \DateTime $to): array
    {
        return [
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getUsername()
            ],
            'period' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d')
            ],
            'workload' => [
                'total_tasks' => 15,
                'total_hours' => 120,
                'by_priority' => [
                    'urgent' => 30,
                    'high' => 50,
                    'medium' => 30,
                    'low' => 10
                ],
                'by_project' => []
            ],
            'capacity' => 160,
            'utilization' => 75
        ];
    }

    /**
     * Balance team workload
     */
    public function balanceTeamWorkload(array $userIds): array
    {
        $workloads = [];
        
        foreach ($userIds as $userId) {
            // TODO: Get user workload
            $workloads[$userId] = rand(50, 150);
        }

        $average = array_sum($workloads) / count($workloads);
        $recommendations = [];

        foreach ($workloads as $userId => $workload) {
            if ($workload > $average * 1.2) {
                $recommendations[] = [
                    'user_id' => $userId,
                    'action' => 'reduce',
                    'amount' => $workload - $average
                ];
            } elseif ($workload < $average * 0.8) {
                $recommendations[] = [
                    'user_id' => $userId,
                    'action' => 'increase',
                    'amount' => $average - $workload
                ];
            }
        }

        return [
            'workloads' => $workloads,
            'average' => $average,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Find available resources
     */
    public function findAvailableResources(\DateTime $date, float $requiredHours, array $skills = []): array
    {
        // TODO: Query database
        return [];
    }

    /**
     * Get resource utilization report
     */
    public function getUtilizationReport(array $userIds, \DateTime $from, \DateTime $to): array
    {
        $report = [];
        
        foreach ($userIds as $userId) {
            $report[] = [
                'user_id' => $userId,
                'utilization' => rand(60, 95),
                'billable_hours' => rand(80, 120),
                'non_billable_hours' => rand(20, 40),
                'overtime_hours' => rand(0, 20)
            ];
        }

        return [
            'period' => ['from' => $from, 'to' => $to],
            'resources' => $report,
            'team_average' => array_sum(array_column($report, 'utilization')) / count($report)
        ];
    }

    /**
     * Create resource pool
     */
    public function createResourcePool(string $name, array $userIds, array $skills): array
    {
        // TODO: Save to database
        return [
            'id' => uniqid(),
            'name' => $name,
            'members' => $userIds,
            'skills' => $skills,
            'created_at' => new \DateTime()
        ];
    }

    /**
     * Get resource pools
     */
    public function getResourcePools(): array
    {
        // TODO: Get from database
        return [];
    }

    /**
     * Request resource
     */
    public function requestResource(Task $task, array $requirements): array
    {
        return [
            'id' => uniqid(),
            'task_id' => $task->getId(),
            'requirements' => $requirements,
            'status' => 'pending',
            'requested_at' => new \DateTime()
        ];
    }

    /**
     * Approve resource request
     */
    public function approveResourceRequest(int $requestId, User $approver): bool
    {
        // TODO: Update in database
        return true;
    }

    /**
     * Get resource conflicts
     */
    public function getResourceConflicts(\DateTime $from, \DateTime $to): array
    {
        return [
            'conflicts' => [
                [
                    'user_id' => 1,
                    'date' => '2026-02-20',
                    'allocated_hours' => 12,
                    'available_hours' => 8,
                    'overallocation' => 4,
                    'tasks' => []
                ]
            ],
            'total_conflicts' => 1
        ];
    }

    /**
     * Resolve resource conflict
     */
    public function resolveConflict(int $conflictId, string $resolution): bool
    {
        // TODO: Apply resolution
        return true;
    }

    /**
     * Get resource forecast
     */
    public function getResourceForecast(array $userIds, int $weeks = 4): array
    {
        $forecast = [];
        
        for ($i = 1; $i <= $weeks; $i++) {
            $week = (new \DateTime())->modify("+$i weeks");
            $forecast[] = [
                'week' => $week->format('Y-W'),
                'required_resources' => rand(5, 15),
                'available_resources' => count($userIds),
                'shortage' => max(0, rand(5, 15) - count($userIds))
            ];
        }

        return $forecast;
    }

    /**
     * Get skill matrix
     */
    public function getSkillMatrix(array $userIds): array
    {
        $skills = ['PHP', 'JavaScript', 'Python', 'DevOps', 'Design', 'Testing'];
        $matrix = [];

        foreach ($userIds as $userId) {
            $userSkills = [];
            foreach ($skills as $skill) {
                $userSkills[$skill] = rand(1, 5); // 1-5 proficiency
            }
            $matrix[$userId] = $userSkills;
        }

        return [
            'skills' => $skills,
            'matrix' => $matrix
        ];
    }

    /**
     * Calculate resource cost
     */
    public function calculateResourceCost(User $user, float $hours, float $hourlyRate): array
    {
        $baseCost = $hours * $hourlyRate;
        $overhead = $baseCost * 0.3; // 30% overhead
        $total = $baseCost + $overhead;

        return [
            'hours' => $hours,
            'hourly_rate' => $hourlyRate,
            'base_cost' => $baseCost,
            'overhead' => $overhead,
            'total_cost' => $total
        ];
    }

    /**
     * Get resource efficiency
     */
    public function getResourceEfficiency(User $user, \DateTime $from, \DateTime $to): array
    {
        return [
            'tasks_completed' => 25,
            'hours_worked' => 160,
            'tasks_per_hour' => 0.156,
            'quality_score' => 90,
            'efficiency_score' => 85,
            'trend' => 'improving'
        ];
    }

    /**
     * Optimize resource allocation
     */
    public function optimizeAllocation(array $tasks, array $users): array
    {
        // TODO: Implement optimization algorithm
        return [
            'allocations' => [],
            'optimization_score' => 85,
            'improvements' => [
                'Reduced overallocation by 20%',
                'Improved skill matching by 15%'
            ]
        ];
    }
}
