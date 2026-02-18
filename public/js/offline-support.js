/**
 * Offline Support
 * Поддержка работы в оффлайн режиме
 */

class OfflineSupport {
    constructor() {
        this.isOnline = navigator.onLine;
        this.queue = this.loadQueue();
        this.syncInProgress = false;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.addOfflineIndicatorStyles();
        this.checkConnection();
        this.startPeriodicSync();
    }

    /**
     * Настроить обработчики событий
     */
    setupEventListeners() {
        window.addEventListener('online', () => {
            this.handleOnline();
        });

        window.addEventListener('offline', () => {
            this.handleOffline();
        });

        // Перехват форм для оффлайн режима
        document.addEventListener('submit', (e) => {
            if (!this.isOnline && e.target.matches('[data-offline-support]')) {
                e.preventDefault();
                this.queueFormSubmission(e.target);
            }
        });

        // Перехват AJAX запросов
        this.interceptFetch();
    }

    /**
     * Создать индикатор оффлайн режима (только при необходимости)
     */
    createOfflineIndicator() {
        // Проверяем, не создан ли уже индикатор
        if (document.getElementById('offline-indicator')) {
            return;
        }
        
        const indicator = document.createElement('div');
        indicator.id = 'offline-indicator';
        indicator.className = 'offline-indicator show';
        indicator.innerHTML = `
            <div class="offline-indicator-content">
                <i class="fas fa-wifi-slash"></i>
                <span>Нет связи</span>
            </div>
            <button class="btn btn-sm btn-light" id="retry-connection" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                <i class="fas fa-sync-alt"></i>
            </button>
        `;

        document.body.appendChild(indicator);

        // Кнопка повтора
        document.getElementById('retry-connection').addEventListener('click', () => {
            this.checkConnection();
        });
    }

