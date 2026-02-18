/**
 * Performance Monitor
 * Мониторинг производительности приложения
 */

class PerformanceMonitor {
    constructor() {
        this.metrics = {
            pageLoad: 0,
            firstPaint: 0,
            firstContentfulPaint: 0,
            domInteractive: 0,
            domComplete: 0,
            apiCalls: [],
            errors: [],
            resources: []
        };
        this.enabled = true;
        this.reportingInterval = 60000; // 1 минута
        this.init();
    }

    init() {
        if (!this.enabled) return;

        this.measurePageLoad();
        this.monitorApiCalls();
        this.monitorErrors();
        this.monitorResources();
        this.startReporting();
        this.addPerformanceObserver();
    }

    /**
     * Измерить загрузку страницы
     */
    measurePageLoad() {
        if (!window.performance) return;

        window.addEventListener('load', () => {
            setTimeout(() => {
                const perfData = performance.getEntriesByType('navigation')[0];
                
                if (perfData) {
                    this.metrics.pageLoad = perfData.loadEventEnd - perfData.fetchStart;
                    this.metrics.domInteractive = perfData.domInteractive - perfData.fetchStart;
                    this.metrics.domComplete = perfData.domComplete - perfData.fetchStart;
                }

                // Paint timing
                const paintEntries = performance.getEntriesByType('paint');
                paintEntries.forEach(entry => {
                    if (entry.name === 'first-paint') {
                        this.metrics.firstPaint = entry.startTime;
                    } else if (entry.name === 'first-contentful-paint') {
                        this.metrics.firstContentfulPaint = entry.startTime;
                    }
                });

                this.logMetrics();
            }, 0);
        });
    }

    /**
     * Мониторинг API вызовов
     */
    monitorApiCalls() {
        const originalFetch = window.fetch;
        
        window.fetch = async (...args) => {
            const startTime = performance.now();
            const url = args[0];
            
            try {
                const response = await originalFetch(...args);
                const endTime = performance.now();
                const duration = endTime - startTime;

                this.metrics.apiCalls.push({
                    url: url,
                    duration: duration,
                    status: response.status,
                    timestamp: new Date().toISOString()
                });

                // Предупреждение о медленных запросах
                if (duration > 3000) {
                    console.warn(`Slow API call detected: ${url} took ${duration.toFixed(2)}ms`);
                }

                return response;
            } catch (error) {
                const endTime = performance.now();
                const duration = endTime - startTime;

                this.metrics.apiCalls.push({
                    url: url,
                    duration: duration,
                    status: 'error',
                    error: error.message,
                    timestamp: new Date().toISOString()
                });

                throw error;
            }
        };
    }

    /**
     * Мониторинг ошибок
     */
    monitorErrors() {
        window.addEventListener('error', (event) => {
            this.metrics.errors.push({
                message: event.message,
                source: event.filename,
                line: event.lineno,
                column: event.colno,
                timestamp: new Date().toISOString()
            });
        });

        window.addEventListener('unhandledrejection', (event) => {
            this.metrics.errors.push({
                message: event.reason?.message || 'Unhandled Promise Rejection',
                type: 'promise',
                timestamp: new Date().toISOString()
            });
        });
    }

    /**
     * Мониторинг ресурсов
     */
    monitorResources() {
        if (!window.performance) return;

        window.addEventListener('load', () => {
            const resources = performance.getEntriesByType('resource');
            
            resources.forEach(resource => {
                this.metrics.resources.push({
                    name: resource.name,
                    type: resource.initiatorType,
                    duration: resource.duration,
                    size: resource.transferSize || 0,
                    timestamp: new Date().toISOString()
                });
            });

            this.analyzeResources();
        });
    }

