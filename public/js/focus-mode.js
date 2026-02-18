/**
 * Focus Mode
 * Режим фокусировки - скрывает отвлекающие элементы
 */

class FocusMode {
    constructor() {
        this.isActive = false;
        this.button = null;
        this.hiddenElements = [];
        this.init();
    }

    init() {
        this.createButton();
        this.loadState();
    }

    createButton() {
        this.button = document.createElement('button');
        this.button.className = 'focus-mode-button';
        this.button.innerHTML = '<i class="fas fa-eye-slash"></i>';
        this.button.title = 'Режим фокусировки (F)';
        this.button.setAttribute('aria-label', 'Переключить режим фокусировки');

        this.button.addEventListener('click', () => {
            this.toggle();
        });

        document.body.appendChild(this.button);

        // Горячая клавиша F
        document.addEventListener('keydown', (e) => {
            if (e.key === 'f' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                    return;
                }
                e.preventDefault();
                this.toggle();
            }
        });

        this.addStyles();
    }

    toggle() {
        if (this.isActive) {
            this.deactivate();
        } else {
            this.activate();
        }
    }

    activate() {
        this.isActive = true;
        
        // Скрываем элементы
        const selectors = [
            '.navbar',
            '.sidebar',
            '.footer-enhanced',
            '.quick-actions-fab',
            '.voice-command-button',
            '.undo-redo-buttons',
            '.fullscreen-button',
            '.print-button'
        ];

        selectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(el => {
                if (el !== this.button && !el.classList.contains('focus-mode-button')) {
                    el.dataset.focusHidden = 'true';
                    el.style.display = 'none';
                    this.hiddenElements.push(el);
                }
            });
        });

        // Добавляем класс к body
        document.body.classList.add('focus-mode-active');
        
        // Обновляем кнопку
        this.button.innerHTML = '<i class="fas fa-eye"></i>';
        this.button.title = 'Выйти из режима фокусировки (F)';
        
        this.saveState();
        
        if (window.showToast) {
            window.showToast('Режим фокусировки активирован', 'info');
        }
    }

    deactivate() {
        this.isActive = false;
        
        // Показываем скрытые элементы
        this.hiddenElements.forEach(el => {
            el.style.display = '';
            delete el.dataset.focusHidden;
        });
        this.hiddenElements = [];

        // Удаляем класс с body
        document.body.classList.remove('focus-mode-active');
        
        // Обновляем кнопку
        this.button.innerHTML = '<i class="fas fa-eye-slash"></i>';
        this.button.title = 'Режим фокусировки (F)';
        
        this.saveState();
        
        if (window.showToast) {
            window.showToast('Режим фокусировки деактивирован', 'info');
        }
    }

    saveState() {
        try {
            localStorage.setItem('focusModeActive', this.isActive);
        } catch (e) {
            console.error('Failed to save focus mode state:', e);
        }
    }

    loadState() {
        try {
            const saved = localStorage.getItem('focusModeActive');
            if (saved === 'true') {
                this.activate();
            }
        } catch (e) {
            console.error('Failed to load focus mode state:', e);
        }
    }

    addStyles() {
        if (document.getElementById('focusModeStyles')) return;

        const style = document.createElement('style');
        style.id = 'focusModeStyles';
        style.textContent = `
            .focus-mode-button {
                position: fixed;
                bottom: 270px;
                right: 30px;
                width: 44px;
                height: 44px;
                border-radius: 50%;
                background: var(--bg-card);
                border: 1px solid var(--border);
                color: var(--text-primary);
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1rem;
                z-index: 995;
                transition: all 0.2s ease;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .focus-mode-button:hover {
                background: var(--primary);
                color: white;
                transform: scale(1.05);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            }

            body.focus-mode-active {
                padding-top: 0 !important;
            }

            body.focus-mode-active main {
                margin-top: 2rem;
            }

            @media (max-width: 768px) {
                .focus-mode-button {
                    bottom: 250px;
                    right: 20px;
                    width: 40px;
                    height: 40px;
                }
            }
        `;

        document.head.appendChild(style);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.focusMode = new FocusMode();
});

window.FocusMode = FocusMode;
