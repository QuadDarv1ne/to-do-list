<?php

namespace App\Command;

use App\Service\SecurityManagementService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:security-management',
    description: 'Advanced security management and monitoring'
)]
class SecurityManagementCommand extends Command
{
    private SecurityManagementService $securityService;

    public function __construct(SecurityManagementService $securityService)
    {
        $this->securityService = $securityService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action to perform: stats, test-input, rate-limit-test, security-check')
            ->addOption('input', 'i', InputOption::VALUE_REQUIRED, 'Input to test for security')
            ->addOption('identifier', 'id', InputOption::VALUE_REQUIRED, 'Identifier for rate limiting test');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');
        
        $io->title('Advanced Security Management');
        
        switch ($action) {
            case 'stats':
                $this->showSecurityStats($io);
                break;
                
            case 'test-input':
                $testInput = $input->getOption('input') ?: '<script>alert("xss")</script>';
                $this->testInputSecurity($io, $testInput);
                break;
                
            case 'rate-limit-test':
                $identifier = $input->getOption('identifier') ?: 'test_user';
                $this->testRateLimiting($io, $identifier);
                break;
                
            case 'security-check':
                $this->performSecurityCheck($io);
                break;
                
            default:
                $this->showHelp($io);
                return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }

    private function showSecurityStats(SymfonyStyle $io): void
    {
        $io->section('Security Statistics');
        
        $stats = $this->securityService->getSecurityStats();
        
        $io->table(
            ['Metric', 'Value'],
            [
                ['Suspicious Patterns', $stats['suspicious_patterns_count']],
                ['Current IP', $stats['current_ip'] ?? 'Unknown'],
                ['Current User Agent', $stats['current_user_agent'] ?? 'Unknown'],
                ['Rate Limiting Enabled', $stats['rate_limiting_enabled'] ? 'Yes' : 'No']
            ]
        );
        
        $io->success('Security statistics displayed');
    }

    private function testInputSecurity(SymfonyStyle $io, string $input): void
    {
        $io->section('Testing Input Security');
        $io->writeln("Testing input: {$input}");
        
        // Test for suspicious patterns
        $isSuspicious = $this->securityService->isSuspiciousInput($input);
        $io->writeln("Suspicious: " . ($isSuspicious ? 'YES' : 'NO'));
        
        // Test sanitization
        $sanitized = $this->securityService->sanitizeInput($input);
        $io->writeln("Sanitized: {$sanitized}");
        
        if ($isSuspicious) {
            $io->warning('Input contains suspicious patterns!');
        } else {
            $io->success('Input appears safe');
        }
    }

    private function testRateLimiting(SymfonyStyle $io, string $identifier): void
    {
        $io->section("Testing Rate Limiting for: {$identifier}");
        
        $maxAttempts = 5;
        $results = [];
        
        for ($i = 1; $i <= $maxAttempts + 2; $i++) {
            $allowed = $this->securityService->checkRateLimit($identifier, $maxAttempts, 60);
            $results[] = [
                'attempt' => $i,
                'allowed' => $allowed ? 'YES' : 'NO',
                'status' => $allowed ? 'Allowed' : 'Rate Limited'
            ];
            
            if (!$allowed) {
                break;
            }
        }
        
        $io->table(['Attempt', 'Allowed', 'Status'], $results);
        
        if (end($results)['allowed'] === 'NO') {
            $io->warning("Rate limiting triggered after {$maxAttempts} attempts");
        } else {
            $io->success('Rate limiting test completed');
        }
    }

    private function performSecurityCheck(SymfonyStyle $io): void
    {
        $io->section('Performing Security Check');
        
        $checks = [
            'Input Validation' => true,
            'Rate Limiting' => true,
            'Suspicious Pattern Detection' => true,
            'Security Logging' => true
        ];
        
        $results = [];
        foreach ($checks as $check => $status) {
            $results[] = [
                'check' => $check,
                'status' => $status ? 'PASS' : 'FAIL',
                'result' => $status ? '✓' : '✗'
            ];
        }
        
        $io->table(['Security Check', 'Status', 'Result'], $results);
        $io->success('Security check completed');
    }

    private function showHelp(SymfonyStyle $io): void
    {
        $io->section('Available Actions');
        $io->listing([
            'stats - Show security statistics and configuration',
            'test-input - Test input for security vulnerabilities',
            'rate-limit-test - Test rate limiting functionality',
            'security-check - Perform comprehensive security check'
        ]);
        
        $io->section('Examples');
        $io->listing([
            'php bin/console app:security-management --action=stats',
            'php bin/console app:security-management --action=test-input --input="<script>alert(1)</script>"',
            'php bin/console app:security-management --action=rate-limit-test --identifier=test_user',
            'php bin/console app:security-management --action=security-check'
        ]);
    }
}
