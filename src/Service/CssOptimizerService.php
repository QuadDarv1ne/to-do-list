<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Psr\Log\LoggerInterface;

class CssOptimizerService
{
    private Filesystem $filesystem;
    private LoggerInterface $logger;
    private string $projectDir;

    public function __construct(
        Filesystem $filesystem,
        LoggerInterface $logger,
        string $projectDir
    ) {
        $this->filesystem = $filesystem;
        $this->logger = $logger;
        $this->projectDir = $projectDir;
    }

    /**
     * Optimize and combine CSS files
     */
    public function optimizeAndCombine(): array
    {
        $cssDir = $this->projectDir . '/public/css';
        $outputFile = $cssDir . '/combined.min.css';
        
        if (!is_dir($cssDir)) {
            return ['success' => false, 'error' => 'CSS directory not found'];
        }

        // Priority order for CSS files
        $priorityFiles = [
            'design-system-tokens.css',
            'critical.css',
            'optimized-core.css',
            'components.css',
            'layout-system.css',
            'navigation-system.css',
            'form-system.css',
            'button-system.css',
            'card-system.css',
            'modal-system.css',
        ];

        $combinedCss = "/* Combined and optimized CSS - Generated: " . date('Y-m-d H:i:s') . " */\n\n";
        $processedFiles = [];
        $totalOriginalSize = 0;

        // Process priority files first
        foreach ($priorityFiles as $file) {
            $filePath = $cssDir . '/' . $file;
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                $totalOriginalSize += strlen($content);
                $optimized = $this->minifyCss($content);
                $combinedCss .= "/* From: {$file} */\n{$optimized}\n\n";
                $processedFiles[] = $file;
            }
        }

        // Process remaining CSS files
        $allFiles = glob($cssDir . '/*.css');
        foreach ($allFiles as $filePath) {
            $file = basename($filePath);
            
            // Skip already processed, combined, and minified files
            if (in_array($file, $processedFiles) || 
                str_contains($file, 'combined') || 
                str_contains($file, '.min.')) {
                continue;
            }

            $content = file_get_contents($filePath);
            $totalOriginalSize += strlen($content);
            $optimized = $this->minifyCss($content);
            $combinedCss .= "/* From: {$file} */\n{$optimized}\n\n";
            $processedFiles[] = $file;
        }

        // Write combined file
        file_put_contents($outputFile, $combinedCss);
        $finalSize = strlen($combinedCss);
        $reduction = round((1 - $finalSize / $totalOriginalSize) * 100, 2);

        $this->logger->info('CSS optimization completed', [
            'files_processed' => count($processedFiles),
            'original_size' => $totalOriginalSize,
            'final_size' => $finalSize,
            'reduction' => $reduction . '%'
        ]);

        return [
            'success' => true,
            'files_processed' => count($processedFiles),
            'original_size' => $totalOriginalSize,
            'final_size' => $finalSize,
            'reduction_percent' => $reduction,
            'output_file' => $outputFile
        ];
    }

    /**
     * Minify CSS content
     */
    private function minifyCss(string $css): string
    {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove spaces around special characters
        $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);
        
        // Remove last semicolon in block
        $css = preg_replace('/;}/','}',$css);
        
        // Remove unnecessary zeros
        $css = preg_replace('/:0(px|em|%|rem|vh|vw)/', ':0', $css);
        
        return trim($css);
    }

    /**
     * Remove duplicate CSS rules
     */
    public function removeDuplicates(): array
    {
        $cssDir = $this->projectDir . '/public/css';
        $duplicatesFound = [];
        
        $files = glob($cssDir . '/*.css');
        
        foreach ($files as $filePath) {
            if (str_contains(basename($filePath), 'combined') || 
                str_contains(basename($filePath), '.min.')) {
                continue;
            }

            $content = file_get_contents($filePath);
            $rules = $this->extractCssRules($content);
            
            $seen = [];
            $duplicates = 0;
            
            foreach ($rules as $selector => $declarations) {
                $key = $selector . '|' . $declarations;
                if (isset($seen[$key])) {
                    $duplicates++;
                } else {
                    $seen[$key] = true;
                }
            }
            
            if ($duplicates > 0) {
                $duplicatesFound[basename($filePath)] = $duplicates;
            }
        }

        return [
            'success' => true,
            'duplicates_found' => $duplicatesFound,
            'total_duplicates' => array_sum($duplicatesFound)
        ];
    }

    /**
     * Extract CSS rules from content
     */
    private function extractCssRules(string $css): array
    {
        $rules = [];
        
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Match CSS rules
        preg_match_all('/([^{]+)\{([^}]+)\}/', $css, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $selector = trim($match[1]);
            $declarations = trim($match[2]);
            $rules[$selector] = $declarations;
        }
        
        return $rules;
    }

    /**
     * Analyze CSS usage
     */
    public function analyzeCssUsage(): array
    {
        $cssDir = $this->projectDir . '/public/css';
        $files = glob($cssDir . '/*.css');
        
        $analysis = [];
        $totalSize = 0;
        
        foreach ($files as $filePath) {
            $file = basename($filePath);
            $size = filesize($filePath);
            $totalSize += $size;
            
            $analysis[] = [
                'file' => $file,
                'size' => $size,
                'size_kb' => round($size / 1024, 2),
                'lines' => count(file($filePath))
            ];
        }
        
        // Sort by size descending
        usort($analysis, fn($a, $b) => $b['size'] <=> $a['size']);
        
        return [
            'files' => $analysis,
            'total_files' => count($files),
            'total_size' => $totalSize,
            'total_size_kb' => round($totalSize / 1024, 2)
        ];
    }
}
