/**
 * Production-safe Logger
 * Логирование только в dev режиме
 */
(function() {
    'use strict';
    
    const isDev = document.querySelector('meta[name="environment"]')?.content === 'dev';
    
    class Logger {
        constructor(prefix = '') {
            this.prefix = prefix;
        }
        
        log(...args) {
            if (isDev) {
                console.log(this.prefix, ...args);
            }
        }
        
        warn(...args) {
            if (isDev) {
                console.warn(this.prefix, ...args);
            }
        }
        
        error(...args) {
            // Ошибки логируем всегда
            console.error(this.prefix, ...args);
        }
        
        group(label) {
            if (isDev && console.group) {
                console.group(label);
            }
        }
        
        groupEnd() {
            if (isDev && console.groupEnd) {
                console.groupEnd();
            }
        }
    }
    
    // Глобальный логгер
    window.Logger = Logger;
    window.logger = new Logger('[App]');
})();
