/**
 * Component Manager - Управление UI компонентами
 */

(function() {
    'use strict';
    
    class ComponentManager {
        constructor() {
            this.components = new Map();
            this.init();
        }
        
        init() {
            this.registerDefaultComponents();
            this.initializeComponents();
        }
        
        registerDefaultComponents() {
            // Регистрация стандартных компонентов
            this.register('tooltip', this.initTooltips.bind(this));
            this.register('popover', this.initPopovers.bind(this));
            this.register('dropdown', this.initDropdowns.bind(this));
        }
        
        register(name, initFn) {
            this.components.set(name, initFn);
        }
        
        initializeComponents() {
            this.components.forEach((initFn, name) => {
                try {
                    initFn();
                } catch (e) {
                    console.warn(`Failed to initialize component: ${name}`, e);
                }
            });
        }
        
        initTooltips() {
            const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(el => {
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    new bootstrap.Tooltip(el);
                }
            });
        }
        
        initPopovers() {
            const popovers = document.querySelectorAll('[data-bs-toggle="popover"]');
            popovers.forEach(el => {
                if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
                    new bootstrap.Popover(el);
                }
            });
        }
        
        initDropdowns() {
            const dropdowns = document.querySelectorAll('[data-bs-toggle="dropdown"]');
            dropdowns.forEach(el => {
                if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
                    new bootstrap.Dropdown(el);
                }
            });
        }
        
        refresh() {
            this.initializeComponents();
        }
    }
    
    // Инициализация
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.ComponentManager = new ComponentManager();
        });
    } else {
        window.ComponentManager = new ComponentManager();
    }
    
})();
