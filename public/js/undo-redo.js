/**
 * Undo/Redo Manager
 * Система отмены и повтора действий
 */

class UndoRedoManager {
    constructor() {
        this.history = [];
        this.currentIndex = -1;
        this.maxHistory = 50;
        this.init();
    }

    init() {
        this.bindKeyboardShortcuts();
        this.createButtons();
    }

    bindKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl+Z для отмены
            if (e.ctrlKey && e.key === 'z' && !e.shiftKey) {
                e.preventDefault();
                this.undo();
            }
            
            // Ctrl+Shift+Z или Ctrl+Y для повтора
            if ((e.ctrlKey && e.shiftKey && e.key === 'z') || (e.ctrlKey && e.key === 'y')) {
                e.preventDefault();
                this.redo();
            }
        });
    }

    createButtons() {
        const container = document.createElement('div');
        container.className = 'undo-redo-buttons';
        container.innerHTML = `
            <button class="undo-button" title="Отменить (Ctrl+Z)" disabled>
                <i class="fas fa-undo"></i>
            </button>
            <button class="redo-button" title="Повторить (Ctrl+Shift+Z)" disabled>
                <i class="fas fa-redo"></i>
            </button>
        `;

        document.body.appendChild(container);

        container.querySelector('.undo-button').addEventListener('click', () => this.undo());
        container.querySelector('.redo-button').addEventListener('click', () => this.redo());

        this.undoButton = container.querySelector('.undo-button');
        this.redoButton = container.querySelector('.redo-button');

        this.addStyles();
    }

    record(action) {
        // Удаляем все действия после текущего индекса
        this.history = this.history.slice(0, this.currentIndex + 1);
        
        // Добавляем новое действие
        this.history.push(action);
        this.currentIndex++;

        // Ограничиваем размер истории
        if (this.history.length > this.maxHistory) {
            this.history.shift();
            this.currentIndex--;
        }

        this.updateButtons();
    }

    undo() {
        if (this.currentIndex < 0) return;

        const action = this.history[this.currentIndex];
        if (action && action.undo) {
            action.undo();
            this.currentIndex--;
            this.updateButtons();
            
            if (window.showToast) {
                window.showToast('Действие отменено', 'info');
            }
        }
    }

    redo() {
        if (this.currentIndex >= this.history.length - 1) return;

        this.currentIndex++;
        const action = this.history[this.currentIndex];
        
        if (action && action.redo) {
            action.redo();
            this.updateButtons();
            
            if (window.showToast) {
                window.showToast('Действие повторено', 'info');
            }
        }
    }

    updateButtons() {
        if (this.undoButton) {
            this.undoButton.disabled = this.currentIndex < 0;
        }
        if (this.redoButton) {
            this.redoButton.disabled = this.currentIndex >= this.history.length - 1;
        }
    }

    clear() {
        this.history = [];
        this.currentIndex = -1;
        this.updateButtons();
    }

    addStyles() {
        if (document.getElementById('undoRedoStyles')) return;

        const style = document.createElement('style');
        style.id = 'undoRedoStyles';
        style.textContent = `
            .undo-redo-buttons {
                position: fixed;
                bottom: 160px;
                right: 30px;
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                z-index: 997;
            }

            .undo-button,
            .redo-button {
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
                transition: all 0.2s ease;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .undo-button:hover:not(:disabled),
            .redo-button:hover:not(:disabled) {
                background: var(--primary);
                color: white;
                transform: scale(1.05);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            }

            .undo-button:disabled,
            .redo-button:disabled {
                opacity: 0.4;
                cursor: not-allowed;
            }

            @media (max-width: 768px) {
                .undo-redo-buttons {
                    bottom: 150px;
                    right: 20px;
                }

                .undo-button,
                .redo-button {
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
    window.undoRedoManager = new UndoRedoManager();
    
    // Глобальные функции
    window.recordAction = (action) => {
        window.undoRedoManager.record(action);
    };
});

window.UndoRedoManager = UndoRedoManager;
