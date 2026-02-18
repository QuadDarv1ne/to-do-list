/**
 * Quick Notes
 * Быстрые заметки
 */

class QuickNotes {
    constructor() {
        this.notes = [];
        this.widget = null;
        this.isOpen = false;
        this.init();
    }

    init() {
        this.loadNotes();
        this.createWidget();
        this.createButton();
    }

    createButton() {
        const button = document.createElement('button');
        button.className = 'quick-notes-fab';
        button.innerHTML = '<i class="fas fa-sticky-note"></i>';
        button.title = 'Быстрые заметки (Ctrl+Shift+N)';
        button.setAttribute('aria-label', 'Открыть быстрые заметки');

        button.addEventListener('click', () => {
            this.toggle();
        });

        document.body.appendChild(button);

        // Горячая клавиша
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.shiftKey && e.key === 'N') {
                e.preventDefault();
                this.toggle();
            }
        });

        this.addStyles();
    }

    createWidget() {
        this.widget = document.createElement('div');
        this.widget.className = 'quick-notes-widget';
        this.widget.style.display = 'none';
        this.widget.innerHTML = `
            <div class="quick-notes-header">
                <h4><i class="fas fa-sticky-note"></i> Заметки</h4>
                <button class="quick-notes-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="quick-notes-body">
                <div class="quick-notes-list"></div>
            </div>
            <div class="quick-notes-footer">
                <textarea class="quick-notes-input" placeholder="Новая заметка..."></textarea>
                <button class="btn btn-sm btn-primary quick-notes-add">
                    <i class="fas fa-plus"></i> Добавить
                </button>
            </div>
        `;

        document.body.appendChild(this.widget);

        // Обработчики
        this.widget.querySelector('.quick-notes-close').addEventListener('click', () => {
            this.close();
        });

        this.widget.querySelector('.quick-notes-add').addEventListener('click', () => {
            this.addNote();
        });

        this.widget.querySelector('.quick-notes-input').addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'Enter') {
                this.addNote();
            }
        });

        this.renderNotes();
    }

    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    open() {
        this.isOpen = true;
        this.widget.style.display = 'flex';
        this.widget.querySelector('.quick-notes-input').focus();
    }

    close() {
        this.isOpen = false;
        this.widget.style.display = 'none';
    }

    addNote() {
        const input = this.widget.querySelector('.quick-notes-input');
        const text = input.value.trim();

        if (!text) return;

        const note = {
            id: Date.now(),
            text: text,
            created: new Date().toISOString(),
            pinned: false
        };

        this.notes.unshift(note);
        this.saveNotes();
        this.renderNotes();

        input.value = '';
        input.focus();

        if (window.showToast) {
            window.showToast('Заметка добавлена', 'success');
        }
    }

    deleteNote(id) {
        if (!confirm('Удалить заметку?')) return;

        this.notes = this.notes.filter(n => n.id !== id);
        this.saveNotes();
        this.renderNotes();

        if (window.showToast) {
            window.showToast('Заметка удалена', 'success');
        }
    }

    togglePin(id) {
        const note = this.notes.find(n => n.id === id);
        if (note) {
            note.pinned = !note.pinned;
            this.saveNotes();
            this.renderNotes();
        }
    }

    renderNotes() {
        const list = this.widget.querySelector('.quick-notes-list');
        
        if (this.notes.length === 0) {
            list.innerHTML = '<div class="quick-notes-empty">Нет заметок</div>';
            return;
        }

        // Сортируем: закрепленные сверху
        const sorted = [...this.notes].sort((a, b) => {
            if (a.pinned && !b.pinned) return -1;
            if (!a.pinned && b.pinned) return 1;
            return 0;
        });

        list.innerHTML = sorted.map(note => `
            <div class="quick-note-item ${note.pinned ? 'pinned' : ''}">
                <div class="quick-note-text">${this.escapeHtml(note.text)}</div>
                <div class="quick-note-meta">
                    <span class="quick-note-date">${this.formatDate(note.created)}</span>
                    <div class="quick-note-actions">
                        <button class="btn-pin" data-id="${note.id}" title="${note.pinned ? 'Открепить' : 'Закрепить'}">
                            <i class="fas fa-thumbtack"></i>
                        </button>
                        <button class="btn-delete" data-id="${note.id}" title="Удалить">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `).join('');

        // Обработчики
        list.querySelectorAll('.btn-pin').forEach(btn => {
            btn.addEventListener('click', () => {
                this.togglePin(parseInt(btn.dataset.id));
            });
        });

        list.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', () => {
                this.deleteNote(parseInt(btn.dataset.id));
            });
        });
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;

        if (diff < 60000) return 'только что';
        if (diff < 3600000) return `${Math.floor(diff / 60000)} мин назад`;
        if (diff < 86400000) return `${Math.floor(diff / 3600000)} ч назад`;
        
        return date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    saveNotes() {
        try {
            localStorage.setItem('quickNotes', JSON.stringify(this.notes));
        } catch (e) {
            console.error('Failed to save notes:', e);
        }
    }

    loadNotes() {
        try {
            const saved = localStorage.getItem('quickNotes');
            if (saved) {
                this.notes = JSON.parse(saved);
            }
        } catch (e) {
            console.error('Failed to load notes:', e);
            this.notes = [];
        }
    }

    addStyles() {
        if (document.getElementById('quickNotesStyles')) return;

        const style = document.createElement('style');
        style.id = 'quickNotesStyles';
        style.textContent = `
            .quick-notes-fab {
                position: fixed;
                bottom: 380px;
                right: 30px;
                width: 50px;
                height: 50px;
                border-radius: 50%;
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                color: white;
                border: none;
                box-shadow: 0 4px 12px rgba(240, 147, 251, 0.4);
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.125rem;
                z-index: 992;
                transition: all 0.3s ease;
            }

            .quick-notes-fab:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 20px rgba(240, 147, 251, 0.5);
            }

            .quick-notes-widget {
                position: fixed;
                bottom: 80px;
                right: 30px;
                width: 350px;
                max-height: 500px;
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
                z-index: 991;
                display: flex;
                flex-direction: column;
            }

            .quick-notes-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1rem;
                border-bottom: 1px solid var(--border);
            }

            .quick-notes-header h4 {
                margin: 0;
                font-size: 1rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                color: var(--text-primary);
            }

            .quick-notes-close {
                background: none;
                border: none;
                color: var(--text-secondary);
                cursor: pointer;
                font-size: 1.125rem;
                padding: 4px;
            }

            .quick-notes-body {
                flex: 1;
                overflow-y: auto;
                padding: 1rem;
            }

            .quick-notes-list {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }

            .quick-note-item {
                background: var(--bg-body);
                border: 1px solid var(--border);
                border-radius: 8px;
                padding: 0.75rem;
                transition: all 0.2s ease;
            }

            .quick-note-item.pinned {
                border-color: var(--primary);
                background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            }

            .quick-note-text {
                color: var(--text-primary);
                margin-bottom: 0.5rem;
                white-space: pre-wrap;
                word-break: break-word;
            }

            .quick-note-meta {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .quick-note-date {
                font-size: 0.75rem;
                color: var(--text-secondary);
            }

            .quick-note-actions {
                display: flex;
                gap: 0.25rem;
            }

            .quick-note-actions button {
                background: none;
                border: none;
                color: var(--text-secondary);
                cursor: pointer;
                padding: 4px;
                font-size: 0.875rem;
                transition: color 0.2s ease;
            }

            .quick-note-actions button:hover {
                color: var(--primary);
            }

            .quick-note-item.pinned .btn-pin {
                color: var(--primary);
            }

            .quick-notes-empty {
                text-align: center;
                color: var(--text-secondary);
                padding: 2rem;
            }

            .quick-notes-footer {
                padding: 1rem;
                border-top: 1px solid var(--border);
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }

            .quick-notes-input {
                width: 100%;
                min-height: 60px;
                padding: 0.5rem;
                border: 1px solid var(--border);
                border-radius: 6px;
                background: var(--bg-body);
                color: var(--text-primary);
                resize: vertical;
                font-family: inherit;
            }

            .quick-notes-input:focus {
                outline: none;
                border-color: var(--primary);
            }

            @media (max-width: 768px) {
                .quick-notes-fab {
                    bottom: 360px;
                    right: 20px;
                    width: 46px;
                    height: 46px;
                }

                .quick-notes-widget {
                    bottom: 70px;
                    right: 20px;
                    left: 20px;
                    width: auto;
                }
            }

            body.focus-mode-active .quick-notes-fab,
            body.focus-mode-active .quick-notes-widget {
                display: none !important;
            }
        `;

        document.head.appendChild(style);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.quickNotes = new QuickNotes();
});

window.QuickNotes = QuickNotes;
