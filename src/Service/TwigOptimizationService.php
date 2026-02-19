<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Psr\Log\LoggerInterface;
use Twig\Environment;

class TwigOptimizationService
{
    public function __construct(
        private Environment $twig,
        private Filesystem $filesystem,
        private LoggerInterface $logger,
        private string $projectDir
    ) {
    }

    /**
     * Warm up Twig cache for all templates
     */
    public function warmupCache(): array
    {
        $templatesDir = $this->projectDir . '/templates';
        $templates = $this->findTemplates($templatesDir);
        
        $warmedUp = 0;
        $errors = [];

        foreach ($templates as $template) {
            try {
                // Load template to compile it
                $this->twig->load($template);
                $warmedUp++;
            } catch (\Exception $e) {
                $errors[] = [
                    'template' => $template,
                    'error' => $e->getMessage()
                ];
                $this->logger->warning('Failed to warm up template', [
                    'template' => $template,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'success' => true,
            'templates_warmed' => $warmedUp,
            'total_templates' => count($templates),
            'errors' => $errors
        ];
    }

    /**
     * Find all Twig templates
     */
    private function findTemplates(string $dir, string $prefix = ''): array
    {
        $templates = [];
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            $relativePath = $prefix . $item;

            if (is_dir($path)) {
                $templates = array_merge(
                    $templates,
                    $this->findTemplates($path, $relativePath . '/')
                );
            } elseif (str_ends_with($item, '.twig')) {
                $templates[] = $relativePath;
            }
        }

        return $templates;
    }

    /**
     * Analyze template usage and find unused templates
     */
    public function analyzeTemplateUsage(): array
    {
        $templatesDir = $this->projectDir . '/templates';
        $srcDir = $this->projectDir . '/src';
        
        $allTemplates = $this->findTemplates($templatesDir);
        $usedTemplates = [];
        
        // Find templates referenced in PHP files
        $phpFiles = $this->findPhpFiles($srcDir);
        
        foreach ($phpFiles as $phpFile) {
            $content = file_get_contents($phpFile);
            
            // Match render() calls
            preg_match_all('/render\([\'"]([^\'"]+\.twig)[\'"]/', $content, $matches);
            foreach ($matches[1] as $template) {
                $usedTemplates[$template] = true;
            }
            
            // Match include() calls in Twig
            preg_match_all('/include\([\'"]([^\'"]+\.twig)[\'"]/', $content, $matches);
            foreach ($matches[1] as $template) {
                $usedTemplates[$template] = true;
            }
        }
        
        // Find templates referenced in other templates
        foreach ($allTemplates as $template) {
            $templatePath = $templatesDir . '/' . $template;
            if (file_exists($templatePath)) {
                $content = file_get_contents($templatePath);
                
                // Match extends, include, embed
                preg_match_all('/{%\s*(extends|include|embed)\s+[\'"]([^\'"]+\.twig)[\'"]/', $content, $matches);
                foreach ($matches[2] as $referencedTemplate) {
                    $usedTemplates[$referencedTemplate] = true;
                }
            }
        }
        
        // Find potentially unused templates
        $unusedTemplates = [];
        foreach ($allTemplates as $template) {
            if (!isset($usedTemplates[$template])) {
                $unusedTemplates[] = $template;
            }
        }
        
        return [
            'total_templates' => count($allTemplates),
            'used_templates' => count($usedTemplates),
            'unused_templates' => $unusedTemplates,
            'usage_percent' => round((count($usedTemplates) / count($allTemplates)) * 100, 2)
        ];
    }

    /**
     * Find all PHP files
     */
    private function findPhpFiles(string $dir): array
    {
        $files = [];
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $files = array_merge($files, $this->findPhpFiles($path));
            } elseif (str_ends_with($item, '.php')) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Optimize template includes by finding duplicates
     */
    public function findDuplicateIncludes(): array
    {
        $templatesDir = $this->projectDir . '/templates';
        $templates = $this->findTemplates($templatesDir);
        
        $includeUsage = [];
        
        foreach ($templates as $template) {
            $templatePath = $templatesDir . '/' . $template;
            if (!file_exists($templatePath)) {
                continue;
            }
            
            $content = file_get_contents($templatePath);
            
            // Find all includes
            preg_match_all('/{%\s*include\s+[\'"]([^\'"]+\.twig)[\'"]/', $content, $matches);
            
            foreach ($matches[1] as $includedTemplate) {
                if (!isset($includeUsage[$includedTemplate])) {
                    $includeUsage[$includedTemplate] = [];
                }
                $includeUsage[$includedTemplate][] = $template;
            }
        }
        
        // Find most used includes
        $mostUsed = [];
        foreach ($includeUsage as $included => $usedIn) {
            if (count($usedIn) > 1) {
                $mostUsed[] = [
                    'template' => $included,
                    'used_count' => count($usedIn),
                    'used_in' => $usedIn
                ];
            }
        }
        
        usort($mostUsed, fn($a, $b) => $b['used_count'] <=> $a['used_count']);
        
        return [
            'total_includes' => count($includeUsage),
            'reused_includes' => count($mostUsed),
            'most_used' => array_slice($mostUsed, 0, 10)
        ];
    }
}
