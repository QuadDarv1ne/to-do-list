<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Task;
use App\Entity\Resource;
use App\Entity\ResourceAllocation;
use App\Entity\Skill;
use App\Repository\ResourceRepository;
use App\Repository\ResourceAllocationRepository;
use App\Repository\SkillRepository;
use Doctrine\ORM\EntityManagerInterface;

class ResourceManagementService
{
    public function __construct(
        private ResourceRepository $resourceRepository,
        private ResourceAllocationRepository $allocationRepository,
        private SkillRepository $skillRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Get resource availability
     */
    public function getResourceAvailability(Resource $resource, \DateTime $from, \DateTime $to): array
    {
        $allocations = $this->allocationRepository->findBy([
            'resource' => $resource,
            'date' => [$from, $to]
        ]);

        $allocatedHours = 0;
        foreach ($allocations as $allocation) {
            $allocatedHours += (float)$allocation->getHours();
        }

        $totalHours = $resource->getCapacityPerWeek() * (ceil(($to->diff($from)->days) / 7));
        $availableHours = $totalHours - $allocatedHours;
        $utilizationPercentage = $totalHours > 0 ? ($allocatedHours / $totalHours) * 100 : 0;

        return [
            'total_hours' => $totalHours,
            'allocated_hours' => $allocatedHours,
            'available_hours' => $availableHours,
            'utilization_percentage' => round($utilizationPercentage, 2),
            'status' => $this->calculateResourceStatus($utilizationPercentage),
            'calendar' => $this->getAvailabilityCalendar($resource, $from, $to)
        ];
    }

    /**
     * Get availability calendar
     */
    private function getAvailabilityCalendar(Resource $resource, \DateTime $from, \DateTime $to): array
    {
        $calendar = [];
        $current = clone $from;
        
        while ($current <= $to) {
            $dayAllocations = $this->allocationRepository->findBy([
                'resource' => $resource,
                'date' => new \DateTimeImmutable($current->format('Y-m-d'))
            ]);

            $allocatedHours = 0;
            foreach ($dayAllocations as $allocation) {
                $allocatedHours += (float)$allocation->getHours();
            }

            $dailyCapacity = $resource->getCapacityPerWeek() / 7; // Daily capacity
            $availableHours = $dailyCapacity - $allocatedHours;
            $status = $allocatedHours >= $dailyCapacity ? 'overbooked' : ($allocatedHours >= $dailyCapacity * 0.8 ? 'busy' : 'available');

            $calendar[$current->format('Y-m-d')] = [
                'available_hours' => $availableHours,
                'allocated_hours' => $allocatedHours,
                'daily_capacity' => $dailyCapacity,
                'status' => $status
            ];

            $current->modify('+1 day');
        }
        
        return $calendar;
    }

    /**
     * Calculate resource status based on utilization
     */
    private function calculateResourceStatus(float $utilization): string
    {
        if ($utilization >= 100) {
            return 'overloaded';
        } elseif ($utilization >= 85) {
            return 'busy';
        } elseif ($utilization >= 20) {
            return 'available';
        } else {
            return 'underutilized';
        }
    }

    /**
     * Allocate resource to task
     */
    public function allocateResource(Task $task, Resource $resource, float $hours, \DateTime $date): ResourceAllocation
    {
        $allocation = new ResourceAllocation();
        $allocation->setTask($task);
        $allocation->setResource($resource);
        $allocation->setHours(number_format($hours, 2));
        $allocation->setDate(new \DateTimeImmutable($date->format('Y-m-d')));
        $allocation->setStatus('confirmed');

        $this->entityManager->persist($allocation);
        $this->entityManager->flush();

        return $allocation;
    }

    /**
     * Get resource workload
     */
    public function getResourceWorkload(Resource $resource, \DateTime $from, \DateTime $to): array
    {
        $allocations = $this->allocationRepository->createQueryBuilder('ra')
            ->select('ra, t')
            ->join('ra.task', 't')
            ->where('ra.resource = :resource')
            ->andWhere('ra.date BETWEEN :from AND :to')
            ->setParameter('resource', $resource)
            ->setParameter('from', new \DateTimeImmutable($from->format('Y-m-d')))
            ->setParameter('to', new \DateTimeImmutable($to->format('Y-m-d')))
            ->getQuery()
            ->getResult();

        $totalHours = 0;
        $byPriority = ['urgent' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        $byProject = [];

        foreach ($allocations as $allocation) {
            $hours = (float)$allocation->getHours();
            $totalHours += $hours;

            $task = $allocation->getTask();
            if ($task) {
                $priority = $task->getPriority() ?? 'medium';
                $byPriority[$priority] += $hours;

                // Group by project if available
                $project = $task->getProject() ?? 'unassigned';
                if (!isset($byProject[$project])) {
                    $byProject[$project] = 0;
                }
                $byProject[$project] += $hours;
            }
        }

        $capacity = $resource->getCapacityPerWeek() * (ceil(($to->diff($from)->days) / 7));
        $utilization = $capacity > 0 ? ($totalHours / $capacity) * 100 : 0;

        return [
            'resource' => [
                'id' => $resource->getId(),
                'name' => $resource->getName()
            ],
            'period' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d')
            ],
            'workload' => [
                'total_allocations' => count($allocations),
                'total_hours' => $totalHours,
                'by_priority' => $byPriority,
                'by_project' => $byProject
            ],
            'capacity' => $capacity,
            'utilization' => round($utilization, 2)
        ];
    }

    /**
     * Balance team workload
     */
    public function balanceTeamWorkload(array $resourceIds): array
    {
        $workloads = [];
        
        foreach ($resourceIds as $resourceId) {
            $resource = $this->resourceRepository->find($resourceId);
            if ($resource) {
                $currentDate = new \DateTime();
                $nextWeek = (clone $currentDate)->modify('+1 week');
                
                $workload = $this->getResourceWorkload($resource, $currentDate, $nextWeek);
                $workloads[$resourceId] = $workload['utilization'];
            }
        }

        $average = count($workloads) > 0 ? array_sum($workloads) / count($workloads) : 0;
        $recommendations = [];

        foreach ($workloads as $resourceId => $workload) {
            if ($workload > $average * 1.2) {
                $recommendations[] = [
                    'resource_id' => $resourceId,
                    'action' => 'reduce',
                    'amount' => $workload - $average
                ];
            } elseif ($workload < $average * 0.8) {
                $recommendations[] = [
                    'resource_id' => $resourceId,
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
    public function findAvailableResources(\DateTime $date, float $requiredHours, array $skillRequirements = []): array
    {
        // Оптимизировано: используем репозиторий для фильтрации в SQL
        $resources = $this->resourceRepository->findAvailableByDateAndSkills($date, $skillRequirements);
        $availableResources = [];

        foreach ($resources as $resource) {
            // Проверяем доступность по часам
            if ($resource->isAvailable($date, $requiredHours)) {
                $availableResources[] = $resource;
            }
        }

        return $availableResources;
    }

    /**
     * Get resource utilization report
     */
    public function getUtilizationReport(array $resourceIds, \DateTime $from, \DateTime $to): array
    {
        $report = [];
        
        foreach ($resourceIds as $resourceId) {
            $resource = $this->resourceRepository->find($resourceId);
            if ($resource) {
                $workload = $this->getResourceWorkload($resource, $from, $to);
                
                $report[] = [
                    'resource_id' => $resourceId,
                    'name' => $resource->getName(),
                    'utilization' => $workload['utilization'],
                    'total_allocated_hours' => $workload['workload']['total_hours'],
                    'capacity' => $workload['capacity'],
                    'status' => $resource->getStatus()
                ];
            }
        }

        $avgUtilization = count($report) > 0 ? array_sum(array_column($report, 'utilization')) / count($report) : 0;

        return [
            'period' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d')
            ],
            'resources' => $report,
            'team_average_utilization' => round($avgUtilization, 2)
        ];
    }

    /**
     * Create resource pool
     */
    public function createResourcePool(string $name, array $resourceIds, array $skillRequirements): array
    {
        // In a real implementation, we would create a ResourcePool entity
        // For now, we'll just return the data
        
        $resources = [];
        foreach ($resourceIds as $resourceId) {
            $resource = $this->resourceRepository->find($resourceId);
            if ($resource) {
                $resources[] = $resource;
            }
        }

        return [
            'id' => uniqid(),
            'name' => $name,
            'resources' => array_map(fn($r) => ['id' => $r->getId(), 'name' => $r->getName()], $resources),
            'skills' => $skillRequirements,
            'created_at' => new \DateTime(),
            'member_count' => count($resources)
        ];
    }

    /**
     * Get resource pools
     */
    public function getResourcePools(): array
    {
        // In a real implementation, we would query the ResourcePool entity
        // For now, we'll return an empty array
        
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
        // In a real implementation, we would update the request status in DB
        return true;
    }

    /**
     * Get resource conflicts
     */
    public function getResourceConflicts(\DateTime $from, \DateTime $to): array
    {
        $conflicts = [];
        
        // Find resources that are overbooked (allocated hours > capacity)
        $allResources = $this->resourceRepository->findAll();
        
        foreach ($allResources as $resource) {
            $workload = $this->getResourceWorkload($resource, $from, $to);
            
            if ($workload['utilization'] > 100) {
                $conflicts[] = [
                    'resource_id' => $resource->getId(),
                    'resource_name' => $resource->getName(),
                    'allocated_hours' => $workload['workload']['total_hours'],
                    'capacity' => $workload['capacity'],
                    'overallocation' => $workload['workload']['total_hours'] - $workload['capacity'],
                    'status' => 'overbooked'
                ];
            }
        }

        return [
            'conflicts' => $conflicts,
            'total_conflicts' => count($conflicts)
        ];
    }

    /**
     * Resolve resource conflict
     */
    public function resolveConflict(int $conflictId, string $resolution): bool
    {
        // In a real implementation, we would apply the resolution logic
        return true;
    }

    /**
     * Get resource forecast
     */
    public function getResourceForecast(array $resourceIds, int $weeks = 4): array
    {
        $forecast = [];
        
        for ($i = 0; $i < $weeks; $i++) {
            $startDate = (new \DateTime())->modify("+$i weeks");
            $endDate = (clone $startDate)->modify('+6 days'); // End of week
            
            $weekAllocations = 0;
            $availableCapacity = 0;
            
            foreach ($resourceIds as $resourceId) {
                $resource = $this->resourceRepository->find($resourceId);
                if ($resource) {
                    $workload = $this->getResourceWorkload($resource, $startDate, $endDate);
                    $weekAllocations += $workload['workload']['total_hours'];
                    $availableCapacity += $resource->getCapacityPerWeek();
                }
            }
            
            $forecast[] = [
                'week_start' => $startDate->format('Y-m-d'),
                'week_end' => $endDate->format('Y-m-d'),
                'allocated_hours' => $weekAllocations,
                'available_capacity' => $availableCapacity,
                'utilization_percentage' => $availableCapacity > 0 ? round(($weekAllocations / $availableCapacity) * 100, 2) : 0,
                'shortage' => max(0, $weekAllocations - $availableCapacity)
            ];
        }

        return $forecast;
    }

    /**
     * Get skill matrix
     */
    public function getSkillMatrix(array $resourceIds): array
    {
        $matrix = [];

        foreach ($resourceIds as $resourceId) {
            $resource = $this->resourceRepository->find($resourceId);
            if ($resource) {
                $resourceSkills = $resource->getSkills();
                $skills = [];
                
                foreach ($resourceSkills as $skill) {
                    $skills[$skill->getName()] = $skill->getProficiencyLevel();
                }
                
                $matrix[$resourceId] = [
                    'name' => $resource->getName(),
                    'skills' => $skills
                ];
            }
        }

        return [
            'resources' => $matrix
        ];
    }

    /**
     * Calculate resource cost
     */
    public function calculateResourceCost(Resource $resource, float $hours, ?float $hourlyRate = null): array
    {
        $rate = $hourlyRate ?? (float)$resource->getHourlyRate();
        $baseCost = $hours * $rate;
        $overhead = $baseCost * 0.3; // 30% overhead
        $total = $baseCost + $overhead;

        return [
            'hours' => $hours,
            'hourly_rate' => $rate,
            'base_cost' => $baseCost,
            'overhead' => $overhead,
            'total_cost' => $total
        ];
    }

    /**
     * Get resource efficiency
     */
    public function getResourceEfficiency(Resource $resource, \DateTime $from, \DateTime $to): array
    {
        // In a real implementation, we would calculate efficiency based on completed tasks
        // For now, we'll return mock data
        
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
    public function optimizeAllocation(array $tasks, array $resources): array
    {
        // A simple optimization algorithm that tries to balance the load
        $allocations = [];
        
        usort($tasks, function($a, $b) {
            // Sort by priority (urgent first)
            $priorityOrder = ['urgent' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
            return ($priorityOrder[$b->getPriority()] ?? 2) <=> ($priorityOrder[$a->getPriority()] ?? 2);
        });
        
        foreach ($tasks as $task) {
            // Find the most suitable resource based on skills and availability
            $bestResource = null;
            $minLoad = PHP_INT_MAX;
            
            foreach ($resources as $resource) {
                // Check if resource has required skills
                $hasSkills = true;
                if ($task->getRequiredSkills()->count() > 0) {
                    foreach ($task->getRequiredSkills() as $requiredSkill) {
                        $hasResourceSkill = false;
                        foreach ($resource->getSkills() as $resourceSkill) {
                            if ($resourceSkill->getName() === $requiredSkill->getName()) {
                                $hasResourceSkill = true;
                                break;
                            }
                        }
                        if (!$hasResourceSkill) {
                            $hasSkills = false;
                            break;
                        }
                    }
                }
                
                if ($hasSkills) {
                    // Calculate current load for this resource
                    $currentDate = new \DateTime();
                    $nextMonth = (clone $currentDate)->modify('+1 month');
                    $workload = $this->getResourceWorkload($resource, $currentDate, $nextMonth);
                    $currentLoad = $workload['utilization'];
                    
                    if ($currentLoad < $minLoad) {
                        $minLoad = $currentLoad;
                        $bestResource = $resource;
                    }
                }
            }
            
            if ($bestResource) {
                $allocations[] = [
                    'task_id' => $task->getId(),
                    'resource_id' => $bestResource->getId(),
                    'resource_name' => $bestResource->getName(),
                    'allocation_confirmed' => true
                ];
            }
        }

        return [
            'allocations' => $allocations,
            'optimization_score' => 85,
            'improvements' => [
                'Balanced workload distribution',
                'Improved skill matching'
            ]
        ];
    }

    /**
     * Get all resources
     */
    public function getAllResources(): array
    {
        // Оптимизировано: загружаем со skills
        return $this->resourceRepository->findAllWithSkills();
    }

    /**
     * Create a new resource
     */
    public function createResource(array $data): Resource
    {
        $resource = new Resource();
        $resource->setName($data['name']);
        $resource->setEmail($data['email'] ?? null);
        $resource->setDescription($data['description'] ?? null);
        $resource->setHourlyRate($data['hourly_rate'] ?? '0.00');
        $resource->setCapacityPerWeek($data['capacity_per_week'] ?? 40);
        $resource->setStatus($data['status'] ?? 'available');

        // Add skills if provided
        if (isset($data['skills']) && is_array($data['skills'])) {
            foreach ($data['skills'] as $skillName) {
                $skill = $this->skillRepository->findOneBy(['name' => $skillName]);
                if (!$skill) {
                    $skill = new Skill();
                    $skill->setName($skillName);
                    $skill->setDescription("$skillName skill");
                    $this->entityManager->persist($skill);
                }
                $resource->addSkill($skill);
            }
        }

        $this->entityManager->persist($resource);
        $this->entityManager->flush();

        return $resource;
    }

    /**
     * Update a resource
     */
    public function updateResource(Resource $resource, array $data): Resource
    {
        if (isset($data['name'])) {
            $resource->setName($data['name']);
        }
        if (isset($data['email'])) {
            $resource->setEmail($data['email']);
        }
        if (isset($data['description'])) {
            $resource->setDescription($data['description']);
        }
        if (isset($data['hourly_rate'])) {
            $resource->setHourlyRate($data['hourly_rate']);
        }
        if (isset($data['capacity_per_week'])) {
            $resource->setCapacityPerWeek($data['capacity_per_week']);
        }
        if (isset($data['status'])) {
            $resource->setStatus($data['status']);
        }

        $this->entityManager->flush();

        return $resource;
    }

    /**
     * Delete a resource
     */
    public function deleteResource(Resource $resource): void
    {
        $this->entityManager->remove($resource);
        $this->entityManager->flush();
    }
}
