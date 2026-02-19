<?php

namespace App\Command;

use App\Entity\User;
use App\Service\TaskExportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:task-export',
    description: 'Export tasks to various formats',
)]
class TaskExportCommand extends Command
{
    private TaskExportService $taskExportService;

    public function __construct(TaskExportService $taskExportService)
    {
        $this->taskExportService = $taskExportService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Export format: csv, json, xml, pdf', 'json')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by status')
            ->addOption('priority', 'p', InputOption::VALUE_REQUIRED, 'Filter by priority')
            ->addOption('category', 'c', InputOption::VALUE_REQUIRED, 'Filter by category')
            ->addOption('search', 's', InputOption::VALUE_REQUIRED, 'Search in title/description')
            ->addOption('overdue', 'o', InputOption::VALUE_NONE, 'Show only overdue tasks')
            ->addOption('completed', null, InputOption::VALUE_NONE, 'Show only completed tasks')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit number of tasks', 100)
            ->addOption('output', 'out', InputOption::VALUE_REQUIRED, 'Output file path')
            ->addOption('stats', null, InputOption::VALUE_NONE, 'Show export statistics only');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $format = $input->getOption('format');
        $outputFile = $input->getOption('output');
        $showStats = $input->getOption('stats');

        $io->title('Task Export');

        // For demo purposes, we'll use a mock user
        $mockUser = $this->getMockUser();

        // Show available formats
        if ($showStats) {
            return $this->showStatistics($io, $mockUser);
        }

        // Validate format
        $availableFormats = $this->taskExportService->getAvailableFormats();
        if (!isset($availableFormats[$format])) {
            $io->error("Invalid format: {$format}");
            $io->writeln('Available formats:');
            foreach ($availableFormats as $key => $info) {
                $io->writeln("  - {$key}: {$info['description']}");
            }

            return Command::FAILURE;
        }

        // Build filters
        $filters = [
            'status' => $input->getOption('status'),
            'priority' => $input->getOption('priority'),
            'category' => $input->getOption('category'),
            'search' => $input->getOption('search'),
            'overdue' => $input->getOption('overdue'),
            'completed' => $input->getOption('completed'),
            'limit' => $input->getOption('limit'),
        ];

        // Remove null/empty values
        $filters = array_filter($filters, function ($value) {
            return $value !== null && $value !== false && $value !== '';
        });

        $io->section('Export Configuration');
        $io->table(
            ['Setting', 'Value'],
            [
                ['Format', $format],
                ['Filters Applied', \count($filters) > 0 ? json_encode($filters) : 'None'],
                ['Output File', $outputFile ?? 'stdout'],
            ],
        );

        try {
            // Perform export
            $content = $this->performExport($format, $mockUser, $filters);

            // Output result
            if ($outputFile) {
                file_put_contents($outputFile, $content);
                $io->success("Export completed successfully to: {$outputFile}");
                $io->writeln('File size: ' . number_format(\strlen($content)) . ' bytes');
            } else {
                $io->section('Exported Data');
                $io->writeln($content);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Export failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function performExport(string $format, $user, array $filters): string
    {
        switch ($format) {
            case 'csv':
                return $this->taskExportService->exportToCsv($user, $filters);
            case 'json':
                return $this->taskExportService->exportToJson($user, $filters);
            case 'xml':
                return $this->taskExportService->exportToXml($user, $filters);
            case 'pdf':
                return $this->taskExportService->exportToPdf($user, $filters);
            default:
                throw new \InvalidArgumentException("Unsupported format: {$format}");
        }
    }

    private function showStatistics(SymfonyStyle $io, $user): int
    {
        $stats = $this->taskExportService->getExportStatistics($user);
        $formats = $this->taskExportService->getAvailableFormats();

        $io->section('Export Statistics');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Total Tasks', $stats['total_tasks']],
                ['Completed Tasks', $stats['completed_tasks']],
                ['Pending Tasks', $stats['pending_tasks']],
                ['Overdue Tasks', $stats['overdue_tasks']],
                ['Categories Used', $stats['categories_used']],
                ['Tags Used', $stats['tags_used']],
                ['Completion Rate', $stats['completion_rate'] . '%'],
            ],
        );

        $io->section('Available Export Formats');
        $formatTable = [];
        foreach ($formats as $key => $info) {
            $formatTable[] = [
                $key,
                $info['name'],
                $info['description'],
                $info['extension'],
                $info['mime_type'],
            ];
        }

        $io->table(
            ['Key', 'Name', 'Description', 'Extension', 'MIME Type'],
            $formatTable,
        );

        return Command::SUCCESS;
    }

    private function getMockUser(): User
    {
        // Create a mock user object for demonstration
        $user = new User();
        // Use reflection to set properties
        $reflection = new \ReflectionClass($user);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, 1);

        $usernameProperty = $reflection->getProperty('username');
        $usernameProperty->setAccessible(true);
        $usernameProperty->setValue($user, 'demo_user');

        return $user;
    }
}
