<?php

namespace App\Command;

use App\Service\AdvancedSecurityService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:advanced-security',
    description: 'Advanced security checks and validation'
)]
class AdvancedSecurityCommand extends Command
{
    private AdvancedSecurityService $securityService;

    public function __construct(AdvancedSecurityService $securityService)
    {
        $this->securityService = $securityService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action to perform: report, validate-input, check-session, test-security')
            ->addOption('input', 'i', InputOption::VALUE_OPTIONAL, 'Input to validate for security')
            ->addOption('context', 'c', InputOption::VALUE_OPTIONAL, 'Context for input validation', 'general');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');

        $io->title('Advanced Security Tool');

        switch ($action) {
            case 'report':
                $this->showSecurityReport($io);
                break;

            case 'validate-input':
                $testInput = $input->getOption('input') ?: '<script>alert("test")</script>';
                $context = $input->getOption('context');
                $this->validateInput($io, $testInput, $context);
                break;

            case 'check-session':
                $this->checkSessionSecurity($io);
                break;

            case 'test-security':
                $this->runSecurityTests($io);
                break;

            default:
                $this->showHelp($io);
                return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function showSecurityReport(SymfonyStyle $io): void
    {
        $io->section('Security Report');

        $report = $this->securityService->getSecurityReport();

        $io->table(
            ['Metric', 'Value'],
            [
                ['Timestamp', $report['timestamp']],
                ['Current User', $report['current_user'] ?? 'Anonymous'],
                ['Suspicious Request', $report['suspicious_request'] ? 'Yes' : 'No'],
                ['Pattern Count', $report['pattern_count']],
            ]
        );

        // Show session security
        $sessionSec = $report['session_security'];
        $io->section('Session Security');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Secure', $sessionSec['secure'] ? 'Yes' : 'No'],
                ['Session ID', $sessionSec['session_id'] ?? 'N/A'],
            ]
        );

        if (!empty($sessionSec['issues'])) {
            $io->section('Security Issues Found');
            foreach ($sessionSec['issues'] as $issue) {
                $io->text("- {$issue['type']}: " . json_encode($issue));
            }
        }
    }

    private function validateInput(SymfonyStyle $io, string $input, string $context): void
    {
        $io->section('Input Validation Test');
        $io->writeln("Validating input: " . substr($input, 0, 100) . (strlen($input) > 100 ? '...' : ''));

        $violations = $this->securityService->validateInput($input, $context);

        if (empty($violations)) {
            $io->success('Input appears to be secure.');
        } else {
            $io->error('Security violations detected:');
            foreach ($violations as $violation) {
                $io->writeln("  - Type: {$violation['type']}, Context: {$violation['context']}");
            }
        }

        // Also test sanitization
        $io->section('Sanitization Test');
        $sanitized = $this->securityService->sanitizeInput($input);
        $io->writeln("Original: " . substr($input, 0, 100) . (strlen($input) > 100 ? '...' : ''));
        $io->writeln("Sanitized: " . substr($sanitized, 0, 100) . (strlen($sanitized) > 100 ? '...' : ''));
        
        if ($sanitized !== $input) {
            $io->warning('Input was modified during sanitization.');
        } else {
            $io->success('Input did not require sanitization.');
        }
    }

    private function checkSessionSecurity(SymfonyStyle $io): void
    {
        $io->section('Session Security Check');

        $result = $this->securityService->checkSessionSecurity();

        $io->table(
            ['Metric', 'Value'],
            [
                ['Secure', $result['secure'] ? 'Yes' : 'No'],
                ['Session ID', $result['session_id'] ?? 'N/A'],
                ['Issue Count', count($result['issues'])],
            ]
        );

        if (!empty($result['issues'])) {
            $io->section('Security Issues');
            foreach ($result['issues'] as $issue) {
                $io->text("- {$issue['type']}: " . json_encode($issue));
            }
        } else {
            $io->success('No security issues detected in session.');
        }
    }

    private function runSecurityTests(SymfonyStyle $io): void
    {
        $io->section('Running Security Tests');

        // Test 1: Suspicious request detection
        $io->writeln('Test 1: Suspicious request detection...');
        $isSuspicious = $this->securityService->isSuspiciousRequest();
        $io->writeln("  Result: " . ($isSuspicious ? 'Suspicious' : 'Normal'));

        // Test 2: Input validation
        $io->writeln('Test 2: Input validation...');
        $dangerousInputs = [
            '<script>alert("xss")</script>',
            "'; DROP TABLE users; --",
            '../../../../etc/passwd',
            'test|rm -rf /'
        ];

        foreach ($dangerousInputs as $idx => $testInput) {
            $violations = $this->securityService->validateInput($testInput, "test_{$idx}");
            $io->writeln("  Input " . ($idx + 1) . ": " . (empty($violations) ? 'Safe' : 'Flagged') . " (" . substr($testInput, 0, 30) . ")");
        }

        // Test 3: Session security
        $io->writeln('Test 3: Session security...');
        $sessionSec = $this->securityService->checkSessionSecurity();
        $io->writeln("  Result: " . ($sessionSec['secure'] ? 'Secure' : 'Issues found'));

        $io->success('Security tests completed.');
    }

    private function showHelp(SymfonyStyle $io): void
    {
        $io->section('Available Actions');
        $io->listing([
            'report - Show comprehensive security report',
            'validate-input - Validate specific input for security issues',
            'check-session - Check current session security',
            'test-security - Run comprehensive security tests'
        ]);

        $io->section('Options');
        $io->listing([
            '--action (-a) - Action to perform',
            '--input (-i) - Input to validate for security',
            '--context (-c) - Context for input validation (default: general)'
        ]);

        $io->section('Examples');
        $io->listing([
            'php bin/console app:advanced-security --action=report',
            'php bin/console app:advanced-security --action=validate-input --input="<script>alert(1)</script>"',
            'php bin/console app:advanced-security --action=check-session',
            'php bin/console app:advanced-security --action=test-security'
        ]);
    }
}
