<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Service for automatic backup operations
 */
class BackupService
{
    private string $projectDir;
    private string $backupDir;
    private LoggerInterface $logger;
    private Filesystem $filesystem;

    public function __construct(
        string $projectDir,
        LoggerInterface $logger,
        Filesystem $filesystem
    ) {
        $this->projectDir = $projectDir;
        $this->logger = $logger;
        $this->filesystem = $filesystem;
        $this->backupDir = $this->projectDir . '/var/backups';
        
        // Ensure backup directory exists
        if (!$this->filesystem->exists($this->backupDir)) {
            $this->filesystem->mkdir($this->backupDir, 0755);
        }
    }

    /**
     * Create a backup of the application
     */
    public function createBackup(array $options = []): array
    {
        $startTime = microtime(true);
        $this->logger->info('Starting backup process');
        
        $excludePatterns = $options['exclude'] ?? [
            'var/cache/*',
            'var/log/*',
            'var/backups/*',
            '.git/*',
            'node_modules/*',
            'vendor/.composer/*'
        ];
        
        $includeDatabase = $options['include_database'] ?? true;
        $backupType = $options['type'] ?? 'full'; // full, incremental, differential
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupName = "backup_{$timestamp}";
        $backupPath = $this->backupDir . '/' . $backupName;
        
        try {
            // Create backup directory
            $this->filesystem->mkdir($backupPath, 0755);
            
            // Backup application files
            $fileBackupResult = $this->backupFiles($backupPath, $excludePatterns, $backupType);
            
            // Backup database if requested
            $dbBackupResult = [];
            if ($includeDatabase) {
                $dbBackupResult = $this->backupDatabase($backupPath);
            }
            
            // Create backup manifest
            $manifest = [
                'timestamp' => $timestamp,
                'type' => $backupType,
                'includes_database' => $includeDatabase,
                'file_backup' => $fileBackupResult,
                'database_backup' => $dbBackupResult,
                'duration' => round(microtime(true) - $startTime, 2),
                'size' => $this->getDirectorySize($backupPath)
            ];
            
            // Save manifest
            file_put_contents($backupPath . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
            
            $this->logger->info('Backup completed successfully', [
                'path' => $backupPath,
                'duration' => $manifest['duration'],
                'size' => $manifest['size']
            ]);
            
            return [
                'success' => true,
                'path' => $backupPath,
                'manifest' => $manifest
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Backup failed', ['error' => $e->getMessage()]);
            
            // Clean up partial backup
            if ($this->filesystem->exists($backupPath)) {
                $this->filesystem->remove($backupPath);
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Backup application files
     */
    private function backupFiles(string $backupPath, array $excludePatterns, string $backupType): array
    {
        $filesCopied = 0;
        $directoriesCreated = 0;
        $totalSize = 0;
        
        $finder = new Finder();
        $finder->files()
            ->in($this->projectDir)
            ->exclude(['var', 'node_modules', '.git'])
            ->notName('*.log')
            ->notName('*~');
        
        $filesPath = $backupPath . '/files';
        $this->filesystem->mkdir($filesPath, 0755);
        
        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            
            // Skip excluded patterns
            $shouldExclude = false;
            foreach ($excludePatterns as $pattern) {
                if (fnmatch($pattern, $relativePath)) {
                    $shouldExclude = true;
                    break;
                }
            }
            
            if ($shouldExclude) {
                continue;
            }
            
            $destPath = $filesPath . '/' . $relativePath;
            $destDir = dirname($destPath);
            
            if (!$this->filesystem->exists($destDir)) {
                $this->filesystem->mkdir($destDir, 0755);
                $directoriesCreated++;
            }
            
            $this->filesystem->copy($file->getPathname(), $destPath);
            $filesCopied++;
            $totalSize += $file->getSize();
        }
        
        return [
            'files_copied' => $filesCopied,
            'directories_created' => $directoriesCreated,
            'total_size' => $totalSize,
            'exclude_patterns' => $excludePatterns
        ];
    }

    /**
     * Backup database
     */
    private function backupDatabase(string $backupPath): array
    {
        $dbPath = $backupPath . '/database';
        $this->filesystem->mkdir($dbPath, 0755);
        
        // For SQLite database
        $dbSource = $this->projectDir . '/var/data.db';
        $dbDest = $dbPath . '/data_' . date('Y-m-d_H-i-s') . '.db';
        
        if ($this->filesystem->exists($dbSource)) {
            $this->filesystem->copy($dbSource, $dbDest);
            
            return [
                'source' => $dbSource,
                'destination' => $dbDest,
                'size' => filesize($dbSource),
                'success' => true
            ];
        }
        
        // For other databases, you would typically use mysqldump, pg_dump, etc.
        // This implementation assumes SQLite based on the project structure
        
        return [
            'success' => false,
            'message' => 'Database backup skipped - no SQLite database found'
        ];
    }

    /**
     * Restore from backup
     */
    public function restoreFromBackup(string $backupPath, array $options = []): array
    {
        $this->logger->info('Starting restore process', ['backup_path' => $backupPath]);
        
        if (!$this->filesystem->exists($backupPath)) {
            return [
                'success' => false,
                'error' => 'Backup path does not exist'
            ];
        }
        
        try {
            // Read manifest
            $manifestPath = $backupPath . '/manifest.json';
            if (!$this->filesystem->exists($manifestPath)) {
                return [
                    'success' => false,
                    'error' => 'Manifest file not found in backup'
                ];
            }
            
            $manifest = json_decode(file_get_contents($manifestPath), true);
            
            // Determine restore options
            $restoreFiles = $options['restore_files'] ?? true;
            $restoreDatabase = $options['restore_database'] ?? true;
            
            // Restore files
            if ($restoreFiles && $this->filesystem->exists($backupPath . '/files')) {
                $this->restoreFiles($backupPath . '/files', $options);
            }
            
            // Restore database
            if ($restoreDatabase && $this->filesystem->exists($backupPath . '/database')) {
                $this->restoreDatabase($backupPath . '/database', $options);
            }
            
            $this->logger->info('Restore completed successfully');
            
            return [
                'success' => true,
                'manifest' => $manifest
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Restore failed', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Restore files
     */
    private function restoreFiles(string $sourcePath, array $options): void
    {
        $targetPath = $this->projectDir;
        
        // Copy files back to project directory
        $finder = new Finder();
        $finder->files()->in($sourcePath);
        
        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $destPath = $targetPath . '/' . $relativePath;
            $destDir = dirname($destPath);
            
            if (!$this->filesystem->exists($destDir)) {
                $this->filesystem->mkdir($destDir, 0755);
            }
            
            $this->filesystem->copy($file->getPathname(), $destPath);
        }
    }

    /**
     * Restore database
     */
    private function restoreDatabase(string $sourcePath, array $options): void
    {
        $finder = new Finder();
        $finder->files()->in($sourcePath)->name('*.db');
        
        foreach ($finder as $dbFile) {
            $targetDbPath = $this->projectDir . '/var/data.db';
            
            // Make backup of current DB if it exists
            if ($this->filesystem->exists($targetDbPath)) {
                $backupDbPath = $this->projectDir . '/var/data.db.backup_' . date('Y-m-d_H-i-s');
                $this->filesystem->copy($targetDbPath, $backupDbPath);
            }
            
            $this->filesystem->copy($dbFile->getPathname(), $targetDbPath);
        }
    }

    /**
     * Get list of available backups
     */
    public function getBackups(): array
    {
        $finder = new Finder();
        $finder->directories()->in($this->backupDir)->sortByName();
        
        $backups = [];
        
        foreach ($finder as $dir) {
            $manifestPath = $dir->getPathname() . '/manifest.json';
            
            if ($this->filesystem->exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true);
                $backups[] = [
                    'name' => $dir->getFilename(),
                    'path' => $dir->getPathname(),
                    'manifest' => $manifest,
                    'size' => $this->getDirectorySize($dir->getPathname())
                ];
            }
        }
        
        return $backups;
    }

    /**
     * Delete old backups
     */
    public function cleanupOldBackups(int $keepDays = 7): array
    {
        $finder = new Finder();
        $finder->directories()->in($this->backupDir)->sortByName();
        
        $deletedCount = 0;
        $keptCount = 0;
        
        $cutoffDate = new \DateTime("-{$keepDays} days");
        
        foreach ($finder as $dir) {
            $dirName = $dir->getFilename();
            
            // Extract timestamp from backup name (backup_YYYY-MM-DD_HH-MM-SS)
            if (preg_match('/backup_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})/', $dirName, $matches)) {
                $backupDate = \DateTime::createFromFormat('Y-m-d_H-i-s', $matches[1]);
                
                if ($backupDate && $backupDate < $cutoffDate) {
                    $this->filesystem->remove($dir->getPathname());
                    $deletedCount++;
                } else {
                    $keptCount++;
                }
            } else {
                $keptCount++; // Keep backups with unrecognized format
            }
        }
        
        $this->logger->info('Backup cleanup completed', [
            'deleted' => $deletedCount,
            'kept' => $keptCount,
            'cutoff_days' => $keepDays
        ]);
        
        return [
            'deleted' => $deletedCount,
            'kept' => $keptCount
        ];
    }

    /**
     * Get directory size
     */
    private function getDirectorySize(string $path): int
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
}
