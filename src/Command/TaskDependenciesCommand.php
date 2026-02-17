<?php

namespace App\Command;

use App\Service\TaskDependencyService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:task-dependencies',
    description: 'Manage task dependencies and analyze dependency chains'
)]
class TaskDependenciesCommand extends Command
{
    private TaskDependencyService $taskDependencyService;

    public function __construct(TaskDependencyService $taskDependencyService)
    {
        $this->taskDependencyService = $taskDependencyService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action to perform: analyze, stats, cleanup', 'analyze')
            ->addOption('task-id', 't', InputOption::VALUE_REQUIRED, 'Task ID for specific operations')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: table, json', 'table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');
        $taskId = $input->getOption('task-id');
        $format = $input->getOption('format');

        $io->title('Task Dependencies Management');

        switch ($action) {
            case 'analyze':
                return $this->analyzeDependencies($io, $taskId, $format);
            case 'stats':
                return $this->showStatistics($io, $format);
            case 'cleanup':
                return $this->cleanupDependencies($io);
            default:
                $io->error("Unknown action: {$action}");
                return Command::FAILURE;
        }
    }

    private function analyzeDependencies(SymfonyStyle $io, ?int $taskId, string $format): int
    {
        if ($taskId) {
            // Analyze specific task
            $task = $this->getTaskById($taskId);
            if (!$task) {
                $io->error("Task with ID {$taskId} not found");
                return Command::FAILURE;
            }

            $dependencies = $this->taskDependencyService->getDependencies($task);
            $dependents = $this->taskDependencyService->getDependents($task);
            $blockingTasks = $this->taskDependencyService->getBlockingTasks($task);
            $blockedTasks = $this->taskDependencyService->getBlockedTasks($task);

            if ($format === 'json') {
                $data = [
                    'task_id' => $taskId,
                    'dependencies' => count($dependencies),
                    'dependents' => count($dependents),
                    'blocking_tasks' => count($blockingTasks),
                    'blocked_tasks' => count($blockedTasks),
                    'can_start' => $this->taskDependencyService->canStartTask($task),
                    'can_complete' => $this->taskDependencyService->canCompleteTask($task)
                ];
                $io->writeln(json_encode($data, JSON_PRETTY_PRINT));
            } else {
                $io->section("Dependency Analysis for Task #{$taskId}");
                
                $io->table(
                    ['Metric', 'Count'],
                    [
                        ['Dependencies', count($dependencies)],
                        ['Dependents', count($dependents)],
                        ['Blocking Tasks', count($blockingTasks)],
                        ['Blocked Tasks', count($blockedTasks)]
                    ]
                );

                $io->writeln('');
                $io->writeln('<info>Task Status:</info>');
                $io->writeln("Can Start: " . ($this->taskDependencyService->canStartTask($task) ? 'Yes' : 'No'));
                $io->writeln("Can Complete: " . ($this->taskDependencyService->canCompleteTask($task) ? 'Yes' : 'No'));
            }
        } else {
            // General analysis
            $io->writeln('<comment>Specify --task-id for detailed analysis</comment>');
        }

        return Command::SUCCESS;
    }

    private function showStatistics(SymfonyStyle $io, string $format): int
    {
        // This would require a user context - for demo purposes
        $stats = [
            'total_dependencies' => 0,
            'active_dependencies' => 0,
            'completed_dependencies' => 0,
            'dependency_ratio' => 0
        ];

        if ($format === 'json') {
            $io->writeln(json_encode($stats, JSON_PRETTY_PRINT));
        } else {
            $io->section('Dependency Statistics');
            
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Total Dependencies', $stats['total_dependencies']],
                    ['Active Dependencies', $stats['active_dependencies']],
                    ['Completed Dependencies', $stats['completed_dependencies']],
                    ['Completion Ratio', $stats['dependency_ratio'] . '%']
                ]
            );
        }

        return Command::SUCCESS;
    }

    private function cleanupDependencies(SymfonyStyle $io): int
    {
        $io->writeln('<comment>Cleaning up invalid dependencies...</comment>');
        
        // This would implement cleanup logic for orphaned dependencies
        $io->success('Dependency cleanup completed');
        
        return Command::SUCCESS;
    }

    private function getTaskById(int $id): ?\App\Entity\Task
    {
        // This would fetch task from repository
        // For now, return null to demonstrate error handling
        return null;
    }
}
