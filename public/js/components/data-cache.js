/**
 * Data Cache - Система кэширования данных
 * Улучшает производительность за счет кэширования запросов
 */

class DataCache {
    constructor(options = {}) {
        this.cache = new Map();
        this.options = {
            ttl: options.ttl || 5 * 60 * 1000, // 5 минут по умолчанию
            maxSize: options.maxSize || 100,
            storage: options.storage || 'memory' // 'memory' или 'localStorage'
        };
        
        if (this.options.storage === 'localStorage') {
            this.loadFromStorage();
        }
    }

    set(key, value, ttl = this.options.ttl) {
        const item = {
            value: value,
            expires: Date.now() + ttl,
            created: Date.now()
        };
        
        this.cache.set(key, item);
        this.cleanup();
        
        if (this.options.storage === 'localStorage') {
            this.saveToStorage();
        }
    }

    get(key) {
        const item = this.cache.get(key);
        
        if (!item) return null;
        
        if (Date.now() > item.expires) {
            this.cache.delete(key);
            return null;
        }
        
        return item.value;
    }

    has(key) {
        return this.get(key) !== null;
    }

    delete(key) {
        this.cache.delete(key);
        if (this.options.storage === 'localStorage') {
            this.saveToStorage();
        }
    }

    clear() {
        this.cache.clear();
        if (this.options.storage === 'localStorage') {
            localStorage.removeItem('dataCache');
        }
    }

    cleanup() {
        if (this.cache.size <= this.options.maxSize) return;
        
        const entries = Array.from(this.cache.entries());
        entries.sort((a, b) => a[1].created - b[1].created);
        
        const toRemove = entries.slice(0, entries.length - this.options.maxSize);
        toRemove.forEach(([key]) => this.cache.delete(key));
    }

    loadFromStorage() {
        try {
            const data = localStorage.getItem('dataCache');
            if (data) {
                const parsed = JSON.parse(data);
                this.cache = new Map(parsed);
            }
        } catch (e) {
            console.error('Failed to load cache from storage:', e);
        }
    }

    saveToStorage() {
        try {
            const data = Array.from(this.cache.entries());
            localStorage.setItem('dataCache', JSON.stringify(data));
        } catch (e) {
            console.error('Failed to save cache to storage:', e);
        }
    }

    async fetch(url, options = {}) {
        const cacheKey = `fetch:${url}:${JSON.stringify(options)}`;
        
        if (this.has(cacheKey) && !options.force) {
            return this.get(cacheKey);
        }
        
        try {
            const response = await fetch(url, options);
            const data = await response.json();
            
            if (response.ok) {
                this.set(cacheKey, data, options.ttl);
            }
            
            return data;
        } catch (error) {
            console.error('Fetch error:', error);
            throw error;
        }
    }

    getStats() {
        return {
            size: this.cache.size,
            maxSize: this.options.maxSize,
            items: Array.from(this.cache.entries()).map(([key, item]) => ({
                key,
                age: Date.now() - item.created,
                ttl: item.expires - Date.now()
            }))
        };
    }
}

window.DataCache = DataCache;
window.dataCache = new DataCache();

console.log('💾 Data Cache загружен!');
