<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;

/**
 * Unified service for all asset optimization (CSS, Images, Twig, Themes)
 */
class AssetOptimizerService
{
    private Filesystem $filesystem;
    private string $publicDir;
    private string $projectDir;

    public function __construct(
        private Environment $twig,
        private LoggerInterface $logger,
        string $projectDir,
    ) {
        $this->filesystem = new Filesystem();
        $this->projectDir = $projectDir;
        $this->publicDir = $projectDir . '/public';
    }

    // ==================== CSS OPTIMIZATION ====================

    /**
     * Optimize all CSS files
     */
    public function optimizeCSS(): array
    {
        $cssDir = $this->publicDir . '/css';
        $optimized = [];
        $errors = [];

        if (!is_dir($cssDir)) {
            return ['optimized' => 0, 'errors' => ['CSS directory not found']];
        }

        $cssFiles = $this->findCssFiles($cssDir);

        foreach ($cssFiles as $file) {
            try {
                $content = file_get_contents($file);
                $minified = $this->minifyCSS($content);

                $minifiedPath = str_replace('.css', '.min.css', $file);
                file_put_contents($minifiedPath, $minified);

                $optimized[] = basename($file);
            } catch (\Exception $e) {
                $errors[] = basename($file) . ': ' . $e->getMessage();
                $this->logger->error('CSS optimization failed', [
                    'file' => $file,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'optimized' => \count($optimized),
            'total' => \count($cssFiles),
            'files' => $optimized,
            'errors' => $errors,
        ];
    }

    /**
     * Minify CSS content
     */
    private function minifyCSS(string $css): string
    {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

        // Remove whitespace
        $css = preg_replace('/\s+/', ' ', $css);

        // Remove spaces around special characters
        $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);

        // Remove last semicolon in block
        $css = preg_replace('/;(?=\s*})/', '', $css);

        return trim($css);
    }

    private function findCssFiles(string $dir): array
    {
        $files = [];
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $files = array_merge($files, $this->findCssFiles($path));
            } elseif (str_ends_with($item, '.css') && !str_ends_with($item, '.min.css')) {
                $files[] = $path;
            }
        }

        return $files;
    }

    // ==================== IMAGE OPTIMIZATION ====================

    /**
     * Optimize images in uploads directory
     */
    public function optimizeImages(): array
    {
        $uploadsDir = $this->publicDir . '/uploads';
        $optimized = [];
        $errors = [];

        if (!is_dir($uploadsDir)) {
            return ['optimized' => 0, 'errors' => ['Uploads directory not found']];
        }

        $images = $this->findImages($uploadsDir);

        foreach ($images as $image) {
            try {
                $result = $this->optimizeImage($image);
                if ($result['success']) {
                    $optimized[] = [
                        'file' => basename($image),
                        'original_size' => $result['original_size'],
                        'optimized_size' => $result['optimized_size'],
                        'saved' => $result['saved_bytes'],
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = basename($image) . ': ' . $e->getMessage();
                $this->logger->error('Image optimization failed', [
                    'file' => $image,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'optimized' => \count($optimized),
            'total' => \count($images),
            'images' => $optimized,
            'errors' => $errors,
        ];
    }

    private function optimizeImage(string $imagePath): array
    {
        $originalSize = filesize($imagePath);
        $imageInfo = getimagesize($imagePath);

        if (!$imageInfo) {
            throw new \RuntimeException('Invalid image file');
        }

        $mimeType = $imageInfo['mime'];

        // Create image resource based on type
        $image = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($imagePath),
            'image/png' => imagecreatefrompng($imagePath),
            'image/gif' => imagecreatefromgif($imagePath),
            'image/webp' => imagecreatefromwebp($imagePath),
            default => throw new \RuntimeException('Unsupported image type: ' . $mimeType),
        };

        if (!$image) {
            throw new \RuntimeException('Failed to create image resource');
        }

        // Save optimized version
        $success = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, $imagePath, 85),
            'image/png' => imagepng($image, $imagePath, 6),
            'image/gif' => imagegif($image, $imagePath),
            'image/webp' => imagewebp($image, $imagePath, 85),
            default => false,
        };

        imagedestroy($image);

        $optimizedSize = filesize($imagePath);

        return [
            'success' => $success,
            'original_size' => $originalSize,
            'optimized_size' => $optimizedSize,
            'saved_bytes' => $originalSize - $optimizedSize,
        ];
    }

    private function findImages(string $dir): array
    {
        $images = [];
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $images = array_merge($images, $this->findImages($path));
            } elseif (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $item)) {
                $images[] = $path;
            }
        }

