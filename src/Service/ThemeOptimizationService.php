<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;

class ThemeOptimizationService
{
    private Filesystem $filesystem;
    private string $publicDir;

    public function __construct(string $projectDir)
    {
        $this->filesystem = new Filesystem();
        $this->publicDir = $projectDir . '/public';
    }

    /**
     * Оптимизация CSS тем - объединение и минификация
     */
    public function optimizeThemeCSS(): void
    {
        $cssDir = $this->publicDir . '/css';
        
        // Основные файлы тем для объединения
        $themeFiles = [
            'themes.css',
            'dark-modern-theme.css',
            'components.css',
            'dashboard-widgets.css'
        ];

        $combinedCSS = '';
        foreach ($themeFiles as $file) {
            $filePath = $cssDir . '/' . $file;
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                $combinedCSS .= "/* === {$file} === */\n" . $content . "\n\n";
            }
        }

        // Минификация объединенного CSS
        $minifiedCSS = $this->minifyCSS($combinedCSS);
        
        // Сохранение оптимизированного файла
        file_put_contents($cssDir . '/themes-optimized.min.css', $minifiedCSS);
    }

    /**
     * Создание критических стилей для каждой темы
     */
    public function generateCriticalThemeCSS(): void
    {
        $themes = ['light', 'dark', 'orange', 'purple', 'custom'];
        
        foreach ($themes as $theme) {
            $criticalCSS = $this->getCriticalCSSForTheme($theme);
            file_put_contents(
                $this->publicDir . "/css/critical-{$theme}.min.css", 
                $this->minifyCSS($criticalCSS)
            );
        }
    }

    /**
     * Получение критических стилей для конкретной темы
     */
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

[data-theme='{$theme}'] .navbar {
    background: var(--primary);
    color: white;
}

[data-theme='{$theme}'] .btn-primary {
    background: var(--primary);
    border-color: var(--primary);
}

[data-theme='{$theme}'] .stat-card {
    background: var(--bg-card);
    border-color: var(--border);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

[data-theme='{$theme}'] .stat-card:hover {
    transform: translateY(-4px);
}
CSS;
    }

    /**
     * Получение цветов для темы
     */
    private function getThemeColors(string $theme): array
    {
        return match($theme) {
            'light' => [
                'primary' => '#667eea',
                'bg-body' => '#f5f5f5',
                'bg-card' => '#ffffff',
                'text-primary' => '#212529',
                'text-secondary' => '#6c757d',
                'border' => '#e0e0e0'
            ],
            'dark' => [
                'primary' => '#3b82f6',
                'bg-body' => '#111827',
                'bg-card' => '#1f2937',
                'text-primary' => '#f9fafb',
                'text-secondary' => '#9ca3af',
                'border' => '#374151'
            ],
            'orange' => [
                'primary' => '#f97316',
                'bg-body' => '#fef3f2',
                'bg-card' => '#ffffff',
                'text-primary' => '#1c1917',
                'text-secondary' => '#78716c',
                'border' => '#e7e5e4'
            ],
            'purple' => [
                'primary' => '#a855f7',
                'bg-body' => '#faf5ff',
                'bg-card' => '#ffffff',
                'text-primary' => '#1e1b4b',
                'text-secondary' => '#6b7280',
                'border' => '#e9d5ff'
            ],
            'custom' => [
                'primary' => 'var(--custom-primary, #667eea)',
                'bg-body' => 'var(--custom-bg-body, #f5f5f5)',
                'bg-card' => 'var(--custom-bg-card, #ffffff)',
                'text-primary' => 'var(--custom-text-primary, #212529)',
                'text-secondary' => '#6c757d',
                'border' => '#e0e0e0'
            ]
        };
    }

    /**
     * Минификация CSS
     */
    private function minifyCSS(string $css): string
    {
        // Удаляем комментарии
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Удаляем лишние пробелы и переносы строк
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Удаляем пробелы вокруг специальных символов
        $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);
        
        // Удаляем последнюю точку с запятой в блоке
        $css = preg_replace('/;(?=\s*})/', '', $css);
        
        return trim($css);
    }

    /**
     * Создание адаптивных стилей для мобильных устройств
     */
    public function generateMobileOptimizedCSS(): void
    {
        $mobileCSS = <<<CSS
/* Mobile optimizations for all themes */
@media (max-width: 768px) {
    .container-fluid {
        padding: 0 10px;
    }
    
    .card {
        margin-bottom: 0.75rem;
        border-radius: 12px;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    .stat-card {
        padding: 1rem;
        text-align: center;
    }
    
    .stat-value {
        font-size: 1.5rem;
    }
    
    .navbar {
        padding: 0.5rem 1rem;
    }
    
    .btn {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }
    
    .dashboard-welcome {
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    .quick-actions {
        bottom: 20px;
        right: 20px;
    }
    
    .quick-action-btn {
        width: 50px;
        height: 50px;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .card-header {
        padding: 0.75rem 1rem;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
}
CSS;

        file_put_contents(
            $this->publicDir . '/css/mobile-optimized.min.css',
            $this->minifyCSS($mobileCSS)
        );
    }

    /**
     * Создание CSS переменных для динамического переключения тем
     */
    public function generateThemeVariables(): void
    {
        $variablesCSS = <<<CSS
/* CSS Custom Properties for dynamic theme switching */
:root {
    /* Animation durations */
    --theme-transition-duration: 0.3s;
    --hover-transition-duration: 0.2s;
    
    /* Border radius */
    --radius-sm: 0.25rem;
    --radius: 0.375rem;
    --radius-lg: 0.5rem;
    --radius-xl: 0.75rem;
    
    /* Shadows */
    --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
    --shadow: 0 1px 3px rgba(0,0,0,0.1);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
    
    /* Z-index scale */
    --z-dropdown: 1000;
    --z-sticky: 1020;
    --z-fixed: 1030;
    --z-modal-backdrop: 1040;
    --z-modal: 1050;
    --z-popover: 1060;
    --z-tooltip: 1070;
}

/* Theme transition class */
.theme-transitioning,
.theme-transitioning *,
.theme-transitioning *::before,
.theme-transitioning *::after {
    transition: background-color var(--theme-transition-duration) ease,
                color var(--theme-transition-duration) ease,
                border-color var(--theme-transition-duration) ease,
                box-shadow var(--theme-transition-duration) ease !important;
}

/* Smooth hover transitions */
.card,
.btn,
.stat-card,
.task-item,
.nav-link {
    transition: all var(--hover-transition-duration) ease;
}
CSS;

        file_put_contents(
            $this->publicDir . '/css/theme-variables.min.css',
            $this->minifyCSS($variablesCSS)
        );
    }

    /**
     * Полная оптимизация системы тем
     */
    public function optimizeAll(): void
    {
        $this->optimizeThemeCSS();
        $this->generateCriticalThemeCSS();
        $this->generateMobileOptimizedCSS();
        $this->generateThemeVariables();
    }
}