    /**
     * Добавить Performance Observer
     */
    addPerformanceObserver() {
        if (!window.PerformanceObserver) return;

        // Long Tasks Observer
        try {
            const longTaskObserver = new PerformanceObserver((list) => {
                for (const entry of list.getEntries()) {
                    if (entry.duration > 50) {
                        console.warn(`Long task detected: ${entry.duration.toFixed(2)}ms`);
                    }
                }
            });
            longTaskObserver.observe({ entryTypes: ['longtask'] });
        } catch (e) {
            // Long tasks not supported
        }

        // Layout Shift Observer
        try {
            const layoutShiftObserver = new PerformanceObserver((list) => {
                let cls = 0;
                for (const entry of list.getEntries()) {
                    if (!entry.hadRecentInput) {
                        cls += entry.value;
                    }
                }
                if (cls > 0.1) {
                    console.warn(`Cumulative Layout Shift: ${cls.toFixed(3)}`);
                }
            });
            layoutShiftObserver.observe({ entryTypes: ['layout-shift'] });
        } catch (e) {
            // Layout shift not supported
        }
    }

    /**
     * Анализировать ресурсы
     */
    analyzeResources() {
        const largeResources = this.metrics.resources.filter(r => r.size > 500000); // > 500KB
        const slowResources = this.metrics.resources.filter(r => r.duration > 1000); // > 1s

        if (largeResources.length > 0) {
            console.warn('Large resources detected:', largeResources);
        }

        if (slowResources.length > 0) {
            console.warn('Slow loading resources detected:', slowResources);
        }

        // Подсчет общего размера
        const totalSize = this.metrics.resources.reduce((sum, r) => sum + r.size, 0);
        console.log(`Total resources size: ${(totalSize / 1024 / 1024).toFixed(2)} MB`);
    }

    /**
     * Логировать метрики
     */
    logMetrics() {
        console.group('📊 Performance Metrics');
        console.log(`Page Load: ${this.metrics.pageLoad.toFixed(2)}ms`);
        console.log(`First Paint: ${this.metrics.firstPaint.toFixed(2)}ms`);
        console.log(`First Contentful Paint: ${this.metrics.firstContentfulPaint.toFixed(2)}ms`);
        console.log(`DOM Interactive: ${this.metrics.domInteractive.toFixed(2)}ms`);
        console.log(`DOM Complete: ${this.metrics.domComplete.toFixed(2)}ms`);
        console.groupEnd();

        // Оценка производительности
        this.evaluatePerformance();
    }

    /**
     * Оценить производительность
     */
    evaluatePerformance() {
        const scores = {
            pageLoad: this.scoreMetric(this.metrics.pageLoad, 1000, 3000),
            fcp: this.scoreMetric(this.metrics.firstContentfulPaint, 1000, 2500),
            domInteractive: this.scoreMetric(this.metrics.domInteractive, 1500, 3500)
        };

        const avgScore = Object.values(scores).reduce((a, b) => a + b, 0) / Object.keys(scores).length;

        let rating = 'Отлично';
        let color = '#28a745';
        
        if (avgScore < 70) {
            rating = 'Хорошо';
            color = '#ffc107';
        }
        if (avgScore < 50) {
            rating = 'Удовлетворительно';
            color = '#fd7e14';
        }
        if (avgScore < 30) {
            rating = 'Плохо';
            color = '#dc3545';
        }

        console.log(`%c Performance Score: ${avgScore.toFixed(0)}/100 - ${rating}`, 
            `color: ${color}; font-weight: bold; font-size: 14px;`);
    }

    /**
     * Оценить метрику
     */
    scoreMetric(value, good, poor) {
        if (value <= good) return 100;
        if (value >= poor) return 0;
        return Math.round(100 - ((value - good) / (poor - good)) * 100);
    }

    /**
     * Начать отчетность
     */
    startReporting() {
        setInterval(() => {
            this.sendReport();
        }, this.reportingInterval);
    }