    /**
     * Добавить стили индикатора
     */
    addOfflineIndicatorStyles() {
        if (document.getElementById('offlineIndicatorStyles')) return;

        const style = document.createElement('style');
        style.id = 'offlineIndicatorStyles';
        style.textContent = `
            .offline-indicator {
                position: fixed;
                top: 70px;
                right: 20px;
                background: #f59e0b;
                color: white;
                padding: 0.5rem 0.875rem;
                border-radius: 6px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
                z-index: 1050;
                display: none;
                font-size: 0.875rem;
                max-width: 280px;
                flex-direction: row;
                align-items: center;
                gap: 0.75rem;
                animation: slideIn 0.3s ease-out;
            }

            .offline-indicator.show {
                display: flex;
            }

            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateX(100px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }

            .offline-indicator-content {
                display: flex;
                align-items: center;
                font-weight: 500;
                font-size: 0.875rem;
                flex: 1;
            }

            .offline-queue-info {
                font-size: 0.75rem;
                opacity: 0.9;
                white-space: nowrap;
            }

            #queue-count {
                font-weight: 600;
            }

            .offline-badge {
                display: none;
                box-shadow: var(--shadow);
                z-index: 1000;
                display: none;
            }

            .offline-badge.show {
                display: block;
                animation: pulse 2s infinite;
            }

            @keyframes pulse {
                0%, 100% {
                    opacity: 1;
                }
                50% {
                    opacity: 0.7;
                }
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Обработка перехода в онлайн
     */
    handleOnline() {
        this.isOnline = true;
        
        const indicator = document.getElementById('offline-indicator');
        if (indicator) {
            indicator.classList.remove('show');
        }

        this.showToast('Соединение восстановлено', 'success');
        
        // Синхронизация очереди
        if (this.queue.length > 0) {
            this.syncQueue();
        }
    }

    /**
     * Обработка перехода в оффлайн
     */
    handleOffline() {
        this.isOnline = false;
        
        // Создать индикатор только когда действительно оффлайн
        this.createOfflineIndicator();
        
        // Не показываем toast - достаточно индикатора
    }

    /**
     * Проверить подключение
     */
    async checkConnection() {
        try {
            // Проверяем соединение через публичный endpoint
            const response = await fetch('/login', {
                method: 'HEAD',
                cache: 'no-cache'
            });
            
            if (response.ok) {
                if (!this.isOnline) {
                    this.handleOnline();
                }
            } else {
                if (this.isOnline) {
                    this.handleOffline();
                }
            }
        } catch (error) {
            if (this.isOnline) {
                this.handleOffline();
            }
        }
    }

    /**
     * Добавить отправку формы в очередь
     */
    queueFormSubmission(form) {
        const formData = new FormData(form);
        const data = {
            id: Date.now(),
            type: 'form',
            url: form.action,
            method: form.method || 'POST',
            data: Object.fromEntries(formData.entries()),
            timestamp: new Date().toISOString()
        };

        this.queue.push(data);
        this.saveQueue();
        this.updateQueueCount();

        this.showToast('Действие добавлено в очередь и будет выполнено при восстановлении соединения', 'info');
    }

    /**
     * Добавить запрос в очередь
     */
    queueRequest(url, options) {
        const data = {
            id: Date.now(),
            type: 'fetch',
            url: url,
            method: options.method || 'GET',
            headers: options.headers || {},
            body: options.body,
            timestamp: new Date().toISOString()
        };

        this.queue.push(data);
        this.saveQueue();
        this.updateQueueCount();
    }

    /**
     * Синхронизировать очередь
     */
    async syncQueue() {
        if (this.syncInProgress || this.queue.length === 0) return;

        this.syncInProgress = true;
        this.showToast(`Синхронизация ${this.queue.length} действий...`, 'info');

        const results = {
            success: 0,
            failed: 0
        };

        for (const item of [...this.queue]) {
            try {
                if (item.type === 'form') {
                    await this.syncFormSubmission(item);
                } else if (item.type === 'fetch') {
                    await this.syncFetchRequest(item);
                }

                // Удаляем из очереди при успехе
                this.queue = this.queue.filter(q => q.id !== item.id);
                results.success++;
            } catch (error) {
                console.error('Sync error:', error);
                results.failed++;
            }
        }

        this.saveQueue();
        this.updateQueueCount();
        this.syncInProgress = false;

        if (results.success > 0) {
            this.showToast(`Синхронизировано ${results.success} действий`, 'success');
        }

        if (results.failed > 0) {
            this.showToast(`Не удалось синхронизировать ${results.failed} действий`, 'error');
        }
    }

    /**
     * Синхронизировать отправку формы
     */
    async syncFormSubmission(item) {
        const formData = new FormData();
        for (const [key, value] of Object.entries(item.data)) {
            formData.append(key, value);
        }

        const response = await fetch(item.url, {
            method: item.method,
            body: formData
        });

        if (!response.ok) {
            throw new Error('Form submission failed');
        }

        return response;
    }

    /**
     * Синхронизировать fetch запрос
     */
    async syncFetchRequest(item) {
        const response = await fetch(item.url, {
            method: item.method,
            headers: item.headers,
            body: item.body
        });

        if (!response.ok) {
            throw new Error('Fetch request failed');
        }

        return response;
    }

    /**
     * Перехватить fetch запросы
     */
    interceptFetch() {
        const originalFetch = window.fetch;
        
        window.fetch = async (...args) => {
            if (!this.isOnline) {
                const [url, options = {}] = args;
                
                // Только для POST, PUT, PATCH, DELETE
                if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(options.method?.toUpperCase())) {
                    this.queueRequest(url, options);
                    
                    // Возвращаем фейковый успешный ответ
                    return new Response(JSON.stringify({ queued: true }), {
                        status: 202,
                        headers: { 'Content-Type': 'application/json' }
                    });
                }
            }

            return originalFetch(...args);
        };
    }

    /**
     * Обновить счетчик очереди
     */
    updateQueueCount() {
        const countEl = document.getElementById('queue-count');
        if (countEl) {
            countEl.textContent = this.queue.length;
        }

        // Показать индикатор только если есть элементы в очереди и оффлайн
        const indicator = document.getElementById('offline-indicator');
        if (indicator && !this.isOnline && this.queue.length > 0) {
            indicator.classList.add('show');
        }
    }

    /**
     * Загрузить очередь
     */
    loadQueue() {
        try {
            const stored = localStorage.getItem('offlineQueue');
            return stored ? JSON.parse(stored) : [];
        } catch (e) {
            return [];
        }
    }

    /**
     * Сохранить очередь
     */
    saveQueue() {
        try {
            localStorage.setItem('offlineQueue', JSON.stringify(this.queue));
        } catch (e) {
            console.error('Failed to save queue:', e);
        }
    }

    /**
     * Периодическая синхронизация
     */
    startPeriodicSync() {
        setInterval(() => {
            if (this.isOnline && this.queue.length > 0) {
                this.syncQueue();
            }
        }, 60000); // Каждую минуту
    }

    /**
     * Показать уведомление
     */
    showToast(message, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        }
    }

    /**
     * Очистить очередь
     */
    clearQueue() {
        this.queue = [];
        this.saveQueue();
        this.updateQueueCount();
    }

    /**
     * Получить статистику
     */
    getStats() {
        return {
            isOnline: this.isOnline,
            queueLength: this.queue.length,
            oldestItem: this.queue.length > 0 ? this.queue[0].timestamp : null
        };
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.offlineSupport = new OfflineSupport();
    });
} else {
    window.offlineSupport = new OfflineSupport();
}

// Экспорт
window.OfflineSupport = OfflineSupport;
