/**
 * Modern Toast Notification System
 * Компактные, стильные уведомления с автоматическим скрытием
 */

(function() {
    'use strict';
    
    class ToastSystem {
        constructor() {
            this.container = null;
            this.queue = [];
            this.activeToasts = new Set();
            this.maxToasts = 3;
            this.defaultDuration = 3000;
            
            this.init();
        }
        
        init() {
            this.createContainer();
            this.setupStyles();
        }
        
        createContainer() {
            this.container = document.createElement('div');
            this.container.className = 'toast-system-container';
            this.container.setAttribute('aria-live', 'polite');
            this.container.setAttribute('aria-atomic', 'true');
            document.body.appendChild(this.container);
        }
        
        setupStyles() {
            if (document.getElementById('toast-system-styles')) return;
            
            const style = document.createElement('style');
            style.id = 'toast-system-styles';
            style.textContent = `
                .toast-system-container {
                    position: fixed;
                    top: 70px;
                    right: 20px;
                    z-index: 9999;
                    display: flex;
                    flex-direction: column;
                    gap: 8px;
                    pointer-events: none;
                }
                
                .toast-item {
                    pointer-events: auto;
                    background: var(--bg-card, #fff);
                    border-left: 3px solid;
                    border-radius: 6px;
                    padding: 12px 16px;
                    min-width: 280px;
                    max-width: 400px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    animation: toastSlideIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                    transition: all 0.3s ease;
                }
                
                .toast-item.removing {
                    animation: toastSlideOut 0.3s ease forwards;
                }
                
                .toast-item.success {
                    border-left-color: #10b981;
                }
                
                .toast-item.error,
                .toast-item.danger {
                    border-left-color: #ef4444;
                }
                
                .toast-item.warning {
                    border-left-color: #f59e0b;
                }
                
                .toast-item.info {
                    border-left-color: #3b82f6;
                }
                
                .toast-icon {
                    flex-shrink: 0;
                    width: 20px;
                    height: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 16px;
                }
                
                .toast-icon.success { color: #10b981; }
                .toast-icon.error,
                .toast-icon.danger { color: #ef4444; }
                .toast-icon.warning { color: #f59e0b; }
                .toast-icon.info { color: #3b82f6; }
                
                .toast-content {
                    flex: 1;
                    font-size: 14px;
                    line-height: 1.4;
                    color: var(--text-primary, #1f2937);
                }
                
                .toast-close {
                    flex-shrink: 0;
                    background: none;
                    border: none;
                    color: var(--text-muted, #9ca3af);
                    cursor: pointer;
                    padding: 0;
                    width: 20px;
                    height: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 4px;
                    transition: all 0.2s;
                }
                
                .toast-close:hover {
                    background: rgba(0,0,0,0.05);
                    color: var(--text-primary, #1f2937);
                }
                
                .toast-progress {
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    height: 2px;
                    background: currentColor;
                    opacity: 0.3;
                    animation: toastProgress linear forwards;
                }
                
                @keyframes toastSlideIn {
                    from {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                
                @keyframes toastSlideOut {
                    to {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                }
                
                @keyframes toastProgress {
                    from { width: 100%; }
                    to { width: 0%; }
                }
                
                @media (max-width: 640px) {
                    .toast-system-container {
                        top: 60px;
                        right: 10px;
                        left: 10px;
                    }
                    
                    .toast-item {
                        min-width: auto;
                        max-width: none;
                    }
                }
                
                @media (prefers-reduced-motion: reduce) {
                    .toast-item {
                        animation: none;
                    }
                    
                    .toast-progress {
                        animation: none;
                    }
                }
            `;
            document.head.appendChild(style);
        }
        
        show(message, type = 'info', duration = this.defaultDuration) {
            // Нормализация типа
            if (type === 'danger') type = 'error';
            
            // Если достигнут лимит, добавляем в очередь
            if (this.activeToasts.size >= this.maxToasts) {
                this.queue.push({ message, type, duration });
                return;
            }
            
            const toast = this.createToast(message, type, duration);
            this.container.appendChild(toast);
            this.activeToasts.add(toast);
            
            // Автоматическое удаление
            if (duration > 0) {
                setTimeout(() => {
                    this.remove(toast);
                }, duration);
            }
        }
        
        createToast(message, type, duration) {
            const toast = document.createElement('div');
            toast.className = `toast-item ${type}`;
            toast.setAttribute('role', 'alert');
            
            const icon = this.getIcon(type);
            
            toast.innerHTML = `
                <div class="toast-icon ${type}">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="toast-content">${this.escapeHtml(message)}</div>
                <button class="toast-close" aria-label="Закрыть">
                    <i class="fas fa-times"></i>
                </button>
                ${duration > 0 ? `<div class="toast-progress" style="animation-duration: ${duration}ms;"></div>` : ''}
            `;
            
            // Обработчик закрытия
            toast.querySelector('.toast-close').addEventListener('click', () => {
                this.remove(toast);
            });
            
            return toast;
        }
        
        getIcon(type) {
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            return icons[type] || icons.info;
        }
        
        remove(toast) {
            if (!toast || !toast.parentNode) return;
            
            toast.classList.add('removing');
            this.activeToasts.delete(toast);
            
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
                
                // Показываем следующий из очереди
                if (this.queue.length > 0) {
                    const next = this.queue.shift();
                    this.show(next.message, next.type, next.duration);
                }
            }, 300);
        }
        
        removeAll() {
            this.activeToasts.forEach(toast => {
                this.remove(toast);
            });
            this.queue = [];
        }
        
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }
    
    // Инициализация
    const toastSystem = new ToastSystem();
    
    // Глобальная функция
    window.showToast = function(message, type = 'info', duration = 3000) {
        toastSystem.show(message, type, duration);
    };
    
    // API
    window.toast = {
        success: (msg, duration) => toastSystem.show(msg, 'success', duration),
        error: (msg, duration) => toastSystem.show(msg, 'error', duration),
        warning: (msg, duration) => toastSystem.show(msg, 'warning', duration),
        info: (msg, duration) => toastSystem.show(msg, 'info', duration),
        clear: () => toastSystem.removeAll()
    };
    
})();