    /**
     * Отправить отчет
     */
    async sendReport() {
        if (this.metrics.apiCalls.length === 0 && this.metrics.errors.length === 0) {
            return;
        }

        const report = {
            pageLoad: this.metrics.pageLoad,
            firstContentfulPaint: this.metrics.firstContentfulPaint,
            apiCalls: this.metrics.apiCalls.slice(-10), // Последние 10
            errors: this.metrics.errors.slice(-5), // Последние 5
            timestamp: new Date().toISOString(),
            userAgent: navigator.userAgent,
            url: window.location.href
        };

        try {
            await fetch('/api/performance/report', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(report)
            });

            // Очистить старые данные
            this.metrics.apiCalls = [];
            this.metrics.errors = [];
        } catch (error) {
            console.error('Failed to send performance report:', error);
        }
    }

    /**
     * Получить метрики
     */
    getMetrics() {
        return { ...this.metrics };
    }

    /**
     * Измерить пользовательское действие
     */
    measureAction(name, callback) {
        const startTime = performance.now();
        
        const result = callback();
        
        if (result instanceof Promise) {
            return result.then(res => {
                const duration = performance.now() - startTime;
                console.log(`Action "${name}" took ${duration.toFixed(2)}ms`);
                return res;
            });
        }
        
        const duration = performance.now() - startTime;
        console.log(`Action "${name}" took ${duration.toFixed(2)}ms`);
        return result;
    }

    /**
     * Создать индикатор производительности
     */
    createPerformanceIndicator() {
        if (document.getElementById('performance-indicator')) return;

        const indicator = document.createElement('div');
        indicator.id = 'performance-indicator';
        indicator.className = 'performance-indicator';
        indicator.innerHTML = `
            <div class="performance-indicator-content">
                <i class="fas fa-tachometer-alt"></i>
                <span id="performance-score">--</span>
            </div>
        `;

        document.body.appendChild(indicator);

        // Обновить индикатор
        this.updateIndicator();

        // Добавить стили
        this.addIndicatorStyles();
    }

    /**
     * Обновить индикатор
     */
    updateIndicator() {
        const scoreEl = document.getElementById('performance-score');
        if (!scoreEl) return;

        const scores = {
            pageLoad: this.scoreMetric(this.metrics.pageLoad, 1000, 3000),
            fcp: this.scoreMetric(this.metrics.firstContentfulPaint, 1000, 2500)
        };

        const avgScore = Object.values(scores).reduce((a, b) => a + b, 0) / Object.keys(scores).length;
        scoreEl.textContent = avgScore.toFixed(0);

        const indicator = document.getElementById('performance-indicator');
        if (indicator) {
            indicator.className = 'performance-indicator';
            if (avgScore >= 70) indicator.classList.add('good');
            else if (avgScore >= 50) indicator.classList.add('ok');
            else indicator.classList.add('poor');
        }
    }

    /**
     * Добавить стили индикатора
     */
    addIndicatorStyles() {
        if (document.getElementById('performanceIndicatorStyles')) return;

        const style = document.createElement('style');
        style.id = 'performanceIndicatorStyles';
        style.textContent = `
            .performance-indicator {
                position: fixed;
                bottom: 20px;
                left: 20px;
                background: var(--bg-card);
                border: 2px solid var(--border);
                border-radius: 50%;
                width: 60px;
                height: 60px;
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
                cursor: pointer;
                transition: all 0.3s ease;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }

            .performance-indicator:hover {
                transform: scale(1.1);
            }

            .performance-indicator.good {
                border-color: #28a745;
                color: #28a745;
            }

            .performance-indicator.ok {
                border-color: #ffc107;
                color: #ffc107;
            }

            .performance-indicator.poor {
                border-color: #dc3545;
                color: #dc3545;
            }

            .performance-indicator-content {
                text-align: center;
                font-size: 0.75rem;
                font-weight: 600;
            }

            .performance-indicator-content i {
                display: block;
                font-size: 1.25rem;
                margin-bottom: 0.25rem;
            }

            @media (max-width: 768px) {
                .performance-indicator {
                    width: 50px;
                    height: 50px;
                    font-size: 0.625rem;
                }

                .performance-indicator-content i {
                    font-size: 1rem;
                }
            }
        `;

        document.head.appendChild(style);
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.performanceMonitor = new PerformanceMonitor();
        
        // Показать индикатор только для админов
        if (document.body.dataset.userRole === 'ROLE_ADMIN') {
            window.performanceMonitor.createPerformanceIndicator();
        }
    });
} else {
    window.performanceMonitor = new PerformanceMonitor();
}

// Экспорт
window.PerformanceMonitor = PerformanceMonitor;
