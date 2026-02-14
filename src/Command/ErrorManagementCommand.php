<?php

namespace App\Command;

use App\Service\AdvancedErrorHandlingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:error-management',
    description: 'Advanced error handling and log management'
)]
class ErrorManagementCommand extends Command
{
    private AdvancedErrorHandlingService $errorHandlingService;

    public function __construct(AdvancedErrorHandlingService $errorHandlingService)
    {
        $this->errorHandlingService = $errorHandlingService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action to perform: stats, test-error, simulate-security, performance-warning')
            ->addOption('error-type', 'type', InputOption::VALUE_REQUIRED, 'Type of error to simulate')
            ->addOption('execution-time', 'time', InputOption::VALUE_REQUIRED, 'Execution time for performance warning (ms)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');
        
        $io->title('Advanced Error Management');
        
        switch ($action) {
            case 'stats':
                $this->showErrorStatistics($io);
                break;
                
            case 'test-error':
                $errorType = $input->getOption('error-type') ?: 'general';
                $this->simulateError($io, $errorType);
                break;
                
            case 'simulate-security':
                $this->simulateSecurityEvent($io);
                break;
                
            case 'performance-warning':
                $executionTime = (float)($input->getOption('execution-time') ?: 1500);
                $this->simulatePerformanceWarning($io, $executionTime);
                break;
                
            default:
                $this->showHelp($io);
                return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }

    private function showErrorStatistics(SymfonyStyle $io): void
    {
        $io->section('Error Statistics');
        
        $stats = $this->errorHandlingService->getErrorStatistics();
        
        $io->table(
            ['Error Type', 'Count'],
            [
                ['Total Errors', $stats['total_errors']],
                ['Critical Errors', $stats['critical_errors']],
                ['Warning Errors', $stats['warning_errors']],
                ['HTTP Errors', $stats['http_errors']],
                ['Security Events', $stats['security_events']],
                ['Performance Warnings', $stats['performance_warnings']]
            ]
        );
        
        $io->success('Error statistics displayed');
    }

    private function simulateError(SymfonyStyle $io, string $errorType): void
    {
        $io->section("Simulating {$errorType} error");
        
        try {
            switch ($errorType) {
                case 'database':
                    throw new \Exception('Database connection failed');
                case 'security':
                    throw new \Exception('Authentication failed');
                case 'http':
                    throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Page not found');
                default:
                    throw new \Exception('General application error occurred');
            }
        } catch (\Exception $e) {
            $this->errorHandlingService->handleException($e);
            $io->success("Error simulated and handled: " . $e->getMessage());
        }
    }

    private function simulateSecurityEvent(SymfonyStyle $io): void
    {
        $io->section('Simulating security events');
        
        // Simulate various security events
        $events = [
            ['type' => 'failed_login', 'details' => ['username' => 'testuser', 'attempts' => 3]],
            ['type' => 'suspicious_activity', 'details' => ['ip' => '192.168.1.100', 'activity' => 'multiple failed requests']],
            ['type' => 'brute_force_attempt', 'details' => ['ip' => '10.0.0.1', 'attempts_per_minute' => 50]]
        ];
        
        foreach ($events as $event) {
            $this->errorHandlingService->logSecurityEvent($event['type'], $event['details']);
            $io->writeln("Logged security event: {$event['type']}");
        }
        
        $io->success('Security events simulated');
    }

    private function simulatePerformanceWarning(SymfonyStyle $io, float $executionTime): void
    {
        $io->section("Simulating performance warning ({$executionTime}ms)");
        
        $this->errorHandlingService->logPerformanceWarning(
            'test_operation',
            $executionTime,
            ['test_operation' => true, 'simulated' => true]
        );
        
        $io->success("Performance warning logged for {$executionTime}ms execution time");
    }

    private function showHelp(SymfonyStyle $io): void
    {
        $io->section('Available Actions');
        $io->listing([
            'stats - Show error statistics and counts',
            'test-error - Simulate and handle different types of errors',
            'simulate-security - Generate security event logs',
            'performance-warning - Create performance warning with specified execution time'
        ]);
        
        $io->section('Examples');
        $io->listing([
            'php bin/console app:error-management --action=stats',
            'php bin/console app:error-management --action=test-error --error-type=database',
            'php bin/console app:error-management --action=simulate-security',
            'php bin/console app:error-management --action=performance-warning --execution-time=2000'
        ]);
        
        $io->section('Error Types for test-error');
        $io->listing([
            'general - General application error',
            'database - Database connection error',
            'security - Authentication error',
            'http - HTTP not found error'
        ]);
    }
}