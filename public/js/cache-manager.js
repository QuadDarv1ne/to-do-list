/**
 * Cache Manager
 * Система кэширования для улучшения производительности
 */

class CacheManager {
    constructor() {
        this.cacheName = 'crm-cache-v1';
        this.maxAge = 3600000; // 1 час
        this.memoryCache = new Map();
        this.init();
    }

    init() {
        this.cleanExpiredCache();
        // Очистка каждые 10 минут
        setInterval(() => this.cleanExpiredCache(), 600000);
    }

    // Получить из кэша
    async get(key) {
        // Проверяем memory cache
        if (this.memoryCache.has(key)) {
            const cached = this.memoryCache.get(key);
            if (Date.now() - cached.timestamp < this.maxAge) {
                return cached.data;
            }
            this.memoryCache.delete(key);
        }

        // Проверяем localStorage
        try {
            const cached = localStorage.getItem(`cache_${key}`);
            if (cached) {
                const { data, timestamp } = JSON.parse(cached);
                if (Date.now() - timestamp < this.maxAge) {
                    // Восстанавливаем в memory cache
                    this.memoryCache.set(key, { data, timestamp });
                    return data;
                }
                localStorage.removeItem(`cache_${key}`);
            }
        } catch (e) {
            console.error('Cache get error:', e);
        }

        return null;
    }

    // Сохранить в кэш
    async set(key, data) {
        const timestamp = Date.now();
        
        // Сохраняем в memory cache
        this.memoryCache.set(key, { data, timestamp });

        // Сохраняем в localStorage
        try {
            localStorage.setItem(`cache_${key}`, JSON.stringify({ data, timestamp }));
        } catch (e) {
            // Если localStorage переполнен, очищаем старые записи
            if (e.name === 'QuotaExceededError') {
                this.cleanExpiredCache();
                try {
                    localStorage.setItem(`cache_${key}`, JSON.stringify({ data, timestamp }));
                } catch (e2) {
                    console.error('Cache set error:', e2);
                }
            }
        }
    }

    // Удалить из кэша
    async remove(key) {
        this.memoryCache.delete(key);
        try {
            localStorage.removeItem(`cache_${key}`);
        } catch (e) {
            console.error('Cache remove error:', e);
        }
    }

    // Очистить весь кэш
    async clear() {
        this.memoryCache.clear();
        try {
            const keys = Object.keys(localStorage);
            keys.forEach(key => {
                if (key.startsWith('cache_')) {
                    localStorage.removeItem(key);
                }
            });
        } catch (e) {
            console.error('Cache clear error:', e);
        }
    }

    // Очистить устаревший кэш
    cleanExpiredCache() {
        const now = Date.now();

        // Очистка memory cache
        for (const [key, value] of this.memoryCache.entries()) {
            if (now - value.timestamp >= this.maxAge) {
                this.memoryCache.delete(key);
            }
        }

        // Очистка localStorage
        try {
            const keys = Object.keys(localStorage);
            keys.forEach(key => {
                if (key.startsWith('cache_')) {
                    try {
                        const cached = JSON.parse(localStorage.getItem(key));
                        if (now - cached.timestamp >= this.maxAge) {
                            localStorage.removeItem(key);
                        }
                    } catch (e) {
                        // Удаляем поврежденные записи
                        localStorage.removeItem(key);
                    }
                }
            });
        } catch (e) {
            console.error('Cache cleanup error:', e);
        }
    }

    // Получить размер кэша
    getCacheSize() {
        let size = 0;
        try {
            const keys = Object.keys(localStorage);
            keys.forEach(key => {
                if (key.startsWith('cache_')) {
                    size += localStorage.getItem(key).length;
                }
            });
        } catch (e) {
            console.error('Cache size error:', e);
        }
        return size;
    }

    // Получить статистику кэша
    getStats() {
        return {
            memoryEntries: this.memoryCache.size,
            storageSize: this.getCacheSize(),
            maxAge: this.maxAge
        };
    }
}

// Wrapper для fetch с кэшированием
class CachedFetch {
    constructor(cacheManager) {
        this.cache = cacheManager;
    }

    async fetch(url, options = {}) {
        const cacheKey = `fetch_${url}_${JSON.stringify(options)}`;
        
        // Проверяем кэш только для GET запросов
        if (!options.method || options.method === 'GET') {
            const cached = await this.cache.get(cacheKey);
            if (cached) {
                return cached;
            }
        }

        // Выполняем запрос
        try {
            const response = await fetch(url, options);
            const data = await response.json();

            // Кэшируем только успешные GET запросы
            if (response.ok && (!options.method || options.method === 'GET')) {
                await this.cache.set(cacheKey, data);
            }

            return data;
        } catch (error) {
            console.error('Fetch error:', error);
            throw error;
        }
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.cacheManager = new CacheManager();
    window.cachedFetch = new CachedFetch(window.cacheManager);
    
    // Добавляем команду для очистки кэша в консоль
    window.clearCache = () => {
        window.cacheManager.clear();
        console.log('Cache cleared');
    };
    
    window.cacheStats = () => {
        const stats = window.cacheManager.getStats();
        console.log('Cache Statistics:', stats);
        return stats;
    };
});

// Экспорт
window.CacheManager = CacheManager;
window.CachedFetch = CachedFetch;
