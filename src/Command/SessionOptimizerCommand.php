<?php

namespace App\Command;

use App\Service\SessionOptimizerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:optimize-session',
    description: 'Optimize and manage session handling'
)]
class SessionOptimizerCommand extends Command
{
    private SessionOptimizerService $sessionOptimizerService;

    public function __construct(SessionOptimizerService $sessionOptimizerService)
    {
        $this->sessionOptimizerService = $sessionOptimizerService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action to perform: optimize-settings, cleanup, security-info, validate-integrity, optimize-storage, garbage-collect', 'optimize-settings')
            ->addOption('inactive-hours', null, InputOption::VALUE_REQUIRED, 'Hours for inactive session cleanup', '24')
            ->addOption('regenerate-id', 'r', InputOption::VALUE_NONE, 'Regenerate session ID')
            ->addOption('destroy-old', null, InputOption::VALUE_NONE, 'Destroy old session when regenerating ID')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: text, json', 'text')
            ->addOption('output-file', 'o', InputOption::VALUE_REQUIRED, 'Output to file instead of console');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');
        $format = $input->getOption('format');
        $outputFile = $input->getOption('output-file');
        $inactiveHours = (int)$input->getOption('inactive-hours');
        $regenerateId = $input->getOption('regenerate-id');
        $destroyOld = $input->getOption('destroy-old');

        $io->title('Session Optimization Tool');

        switch ($action) {
            case 'optimize-settings':
                $io->writeln('Optimizing session settings...');
                
                $this->sessionOptimizerService->optimizeSessionSettings();
                
                $io->success('Session settings optimized based on environment.');
                break;

            case 'cleanup':
                $io->writeln("Cleaning up inactive sessions (older than {$inactiveHours} hours)...");
                
                $cleanedCount = $this->sessionOptimizerService->cleanupInactiveSessions($inactiveHours);
                
                $io->success("Session cleanup completed. Sessions processed: {$cleanedCount}");
                break;

            case 'security-info':
                $io->writeln('Retrieving session security information...');
                
                $securityInfo = $this->sessionOptimizerService->getSessionSecurityInfo();
                
                if ($format === 'json') {
                    $outputData = [
                        'security_info' => $securityInfo,
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    
                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    
                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Security info saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    $this->displaySecurityInfo($io, $securityInfo);
                    
                    if ($outputFile) {
                        $textData = $this->getSecurityInfoTextReport($securityInfo);
                        file_put_contents($outputFile, $textData);
                        $io->success("Security info saved to {$outputFile}");
                    }
                }
                break;

            case 'validate-integrity':
                $io->writeln('Validating session integrity...');
                
                $integrityResult = $this->sessionOptimizerService->validateSessionIntegrity();
                
                if ($format === 'json') {
                    $outputData = [
                        'integrity_result' => $integrityResult,
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    
                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    
                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Integrity report saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    $this->displayIntegrityReport($io, $integrityResult);
                    
                    if ($outputFile) {
                        $textData = $this->getIntegrityTextReport($integrityResult);
                        file_put_contents($outputFile, $textData);
                        $io->success("Integrity report saved to {$outputFile}");
                    }
                }
                break;

            case 'optimize-storage':
                $io->writeln('Optimizing session storage...');
                
                $storageResult = $this->sessionOptimizerService->optimizeSessionStorage();
                
                if ($format === 'json') {
                    $outputData = [
                        'storage_optimization' => $storageResult,
                        'timestamp' => date('Y-m-d H:i:s')
                    ];
                    
                    $jsonData = json_encode($outputData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    
                    if ($outputFile) {
                        file_put_contents($outputFile, $jsonData);
                        $io->success("Storage optimization report saved to {$outputFile}");
                    } else {
                        $output->writeln($jsonData);
                    }
                } else {
                    $this->displayStorageOptimizationReport($io, $storageResult);
                    
                    if ($outputFile) {
                        $textData = $this->getStorageOptimizationTextReport($storageResult);
                        file_put_contents($outputFile, $textData);
                        $io->success("Storage optimization report saved to {$outputFile}");
                    }
                }
                break;

            case 'garbage-collect':
                $io->writeln('Forcing session garbage collection...');
                
                $gcResult = $this->sessionOptimizerService->forceGarbageCollection();
                
                if ($gcResult) {
                    $io->success('Session garbage collection completed successfully.');
                } else {
                    $io->error('Session garbage collection failed.');
                    return 1;
                }
                break;

            default:
                $io->error("Unknown action: {$action}. Use optimize-settings, cleanup, security-info, validate-integrity, optimize-storage, or garbage-collect.");
                return 1;
        }

        // Optionally regenerate session ID if requested
        if ($regenerateId) {
            $io->writeln('Regenerating session ID...');
            
            $regenResult = $this->sessionOptimizerService->regenerateSessionId($destroyOld);
            
            if ($regenResult) {
                $io->success('Session ID regenerated successfully.');
            } else {
                $io->error('Failed to regenerate session ID.');
            }
        }

        return 0;
    }

    private function displaySecurityInfo(SymfonyStyle $io, array $securityInfo): void
    {
        $io->section('Session Security Information');
        
        $io->table(
            ['Property', 'Value'],
            [
                ['Session ID', $securityInfo['session_id'] ?? 'N/A'],
                ['Session Name', $securityInfo['session_name']],
                ['Session Status', $securityInfo['session_status']],
                ['Session Started', $securityInfo['session_started'] ? 'Yes' : 'No'],
                ['Authenticated', $securityInfo['user_info']['authenticated'] ? 'Yes' : 'No'],
                ['User ID', $securityInfo['user_info']['user_id'] ?? 'N/A'],
                ['Username', $securityInfo['user_info']['username'] ?? 'N/A'],
                ['IP Address', $securityInfo['client_info']['ip_address'] ?? 'N/A'],
                ['User Agent', substr($securityInfo['client_info']['user_agent'] ?? '', 0, 50) . (strlen($securityInfo['client_info']['user_agent'] ?? '') > 50 ? '...' : '')],
            ]
        );

        $io->section('Security Configuration');
        $secConfig = $securityInfo['security_config'];
        $io->table(
            ['Setting', 'Value'],
            [
                ['Use Strict Mode', $secConfig['use_strict_mode']],
                ['Cookie HttpOnly', $secConfig['cookie_httponly']],
                ['Cookie Secure', $secConfig['cookie_secure']],
                ['Use Cookies', $secConfig['use_cookies']],
                ['Use Only Cookies', $secConfig['use_only_cookies']],
                ['Entropy Length', $secConfig['entropy_length']],
                ['Hash Function', $secConfig['hash_function']],
            ]
        );

        if (!empty($securityInfo['user_info']['roles'])) {
            $io->section('User Roles');
            $io->listing($securityInfo['user_info']['roles']);
        }
    }

    private function displayIntegrityReport(SymfonyStyle $io, array $integrityResult): void
    {
        $status = $integrityResult['valid'] ? 'VALID' : 'INVALID';
        $statusColor = $integrityResult['valid'] ? 'green' : 'red';
        
        $io->writeln("<fg={$statusColor};options=bold>Session Integrity: {$status}</>");
        
        if (!empty($integrityResult['issues'])) {
            $io->section('Issues Found');
            $io->error('The following issues were detected with the session:');
            $io->listing($integrityResult['issues']);
        } else {
            $io->success('No integrity issues found with the session.');
        }
        
        $io->section('Session Details');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Session Size Estimate', $this->formatBytes($integrityResult['session_size_estimate'])],
                ['Data Keys Count', count($integrityResult['session_data_keys'])],
            ]
        );
        
        if (!empty($integrityResult['session_data_keys'])) {
            $io->section('Session Data Keys');
            $keysList = array_slice($integrityResult['session_data_keys'], 0, 20); // Limit to first 20 keys
            $io->listing($keysList);
            
            if (count($integrityResult['session_data_keys']) > 20) {
                $io->writeln('... and ' . (count($integrityResult['session_data_keys']) - 20) . ' more keys');
            }
        }
    }

    private function displayStorageOptimizationReport(SymfonyStyle $io, array $storageResult): void
    {
        if ($storageResult['success']) {
            $io->success('Session storage optimization completed successfully.');
            
            $io->section('Optimization Results');
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Original Size', $this->formatBytes($storageResult['original_size'])],
                    ['New Size', $this->formatBytes($storageResult['new_size'])],
                    ['Space Saved', $this->formatBytes($storageResult['space_saved'])],
                    ['Keys Removed', count($storageResult['keys_removed'])],
                ]
            );
            
            if (!empty($storageResult['keys_removed'])) {
                $io->section('Keys Removed');
                $io->listing($storageResult['keys_removed']);
            }
        } else {
            $io->error('Session storage optimization failed: ' . $storageResult['message']);
        }
    }

    private function getSecurityInfoTextReport(array $securityInfo): string
    {
        $report = "SESSION SECURITY INFORMATION REPORT\n";
        $report .= str_repeat("=", 50) . "\n\n";

        $report .= "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

        $report .= "SESSION PROPERTIES\n";
        $report .= str_repeat("-", 20) . "\n";
        $report .= "Session ID: " . ($securityInfo['session_id'] ?? 'N/A') . "\n";
        $report .= "Session Name: {$securityInfo['session_name']}\n";
        $report .= "Session Status: {$securityInfo['session_status']}\n";
        $report .= "Session Started: " . ($securityInfo['session_started'] ? 'Yes' : 'No') . "\n";
        $report .= "Authenticated: " . ($securityInfo['user_info']['authenticated'] ? 'Yes' : 'No') . "\n";
        $report .= "User ID: " . ($securityInfo['user_info']['user_id'] ?? 'N/A') . "\n";
        $report .= "Username: " . ($securityInfo['user_info']['username'] ?? 'N/A') . "\n";
        $report .= "IP Address: " . ($securityInfo['client_info']['ip_address'] ?? 'N/A') . "\n";
        $report .= "User Agent: " . substr($securityInfo['client_info']['user_agent'] ?? '', 0, 100) . (strlen($securityInfo['client_info']['user_agent'] ?? '') > 100 ? '...' : '') . "\n\n";

        $report .= "SECURITY CONFIGURATION\n";
        $report .= str_repeat("-", 20) . "\n";
        $secConfig = $securityInfo['security_config'];
        $report .= "Use Strict Mode: {$secConfig['use_strict_mode']}\n";
        $report .= "Cookie HttpOnly: {$secConfig['cookie_httponly']}\n";
        $report .= "Cookie Secure: {$secConfig['cookie_secure']}\n";
        $report .= "Use Cookies: {$secConfig['use_cookies']}\n";
        $report .= "Use Only Cookies: {$secConfig['use_only_cookies']}\n";
        $report .= "Entropy Length: {$secConfig['entropy_length']}\n";
        $report .= "Hash Function: {$secConfig['hash_function']}\n\n";

        if (!empty($securityInfo['user_info']['roles'])) {
            $report .= "USER ROLES\n";
            $report .= str_repeat("-", 20) . "\n";
            foreach ($securityInfo['user_info']['roles'] as $role) {
                $report .= "- {$role}\n";
            }
            $report .= "\n";
        }

        return $report;
    }

    private function getIntegrityTextReport(array $integrityResult): string
    {
        $report = "SESSION INTEGRITY REPORT\n";
        $report .= str_repeat("=", 50) . "\n\n";

        $report .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        $report .= "Integrity Status: " . ($integrityResult['valid'] ? 'VALID' : 'INVALID') . "\n\n";

        if (!empty($integrityResult['issues'])) {
            $report .= "ISSUES FOUND\n";
            $report .= str_repeat("-", 20) . "\n";
            foreach ($integrityResult['issues'] as $issue) {
                $report .= "- {$issue}\n";
            }
            $report .= "\n";
        } else {
            $report .= "No integrity issues found with the session.\n\n";
        }

        $report .= "SESSION DETAILS\n";
        $report .= str_repeat("-", 20) . "\n";
        $report .= "Session Size Estimate: {$this->formatBytes($integrityResult['session_size_estimate'])}\n";
        $report .= "Data Keys Count: " . count($integrityResult['session_data_keys']) . "\n\n";

        if (!empty($integrityResult['session_data_keys'])) {
            $report .= "SESSION DATA KEYS\n";
            $report .= str_repeat("-", 20) . "\n";
            $keysList = array_slice($integrityResult['session_data_keys'], 0, 20); // Limit to first 20 keys
            foreach ($keysList as $key) {
                $report .= "- {$key}\n";
            }
            
            if (count($integrityResult['session_data_keys']) > 20) {
                $report .= "\n... and " . (count($integrityResult['session_data_keys']) - 20) . " more keys\n";
            }
            
            $report .= "\n";
        }

        return $report;
    }

    private function getStorageOptimizationTextReport(array $storageResult): string
    {
        $report = "SESSION STORAGE OPTIMIZATION REPORT\n";
        $report .= str_repeat("=", 50) . "\n\n";

        $report .= "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

        if ($storageResult['success']) {
            $report .= "STATUS: SUCCESSFUL\n\n";
            
            $report .= "OPTIMIZATION RESULTS\n";
            $report .= str_repeat("-", 20) . "\n";
            $report .= "Original Size: {$this->formatBytes($storageResult['original_size'])}\n";
            $report .= "New Size: {$this->formatBytes($storageResult['new_size'])}\n";
            $report .= "Space Saved: {$this->formatBytes($storageResult['space_saved'])}\n";
            $report .= "Keys Removed: " . count($storageResult['keys_removed']) . "\n\n";
            
            if (!empty($storageResult['keys_removed'])) {
                $report .= "KEYS REMOVED\n";
                $report .= str_repeat("-", 20) . "\n";
                foreach ($storageResult['keys_removed'] as $key) {
                    $report .= "- {$key}\n";
                }
                $report .= "\n";
            }
        } else {
            $report .= "STATUS: FAILED\n";
            $report .= "Message: {$storageResult['message']}\n\n";
        }

        return $report;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}