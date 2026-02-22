/**
 * Enhanced Toast Notifications - улучшенные уведомления
 */

class ToastNotifications {
    constructor() {
        this.container = null;
        this.toasts = [];
        this.init();
    }

    init() {
        this.createContainer();
        window.notify = this;
    }

    createContainer() {
        this.container = document.createElement('div');
        this.container.id = 'toast-container';
        this.container.className = 'toast-container';
        document.body.appendChild(this.container);
    }

    show(message, type = 'info', options = {}) {
        const toast = document.createElement('div');
        toast.className = `toast-item toast-${type}`;
        
        const icons = {
            success: 'check-circle',
            error: 'times-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };

        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas fa-${icons[type] || 'info-circle'}"></i>
            </div>
            <div class="toast-content">
                ${options.title ? `<div class="toast-title">${options.title}</div>` : ''}
                <div class="toast-message">${message}</div>
            </div>
            ${options.dismissible !== false ? `
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            ` : ''}
        `;

        // Progress bar
        const duration = options.duration || 5000;
        if (duration > 0) {
            const progress = document.createElement('div');
            progress.className = 'toast-progress';
            progress.style.animationDuration = duration + 'ms';
            toast.appendChild(progress);
        }

        this.container.appendChild(toast);

        // Auto remove
        if (duration > 0) {
            setTimeout(() => {
                toast.classList.add('toast-exit');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }

        // Sound (optional)
        if (options.sound && type === 'error') {
            this.playSound('error');
        }

        return toast;
    }

    success(message, options = {}) {
        return this.show(message, 'success', options);
    }

    error(message, options = {}) {
        return this.show(message, 'error', { ...options, sound: true });
    }

    warning(message, options = {}) {
        return this.show(message, 'warning', options);
    }

    info(message, options = {}) {
        return this.show(message, 'info', options);
    }

    playSound(type) {
        // Можно добавить звуки
        // const audio = new Audio(`/sounds/${type}.mp3`);
        // audio.play().catch(() => {});
    }

    clear() {
        this.container.innerHTML = '';
    }
}

// CSS
const style = document.createElement('style');
style.textContent = `
    .toast-container {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 10000;
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-width: 380px;
        width: 100%;
        pointer-events: none;
    }

    .toast-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        pointer-events: auto;
        animation: toastSlideIn 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .toast-exit {
        animation: toastSlideOut 0.3s ease forwards;
    }

    @keyframes toastSlideIn {
        from {
            opacity: 0;
            transform: translateX(100%);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes toastSlideOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }

    .toast-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .toast-success .toast-icon {
        background: rgba(34, 197, 94, 0.1);
        color: #22c55e;
    }

    .toast-error .toast-icon {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }

    .toast-warning .toast-icon {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
    }

    .toast-info .toast-icon {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }

    .toast-content {
        flex: 1;
        min-width: 0;
    }

    .toast-title {
        font-weight: 600;
        font-size: 14px;
        color: #212529;
        margin-bottom: 2px;
    }

    .toast-message {
        font-size: 13px;
        color: #6c757d;
        word-wrap: break-word;
    }

    .toast-close {
        background: none;
        border: none;
        color: #adb5bd;
        cursor: pointer;
        padding: 4px;
        margin: -4px;
        transition: color 0.2s;
    }

    .toast-close:hover {
        color: #212529;
    }

    .toast-progress {
        position: absolute;
        bottom: 0;
        left: 0;
        height: 3px;
        background: currentColor;
        opacity: 0.3;
        animation: toastProgress linear forwards;
    }

    .toast-success .toast-progress { background: #22c55e; }
    .toast-error .toast-progress { background: #ef4444; }
    .toast-warning .toast-progress { background: #f59e0b; }
    .toast-info .toast-progress { background: #3b82f6; }

    @keyframes toastProgress {
        from { width: 100%; }
        to { width: 0%; }
    }

    /* Dark theme */
    [data-theme="dark"] .toast-item {
        background: #1e293b;
    }

    [data-theme="dark"] .toast-title {
        color: #f1f5f9;
    }

    [data-theme="dark"] .toast-message {
        color: #94a3b8;
    }

    [data-theme="dark"] .toast-close {
        color: #64748b;
    }

    [data-theme="dark"] .toast-close:hover {
        color: #f1f5f9;
    }

    /* Mobile */
    @media (max-width: 480px) {
        .toast-container {
            top: auto;
            bottom: 20px;
            left: 10px;
            right: 10px;
            max-width: none;
        }
    }
`;
document.head.appendChild(style);

// Initialize
const toastNotifications = new ToastNotifications();
