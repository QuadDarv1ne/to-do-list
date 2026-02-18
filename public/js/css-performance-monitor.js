/**
 * CSS Performance Monitor
 * Отслеживает производительность загрузки и применения CSS
 */

(function() {
    'use strict';
    
    class CSSPerformanceMonitor {
        constructor() {
            this.metrics = {
                loadTimes: [],
                totalSize: 0,
                filesLoaded: 0,
                renderTime: 0,
                firstPaint: 0,
                firstContentfulPaint: 0
            };
            
            this.init();
        }
        
        init() {
            this.measureLoadTimes();
            this.measurePaintTimes();
            this.observeStyleSheets();
            
            // Отчет после загрузки
            window.addEventListener('load', () => {
                setTimeout(() => this.generateReport(), 2000);
            });
        }
        
        // Измерение времени загрузки CSS
        measureLoadTimes() {
            if (!window.performance || !window.performance.getEntriesByType) {
                return;
            }
            
            const resources = performance.getEntriesByType('resource');
            
            resources.forEach(resource => {
                if (resource.initiatorType === 'link' && resource.name.includes('.css')) {
                    this.metrics.loadTimes.push({
                        url: resource.name,
                        duration: resource.duration,
                        size: resource.transferSize || 0,
                        cached: resource.transferSize === 0
                    });
                    
                    this.metrics.totalSize += resource.transferSize || 0;
                    this.metrics.filesLoaded++;
                }
            });
        }
        
        // Измерение времени отрисовки
        measurePaintTimes() {
            if (!window.performance || !window.performance.getEntriesByType) {
                return;
            }
            
            const paintEntries = performance.getEntriesByType('paint');
            
            paintEntries.forEach(entry => {
                if (entry.name === 'first-paint') {
                    this.metrics.firstPaint = entry.startTime;
                } else if (entry.name === 'first-contentful-paint') {
                    this.metrics.firstContentfulPaint = entry.startTime;
                }
            });
        }
        
        // Наблюдение за добавлением стилей
        observeStyleSheets() {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach(mutation => {
                    mutation.addedNodes.forEach(node => {
                        if (node.tagName === 'LINK' && node.rel === 'stylesheet') {
                            this.trackStyleSheet(node);
                        } else if (node.tagName === 'STYLE') {
                            this.trackInlineStyle(node);
                        }
                    });
                });
            });
            
            observer.observe(document.head, {
                childList: true,
                subtree: true
            });
        }
        
        // Отслеживание загрузки stylesheet
        trackStyleSheet(link) {
            const startTime = performance.now();
            
            link.addEventListener('load', () => {
                const loadTime = performance.now() - startTime;
                console.log(`CSS loaded: ${link.href} (${loadTime.toFixed(2)}ms)`);
            });
            
            link.addEventListener('error', () => {
                console.error(`Failed to load CSS: ${link.href}`);
            });
        }
        
        // Отслеживание inline стилей
        trackInlineStyle(style) {
            const size = new Blob([style.textContent]).size;
            console.log(`Inline style added: ${size} bytes`);
        }
        
        // Анализ критического CSS
        analyzeCriticalCSS() {
            const viewportHeight = window.innerHeight;
            const elementsAboveFold = [];
            
            document.querySelectorAll('*').forEach(el => {
                const rect = el.getBoundingClientRect();
                if (rect.top < viewportHeight) {
                    elementsAboveFold.push(el);
                }
            });
            
            // Собираем используемые стили
            const criticalSelectors = new Set();
            elementsAboveFold.forEach(el => {
                if (el.classList.length > 0) {
                    el.classList.forEach(cls => criticalSelectors.add('.' + cls));
                }
                if (el.id) {
                    criticalSelectors.add('#' + el.id);
                }
            });
            
            return {
                elementsCount: elementsAboveFold.length,
                selectorsCount: criticalSelectors.size,
                selectors: Array.from(criticalSelectors)
            };
        }
        
        // Расчет блокирующего времени
        calculateBlockingTime() {
            if (!window.performance || !window.performance.getEntriesByType) {
                return 0;
            }
            
            const resources = performance.getEntriesByType('resource');
            let blockingTime = 0;
            
            resources.forEach(resource => {
                if (resource.initiatorType === 'link' && 
                    resource.name.includes('.css') && 
                    !resource.name.includes('async')) {
                    blockingTime += resource.duration;
                }
            });
            
            return blockingTime;
        }
        
        // Оценка производительности
        getPerformanceScore() {
            const fcp = this.metrics.firstContentfulPaint;
            const blockingTime = this.calculateBlockingTime();
            const totalSize = this.metrics.totalSize / 1024; // KB
            
            let score = 100;
            
            // Штраф за медленный FCP
            if (fcp > 2500) score -= 30;
            else if (fcp > 1800) score -= 20;
            else if (fcp > 1000) score -= 10;
            
            // Штраф за блокирующее время
            if (blockingTime > 1000) score -= 20;
            else if (blockingTime > 500) score -= 10;
            
            // Штраф за размер CSS
            if (totalSize > 200) score -= 20;
            else if (totalSize > 100) score -= 10;
            
            return Math.max(0, score);
        }
        
        // Рекомендации по оптимизации
        getRecommendations() {
            const recommendations = [];
            const fcp = this.metrics.firstContentfulPaint;
            const blockingTime = this.calculateBlockingTime();
            const totalSize = this.metrics.totalSize / 1024;
            
            if (fcp > 1800) {
                recommendations.push({
                    type: 'critical',
                    message: 'First Contentful Paint слишком медленный',
                    suggestion: 'Используйте inline critical CSS и отложенную загрузку остальных стилей'
                });
            }
            
            if (blockingTime > 500) {
                recommendations.push({
                    type: 'warning',
                    message: 'Высокое время блокировки рендеринга',
                    suggestion: 'Используйте async/defer для некритичных стилей'
                });
            }
            
            if (totalSize > 100) {
                recommendations.push({
                    type: 'info',
                    message: `Общий размер CSS: ${totalSize.toFixed(2)} KB`,
                    suggestion: 'Рассмотрите минификацию и удаление неиспользуемых стилей'
                });
            }
            
            if (this.metrics.filesLoaded > 10) {
                recommendations.push({
                    type: 'warning',
                    message: `Загружено ${this.metrics.filesLoaded} CSS файлов`,
                    suggestion: 'Объедините файлы для уменьшения HTTP запросов'
                });
            }
            
            return recommendations;
        }
        
        // Генерация отчета
        generateReport() {
            const score = this.getPerformanceScore();
            const recommendations = this.getRecommendations();
            const critical = this.analyzeCriticalCSS();
            
            const report = {
                score,
                metrics: {
                    firstPaint: this.metrics.firstPaint.toFixed(2) + 'ms',
                    firstContentfulPaint: this.metrics.firstContentfulPaint.toFixed(2) + 'ms',
                    blockingTime: this.calculateBlockingTime().toFixed(2) + 'ms',
                    totalSize: (this.metrics.totalSize / 1024).toFixed(2) + ' KB',
                    filesLoaded: this.metrics.filesLoaded
                },
                critical: {
                    elementsAboveFold: critical.elementsCount,
                    criticalSelectors: critical.selectorsCount
                },
                recommendations,
                timestamp: new Date().toISOString()
            };
            
            // Вывод в консоль
            console.group('🎨 CSS Performance Report');
            console.log(`Score: ${score}/100`);
            console.log('Metrics:', report.metrics);
            console.log('Critical CSS:', report.critical);
            
            if (recommendations.length > 0) {
                console.group('Recommendations:');
                recommendations.forEach(rec => {
                    const icon = rec.type === 'critical' ? '🔴' : rec.type === 'warning' ? '⚠️' : 'ℹ️';
                    console.log(`${icon} ${rec.message}`);
                    console.log(`   → ${rec.suggestion}`);
                });
                console.groupEnd();
            }
            
            console.groupEnd();
            
            // Сохранение в StateManager
            if (window.StateManager) {
                window.StateManager.set('css.performance', report, false);
            }
            
            return report;
        }
        
        // Экспорт метрик для аналитики
        exportMetrics() {
            return {
                ...this.metrics,
                score: this.getPerformanceScore(),
                recommendations: this.getRecommendations()
            };
        }
    }
    
    // Инициализация
    window.addEventListener('load', () => {
        setTimeout(() => {
            window.CSSPerformanceMonitor = new CSSPerformanceMonitor();
        }, 1000);
    });
    
    // API
    window.cssPerformance = {
        report: () => window.CSSPerformanceMonitor && window.CSSPerformanceMonitor.generateReport(),
        metrics: () => window.CSSPerformanceMonitor && window.CSSPerformanceMonitor.exportMetrics(),
        score: () => window.CSSPerformanceMonitor && window.CSSPerformanceMonitor.getPerformanceScore()
    };
    
})();
