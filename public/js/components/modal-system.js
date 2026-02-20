/**
 * Advanced Modal System
 * Продвинутая система модальных окон
 */

class ModalSystem {
    constructor() {
        this.modals = new Map();
        this.init();
    }

    init() {
        this.createModalContainer();
        this.initModalTriggers();
        this.initKeyboardControls();
    }

    createModalContainer() {
        if (!document.querySelector('.modal-container')) {
            const container = document.createElement('div');
            container.className = 'modal-container';
            document.body.appendChild(container);
        }
    }

    initModalTriggers() {
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-modal]');
            if (trigger) {
                e.preventDefault();
                const modalId = trigger.dataset.modal;
                this.open(modalId);
            }

            const closeBtn = e.target.closest('[data-modal-close]');
            if (closeBtn) {
                const modal = closeBtn.closest('.modal');
                if (modal) {
                    this.close(modal.id);
                }
            }
        });
    }

    initKeyboardControls() {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAll();
            }
        });
    }

    open(modalId, options = {}) {
        const modal = this.create(modalId, options);
        const container = document.querySelector('.modal-container');
        
        // Добавляем backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop';
        backdrop.dataset.modalId = modalId;
        backdrop.addEventListener('click', () => this.close(modalId));
        
        container.appendChild(backdrop);
        container.appendChild(modal);

        // Блокируем скролл body
        document.body.style.overflow = 'hidden';

        // Анимация появления
        requestAnimationFrame(() => {
            backdrop.classList.add('show');
            modal.classList.add('show');
        });

        // Фокус на первый input
        setTimeout(() => {
            const firstInput = modal.querySelector('input, textarea, select');
            if (firstInput) firstInput.focus();
        }, 300);

        return modal;
    }

    create(modalId, options = {}) {
        const {
            title = 'Модальное окно',
            content = '',
            size = 'medium',
            buttons = [],
            closable = true
        } = options;

        const modal = document.createElement('div');
        modal.className = `modal modal-${size}`;
        modal.id = modalId;

        modal.innerHTML = `
            <div class="modal-header">
                <h3 class="modal-title">${title}</h3>
                ${closable ? '<button class="modal-close" data-modal-close><i class="fas fa-times"></i></button>' : ''}
            </div>
            <div class="modal-body">
                ${content}
            </div>
            ${buttons.length > 0 ? `
                <div class="modal-footer">
                    ${buttons.map(btn => `
                        <button class="btn ${btn.class || 'btn-secondary'}" data-action="${btn.action || ''}">
                            ${btn.icon ? `<i class="fas fa-${btn.icon}"></i>` : ''}
                            ${btn.label}
                        </button>
                    `).join('')}
                </div>
            ` : ''}
        `;

        // Обработчики кнопок
        buttons.forEach(btn => {
            if (btn.onClick) {
                modal.querySelector(`[data-action="${btn.action}"]`)?.addEventListener('click', btn.onClick);
            }
        });

        this.modals.set(modalId, modal);
        return modal;
    }

    close(modalId) {
        const modal = document.getElementById(modalId);
        const backdrop = document.querySelector(`.modal-backdrop[data-modal-id="${modalId}"]`);

        if (modal) {
            modal.classList.remove('show');
            backdrop?.classList.remove('show');

            setTimeout(() => {
                modal.remove();
                backdrop?.remove();
                this.modals.delete(modalId);

                // Разблокируем скролл если нет других модалок
                if (this.modals.size === 0) {
                    document.body.style.overflow = '';
                }
            }, 300);
        }
    }

    closeAll() {
        this.modals.forEach((modal, id) => {
            this.close(id);
        });
    }

    // Специальные типы модалок
    confirm(title, message, onConfirm, onCancel) {
        return this.open('confirm-modal', {
            title,
            content: `<p>${message}</p>`,
            size: 'small',
            buttons: [
                {
                    label: 'Отмена',
                    class: 'btn-secondary',
                    action: 'cancel',
                    onClick: () => {
                        this.close('confirm-modal');
                        if (onCancel) onCancel();
                    }
                },
                {
                    label: 'Подтвердить',
                    class: 'btn-gradient-primary',
                    action: 'confirm',
                    icon: 'check',
                    onClick: () => {
                        this.close('confirm-modal');
                        if (onConfirm) onConfirm();
                    }
                }
            ]
        });
    }

    alert(title, message, onClose) {
        return this.open('alert-modal', {
            title,
            content: `<p>${message}</p>`,
            size: 'small',
            buttons: [
                {
                    label: 'OK',
                    class: 'btn-gradient-primary',
                    action: 'ok',
                    onClick: () => {
                        this.close('alert-modal');
                        if (onClose) onClose();
                    }
                }
            ]
        });
    }

    prompt(title, message, defaultValue = '', onSubmit, onCancel) {
        const content = `
            <p>${message}</p>
            <input type="text" class="form-control" id="prompt-input" value="${defaultValue}" />
        `;

        const modal = this.open('prompt-modal', {
            title,
            content,
            size: 'small',
            buttons: [
                {
                    label: 'Отмена',
                    class: 'btn-secondary',
                    action: 'cancel',
                    onClick: () => {
                        this.close('prompt-modal');
                        if (onCancel) onCancel();
                    }
                },
                {
                    label: 'OK',
                    class: 'btn-gradient-primary',
                    action: 'submit',
                    onClick: () => {
                        const value = document.getElementById('prompt-input').value;
                        this.close('prompt-modal');
                        if (onSubmit) onSubmit(value);
                    }
                }
            ]
        });

        // Enter для отправки
        modal.querySelector('#prompt-input')?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                modal.querySelector('[data-action="submit"]')?.click();
            }
        });

        return modal;
    }
}

