/**
 * CSS Master Optimizer - Центральный контроллер оптимизации
 * Координирует работу всех систем оптимизации CSS
 */

(function() {
    'use strict';
    
    class CSSMasterOptimizer {
        constructor() {
            this.systems = {
                loader: null,
                deduplicator: null,
                performance: null
            };
            
            this.config = {
                autoOptimize: true,
                reportInterval: 30000, // 30 секунд
                enableDeduplication: false, // Отключено по умолчанию (может сломать стили)
                enableMonitoring: true
            };
            
            this.init();
        }
        
        init() {
            // Ждем загрузки всех систем
            this.waitForSystems().then(() => {
                this.registerSystems();
                this.setupAutoOptimization();
                this.setupReporting();
            });
        }
        
        // Ожидание загрузки систем
        waitForSystems() {
            return new Promise((resolve) => {
                const checkInterval = setInterval(() => {
                    if (window.CSSLazyLoader && 
                        window.CSSDeduplicator && 
                        window.CSSPerformanceMonitor) {
                        clearInterval(checkInterval);
                        resolve();
                    }
                }, 100);
                
                // Таймаут 10 секунд
                setTimeout(() => {
                    clearInterval(checkInterval);
                    resolve();
                }, 10000);
            });
        }
        
        // Регистрация систем
        registerSystems() {
            this.systems.loader = window.CSSLazyLoader;
            this.systems.deduplicator = window.CSSDeduplicator;
            this.systems.performance = window.CSSPerformanceMonitor;
            
            console.log('✓ CSS Master Optimizer initialized');
        }
        
        // Автоматическая оптимизация
        setupAutoOptimization() {
            if (!this.config.autoOptimize) return;
            
            // Оптимизация после загрузки
            window.addEventListener('load', () => {
                setTimeout(() => {
                    this.runOptimization();
                }, 3000);
            });
        }
        
        // Настройка отчетности
        setupReporting() {
            if (!this.config.enableMonitoring) return;
            
            // Периодические отчеты
            setInterval(() => {
                this.generateQuickReport();
            }, this.config.reportInterval);
        }
        
        // Запуск полной оптимизации
        runOptimization() {
            console.group('🚀 Running CSS Optimization');
            
            const results = {
                loader: this.optimizeLoading(),
                deduplication: this.optimizeDeduplication(),
                performance: this.optimizePerformance()
            };
            
            console.log('Optimization Results:', results);
            console.groupEnd();
            
            return results;
        }
        
        // Оптимизация загрузки
        optimizeLoading() {
            if (!this.systems.loader) return null;
            
            const stats = this.systems.loader.getStats();
            
            return {
                loaded: stats.loaded,
                total: stats.total,
                percentage: ((stats.loaded / stats.total) * 100).toFixed(1) + '%'
            };
        }
        
        // Оптимизация дубликатов
        optimizeDeduplication() {
            if (!this.systems.deduplicator) return null;
            
            const stats = this.systems.deduplicator.getStats();
            
            // Автоматическое удаление отключено для безопасности
            if (this.config.enableDeduplication && stats.duplicates > 0) {
                console.warn('Auto-deduplication is disabled. Use cssOptimize.removeDuplicates() manually.');
            }
            
            return {
                duplicates: stats.duplicates,
                unused: stats.unused,
                savings: stats.potentialSavings
            };
        }
        
        // Оптимизация производительности
        optimizePerformance() {
            if (!this.systems.performance) return null;
            
            const score = this.systems.performance.getPerformanceScore();
            const metrics = this.systems.performance.exportMetrics();
            
            return {
                score,
                fcp: metrics.firstContentfulPaint,
                recommendations: metrics.recommendations.length
            };
        }
        
        // Быстрый отчет
        generateQuickReport() {
            const report = {
                timestamp: new Date().toISOString(),
                loader: this.systems.loader ? this.systems.loader.getStats() : null,
                performance: this.systems.performance ? {
                    score: this.systems.performance.getPerformanceScore()
                } : null
            };
            
            // Сохранение в StateManager
            if (window.StateManager) {
                window.StateManager.set('css.quickReport', report, false);
            }
            
            return report;
        }
        
        // Полный отчет
        generateFullReport() {
            console.group('📊 CSS Full Report');
            
            // Loader stats
            if (this.systems.loader) {
                console.log('Loader:', this.systems.loader.getStats());
            }
            
            // Deduplication stats
            if (this.systems.deduplicator) {
                console.log('Deduplication:', this.systems.deduplicator.getStats());
            }
            
            // Performance stats
            if (this.systems.performance) {
                this.systems.performance.generateReport();
            }
            
            console.groupEnd();
        }
        
        // Получение рекомендаций
        getRecommendations() {
            const recommendations = [];
            
            // От системы производительности
            if (this.systems.performance) {
                const perfRecs = this.systems.performance.getRecommendations();
                recommendations.push(...perfRecs);
            }
            
            // От дедупликатора
            if (this.systems.deduplicator) {
                const stats = this.systems.deduplicator.getStats();
                
                if (stats.duplicates > 10) {
                    recommendations.push({
                        type: 'warning',
                        message: `Найдено ${stats.duplicates} дублирующихся правил`,
                        suggestion: 'Используйте cssOptimize.removeDuplicates() для очистки'
                    });
                }
                
                if (stats.unused > 20) {
                    recommendations.push({
                        type: 'info',
                        message: `Найдено ${stats.unused} неиспользуемых правил`,
                        suggestion: 'Рассмотрите удаление неиспользуемых стилей'
                    });
                }
            }
            
            return recommendations;
        }
        
        // Экспорт всех метрик
        exportAllMetrics() {
            return {
                loader: this.systems.loader ? this.systems.loader.getStats() : null,
                deduplicator: this.systems.deduplicator ? this.systems.deduplicator.getStats() : null,
                performance: this.systems.performance ? this.systems.performance.exportMetrics() : null,
                recommendations: this.getRecommendations(),
                timestamp: new Date().toISOString()
            };
        }
        
        // Настройка конфигурации
        configure(options) {
            Object.assign(this.config, options);
            console.log('Configuration updated:', this.config);
        }
    }
    
    // Инициализация после загрузки страницы
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                window.CSSMasterOptimizer = new CSSMasterOptimizer();
            }, 500);
        });
    } else {
        setTimeout(() => {
            window.CSSMasterOptimizer = new CSSMasterOptimizer();
        }, 500);
    }
    
    // Глобальный API
    window.cssOptimizer = {
        // Запуск оптимизации
        optimize: () => window.CSSMasterOptimizer && window.CSSMasterOptimizer.runOptimization(),
        
        // Отчеты
        report: () => window.CSSMasterOptimizer && window.CSSMasterOptimizer.generateFullReport(),
        quickReport: () => window.CSSMasterOptimizer && window.CSSMasterOptimizer.generateQuickReport(),
        
        // Метрики
        metrics: () => window.CSSMasterOptimizer && window.CSSMasterOptimizer.exportAllMetrics(),
        recommendations: () => window.CSSMasterOptimizer && window.CSSMasterOptimizer.getRecommendations(),
        
        // Конфигурация
        config: (options) => window.CSSMasterOptimizer && window.CSSMasterOptimizer.configure(options),
        
        // Доступ к подсистемам
        loader: () => window.cssLoader,
        dedup: () => window.cssOptimize,
        perf: () => window.cssPerformance
    };
    
    // Команды для консоли
    console.log('%c💡 CSS Optimizer Commands:', 'color: #667eea; font-weight: bold; font-size: 14px;');
    console.log('%ccssOptimizer.report()%c - Полный отчет', 'color: #3b82f6; font-family: monospace;', 'color: inherit;');
    console.log('%ccssOptimizer.metrics()%c - Все метрики', 'color: #3b82f6; font-family: monospace;', 'color: inherit;');
    console.log('%ccssOptimizer.recommendations()%c - Рекомендации', 'color: #3b82f6; font-family: monospace;', 'color: inherit;');
    
})();
