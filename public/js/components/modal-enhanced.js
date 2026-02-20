/**
 * Enhanced Modal System - Улучшенная система модальных окон
 */

class ModalEnhanced {
    constructor(options = {}) {
        this.options = {
            title: options.title || '',
            content: options.content || '',
            size: options.size || 'md', // sm, md, lg, xl, fullscreen
            closeOnBackdrop: options.closeOnBackdrop !== false,
            closeOnEscape: options.closeOnEscape !== false,
            buttons: options.buttons || [],
            onShow: options.onShow || null,
            onHide: options.onHide || null,
            ...options
        };
        
        this.modal = null;
        this.isOpen = false;
        this.create();
    }

    create() {
        this.modal = document.createElement('div');
        this.modal.className = 'modal-enhanced';
        this.modal.setAttribute('role', 'dialog');
        this.modal.setAttribute('aria-modal', 'true');
        
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop-enhanced';
        
        if (this.options.closeOnBackdrop) {
            backdrop.addEventListener('click', () => this.hide());
        }
        
        const dialog = document.createElement('div');
        dialog.className = `modal-dialog-enhanced modal-${this.options.size}`;
        
        dialog.innerHTML = `
            <div class="modal-header-enhanced">
                <h3 class="modal-title-enhanced">${this.options.title}</h3>
                <button class="modal-close-enhanced" aria-label="Закрыть">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body-enhanced">
                ${this.options.content}
            </div>
            ${this.options.buttons.length > 0 ? `
                <div class="modal-footer-enhanced">
                    ${this.renderButtons()}
                </div>
            ` : ''}
        `;
        
        this.modal.appendChild(backdrop);
        this.modal.appendChild(dialog);
        
        dialog.querySelector('.modal-close-enhanced').addEventListener('click', () => this.hide());
        
        if (this.options.closeOnEscape) {
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.hide();
                }
            });
        }
        
        this.attachButtonHandlers(dialog);
    }

    renderButtons() {
        return this.options.buttons.map((btn, index) => {
            const className = btn.className || 'btn btn-secondary';
            return `<button class="${className}" data-modal-btn="${index}">${btn.text}</button>`;
        }).join('');
    }

    attachButtonHandlers(dialog) {
        this.options.buttons.forEach((btn, index) => {
            const button = dialog.querySelector(`[data-modal-btn="${index}"]`);
            if (button && btn.onClick) {
                button.addEventListener('click', (e) => {
                    const result = btn.onClick(e, this);
                    if (result !== false && btn.close !== false) {
                        this.hide();
                    }
                });
            }
        });
    }

    show() {
        if (this.isOpen) return;
        
        document.body.appendChild(this.modal);
        document.body.style.overflow = 'hidden';
        
        requestAnimationFrame(() => {
            this.modal.classList.add('show', 'animate-in');
            this.isOpen = true;
            
            if (this.options.onShow) {
                this.options.onShow(this);
            }
        });
    }

    hide() {
        if (!this.isOpen) return;
        
        this.modal.classList.remove('show');
        document.body.style.overflow = '';
        
        setTimeout(() => {
            if (this.modal.parentNode) {
                this.modal.parentNode.removeChild(this.modal);
            }
            this.isOpen = false;
            
            if (this.options.onHide) {
                this.options.onHide(this);
            }
        }, 300);
    }

    setContent(content) {
        const body = this.modal.querySelector('.modal-body-enhanced');
        if (body) {
            body.innerHTML = content;
        }
    }

    setTitle(title) {
        const titleEl = this.modal.querySelector('.modal-title-enhanced');
        if (titleEl) {
            titleEl.textContent = title;
        }
    }

    static confirm(message, options = {}) {
        return new Promise((resolve) => {
            const modal = new ModalEnhanced({
                title: options.title || 'Подтверждение',
                content: message,
                size: options.size || 'sm',
                buttons: [
                    {
                        text: options.cancelText || 'Отмена',
                        className: 'btn btn-secondary',
                        onClick: () => resolve(false)
                    },
                    {
                        text: options.confirmText || 'Подтвердить',
                        className: 'btn btn-primary',
                        onClick: () => resolve(true)
                    }
                ]
            });
            modal.show();
        });
    }

    static alert(message, options = {}) {
        return new Promise((resolve) => {
            const modal = new ModalEnhanced({
                title: options.title || 'Уведомление',
                content: message,
                size: options.size || 'sm',
                buttons: [
                    {
                        text: options.buttonText || 'OK',
                        className: 'btn btn-primary',
                        onClick: () => resolve(true)
                    }
                ]
            });
            modal.show();
        });
    }
}

window.ModalEnhanced = ModalEnhanced;

console.log('🪟 Modal Enhanced загружен!');