// Стили для модальных окон
const modalStyles = `
    .modal-container {
        position: fixed;
        inset: 0;
        z-index: 9999;
        pointer-events: none;
    }

    .modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        opacity: 0;
        transition: opacity 0.3s ease;
        pointer-events: auto;
    }

    .modal-backdrop.show {
        opacity: 1;
    }

    .modal {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) scale(0.9);
        background: white;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        pointer-events: auto;
    }

    .modal.show {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
    }

    .modal-small {
        width: 90%;
        max-width: 400px;
    }

    .modal-medium {
        width: 90%;
        max-width: 600px;
    }

    .modal-large {
        width: 90%;
        max-width: 900px;
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .modal-title {
        font-size: 1.25rem;
        font-weight: 600;
        margin: 0;
    }

    .modal-close {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        background: transparent;
        color: #6b7280;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .modal-close:hover {
        background: #f3f4f6;
        color: #374151;
    }

    .modal-body {
        padding: 1.5rem;
        overflow-y: auto;
        flex: 1;
    }

    .modal-body p {
        margin: 0 0 1rem 0;
        color: #4b5563;
        line-height: 1.6;
    }

    .modal-footer {
        padding: 1.5rem;
        border-top: 1px solid #e5e7eb;
        display: flex;
        gap: 0.75rem;
        justify-content: flex-end;
    }

    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 0.9375rem;
        transition: all 0.2s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    .btn-secondary {
        padding: 0.75rem 1.5rem;
        border: 1px solid #d1d5db;
        background: white;
        color: #374151;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-secondary:hover {
        background: #f9fafb;
        border-color: #9ca3af;
    }

    @media (max-width: 768px) {
        .modal {
            width: 95%;
            max-width: none;
        }

        .modal-header,
        .modal-body,
        .modal-footer {
            padding: 1rem;
        }
    }
`;

const styleSheet = document.createElement('style');
styleSheet.textContent = modalStyles;
document.head.appendChild(styleSheet);

// Инициализация и экспорт
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.modalSystem = new ModalSystem();
    });
} else {
    window.modalSystem = new ModalSystem();
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = ModalSystem;
}
