<?php

namespace App\Command;

use App\Entity\User;
use App\Service\TaskFilterService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:task-filter',
    description: 'Advanced task filtering and search capabilities'
)]
class TaskFilterCommand extends Command
{
    private TaskFilterService $taskFilterService;

    public function __construct(TaskFilterService $taskFilterService)
    {
        $this->taskFilterService = $taskFilterService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action to perform: search, stats, presets, suggestions', 'stats')
            ->addOption('search', 's', InputOption::VALUE_REQUIRED, 'Search query')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by status')
            ->addOption('priority', 'p', InputOption::VALUE_REQUIRED, 'Filter by priority')
            ->addOption('category', 'c', InputOption::VALUE_REQUIRED, 'Filter by category')
            ->addOption('overdue', 'o', InputOption::VALUE_NONE, 'Show only overdue tasks')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit results', 50)
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: table, json', 'table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');
        $format = $input->getOption('format');

        $io->title('Advanced Task Filtering');

        // For demo purposes, we'll use a mock user
        $mockUser = $this->getMockUser();

        switch ($action) {
            case 'search':
                return $this->performSearch($io, $input, $mockUser, $format);
            case 'stats':
                return $this->showStatistics($io, $mockUser, $format);
            case 'presets':
                return $this->showPresets($io, $format);
            case 'suggestions':
                return $this->showSuggestions($io, $mockUser, $format);
            default:
                $io->error("Unknown action: {$action}");
                return Command::FAILURE;
        }
    }

    private function performSearch(SymfonyStyle $io, InputInterface $input, $user, string $format): int
    {
        $criteria = [
            'search' => $input->getOption('search'),
            'status' => $input->getOption('status'),
            'priority' => $input->getOption('priority'),
            'category' => $input->getOption('category'),
            'overdue' => $input->getOption('overdue'),
            'limit' => $input->getOption('limit')
        ];

        // Remove null values
        $criteria = array_filter($criteria, function($value) {
            return $value !== null && $value !== false;
        });

        $io->section('Search Criteria');
        $io->writeln(json_encode($criteria, JSON_PRETTY_PRINT));

        // In real implementation, this would search actual tasks
        $results = [
            ['id' => 1, 'title' => 'Sample Task 1', 'status' => 'pending', 'priority' => 'high'],
            ['id' => 2, 'title' => 'Sample Task 2', 'status' => 'completed', 'priority' => 'medium']
        ];

        if ($format === 'json') {
            $io->writeln(json_encode($results, JSON_PRETTY_PRINT));
        } else {
            $io->section('Search Results');
            $io->table(
                ['ID', 'Title', 'Status', 'Priority'],
                array_map(function($task) {
                    return [$task['id'], $task['title'], $task['status'], $task['priority']];
                }, $results)
            );
        }

        return Command::SUCCESS;
    }

    private function showStatistics(SymfonyStyle $io, $user, string $format): int
    {
        $stats = $this->taskFilterService->getTaskStatistics($user);

        if ($format === 'json') {
            $io->writeln(json_encode($stats, JSON_PRETTY_PRINT));
        } else {
            $io->section('Task Statistics');
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Total Tasks', $stats['total']],
                    ['Completed', $stats['completed']],
                    ['Pending', $stats['pending']],
                    ['Overdue', $stats['overdue']],
                    ['High Priority', $stats['high_priority']],
                    ['Completion Rate', $stats['completion_rate'] . '%']
                ]
            );
        }

        return Command::SUCCESS;
    }

    private function showPresets(SymfonyStyle $io, string $format): int
    {
        $presets = $this->taskFilterService->getQuickFilters();

        if ($format === 'json') {
            $io->writeln(json_encode($presets, JSON_PRETTY_PRINT));
        } else {
            $io->section('Quick Filter Presets');
            $tableData = [];
            foreach ($presets as $key => $preset) {
                $tableData[] = [$key, $preset['name'], json_encode($preset['criteria'])];
            }
            $io->table(['Key', 'Name', 'Criteria'], $tableData);
        }

        return Command::SUCCESS;
    }

    private function showSuggestions(SymfonyStyle $io, $user, string $format): int
    {
        $suggestions = $this->taskFilterService->getFilterSuggestions($user);

        if ($format === 'json') {
            $io->writeln(json_encode($suggestions, JSON_PRETTY_PRINT));
        } else {
            $io->section('Filter Suggestions');
            
            foreach ($suggestions as $type => $values) {
                $io->writeln("<info>{$type}:</info>");
                if (!empty($values)) {
                    foreach ($values as $value) {
                        $io->writeln("  - {$value}");
                    }
                } else {
                    $io->writeln("  <comment>No suggestions available</comment>");
                }
                $io->writeln('');
            }
        }

        return Command::SUCCESS;
    }

    private function getMockUser(): User
    {
        // Create a mock user object for demonstration
        $user = new User();
        // Use reflection to set ID since User entity doesn't have setId method
        $reflection = new \ReflectionClass($user);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, 1);
        return $user;
    }
}
