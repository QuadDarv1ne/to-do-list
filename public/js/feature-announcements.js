/**
 * Feature Announcements
 * Система уведомлений о новых функциях
 */

class FeatureAnnouncements {
    constructor() {
        this.announcements = [];
        this.seenAnnouncements = new Set();
        this.init();
    }

    init() {
        this.loadSeenAnnouncements();
        this.registerAnnouncements();
        this.checkForNew();
    }

    registerAnnouncements() {
        // Регистрируем новые функции
        this.announcements = [
            {
                id: 'quick-actions-menu',
                version: '2.0.0',
                title: 'Меню быстрых действий',
                description: 'Новое плавающее меню для быстрого доступа к основным функциям. Нажмите Q для открытия.',
                icon: 'fa-bolt',
                color: '#667eea',
                date: '2024-02-18'
            },
            {
                id: 'voice-commands',
                version: '2.0.0',
                title: 'Голосовые команды',
                description: 'Управляйте приложением голосом! Нажмите V или кнопку микрофона для активации.',
                icon: 'fa-microphone',
                color: '#f093fb',
                date: '2024-02-18'
            },
            {
                id: 'batch-operations',
                version: '2.0.0',
                title: 'Массовые операции',
                description: 'Выбирайте несколько задач и выполняйте действия над ними одновременно.',
                icon: 'fa-tasks',
                color: '#4facfe',
                date: '2024-02-18'
            },
            {
                id: 'export-manager',
                version: '2.0.0',
                title: 'Экспорт данных',
                description: 'Экспортируйте таблицы в CSV, JSON, Excel и PDF форматы.',
                icon: 'fa-download',
                color: '#43e97b',
                date: '2024-02-18'
            },
            {
                id: 'tour-guide',
                version: '2.0.0',
                title: 'Интерактивный тур',
                description: 'Новый интерактивный тур для знакомства с возможностями системы.',
                icon: 'fa-route',
                color: '#fa709a',
                date: '2024-02-18'
            }
        ];
    }

    checkForNew() {
        const newAnnouncements = this.announcements.filter(a => !this.seenAnnouncements.has(a.id));
        
        if (newAnnouncements.length > 0) {
            setTimeout(() => {
                this.showAnnouncements(newAnnouncements);
            }, 2000);
        }
    }

