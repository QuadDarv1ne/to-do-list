/**
 * Enhanced Teams & Projects Management
 * Interactive features for team collaboration
 */

class TeamsProjectsManager {
    constructor() {
        this.init();
    }

    init() {
        this.setupInviteModal();
        this.setupMemberActions();
        this.setupProjectFilters();
        this.setupTeamCards();
        this.setupActivityFeed();
    }

    /**
     * Setup invite modal
     */
    setupInviteModal() {
        const inviteButtons = document.querySelectorAll('[data-action="invite"]');
        const modal = document.getElementById('inviteModal');
        
        if (!modal) return;
        
        inviteButtons.forEach(button => {
            button.addEventListener('click', () => {
                const teamId = button.dataset.teamId;
                this.showInviteModal(teamId);
            });
        });
        
        // Close modal
        const closeButtons = modal.querySelectorAll('[data-action="close"]');
        closeButtons.forEach(button => {
            button.addEventListener('click', () => {
                this.hideInviteModal();
            });
        });
        
        // Close on outside click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.hideInviteModal();
            }
        });
        
        // Submit form
        const form = modal.querySelector('form');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleInvite(form);
            });
        }
    }

    showInviteModal(teamId) {
        const modal = document.getElementById('inviteModal');
        if (modal) {
            modal.dataset.teamId = teamId;
            modal.classList.add('show');
        }
    }

    hideInviteModal() {
        const modal = document.getElementById('inviteModal');
        if (modal) {
            modal.classList.remove('show');
        }
    }

    async handleInvite(form) {
        const formData = new FormData(form);
        const teamId = document.getElementById('inviteModal').dataset.teamId;
        
        try {
            const response = await fetch(`/api/teams/${teamId}/invite`, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Приглашение отправлено', 'success');
                this.hideInviteModal();
                form.reset();
                
                // Reload members list
                setTimeout(() => window.location.reload(), 1000);
            } else {
                this.showNotification(data.message || 'Ошибка при отправке приглашения', 'error');
            }
        } catch (error) {
            console.error('Error sending invite:', error);
            this.showNotification('Ошибка при отправке приглашения', 'error');
        }
    }

    /**
     * Setup member actions
     */
    setupMemberActions() {
        // Remove member
        document.addEventListener('click', async (e) => {
            const removeBtn = e.target.closest('[data-action="remove-member"]');
            if (!removeBtn) return;
            
            const memberId = removeBtn.dataset.memberId;
            const teamId = removeBtn.dataset.teamId;
            
            if (!confirm('Удалить участника из команды?')) return;
            
            try {
                const response = await fetch(`/api/teams/${teamId}/members/${memberId}`, {
                    method: 'DELETE'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.showNotification('Участник удален', 'success');
                    
                    // Remove from DOM
                    const memberItem = removeBtn.closest('.member-item');
                    if (memberItem) {
                        memberItem.style.opacity = '0';
                        setTimeout(() => memberItem.remove(), 300);
                    }
                } else {
                    this.showNotification(data.message || 'Ошибка при удалении участника', 'error');
                }
            } catch (error) {
                console.error('Error removing member:', error);
                this.showNotification('Ошибка при удалении участника', 'error');
            }
        });
        
        // Change role
        document.addEventListener('click', async (e) => {
            const roleBtn = e.target.closest('[data-action="change-role"]');
            if (!roleBtn) return;
            
            const memberId = roleBtn.dataset.memberId;
            const teamId = roleBtn.dataset.teamId;
            
            // Show role selection
            this.showRoleSelector(teamId, memberId);
        });
    }

    showRoleSelector(teamId, memberId) {
        // Create role selector modal
        const roles = [
            { value: 'member', label: 'Участник' },
            { value: 'manager', label: 'Менеджер' },
            { value: 'admin', label: 'Администратор' }
        ];
        
        const modal = document.createElement('div');
        modal.className = 'role-selector-modal';
        modal.innerHTML = `
            <div class="role-selector-content">
                <h3>Выберите роль</h3>
                <div class="role-selector-options">
                    ${roles.map(role => `
                        <button class="role-selector-option" data-role="${role.value}">
                            ${role.label}
                        </button>
                    `).join('')}
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        setTimeout(() => modal.classList.add('show'), 10);
        
        // Handle selection
        modal.querySelectorAll('.role-selector-option').forEach(button => {
            button.addEventListener('click', async () => {
                const role = button.dataset.role;
                await this.changeRole(teamId, memberId, role);
                modal.remove();
            });
        });
        
        // Close on outside click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }

    async changeRole(teamId, memberId, role) {
        try {
            const response = await fetch(`/api/teams/${teamId}/members/${memberId}/role`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ role })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Роль изменена', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                this.showNotification(data.message || 'Ошибка при изменении роли', 'error');
            }
        } catch (error) {
            console.error('Error changing role:', error);
            this.showNotification('Ошибка при изменении роли', 'error');
        }
    }

    /**
     * Setup project filters
     */
    setupProjectFilters() {
        const filterButtons = document.querySelectorAll('[data-filter]');
        
        filterButtons.forEach(button => {
            button.addEventListener('click', () => {
                const filter = button.dataset.filter;
                
                // Update active state
                filterButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                
                // Filter projects
                this.filterProjects(filter);
            });
        });
    }

    filterProjects(filter) {
        const projects = document.querySelectorAll('.project-card');
        
        projects.forEach(project => {
            const status = project.dataset.status;
            
            if (filter === 'all' || status === filter) {
                project.style.display = 'block';
                project.style.animation = 'fadeInUp 0.4s ease-out';
            } else {
                project.style.display = 'none';
            }
        });
    }

    /**
     * Setup team cards
     */
    setupTeamCards() {
        const teamCards = document.querySelectorAll('.team-card');
        
        teamCards.forEach(card => {
            // Add hover effect
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-8px)';
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
            });
        });
    }

    /**
     * Setup activity feed
     */
    setupActivityFeed() {
        // Auto-refresh activity feed
        const feed = document.querySelector('.activity-feed');
        if (!feed) return;
        
        const teamId = feed.dataset.teamId;
        if (!teamId) return;
        
        // Refresh every 30 seconds
        setInterval(() => {
            this.refreshActivityFeed(teamId);
        }, 30000);
    }

    async refreshActivityFeed(teamId) {
        try {
            const response = await fetch(`/api/teams/${teamId}/activity`);
            const data = await response.json();
            
            if (data.activities) {
                this.updateActivityFeed(data.activities);
            }
        } catch (error) {
            console.error('Error refreshing activity feed:', error);
        }
    }

    updateActivityFeed(activities) {
        const list = document.querySelector('.activity-feed-list');
        if (!list) return;
        
        list.innerHTML = activities.map(activity => this.renderActivity(activity)).join('');
    }

    renderActivity(activity) {
        return `
            <div class="activity-feed-item">
                <div class="activity-feed-marker"></div>
                <div class="activity-feed-content">
                    <div class="activity-feed-header">
                        <span class="activity-feed-user">${activity.user}</span>
                        <span class="activity-feed-time">${this.getTimeAgo(activity.timestamp)}</span>
                    </div>
                    <div class="activity-feed-text">${activity.text}</div>
                </div>
            </div>
        `;
    }

    getTimeAgo(timestamp) {
        const now = new Date();
        const date = new Date(timestamp);
        const seconds = Math.floor((now - date) / 1000);
        
        if (seconds < 60) return 'только что';
        if (seconds < 3600) return `${Math.floor(seconds / 60)} мин назад`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)} ч назад`;
        if (seconds < 604800) return `${Math.floor(seconds / 86400)} дн назад`;
        
        return date.toLocaleDateString('ru-RU');
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        const container = this.getNotificationContainer();
        
        const notification = document.createElement('div');
        notification.className = `notification-toast type-${type}`;
        notification.innerHTML = `
            <div class="notification-icon">
                <i class="fas fa-${this.getNotificationIcon(type)}"></i>
            </div>
            <div class="notification-message">${message}</div>
            <button class="notification-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        container.appendChild(notification);
        
        // Animate in
        setTimeout(() => notification.classList.add('show'), 10);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    getNotificationContainer() {
        let container = document.getElementById('notification-container');
        
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-container';
            container.style.cssText = `
                position: fixed;
                top: 2rem;
                right: 2rem;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                gap: 1rem;
            `;
            document.body.appendChild(container);
        }
        
        return container;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new TeamsProjectsManager();
});

// Add required CSS
const style = document.createElement('style');
style.textContent = `
    /* Role selector modal */
    .role-selector-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .role-selector-modal.show {
        opacity: 1;
    }

    .role-selector-content {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }

    .role-selector-content h3 {
        margin-bottom: 1.5rem;
        color: #212529;
    }

    .role-selector-options {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .role-selector-option {
        padding: 1rem;
        background: #f8f9fa;
        border: 2px solid #e9ecef;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 600;
        color: #495057;
    }

    .role-selector-option:hover {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        border-color: #667eea;
        color: #667eea;
    }

    /* Notification toast */
    .notification-toast {
        display: flex;
        align-items: center;
        gap: 1rem;
        background: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        min-width: 300px;
        opacity: 0;
        transform: translateX(400px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .notification-toast.show {
        opacity: 1;
        transform: translateX(0);
    }

    .notification-toast.type-success {
        border-left: 4px solid #28a745;
    }

    .notification-toast.type-error {
        border-left: 4px solid #dc3545;
    }

    .notification-toast.type-warning {
        border-left: 4px solid #ffc107;
    }

    .notification-toast.type-info {
        border-left: 4px solid #17a2b8;
    }

    .notification-icon {
        font-size: 1.5rem;
    }

    .notification-toast.type-success .notification-icon {
        color: #28a745;
    }

    .notification-toast.type-error .notification-icon {
        color: #dc3545;
    }

    .notification-toast.type-warning .notification-icon {
        color: #ffc107;
    }

    .notification-toast.type-info .notification-icon {
        color: #17a2b8;
    }

    .notification-message {
        flex: 1;
        font-weight: 500;
        color: #212529;
    }

    .notification-close {
        background: none;
        border: none;
        color: #6c757d;
        cursor: pointer;
        padding: 0.25rem;
        transition: all 0.2s ease;
    }

    .notification-close:hover {
        color: #212529;
    }

    /* Dark theme */
    [data-theme='dark'] .role-selector-content {
        background: #2d3748;
    }

    [data-theme='dark'] .role-selector-content h3 {
        color: #e2e8f0;
    }

    [data-theme='dark'] .role-selector-option {
        background: #374151;
        border-color: #4a5568;
        color: #e2e8f0;
    }

    [data-theme='dark'] .notification-toast {
        background: #2d3748;
    }

    [data-theme='dark'] .notification-message {
        color: #e2e8f0;
    }
`;
document.head.appendChild(style);
