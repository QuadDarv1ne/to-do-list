/**
 * Collaboration Tools
 * Инструменты для совместной работы
 */

class CollaborationTools {
    constructor() {
        this.activeUsers = new Map();
        this.mentions = [];
        this.comments = [];
        this.updateInterval = 30000; // 30 секунд
        this.init();
    }

    init() {
        this.setupMentions();
        this.setupComments();
        this.setupPresence();
        this.setupRealTimeEditing();
        this.startPresenceUpdates();
    }

    /**
     * Настроить упоминания (@mentions)
     */
    setupMentions() {
        document.addEventListener('input', (e) => {
            if (e.target.matches('textarea, [contenteditable="true"]')) {
                this.handleMentionInput(e.target);
            }
        });
    }

    /**
     * Обработать ввод упоминаний
     */
    handleMentionInput(element) {
        const text = element.value || element.textContent;
        const cursorPos = element.selectionStart || text.length;
        
        // Найти последний @
        const beforeCursor = text.substring(0, cursorPos);
        const lastAtIndex = beforeCursor.lastIndexOf('@');
        
        if (lastAtIndex === -1) {
            this.hideMentionSuggestions();
            return;
        }

        const afterAt = beforeCursor.substring(lastAtIndex + 1);
        
        // Проверить, что после @ нет пробелов
        if (afterAt.includes(' ')) {
            this.hideMentionSuggestions();
            return;
        }

        // Показать подсказки
        this.showMentionSuggestions(element, afterAt, lastAtIndex);
    }

    /**
     * Показать подсказки упоминаний
     */
    async showMentionSuggestions(element, query, position) {
        try {
            const response = await fetch(`/api/users/search?q=${encodeURIComponent(query)}`);
            if (!response.ok) return;

            const users = await response.json();
            
            if (users.length === 0) {
                this.hideMentionSuggestions();
                return;
            }

            this.renderMentionSuggestions(element, users, position);
        } catch (error) {
            console.error('Failed to load mention suggestions:', error);
        }
    }

    /**
     * Отрисовать подсказки упоминаний
     */
    renderMentionSuggestions(element, users, position) {
        // Удалить старые подсказки
        this.hideMentionSuggestions();

        const suggestions = document.createElement('div');
        suggestions.className = 'mention-suggestions';
        suggestions.innerHTML = users.map((user, index) => `
            <div class="mention-suggestion-item" data-user-id="${user.id}" data-username="${user.username}" data-index="${index}">
                <div class="mention-avatar">
                    ${user.avatar ? `<img src="${user.avatar}" alt="${user.fullName}">` : 
                      `<div class="avatar-placeholder">${user.fullName.charAt(0)}</div>`}
                </div>
                <div class="mention-info">
                    <div class="mention-name">${user.fullName}</div>
                    <div class="mention-username">@${user.username}</div>
                </div>
            </div>
        `).join('');

        // Позиционирование
        const rect = element.getBoundingClientRect();
        suggestions.style.position = 'absolute';
        suggestions.style.top = `${rect.bottom + window.scrollY}px`;
        suggestions.style.left = `${rect.left + window.scrollX}px`;
        suggestions.style.width = `${Math.min(300, rect.width)}px`;

        document.body.appendChild(suggestions);

        // Обработчики
        suggestions.querySelectorAll('.mention-suggestion-item').forEach(item => {
            item.addEventListener('click', () => {
                this.insertMention(element, item.dataset.username, position);
                this.hideMentionSuggestions();
            });
        });

        // Навигация клавиатурой
        this.setupMentionKeyboardNav(element, suggestions, position);

        this.addMentionStyles();
    }

    /**
     * Настроить навигацию клавиатурой для упоминаний
     */
    setupMentionKeyboardNav(element, suggestions, position) {
        const items = suggestions.querySelectorAll('.mention-suggestion-item');
        let selectedIndex = 0;

        const keyHandler = (e) => {
            if (!suggestions.parentElement) {
                element.removeEventListener('keydown', keyHandler);
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                this.updateMentionSelection(items, selectedIndex);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, 0);
                this.updateMentionSelection(items, selectedIndex);
            } else if (e.key === 'Enter' || e.key === 'Tab') {
                e.preventDefault();
                const selected = items[selectedIndex];
                if (selected) {
                    this.insertMention(element, selected.dataset.username, position);
                    this.hideMentionSuggestions();
                }
            } else if (e.key === 'Escape') {
                this.hideMentionSuggestions();
            }
        };