    showAnnouncements(announcements) {
        const modal = document.createElement('div');
        modal.className = 'feature-announcements-modal';
        modal.innerHTML = `
            <div class="feature-announcements-content">
                <div class="feature-announcements-header">
                    <div class="feature-announcements-badge">
                        <i class="fas fa-sparkles"></i>
                        Новое
                    </div>
                    <h2>Что нового в CRM?</h2>
                    <button class="feature-announcements-close" aria-label="Закрыть">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="feature-announcements-body">
                    ${announcements.map(a => this.renderAnnouncement(a)).join('')}
                </div>
                <div class="feature-announcements-footer">
                    <button class="btn btn-primary" id="gotIt">
                        <i class="fas fa-check"></i> Понятно
                    </button>
                    <button class="btn btn-secondary" id="dontShowAgain">
                        Больше не показывать
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Обработчики
        modal.querySelector('.feature-announcements-close').addEventListener('click', () => {
            this.markAsSeen(announcements);
            modal.remove();
        });

        modal.querySelector('#gotIt').addEventListener('click', () => {
            this.markAsSeen(announcements);
            modal.remove();
        });

        modal.querySelector('#dontShowAgain').addEventListener('click', () => {
            this.markAllAsSeen();
            modal.remove();
        });

        this.addStyles();
    }

    renderAnnouncement(announcement) {
        return `
            <div class="feature-announcement-item">
                <div class="feature-announcement-icon" style="background: ${announcement.color}">
                    <i class="fas ${announcement.icon}"></i>
                </div>
                <div class="feature-announcement-content">
                    <h4>${announcement.title}</h4>
                    <p>${announcement.description}</p>
                    <div class="feature-announcement-meta">
                        <span class="feature-announcement-version">v${announcement.version}</span>
                        <span class="feature-announcement-date">${this.formatDate(announcement.date)}</span>
                    </div>
                </div>
            </div>
        `;
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('ru-RU', { 
            day: 'numeric', 
            month: 'long', 
            year: 'numeric' 
        });
    }

    markAsSeen(announcements) {
        announcements.forEach(a => {
            this.seenAnnouncements.add(a.id);
        });
        this.saveSeenAnnouncements();
    }

    markAllAsSeen() {
        this.announcements.forEach(a => {
            this.seenAnnouncements.add(a.id);
        });
        this.saveSeenAnnouncements();
    }

    loadSeenAnnouncements() {
        try {
            const seen = localStorage.getItem('seenAnnouncements');
            if (seen) {
                this.seenAnnouncements = new Set(JSON.parse(seen));
            }
        } catch (e) {
            console.error('Failed to load seen announcements:', e);
        }
    }

    saveSeenAnnouncements() {
        try {
            localStorage.setItem('seenAnnouncements', JSON.stringify(Array.from(this.seenAnnouncements)));
        } catch (e) {
            console.error('Failed to save seen announcements:', e);
        }
    }

    addStyles() {
        if (document.getElementById('featureAnnouncementsStyles')) return;

        const style = document.createElement('style');
        style.id = 'featureAnnouncementsStyles';
        style.textContent = `
            .feature-announcements-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.6);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10004;
                animation: fadeIn 0.3s ease;
            }

            .feature-announcements-content {
                background: var(--bg-card);
                border-radius: 16px;
                max-width: 600px;
                width: 90%;
                max-height: 80vh;
                display: flex;
                flex-direction: column;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            }

            .feature-announcements-header {
                padding: 1.5rem;
                border-bottom: 1px solid var(--border);
                position: relative;
            }

            .feature-announcements-badge {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 0.375rem 0.875rem;
                border-radius: 20px;
                font-size: 0.875rem;
                font-weight: 600;
                margin-bottom: 0.75rem;
            }

            .feature-announcements-header h2 {
                margin: 0;
                color: var(--text-primary);
                font-size: 1.5rem;
            }

            .feature-announcements-close {
                position: absolute;
                top: 1.5rem;
                right: 1.5rem;
                background: none;
                border: none;
                color: var(--text-secondary);
                cursor: pointer;
                font-size: 1.25rem;
                padding: 4px;
                transition: color 0.2s ease;
            }

            .feature-announcements-close:hover {
                color: var(--text-primary);
            }

            .feature-announcements-body {
                padding: 1.5rem;
                overflow-y: auto;
                flex: 1;
            }

            .feature-announcement-item {
                display: flex;
                gap: 1rem;
                padding: 1rem;
                background: var(--bg-body);
                border-radius: 12px;
                margin-bottom: 1rem;
                transition: transform 0.2s ease;
            }

            .feature-announcement-item:hover {
                transform: translateX(4px);
            }

            .feature-announcement-item:last-child {
                margin-bottom: 0;
            }

            .feature-announcement-icon {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 1.25rem;
                flex-shrink: 0;
            }

            .feature-announcement-content {
                flex: 1;
            }

            .feature-announcement-content h4 {
                margin: 0 0 0.5rem 0;
                color: var(--text-primary);
                font-size: 1rem;
            }

            .feature-announcement-content p {
                margin: 0 0 0.75rem 0;
                color: var(--text-secondary);
                font-size: 0.875rem;
                line-height: 1.5;
            }

            .feature-announcement-meta {
                display: flex;
                gap: 1rem;
                font-size: 0.75rem;
                color: var(--text-secondary);
            }

            .feature-announcement-version {
                background: var(--bg-card);
                padding: 0.25rem 0.5rem;
                border-radius: 4px;
                font-weight: 600;
            }

            .feature-announcements-footer {
                padding: 1.5rem;
                border-top: 1px solid var(--border);
                display: flex;
                gap: 1rem;
                justify-content: flex-end;
            }

            @media (max-width: 768px) {
                .feature-announcements-content {
                    width: 95%;
                    max-height: 90vh;
                }

                .feature-announcement-item {
                    flex-direction: column;
                }

                .feature-announcements-footer {
                    flex-direction: column;
                }

                .feature-announcements-footer button {
                    width: 100%;
                }
            }
        `;

        document.head.appendChild(style);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.featureAnnouncements = new FeatureAnnouncements();
    
    // Глобальная функция для показа анонсов
    window.showFeatureAnnouncements = () => {
        if (window.featureAnnouncements) {
            window.featureAnnouncements.checkForNew();
        }
    };
});

window.FeatureAnnouncements = FeatureAnnouncements;
