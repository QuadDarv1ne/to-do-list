/**
 * Performance Monitor - Track and report performance metrics
 * Monitors Core Web Vitals and custom metrics
 */

(function() {
    'use strict';
    
    class PerformanceMonitor {
        constructor() {
            this.metrics = {};
            this.observers = {};
            this.init();
        }
        
        // Initialize monitoring
        init() {
            if (!window.performance) {
                console.warn('Performance API not supported');
                return;
            }
            
            // Wait for page load
            if (document.readyState === 'complete') {
                this.startMonitoring();
            } else {
                window.addEventListener('load', () => this.startMonitoring());
            }
        }
        
        // Start monitoring
        startMonitoring() {
            this.measureNavigationTiming();
            this.measureCoreWebVitals();
            this.measureResourceTiming();
            this.setupPerformanceObserver();
            this.monitorLongTasks();
            this.monitorMemory();
        }
        
        // Measure Navigation Timing
        measureNavigationTiming() {
            const navigation = performance.getEntriesByType('navigation')[0];
            if (!navigation) return;
            
            this.metrics.navigation = {
                dns: navigation.domainLookupEnd - navigation.domainLookupStart,
                tcp: navigation.connectEnd - navigation.connectStart,
                request: navigation.responseStart - navigation.requestStart,
                response: navigation.responseEnd - navigation.responseStart,
                dom: navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart,
                load: navigation.loadEventEnd - navigation.loadEventStart,
                total: navigation.loadEventEnd - navigation.fetchStart
            };
            
            this.reportMetric('navigation', this.metrics.navigation);
        }
        
        // Measure Core Web Vitals
        measureCoreWebVitals() {
            // Largest Contentful Paint (LCP)
            this.measureLCP();
            
            // First Input Delay (FID)
            this.measureFID();
            
            // Cumulative Layout Shift (CLS)
            this.measureCLS();
            
            // First Contentful Paint (FCP)
            this.measureFCP();
            
            // Time to Interactive (TTI)
            this.measureTTI();
        }
        
        // Measure LCP
        measureLCP() {
            if (!('PerformanceObserver' in window)) return;
            
            try {
                const observer = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    const lastEntry = entries[entries.length - 1];
                    
                    this.metrics.lcp = lastEntry.renderTime || lastEntry.loadTime;
                    this.reportMetric('lcp', this.metrics.lcp);
                    
                    // LCP should be under 2.5s (good), under 4s (needs improvement)
                    if (this.metrics.lcp > 4000) {
                        console.warn('LCP is poor:', this.metrics.lcp);
                    }
                });
                
                observer.observe({ entryTypes: ['largest-contentful-paint'] });
                this.observers.lcp = observer;
            } catch (e) {
                console.warn('LCP measurement failed:', e);
            }
        }
        
        // Measure FID
        measureFID() {
            if (!('PerformanceObserver' in window)) return;
            
            try {
                const observer = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    entries.forEach(entry => {
                        this.metrics.fid = entry.processingStart - entry.startTime;
                        this.reportMetric('fid', this.metrics.fid);
                        
                        // FID should be under 100ms (good), under 300ms (needs improvement)
                        if (this.metrics.fid > 300) {
                            console.warn('FID is poor:', this.metrics.fid);
                        }
                    });
                });
                
                observer.observe({ entryTypes: ['first-input'] });
                this.observers.fid = observer;
            } catch (e) {
                console.warn('FID measurement failed:', e);
            }
        }
        
        // Measure CLS
        measureCLS() {
            if (!('PerformanceObserver' in window)) return;
            
            let clsScore = 0;
            
            try {
                const observer = new PerformanceObserver((list) => {
                    list.getEntries().forEach(entry => {
                        if (!entry.hadRecentInput) {
                            clsScore += entry.value;
                            this.metrics.cls = clsScore;
                            this.reportMetric('cls', this.metrics.cls);
                            
                            // CLS should be under 0.1 (good), under 0.25 (needs improvement)
                            if (this.metrics.cls > 0.25) {
                                console.warn('CLS is poor:', this.metrics.cls);
                            }
                        }
                    });
                });
                
                observer.observe({ entryTypes: ['layout-shift'] });
                this.observers.cls = observer;
            } catch (e) {
                console.warn('CLS measurement failed:', e);
            }
        }
        
        // Measure FCP
        measureFCP() {
            const paint = performance.getEntriesByType('paint');
            const fcp = paint.find(entry => entry.name === 'first-contentful-paint');
            
            if (fcp) {
                this.metrics.fcp = fcp.startTime;
                this.reportMetric('fcp', this.metrics.fcp);
            }
        }
        
        // Measure TTI (simplified)
        measureTTI() {
            // TTI is when the page is fully interactive
            // Simplified: measure when all resources are loaded and main thread is idle
            
            if (document.readyState === 'complete') {
                this.metrics.tti = performance.now();
                this.reportMetric('tti', this.metrics.tti);
            } else {
                window.addEventListener('load', () => {
                    setTimeout(() => {
                        this.metrics.tti = performance.now();
                        this.reportMetric('tti', this.metrics.tti);
                    }, 0);
                });
            }
        }
        
        // Measure Resource Timing
        measureResourceTiming() {
            const resources = performance.getEntriesByType('resource');
            
            const stats = {
                total: resources.length,
                css: 0,
                js: 0,
                img: 0,
                font: 0,
                other: 0,
                totalSize: 0,
                totalDuration: 0
            };
            
            resources.forEach(resource => {
                const type = this.getResourceType(resource.name);
                stats[type]++;
                stats.totalSize += resource.transferSize || 0;
                stats.totalDuration += resource.duration;
            });
            
            this.metrics.resources = stats;
            this.reportMetric('resources', stats);
        }
        
        // Get resource type
        getResourceType(url) {
            if (url.match(/\.css/)) return 'css';
            if (url.match(/\.js/)) return 'js';
            if (url.match(/\.(jpg|jpeg|png|gif|webp|svg)/)) return 'img';
            if (url.match(/\.(woff|woff2|ttf|otf)/)) return 'font';
            return 'other';
        }
        
        // Setup Performance Observer
        setupPerformanceObserver() {
            if (!('PerformanceObserver' in window)) return;
            
            try {
                const observer = new PerformanceObserver((list) => {
                    list.getEntries().forEach(entry => {
                        // Log slow resources
                        if (entry.duration > 1000) {
                            console.warn('Slow resource:', entry.name, entry.duration + 'ms');
                        }
                    });
                });
                
                observer.observe({ entryTypes: ['resource', 'measure'] });
                this.observers.performance = observer;
            } catch (e) {
                console.warn('Performance Observer setup failed:', e);
            }
        }
        
        // Monitor Long Tasks
        monitorLongTasks() {
            if (!('PerformanceObserver' in window)) return;
            
            try {
                const observer = new PerformanceObserver((list) => {
                    list.getEntries().forEach(entry => {
                        console.warn('Long task detected:', entry.duration + 'ms');
                        
                        if (!this.metrics.longTasks) {
                            this.metrics.longTasks = [];
                        }
                        
                        this.metrics.longTasks.push({
                            duration: entry.duration,
                            startTime: entry.startTime
                        });
                    });
                });
                
                observer.observe({ entryTypes: ['longtask'] });
                this.observers.longTasks = observer;
            } catch (e) {
                // Long Tasks API not supported
            }
        }
        
        // Monitor Memory
        monitorMemory() {
            if (!performance.memory) return;
            
            this.metrics.memory = {
                used: performance.memory.usedJSHeapSize,
                total: performance.memory.totalJSHeapSize,
                limit: performance.memory.jsHeapSizeLimit
            };
            
            this.reportMetric('memory', this.metrics.memory);
            
            // Warn if memory usage is high
            const usagePercent = (this.metrics.memory.used / this.metrics.memory.limit) * 100;
            if (usagePercent > 90) {
                console.warn('High memory usage:', usagePercent.toFixed(2) + '%');
            }
        }
        
        // Report metric
        reportMetric(name, value) {
            // Store in StateManager if available
            if (window.StateManager) {
                window.StateManager.recordMetric(name, value);
            }
            
            // Dispatch custom event
            document.dispatchEvent(new CustomEvent('performance-metric', {
                detail: { name, value }
            }));
        }
        
        // Get all metrics
        getMetrics() {
            return this.metrics;
        }
        
        // Get performance score (0-100)
        getScore() {
            let score = 100;
            
            // Deduct points for poor metrics
            if (this.metrics.lcp > 4000) score -= 20;
            else if (this.metrics.lcp > 2500) score -= 10;
            
            if (this.metrics.fid > 300) score -= 20;
            else if (this.metrics.fid > 100) score -= 10;
            
            if (this.metrics.cls > 0.25) score -= 20;
            else if (this.metrics.cls > 0.1) score -= 10;
            
            if (this.metrics.fcp > 3000) score -= 15;
            else if (this.metrics.fcp > 1800) score -= 7;
            
            if (this.metrics.tti > 5000) score -= 15;
            else if (this.metrics.tti > 3800) score -= 7;
            
            return Math.max(0, score);
        }
        
        // Generate report
        generateReport() {
            const score = this.getScore();
            const grade = score >= 90 ? 'A' : score >= 80 ? 'B' : score >= 70 ? 'C' : score >= 60 ? 'D' : 'F';
            
            return {
                score,
                grade,
                metrics: this.metrics,
                timestamp: new Date().toISOString()
            };
        }
        
        // Log report to console
        logReport() {
            const report = this.generateReport();
            
            if (window.logger) {
                window.logger.group('Performance Report');
                window.logger.log('Score:', report.score, '(' + report.grade + ')');
                window.logger.log('Metrics:', report.metrics);
                window.logger.groupEnd();
            }
            
            return report;
        }
        
        // Disconnect all observers
        disconnect() {
            Object.values(this.observers).forEach(observer => {
                if (observer && observer.disconnect) {
                    observer.disconnect();
                }
            });
        }
    }
    
    // Initialize on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.PerformanceMonitor = new PerformanceMonitor();
        });
    } else {
        window.PerformanceMonitor = new PerformanceMonitor();
    }
    
    // Log report after 5 seconds
    setTimeout(() => {
        if (window.PerformanceMonitor) {
            window.PerformanceMonitor.logReport();
        }
    }, 5000);
})();