        element.addEventListener('keydown', keyHandler);
        this.updateMentionSelection(items, selectedIndex);
    }

    /**
     * Обновить выбор упоминания
     */
    updateMentionSelection(items, selectedIndex) {
        items.forEach((item, index) => {
            item.classList.toggle('selected', index === selectedIndex);
        });
    }

    /**
     * Вставить упоминание
     */
    insertMention(element, username, position) {
        const text = element.value || element.textContent;
        const before = text.substring(0, position);
        const after = text.substring(element.selectionStart || text.length);
        
        const newText = `${before}@${username} ${after}`;
        
        if (element.tagName === 'TEXTAREA' || element.tagName === 'INPUT') {
            element.value = newText;
            element.selectionStart = element.selectionEnd = position + username.length + 2;
        } else {
            element.textContent = newText;
        }

        element.focus();
    }

    /**
     * Скрыть подсказки упоминаний
     */
    hideMentionSuggestions() {
        document.querySelectorAll('.mention-suggestions').forEach(el => el.remove());
    }

    /**
     * Добавить стили упоминаний
     */
    addMentionStyles() {
        if (document.getElementById('mentionStyles')) return;

        const style = document.createElement('style');
        style.id = 'mentionStyles';
        style.textContent = `
            .mention-suggestions {
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                max-height: 300px;
                overflow-y: auto;
                z-index: 10000;
            }

            .mention-suggestion-item {
                padding: 0.75rem;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                cursor: pointer;
                transition: background 0.2s ease;
            }

            .mention-suggestion-item:hover,
            .mention-suggestion-item.selected {
                background: var(--bg-body);
            }

            .mention-avatar {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                overflow: hidden;
                flex-shrink: 0;
            }

            .mention-avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .avatar-placeholder {
                width: 100%;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: var(--primary);
                color: white;
                font-weight: 600;
            }

            .mention-info {
                flex: 1;
            }

            .mention-name {
                font-weight: 500;
                color: var(--text-primary);
            }

            .mention-username {
                font-size: 0.875rem;
                color: var(--text-muted);
            }

            .presence-indicator {
                position: fixed;
                top: 70px;
                right: 20px;
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: 8px;
                padding: 1rem;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                max-width: 250px;
                z-index: 1000;
            }

            .presence-title {
                font-size: 0.875rem;
                font-weight: 600;
                margin-bottom: 0.75rem;
                color: var(--text-primary);
            }

            .presence-user {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.5rem;
                border-radius: 4px;
                margin-bottom: 0.25rem;
            }

            .presence-user:hover {
                background: var(--bg-body);
            }

            .presence-avatar {
                width: 24px;
                height: 24px;
                border-radius: 50%;
                position: relative;
            }

            .presence-status {
                position: absolute;
                bottom: 0;
                right: 0;
                width: 8px;
                height: 8px;
                border-radius: 50%;
                border: 2px solid var(--bg-card);
            }

            .presence-status.online {
                background: #28a745;
            }

            .presence-status.away {
                background: #ffc107;
            }

            .presence-name {
                font-size: 0.875rem;
                color: var(--text-primary);
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Настроить комментарии
     */
    setupComments() {
        // Улучшенная система комментариев с реакциями
        document.querySelectorAll('.comment-item').forEach(comment => {
            this.enhanceComment(comment);
        });
    }

    /**
     * Улучшить комментарий
     */
    enhanceComment(comment) {
        // Добавить кнопки реакций
        if (!comment.querySelector('.comment-reactions')) {
            const reactions = document.createElement('div');
            reactions.className = 'comment-reactions';
            reactions.innerHTML = `
                <button class="reaction-btn" data-reaction="👍" title="Нравится">👍</button>
                <button class="reaction-btn" data-reaction="❤️" title="Любовь">❤️</button>
                <button class="reaction-btn" data-reaction="😄" title="Смешно">😄</button>
                <button class="reaction-btn" data-reaction="🎉" title="Празднование">🎉</button>
            `;

            comment.appendChild(reactions);

            // Обработчики реакций
            reactions.querySelectorAll('.reaction-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    this.addReaction(comment.dataset.commentId, btn.dataset.reaction);
                });
            });
        }
    }

    /**
     * Добавить реакцию
     */
    async addReaction(commentId, reaction) {
        try {
            const response = await fetch(`/api/comments/${commentId}/reactions`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ reaction })
            });

            if (response.ok) {
                this.showNotification('Реакция добавлена', 'success');
            }
        } catch (error) {
            console.error('Failed to add reaction:', error);
        }
    }

    /**
     * Настроить присутствие
     */
    setupPresence() {
        this.createPresenceIndicator();
        this.updatePresence();
    }

    /**
     * Создать индикатор присутствия
     */
    createPresenceIndicator() {
        if (document.getElementById('presence-indicator')) return;

        const indicator = document.createElement('div');
        indicator.id = 'presence-indicator';
        indicator.className = 'presence-indicator';
        indicator.innerHTML = `
            <div class="presence-title">
                <i class="fas fa-users me-2"></i>
                Онлайн
            </div>
            <div id="presence-list"></div>
        `;

        document.body.appendChild(indicator);
    }

    /**
     * Обновить присутствие
     */
    async updatePresence() {
        try {
            const response = await fetch('/api/presence/active');
            if (!response.ok) return;

            const users = await response.json();
            this.renderPresence(users);
        } catch (error) {
            console.error('Failed to update presence:', error);
        }
    }

    /**
     * Отрисовать присутствие
     */
    renderPresence(users) {
        const list = document.getElementById('presence-list');
        if (!list) return;

        if (users.length === 0) {
            list.innerHTML = '<div class="text-muted small">Никого нет онлайн</div>';
            return;
        }

        list.innerHTML = users.map(user => `
            <div class="presence-user">
                <div class="presence-avatar">
                    ${user.avatar ? `<img src="${user.avatar}" alt="${user.name}">` : 
                      `<div class="avatar-placeholder">${user.name.charAt(0)}</div>`}
                    <div class="presence-status ${user.status}"></div>
                </div>
                <div class="presence-name">${user.name}</div>
            </div>
        `).join('');
    }

    /**
     * Начать обновления присутствия
     */
    startPresenceUpdates() {
        setInterval(() => {
            this.updatePresence();
        }, this.updateInterval);

        // Отправлять heartbeat
        setInterval(() => {
            this.sendHeartbeat();
        }, 15000); // Каждые 15 секунд
    }

    /**
     * Отправить heartbeat
     */
    async sendHeartbeat() {
        try {
            await fetch('/api/presence/heartbeat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
        } catch (error) {
            // Игнорировать ошибки heartbeat
        }
    }

    /**
     * Настроить редактирование в реальном времени
     */
    setupRealTimeEditing() {
        // Показывать, кто сейчас редактирует
        document.querySelectorAll('[data-collaborative]').forEach(element => {
            this.makeCollaborative(element);
        });
    }

    /**
     * Сделать элемент совместным
     */
    makeCollaborative(element) {
        let typingTimeout;

        element.addEventListener('input', () => {
            clearTimeout(typingTimeout);
            
            // Показать индикатор "печатает"
            this.showTypingIndicator(element);

            typingTimeout = setTimeout(() => {
                this.hideTypingIndicator(element);
            }, 3000);
        });
    }

    /**
     * Показать индикатор печати
     */
    showTypingIndicator(element) {
        // Отправить уведомление другим пользователям
        this.broadcastTyping(element.dataset.resourceId);
    }

    /**
     * Скрыть индикатор печати
     */
    hideTypingIndicator(element) {
        // Отправить уведомление о прекращении печати
        this.broadcastStopTyping(element.dataset.resourceId);
    }

    /**
     * Транслировать печать
     */
    async broadcastTyping(resourceId) {
        try {
            await fetch('/api/collaboration/typing', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ resourceId })
            });
        } catch (error) {
            // Игнорировать ошибки
        }
    }

    /**
     * Транслировать прекращение печати
     */
    async broadcastStopTyping(resourceId) {
        try {
            await fetch('/api/collaboration/stop-typing', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ resourceId })
            });
        } catch (error) {
            // Игнорировать ошибки
        }
    }

    /**
     * Показать уведомление
     */
    showNotification(message, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        }
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.collaborationTools = new CollaborationTools();
    });
} else {
    window.collaborationTools = new CollaborationTools();
}

// Экспорт
window.CollaborationTools = CollaborationTools;
