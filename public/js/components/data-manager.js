/**
 * Data Manager
 * Управление данными, кэширование и real-time обновления
 */

class DataManager {
    constructor() {
        this.cache = new Map();
        this.subscribers = new Map();
        this.updateQueue = [];
        this.isOnline = navigator.onLine;
        this.init();
    }

    init() {
        this.initNetworkMonitoring();
        this.initServiceWorker();
        this.initPeriodicSync();
        this.initOptimisticUI();
    }

    // Мониторинг сети
    initNetworkMonitoring() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.showNetworkStatus('Соединение восстановлено', 'success');
            this.syncPendingUpdates();
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.showNetworkStatus('Нет соединения', 'warning');
        });
    }

    showNetworkStatus(message, type) {
        if (window.advancedUI) {
            window.advancedUI.showNotification(message, type, 2000);
        }
    }

    // Service Worker для offline работы
    initServiceWorker() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(registration => {
                    console.log('Service Worker registered:', registration);
                })
                .catch(error => {
                    console.error('Service Worker registration failed:', error);
                });
        }
    }

    // Периодическая синхронизация
    initPeriodicSync() {
        setInterval(() => {
            if (this.isOnline) {
                this.refreshData();
            }
        }, 30000); // Каждые 30 секунд
    }

    // Optimistic UI Updates
    initOptimisticUI() {
        this.optimisticUpdates = new Map();
    }

    // Получение данных с кэшированием
    async fetch(url, options = {}) {
        const cacheKey = this.getCacheKey(url, options);
        
        // Проверяем кэш
        if (this.cache.has(cacheKey) && !options.forceRefresh) {
            const cached = this.cache.get(cacheKey);
            if (Date.now() - cached.timestamp < (options.cacheDuration || 60000)) {
                return cached.data;
            }
        }

        try {
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...options.headers
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            // Кэшируем результат
            this.cache.set(cacheKey, {
                data,
                timestamp: Date.now()
            });

            // Уведомляем подписчиков
            this.notifySubscribers(cacheKey, data);

            return data;
        } catch (error) {
            console.error('Fetch error:', error);
            
            // Возвращаем кэшированные данные если есть
            if (this.cache.has(cacheKey)) {
                return this.cache.get(cacheKey).data;
            }
            
            throw error;
        }
    }

    // Optimistic update
    async update(url, data, options = {}) {
        const optimisticId = Date.now();
        
        // Сразу обновляем UI
        if (options.optimistic) {
            this.optimisticUpdates.set(optimisticId, data);
            this.notifySubscribers(url, data);
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data),
                ...options
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            
            // Удаляем optimistic update
            this.optimisticUpdates.delete(optimisticId);
            
            // Обновляем кэш
            this.invalidateCache(url);
            
            return result;
        } catch (error) {
            // Откатываем optimistic update
            if (options.optimistic) {
                this.optimisticUpdates.delete(optimisticId);
                this.notifySubscribers(url, null);
            }
            
            // Добавляем в очередь для повторной попытки
            if (!this.isOnline) {
                this.updateQueue.push({ url, data, options });
            }
            
            throw error;
        }
    }

    // Подписка на изменения данных
    subscribe(key, callback) {
        if (!this.subscribers.has(key)) {
            this.subscribers.set(key, new Set());
        }
        this.subscribers.get(key).add(callback);

        // Возвращаем функцию отписки
        return () => {
            const subscribers = this.subscribers.get(key);
            if (subscribers) {
                subscribers.delete(callback);
            }
        };
    }

    // Уведомление подписчиков
    notifySubscribers(key, data) {
        const subscribers = this.subscribers.get(key);
        if (subscribers) {
            subscribers.forEach(callback => callback(data));
        }
    }

    // Синхронизация отложенных обновлений
    async syncPendingUpdates() {
        if (this.updateQueue.length === 0) return;

        const updates = [...this.updateQueue];
        this.updateQueue = [];

        for (const update of updates) {
            try {
                await this.update(update.url, update.data, update.options);
            } catch (error) {
                console.error('Sync failed:', error);
                this.updateQueue.push(update);
            }
        }

        if (this.updateQueue.length === 0) {
            this.showNetworkStatus('Все изменения синхронизированы', 'success');
        }
    }

    // Обновление данных
    async refreshData() {
        const keys = Array.from(this.cache.keys());
        for (const key of keys) {
            try {
                await this.fetch(key, { forceRefresh: true });
            } catch (error) {
                console.error('Refresh failed:', error);
            }
        }
    }

    // Инвалидация кэша
    invalidateCache(pattern) {
        if (typeof pattern === 'string') {
            this.cache.delete(pattern);
        } else if (pattern instanceof RegExp) {
            for (const key of this.cache.keys()) {
                if (pattern.test(key)) {
                    this.cache.delete(key);
                }
            }
        }
    }

    // Очистка кэша
    clearCache() {
        this.cache.clear();
    }

    // Генерация ключа кэша
    getCacheKey(url, options) {
        return `${url}_${JSON.stringify(options.params || {})}`;
    }

    // Prefetch данных
    async prefetch(urls) {
        const promises = urls.map(url => this.fetch(url));
        return Promise.allSettled(promises);
    }

    // Batch запросы
    async batchFetch(requests) {
        const promises = requests.map(req => 
            this.fetch(req.url, req.options)
        );
        return Promise.allSettled(promises);
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.dataManager = new DataManager();
    });
} else {
    window.dataManager = new DataManager();
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = DataManager;
}
