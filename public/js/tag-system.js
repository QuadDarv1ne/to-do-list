/**
 * Tag System
 * Улучшенная система тегов с автодополнением и цветами
 */

class TagSystem {
    constructor() {
        this.tags = [];
        this.selectedTags = new Set();
        this.colors = [
            '#667eea', '#764ba2', '#f093fb', '#f5576c',
            '#4facfe', '#00f2fe', '#43e97b', '#38f9d7',
            '#fa709a', '#fee140', '#30cfd0', '#330867'
        ];
        this.init();
    }

    init() {
        this.loadTags();
        this.setupTagInputs();
        this.setupTagFilters();
        this.createTagManager();
    }

    /**
     * Загрузить теги
     */
    async loadTags() {
        try {
            const response = await fetch('/api/tags');
            if (response.ok) {
                this.tags = await response.json();
            }
        } catch (error) {
            console.error('Failed to load tags:', error);
        }
    }

    /**
     * Настроить поля ввода тегов
     */
    setupTagInputs() {
        document.querySelectorAll('[data-tag-input]').forEach(input => {
            this.enhanceTagInput(input);
        });
    }

    /**
     * Улучшить поле ввода тегов
     */
    enhanceTagInput(input) {
        const container = document.createElement('div');
        container.className = 'tag-input-container';
        
        const tagsDisplay = document.createElement('div');
        tagsDisplay.className = 'tags-display';
        
        const inputWrapper = document.createElement('div');
        inputWrapper.className = 'tag-input-wrapper';
        
        const newInput = document.createElement('input');
        newInput.type = 'text';
        newInput.className = 'tag-input';
        newInput.placeholder = 'Добавить тег...';
        newInput.autocomplete = 'off';
        
        inputWrapper.appendChild(newInput);
        container.appendChild(tagsDisplay);
        container.appendChild(inputWrapper);
        
        input.parentNode.insertBefore(container, input);
        input.style.display = 'none';
        
        // Загрузить существующие теги
        if (input.value) {
            const existingTags = input.value.split(',').filter(t => t.trim());
            existingTags.forEach(tag => {
                this.addTagToDisplay(tagsDisplay, tag.trim(), input);
            });
        }
        
        // Автодополнение
        this.setupAutocomplete(newInput, tagsDisplay, input);
        
        // Обработка ввода
        newInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                const tag = newInput.value.trim();
                if (tag) {
                    this.addTagToDisplay(tagsDisplay, tag, input);
                    newInput.value = '';
                }
            } else if (e.key === 'Backspace' && newInput.value === '') {
                // Удалить последний тег
                const lastTag = tagsDisplay.lastElementChild;
                if (lastTag) {
                    this.removeTag(lastTag, input);
                }
            }
        });

        this.addTagInputStyles();
    }

    /**
     * Настроить автодополнение
     */
    setupAutocomplete(input, tagsDisplay, hiddenInput) {
        let suggestions = null;

        input.addEventListener('input', async (e) => {
            const query = e.target.value.trim();
            
            if (query.length < 1) {
                this.hideAutocomplete();
                return;
            }

            const matches = this.tags.filter(tag => 
                tag.name.toLowerCase().includes(query.toLowerCase())
            );

            if (matches.length > 0) {
                this.showAutocomplete(input, matches, tagsDisplay, hiddenInput);
            } else {
                this.hideAutocomplete();
            }
        });

        input.addEventListener('blur', () => {
            setTimeout(() => this.hideAutocomplete(), 200);
        });
    }

    /**
     * Показать автодополнение
     */
    showAutocomplete(input, matches, tagsDisplay, hiddenInput) {
        this.hideAutocomplete();

        const suggestions = document.createElement('div');
        suggestions.className = 'tag-suggestions';
        suggestions.innerHTML = matches.map(tag => `
            <div class="tag-suggestion" data-tag-id="${tag.id}" data-tag-name="${tag.name}">
                <span class="tag-color" style="background: ${tag.color || this.getRandomColor()}"></span>
                <span class="tag-name">${tag.name}</span>
                <span class="tag-count">${tag.count || 0}</span>
            </div>
        `).join('');

        input.parentElement.appendChild(suggestions);

        // Обработчики
        suggestions.querySelectorAll('.tag-suggestion').forEach(item => {
            item.addEventListener('click', () => {
                this.addTagToDisplay(tagsDisplay, item.dataset.tagName, hiddenInput);
                input.value = '';
                this.hideAutocomplete();
            });
        });
    }

    /**
     * Скрыть автодополнение
     */
    hideAutocomplete() {
        document.querySelectorAll('.tag-suggestions').forEach(el => el.remove());
    }

    /**
     * Добавить тег в отображение
     */
    addTagToDisplay(container, tagName, hiddenInput) {
        // Проверить дубликаты
        const existing = [...container.querySelectorAll('.tag-item')];
        if (existing.some(el => el.dataset.tagName === tagName)) {
            return;
        }

        const tag = this.tags.find(t => t.name === tagName);
        const color = tag?.color || this.getRandomColor();

        const tagElement = document.createElement('div');
        tagElement.className = 'tag-item';
        tagElement.dataset.tagName = tagName;
        tagElement.style.background = color;
        tagElement.innerHTML = `
            <span class="tag-text">${tagName}</span>
            <button type="button" class="tag-remove">
                <i class="fas fa-times"></i>
            </button>
        `;

        container.appendChild(tagElement);

        // Обработчик удаления
        tagElement.querySelector('.tag-remove').addEventListener('click', () => {
            this.removeTag(tagElement, hiddenInput);
        });

        // Обновить скрытое поле
        this.updateHiddenInput(container, hiddenInput);
    }

    /**
     * Удалить тег
     */
    removeTag(tagElement, hiddenInput) {
        tagElement.remove();
        this.updateHiddenInput(tagElement.parentElement, hiddenInput);
    }

    /**
     * Обновить скрытое поле
     */
    updateHiddenInput(container, hiddenInput) {
        const tags = [...container.querySelectorAll('.tag-item')]
            .map(el => el.dataset.tagName);
        hiddenInput.value = tags.join(',');
    }

    /**
     * Получить случайный цвет
     */
    getRandomColor() {
        return this.colors[Math.floor(Math.random() * this.colors.length)];
    }

    /**
     * Настроить фильтры тегов
     */
    setupTagFilters() {
        document.querySelectorAll('[data-tag-filter]').forEach(filter => {
            this.enhanceTagFilter(filter);
        });
    }

    /**
     * Улучшить фильтр тегов
     */
    enhanceTagFilter(filter) {
        const container = document.createElement('div');
        container.className = 'tag-filter-container';
        
        const selected = document.createElement('div');
        selected.className = 'tag-filter-selected';
        selected.innerHTML = '<span class="tag-filter-placeholder">Выберите теги...</span>';
        
        const dropdown = document.createElement('div');
        dropdown.className = 'tag-filter-dropdown';
        
        container.appendChild(selected);
        container.appendChild(dropdown);
        
        filter.parentNode.insertBefore(container, filter);
        filter.style.display = 'none';
        
        // Загрузить теги в dropdown
        this.loadTagsToDropdown(dropdown, filter);
        
        // Переключение dropdown
        selected.addEventListener('click', () => {
            dropdown.classList.toggle('show');
        });
        
        // Закрытие при клике вне
        document.addEventListener('click', (e) => {
            if (!container.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });
    }

    /**
     * Загрузить теги в dropdown
     */
    async loadTagsToDropdown(dropdown, hiddenInput) {
        if (this.tags.length === 0) {
            await this.loadTags();
        }

        dropdown.innerHTML = this.tags.map(tag => `
            <label class="tag-filter-option">
                <input type="checkbox" value="${tag.id}" data-tag-name="${tag.name}">
                <span class="tag-color" style="background: ${tag.color || this.getRandomColor()}"></span>
                <span class="tag-name">${tag.name}</span>
                <span class="tag-count">${tag.count || 0}</span>
            </label>
        `).join('');

        // Обработчики
        dropdown.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                this.updateTagFilter(dropdown, hiddenInput);
            });
        });
    }

    /**
     * Обновить фильтр тегов
     */
    updateTagFilter(dropdown, hiddenInput) {
        const selected = dropdown.parentElement.querySelector('.tag-filter-selected');
        const checked = [...dropdown.querySelectorAll('input:checked')];
        
        if (checked.length === 0) {
            selected.innerHTML = '<span class="tag-filter-placeholder">Выберите теги...</span>';
            hiddenInput.value = '';
        } else {
            const tags = checked.map(cb => cb.dataset.tagName);
            selected.innerHTML = tags.map(tag => `
                <span class="tag-filter-badge">${tag}</span>
            `).join('');
            hiddenInput.value = checked.map(cb => cb.value).join(',');
        }

        // Триггер события изменения
        hiddenInput.dispatchEvent(new Event('change'));
    }

    /**
     * Создать менеджер тегов
     */
    createTagManager() {
        const managerBtn = document.getElementById('manage-tags-btn');
        if (!managerBtn) return;

        managerBtn.addEventListener('click', () => {
            this.showTagManager();
        });
    }

    /**
     * Показать менеджер тегов
     */
    showTagManager() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Управление тегами</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <button class="btn btn-primary" id="create-tag-btn">
                                <i class="fas fa-plus me-2"></i>Создать тег
                            </button>
                        </div>
                        <div class="tag-list" id="tag-manager-list"></div>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });

        // Загрузить теги
        this.renderTagManagerList();

        // Создание тега
        modal.querySelector('#create-tag-btn').addEventListener('click', () => {
            this.showCreateTagForm();
        });
    }

    /**
     * Отрисовать список тегов в менеджере
     */
    async renderTagManagerList() {
        await this.loadTags();
        
        const list = document.getElementById('tag-manager-list');
        if (!list) return;

        list.innerHTML = this.tags.map(tag => `
            <div class="tag-manager-item" data-tag-id="${tag.id}">
                <div class="tag-manager-color" style="background: ${tag.color || this.getRandomColor()}"></div>
                <div class="tag-manager-info">
                    <div class="tag-manager-name">${tag.name}</div>
                    <div class="tag-manager-meta">${tag.count || 0} задач</div>
                </div>
                <div class="tag-manager-actions">
                    <button class="btn btn-sm btn-outline-primary edit-tag" data-tag-id="${tag.id}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger delete-tag" data-tag-id="${tag.id}">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');

        // Обработчики
        list.querySelectorAll('.edit-tag').forEach(btn => {
            btn.addEventListener('click', () => {
                const tagId = btn.dataset.tagId;
                this.showEditTagForm(tagId);
            });
        });

        list.querySelectorAll('.delete-tag').forEach(btn => {
            btn.addEventListener('click', async () => {
                const tagId = btn.dataset.tagId;
                if (confirm('Удалить этот тег?')) {
                    await this.deleteTag(tagId);
                }
            });
        });
    }

    /**
     * Показать форму создания тега
     */
    showCreateTagForm() {
        // Реализация формы создания
        this.showNotification('Функция в разработке', 'info');
    }

    /**
     * Показать форму редактирования тега
     */
    showEditTagForm(tagId) {
        // Реализация формы редактирования
        this.showNotification('Функция в разработке', 'info');
    }

    /**
     * Удалить тег
     */
    async deleteTag(tagId) {
        try {
            const response = await fetch(`/api/tags/${tagId}`, {
                method: 'DELETE'
            });

            if (response.ok) {
                this.showNotification('Тег удален', 'success');
                await this.loadTags();
                this.renderTagManagerList();
            }
        } catch (error) {
            console.error('Delete tag error:', error);
            this.showNotification('Ошибка удаления тега', 'error');
        }
    }

    /**
     * Добавить стили
     */
    addTagInputStyles() {
        if (document.getElementById('tagSystemStyles')) return;

        const style = document.createElement('style');
        style.id = 'tagSystemStyles';
        style.textContent = `
            .tag-input-container {
                border: 1px solid var(--border);
                border-radius: 8px;
                padding: 0.5rem;
                background: var(--bg-card);
            }

            .tags-display {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
                margin-bottom: 0.5rem;
            }

            .tag-item {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.25rem 0.75rem;
                border-radius: 16px;
                color: white;
                font-size: 0.875rem;
                animation: tagAppear 0.3s ease;
            }

            @keyframes tagAppear {
                from {
                    opacity: 0;
                    transform: scale(0.8);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }

            .tag-text {
                font-weight: 500;
            }

            .tag-remove {
                background: transparent;
                border: none;
                color: white;
                cursor: pointer;
                padding: 0;
                width: 16px;
                height: 16px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                transition: background 0.2s ease;
            }

            .tag-remove:hover {
                background: rgba(255,255,255,0.2);
            }

            .tag-input-wrapper {
                position: relative;
            }

            .tag-input {
                border: none;
                outline: none;
                padding: 0.25rem;
                width: 100%;
                background: transparent;
                color: var(--text-primary);
            }

            .tag-suggestions {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                max-height: 200px;
                overflow-y: auto;
                z-index: 1000;
                margin-top: 0.25rem;
            }

            .tag-suggestion {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.75rem;
                cursor: pointer;
                transition: background 0.2s ease;
            }

            .tag-suggestion:hover {
                background: var(--bg-body);
            }

            .tag-color {
                width: 12px;
                height: 12px;
                border-radius: 50%;
                flex-shrink: 0;
            }

            .tag-name {
                flex: 1;
                font-size: 0.875rem;
                color: var(--text-primary);
            }

            .tag-count {
                font-size: 0.75rem;
                color: var(--text-muted);
            }

            .tag-filter-container {
                position: relative;
            }

            .tag-filter-selected {
                border: 1px solid var(--border);
                border-radius: 8px;
                padding: 0.5rem;
                cursor: pointer;
                min-height: 38px;
                display: flex;
                flex-wrap: wrap;
                gap: 0.25rem;
                align-items: center;
            }

            .tag-filter-placeholder {
                color: var(--text-muted);
                font-size: 0.875rem;
            }

            .tag-filter-badge {
                background: var(--primary);
                color: white;
                padding: 0.25rem 0.5rem;
                border-radius: 12px;
                font-size: 0.75rem;
            }

            .tag-filter-dropdown {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                max-height: 300px;
                overflow-y: auto;
                z-index: 1000;
                margin-top: 0.25rem;
                display: none;
            }

            .tag-filter-dropdown.show {
                display: block;
            }

            .tag-filter-option {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.75rem;
                cursor: pointer;
                transition: background 0.2s ease;
            }

            .tag-filter-option:hover {
                background: var(--bg-body);
            }

            .tag-manager-item {
                display: flex;
                align-items: center;
                gap: 1rem;
                padding: 1rem;
                border: 1px solid var(--border);
                border-radius: 8px;
                margin-bottom: 0.5rem;
            }

            .tag-manager-color {
                width: 24px;
                height: 24px;
                border-radius: 50%;
                flex-shrink: 0;
            }

            .tag-manager-info {
                flex: 1;
            }

            .tag-manager-name {
                font-weight: 500;
                color: var(--text-primary);
            }

            .tag-manager-meta {
                font-size: 0.875rem;
                color: var(--text-muted);
            }

            .tag-manager-actions {
                display: flex;
                gap: 0.5rem;
            }
        `;

        document.head.appendChild(style);
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
        window.tagSystem = new TagSystem();
    });
} else {
    window.tagSystem = new TagSystem();
}

// Экспорт
window.TagSystem = TagSystem;