        return $images;
    }

    // ==================== TWIG OPTIMIZATION ====================

    /**
     * Warm up Twig cache for all templates
     */
    public function warmupTwigCache(): array
    {
        $templatesDir = $this->projectDir . '/templates';
        $templates = $this->findTemplates($templatesDir);

        $warmedUp = 0;
        $errors = [];

        foreach ($templates as $template) {
            try {
                $this->twig->load($template);
                $warmedUp++;
            } catch (\Exception $e) {
                $errors[] = [
                    'template' => $template,
                    'error' => $e->getMessage(),
                ];
                $this->logger->warning('Failed to warm up template', [
                    'template' => $template,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => true,
            'templates_warmed' => $warmedUp,
            'total_templates' => \count($templates),
            'errors' => $errors,
        ];
    }

    /**
     * Analyze template usage
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
            'total_templates' => \count($allTemplates),
            'used_templates' => \count($usedTemplates),
            'unused_templates' => $unusedTemplates,
            'usage_percent' => round((\count($usedTemplates) / \count($allTemplates)) * 100, 2),
        ];
    }

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
                    $this->findTemplates($path, $relativePath . '/'),
                );
            } elseif (str_ends_with($item, '.twig')) {
                $templates[] = $relativePath;
            }
        }

        return $templates;
    }

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

    // ==================== THEME OPTIMIZATION ====================

    /**
     * Optimize theme CSS files
     */
    public function optimizeThemes(): array
    {
        $cssDir = $this->publicDir . '/css';
        $results = [];

        // Combine and minify theme files
        $themeFiles = [
            'themes.css',
            'dark-modern-theme.css',
            'components.css',
            'dashboard-widgets.css',
        ];

        $combinedCSS = '';
        foreach ($themeFiles as $file) {
            $filePath = $cssDir . '/' . $file;
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                $combinedCSS .= "/* === {$file} === */\n" . $content . "\n\n";
            }
        }

        if ($combinedCSS) {
            $minifiedCSS = $this->minifyCSS($combinedCSS);
            file_put_contents($cssDir . '/themes-optimized.min.css', $minifiedCSS);
            $results['combined_themes'] = true;
        }

        // Generate critical CSS for each theme
        $themes = ['light', 'dark', 'orange', 'purple', 'custom'];
        foreach ($themes as $theme) {
            $criticalCSS = $this->getCriticalCSSForTheme($theme);
            file_put_contents(
                $this->publicDir . "/css/critical-{$theme}.min.css",
                $this->minifyCSS($criticalCSS),
            );
            $results["critical_{$theme}"] = true;
        }

        // Generate theme variables
        $this->generateThemeVariables();
        $results['theme_variables'] = true;

        return $results;
    }

    private function getCriticalCSSForTheme(string $theme): string
    {
        $baseColors = $this->getThemeColors($theme);

        return <<<CSS
/* Critical CSS for {$theme} theme */
:root[data-theme='{$theme}'] {
    --primary: {$baseColors['primary']};
    --bg-body: {$baseColors['bg-body']};
    --bg-card: {$baseColors['bg-card']};
    --text-primary: {$baseColors['text-primary']};
    --text-secondary: {$baseColors['text-secondary']};
    --border: {$baseColors['border']};
}

[data-theme='{$theme}'] body {
    background: var(--bg-body);
    color: var(--text-primary);
    transition: background-color 0.3s ease, color 0.3s ease;
}

[data-theme='{$theme}'] .card {
    background: var(--bg-card);
    border-color: var(--border);
}
CSS;
    }

    private function getThemeColors(string $theme): array
    {
        return match ($theme) {
            'light' => [
                'primary' => '#667eea',
                'bg-body' => '#f5f5f5',
                'bg-card' => '#ffffff',
                'text-primary' => '#212529',
                'text-secondary' => '#6c757d',
                'border' => '#e0e0e0',
            ],
            'dark' => [
                'primary' => '#3b82f6',
                'bg-body' => '#111827',
                'bg-card' => '#1f2937',
                'text-primary' => '#f9fafb',
                'text-secondary' => '#9ca3af',
                'border' => '#374151',
            ],
            'orange' => [
                'primary' => '#f97316',
                'bg-body' => '#fef3f2',
                'bg-card' => '#ffffff',
                'text-primary' => '#1c1917',
                'text-secondary' => '#78716c',
                'border' => '#e7e5e4',
            ],
            'purple' => [
                'primary' => '#a855f7',
                'bg-body' => '#faf5ff',
                'bg-card' => '#ffffff',
                'text-primary' => '#1e1b4b',
                'text-secondary' => '#6b7280',
                'border' => '#e9d5ff',
            ],
            'custom' => [
                'primary' => 'var(--custom-primary, #667eea)',
                'bg-body' => 'var(--custom-bg-body, #f5f5f5)',
                'bg-card' => 'var(--custom-bg-card, #ffffff)',
                'text-primary' => 'var(--custom-text-primary, #212529)',
                'text-secondary' => '#6c757d',
                'border' => '#e0e0e0',
            ],
        };
    }

    private function generateThemeVariables(): void
    {
        $variablesCSS = <<<CSS
/* CSS Custom Properties for dynamic theme switching */
:root {
    --theme-transition-duration: 0.3s;
    --hover-transition-duration: 0.2s;
    --radius-sm: 0.25rem;
    --radius: 0.375rem;
    --radius-lg: 0.5rem;
    --radius-xl: 0.75rem;
    --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
    --shadow: 0 1px 3px rgba(0,0,0,0.1);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
}
CSS;

        file_put_contents(
            $this->publicDir . '/css/theme-variables.min.css',
            $this->minifyCSS($variablesCSS),
        );
    }

    // ==================== UNIFIED OPTIMIZATION ====================

    /**
     * Run all asset optimizations
     */
    public function optimizeAll(): array
    {
        return [
            'css' => $this->optimizeCSS(),
            'images' => $this->optimizeImages(),
            'twig' => $this->warmupTwigCache(),
            'themes' => $this->optimizeThemes(),
        ];
    }
}
