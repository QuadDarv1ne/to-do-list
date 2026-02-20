/**
 * Console Wrapper - отключает логи в production
 */
(function() {
    'use strict';
    
    const isDev = window.location.hostname === 'localhost' || 
                  window.location.hostname === '127.0.0.1' ||
                  window.location.port === '8000';
    
    if (!isDev) {
        // Отключаем console в production
        const noop = function() {};
        
        window.console = {
            log: noop,
            debug: noop,
            info: noop,
            warn: console.warn.bind(console), // Оставляем warnings
            error: console.error.bind(console), // Оставляем errors
            group: noop,
            groupEnd: noop,
            groupCollapsed: noop,
            table: noop,
            time: noop,
            timeEnd: noop,
            trace: noop
        };
    }
})();
