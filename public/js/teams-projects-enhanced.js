/**
 * Teams & Projects Enhanced
 * Улучшенное управление командами и проектами
 */

class TeamsProjectsEnhanced {
    constructor() {
        this.init();
    }

    init() {
        this.initTeamManagement();
        this.initProjectManagement();
        this.initCollaboration();
        this.initActivityFeed();
    }

    /**
     * Управление командами
     */
    initTeamManagement() {
        // Добавление участников команды
        const addMemberBtns = document.querySelectorAll('[data-add-team-member]');
        addMemberBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const teamId = btn.dataset.teamId;
                this.showAddMemberModal(teamId);
            });
        });

        // Удаление участников
        document.addEventListener('click', (e) => {
            const removeBtn = e.target.closest('[data-remove-team-member]');
            if (removeBtn) {
                const teamId = removeBtn.dataset.teamId;
                const userId = removeBtn.dataset.userId;
                this.removeMember(teamId, userId);
            }
        });

        // Изменение роли участника
        document.addEventListener('change', (e) => {
            if (e.target.matches('[data-member-role]')) {
                const teamId = e.target.dataset.teamId;
                const userId = e.target.dataset.userId;
                const role = e.target.value;
                this.updateMemberRole(teamId, userId, role);
            }
        });

        // Поиск участников
        const memberSearch = document.querySelector('[data-member-search]');
        if (memberSearch) {
            memberSearch.addEventListener('input', (e) => {
                this.searchMembers(e.target.value);
            });
        }
    }

    /**
     * Показать модальное окно добавления участника
     */
    showAddMemberModal(teamId) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Добавить участника</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Поиск пользователя</label>
                            <input type="text" class="form-control" id="userSearch" placeholder="Введите имя или email">
                            <div id="userSearchResults" class="mt-2"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Роль</label>
                            <select class="form-select" id="memberRole">
                                <option value="member">Участник</option>
                                <option value="manager">Менеджер</option>
                                <option value="admin">Администратор</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="button" class="btn btn-primary" id="addMemberBtn">Добавить</button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        // Поиск пользователей
        const searchInput = modal.querySelector('#userSearch');
        const resultsContainer = modal.querySelector('#userSearchResults');
        let selectedUserId = null;

        searchInput.addEventListener('input', async (e) => {
            const query = e.target.value;
            if (query.length < 2) {
                resultsContainer.innerHTML = '';
                return;
            }

            try {
                const response = await fetch(`/api/users/search?q=${encodeURIComponent(query)}`);
                const users = await response.json();

                resultsContainer.innerHTML = users.map(user => `
                    <div class="user-result p-2 border rounded mb-2" style="cursor: pointer;" data-user-id="${user.id}">
                        <div class="d-flex align-items-center">
                            <div class="user-avatar me-2" style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center;">
                                ${user.firstName[0]}${user.lastName[0]}
                            </div>
                            <div>
                                <div class="fw-semibold">${user.fullName}</div>
                                <div class="small text-muted">${user.email}</div>
                            </div>
                        </div>
                    </div>
                `).join('');

                // Выбор пользователя
                resultsContainer.querySelectorAll('.user-result').forEach(result => {
                    result.addEventListener('click', () => {
                        resultsContainer.querySelectorAll('.user-result').forEach(r => r.classList.remove('border-primary'));
                        result.classList.add('border-primary');
                        selectedUserId = result.dataset.userId;
                    });
                });
            } catch (error) {
                console.error('User search error:', error);
            }
        });

        // Добавление участника
        modal.querySelector('#addMemberBtn').addEventListener('click', async () => {
            if (!selectedUserId) {
                alert('Выберите пользователя');
                return;
            }

            const role = modal.querySelector('#memberRole').value;
            await this.addMember(teamId, selectedUserId, role);
            bsModal.hide();
        });

        // Удаление модального окна после закрытия
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }

    /**
     * Добавить участника в команду
     */
    async addMember(teamId, userId, role) {
        try {
            const response = await fetch(`/api/teams/${teamId}/members`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ userId, role })
            });

            if (response.ok) {
                this.showToast('Участник добавлен в команду', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error('Failed to add member');
            }
        } catch (error) {
            console.error('Add member error:', error);
            this.showToast('Ошибка добавления участника', 'error');
        }
    }

    /**
     * Удалить участника из команды
     */
    async removeMember(teamId, userId) {
        if (!confirm('Удалить участника из команды?')) return;

        try {
            const response = await fetch(`/api/teams/${teamId}/members/${userId}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                this.showToast('Участник удален из команды', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error('Failed to remove member');
            }
        } catch (error) {
            console.error('Remove member error:', error);
            this.showToast('Ошибка удаления участника', 'error');
        }
    }

    /**
     * Обновить роль участника
     */
    async updateMemberRole(teamId, userId, role) {
        try {
            const response = await fetch(`/api/teams/${teamId}/members/${userId}/role`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ role })
            });

            if (response.ok) {
                this.showToast('Роль участника обновлена', 'success');
            } else {
                throw new Error('Failed to update role');
            }
        } catch (error) {
            console.error('Update role error:', error);
            this.showToast('Ошибка обновления роли', 'error');
        }
    }

    /**
     * Поиск участников
     */
    searchMembers(query) {
        const members = document.querySelectorAll('[data-member-item]');
        const search = query.toLowerCase();

        members.forEach(member => {
            const name = member.dataset.memberName.toLowerCase();
            const email = member.dataset.memberEmail.toLowerCase();
            const match = name.includes(search) || email.includes(search);
            member.style.display = match ? '' : 'none';
        });
    }

    /**
     * Управление проектами
     */
    initProjectManagement() {
        // Создание проекта
        const createProjectBtn = document.querySelector('[data-create-project]');
        if (createProjectBtn) {
            createProjectBtn.addEventListener('click', () => {
                this.showCreateProjectModal();
            });
        }

        // Архивирование проекта
        document.addEventListener('click', (e) => {
            const archiveBtn = e.target.closest('[data-archive-project]');
            if (archiveBtn) {
                const projectId = archiveBtn.dataset.projectId;
                this.archiveProject(projectId);
            }
        });

        // Фильтрация проектов
        const projectFilter = document.querySelector('[data-project-filter]');
        if (projectFilter) {
            projectFilter.addEventListener('change', (e) => {
                this.filterProjects(e.target.value);
            });
        }

        // Сортировка проектов
        const projectSort = document.querySelector('[data-project-sort]');
        if (projectSort) {
            projectSort.addEventListener('change', (e) => {
                this.sortProjects(e.target.value);
            });
        }
    }

    /**
     * Показать модальное окно создания проекта
     */
    showCreateProjectModal() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Создать проект</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="createProjectForm">
                            <div class="mb-3">
                                <label class="form-label">Название проекта *</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Описание</label>
                                <textarea class="form-control" name="description" rows="3"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Дата начала</label>
                                    <input type="date" class="form-control" name="startDate">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Дата окончания</label>
                                    <input type="date" class="form-control" name="endDate">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Команда</label>
                                <select class="form-select" name="teamId">
                                    <option value="">Без команды</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Приоритет</label>
                                <select class="form-select" name="priority">
                                    <option value="low">Низкий</option>
                                    <option value="medium" selected>Средний</option>
                                    <option value="high">Высокий</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Статус</label>
                                <select class="form-select" name="status">
                                    <option value="planning">Планирование</option>
                                    <option value="active" selected>Активный</option>
                                    <option value="on_hold">На паузе</option>
                                    <option value="completed">Завершен</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="button" class="btn btn-primary" id="saveProjectBtn">Создать проект</button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();

        // Загрузка команд
        this.loadTeamsForSelect(modal.querySelector('select[name="teamId"]'));

        // Сохранение проекта
        modal.querySelector('#saveProjectBtn').addEventListener('click', async () => {
            const form = modal.querySelector('#createProjectForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());

            await this.createProject(data);
            bsModal.hide();
        });

        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }

    /**
     * Загрузить команды для выбора
     */
    async loadTeamsForSelect(select) {
        try {
            const response = await fetch('/api/teams');
            const teams = await response.json();

            teams.forEach(team => {
                const option = document.createElement('option');
                option.value = team.id;
                option.textContent = team.name;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Load teams error:', error);
        }
    }

    /**
     * Создать проект
     */
    async createProject(data) {
        try {
            const response = await fetch('/api/projects', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });

            if (response.ok) {
                this.showToast('Проект создан', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error('Failed to create project');
            }
        } catch (error) {
            console.error('Create project error:', error);
            this.showToast('Ошибка создания проекта', 'error');
        }
    }

    /**
     * Архивировать проект
     */
    async archiveProject(projectId) {
        if (!confirm('Архивировать проект?')) return;

        try {
            const response = await fetch(`/api/projects/${projectId}/archive`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                this.showToast('Проект архивирован', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                throw new Error('Failed to archive project');
            }
        } catch (error) {
            console.error('Archive project error:', error);
            this.showToast('Ошибка архивирования проекта', 'error');
        }
    }

    /**
     * Фильтрация проектов
     */
    filterProjects(filter) {
        const projects = document.querySelectorAll('[data-project-item]');

        projects.forEach(project => {
            const status = project.dataset.projectStatus;
            const match = filter === 'all' || status === filter;
            project.style.display = match ? '' : 'none';
        });
    }

    /**
     * Сортировка проектов
     */
    sortProjects(sortBy) {
        const container = document.querySelector('[data-projects-container]');
        if (!container) return;

        const projects = Array.from(container.querySelectorAll('[data-project-item]'));

        projects.sort((a, b) => {
            if (sortBy === 'name') {
                return a.dataset.projectName.localeCompare(b.dataset.projectName);
            } else if (sortBy === 'date') {
                return new Date(b.dataset.projectDate) - new Date(a.dataset.projectDate);
            } else if (sortBy === 'priority') {
                const priorities = { high: 3, medium: 2, low: 1 };
                return priorities[b.dataset.projectPriority] - priorities[a.dataset.projectPriority];
            }
            return 0;
        });

        projects.forEach(project => container.appendChild(project));
    }

    /**
     * Совместная работа
     */
    initCollaboration() {
        // Комментарии в реальном времени
        this.initRealtimeComments();
        
        // Упоминания пользователей
        this.initMentions();
        
        // Уведомления о действиях
        this.initActionNotifications();
    }

    /**
     * Комментарии в реальном времени
     */
    initRealtimeComments() {
        const commentForm = document.querySelector('[data-comment-form]');
        if (!commentForm) return;

        commentForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const textarea = commentForm.querySelector('textarea');
            const content = textarea.value.trim();
            
            if (!content) return;

            const entityType = commentForm.dataset.entityType;
            const entityId = commentForm.dataset.entityId;

            await this.postComment(entityType, entityId, content);
            textarea.value = '';
        });
    }

    /**
     * Отправить комментарий
     */
    async postComment(entityType, entityId, content) {
        try {
            const response = await fetch(`/api/${entityType}/${entityId}/comments`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ content })
            });

            if (response.ok) {
                const comment = await response.json();
                this.addCommentToList(comment);
                this.showToast('Комментарий добавлен', 'success');
            } else {
                throw new Error('Failed to post comment');
            }
        } catch (error) {
            console.error('Post comment error:', error);
            this.showToast('Ошибка добавления комментария', 'error');
        }
    }

    /**
     * Добавить комментарий в список
     */
    addCommentToList(comment) {
        const container = document.querySelector('[data-comments-list]');
        if (!container) return;

        const commentEl = document.createElement('div');
        commentEl.className = 'comment-item mb-3 p-3 border rounded';
        commentEl.innerHTML = `
            <div class="d-flex">
                <div class="user-avatar me-3">
                    ${comment.author.firstName[0]}${comment.author.lastName[0]}
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between">
                        <strong>${comment.author.fullName}</strong>
                        <small class="text-muted">только что</small>
                    </div>
                    <div class="mt-2">${comment.content}</div>
                </div>
            </div>
        `;

        container.insertBefore(commentEl, container.firstChild);
    }

    /**
     * Упоминания пользователей
     */
    initMentions() {
        const textareas = document.querySelectorAll('[data-mentions]');
        
        textareas.forEach(textarea => {
            textarea.addEventListener('input', (e) => {
                const value = e.target.value;
                const cursorPos = e.target.selectionStart;
                const textBeforeCursor = value.substring(0, cursorPos);
                const match = textBeforeCursor.match(/@(\w*)$/);

                if (match) {
                    this.showMentionsSuggestions(textarea, match[1]);
                } else {
                    this.hideMentionsSuggestions();
                }
            });
        });
    }

    /**
     * Показать предложения упоминаний
     */
    async showMentionsSuggestions(textarea, query) {
        try {
            const response = await fetch(`/api/users/search?q=${encodeURIComponent(query)}`);
            const users = await response.json();

            // Создаем или обновляем список предложений
            let suggestions = document.querySelector('.mentions-suggestions');
            if (!suggestions) {
                suggestions = document.createElement('div');
                suggestions.className = 'mentions-suggestions';
                suggestions.style.cssText = `
                    position: absolute;
                    background: var(--bg-card);
                    border: 1px solid var(--border);
                    border-radius: var(--radius);
                    box-shadow: var(--shadow);
                    max-height: 200px;
                    overflow-y: auto;
                    z-index: 1000;
                `;
                document.body.appendChild(suggestions);
            }

            // Позиционируем относительно textarea
            const rect = textarea.getBoundingClientRect();
            suggestions.style.top = (rect.bottom + window.scrollY) + 'px';
            suggestions.style.left = rect.left + 'px';
            suggestions.style.width = rect.width + 'px';

            suggestions.innerHTML = users.map(user => `
                <div class="mention-item p-2" style="cursor: pointer;" data-user-id="${user.id}" data-user-name="${user.fullName}">
                    <strong>@${user.fullName}</strong>
                    <div class="small text-muted">${user.email}</div>
                </div>
            `).join('');

            // Выбор упоминания
            suggestions.querySelectorAll('.mention-item').forEach(item => {
                item.addEventListener('click', () => {
                    const userName = item.dataset.userName;
                    const value = textarea.value;
                    const cursorPos = textarea.selectionStart;
                    const textBeforeCursor = value.substring(0, cursorPos);
                    const textAfterCursor = value.substring(cursorPos);
                    const newTextBefore = textBeforeCursor.replace(/@\w*$/, `@${userName} `);
                    
                    textarea.value = newTextBefore + textAfterCursor;
                    textarea.selectionStart = textarea.selectionEnd = newTextBefore.length;
                    textarea.focus();
                    
                    this.hideMentionsSuggestions();
                });
            });
        } catch (error) {
            console.error('Mentions suggestions error:', error);
        }
    }

    /**
     * Скрыть предложения упоминаний
     */
    hideMentionsSuggestions() {
        const suggestions = document.querySelector('.mentions-suggestions');
        if (suggestions) {
            suggestions.remove();
        }
    }

    /**
     * Уведомления о действиях
     */
    initActionNotifications() {
        // Подписка на события через WebSocket или polling
        // Упрощенная реализация через polling
        setInterval(() => {
            this.checkForUpdates();
        }, 30000); // Каждые 30 секунд
    }

    /**
     * Проверить обновления
     */
    async checkForUpdates() {
        try {
            const response = await fetch('/api/updates/check');
            const updates = await response.json();

            if (updates.length > 0) {
                updates.forEach(update => {
                    this.showUpdateNotification(update);
                });
            }
        } catch (error) {
            console.error('Check updates error:', error);
        }
    }

    /**
     * Показать уведомление об обновлении
     */
    showUpdateNotification(update) {
        this.showToast(update.message, 'info');
    }

    /**
     * Лента активности
     */
    initActivityFeed() {
        const feed = document.querySelector('[data-activity-feed]');
        if (!feed) return;

        // Загрузка активности
        this.loadActivityFeed(feed);

        // Автообновление
        setInterval(() => {
            this.loadActivityFeed(feed);
        }, 60000); // Каждую минуту
    }

    /**
     * Загрузить ленту активности
     */
    async loadActivityFeed(container) {
        try {
            const response = await fetch('/api/activity/feed');
            const activities = await response.json();

            container.innerHTML = activities.map(activity => `
                <div class="activity-item mb-3 p-3 border-start border-3 border-${this.getActivityColor(activity.type)}">
                    <div class="d-flex justify-content-between">
                        <strong>${activity.user.fullName}</strong>
                        <small class="text-muted">${this.formatTime(activity.createdAt)}</small>
                    </div>
                    <div class="mt-1">${activity.description}</div>
                </div>
            `).join('');
        } catch (error) {
            console.error('Load activity feed error:', error);
        }
    }

    /**
     * Получить цвет активности
     */
    getActivityColor(type) {
        const colors = {
            'task_created': 'primary',
            'task_completed': 'success',
            'comment_added': 'info',
            'member_added': 'warning',
            'project_updated': 'secondary'
        };
        return colors[type] || 'secondary';
    }

    /**
     * Форматировать время
     */
    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;

        if (diff < 60000) return 'только что';
        if (diff < 3600000) return `${Math.floor(diff / 60000)} мин назад`;
        if (diff < 86400000) return `${Math.floor(diff / 3600000)} ч назад`;
        return date.toLocaleDateString();
    }

    /**
     * Показать уведомление
     */
    showToast(message, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        }
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.teamsProjectsEnhanced = new TeamsProjectsEnhanced();
    });
} else {
    window.teamsProjectsEnhanced = new TeamsProjectsEnhanced();
}

// Экспорт
window.TeamsProjectsEnhanced = TeamsProjectsEnhanced;
