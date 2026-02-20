/**
 * State Manager - Centralized state management with persistence
 * Handles app state, user preferences, and caching
 */

class StateManager {
    constructor() {
        this.state = {};
        this.listeners = {};
        this.storage = this.getStorage();
        this.init();
    }
    
    // Initialize state from storage
    init() {
        try {
            const savedState = this.storage.getItem('app-state');
            if (savedState) {
                this.state = JSON.parse(savedState);
            }
        } catch (e) {
            console.warn('Failed to load state from storage:', e);
        }
        
        // Set default state
        this.state = {
            theme: this.state.theme || this.detectTheme(),
            sidebarCollapsed: this.state.sidebarCollapsed || false,
            notifications: this.state.notifications || [],
            preferences: this.state.preferences || {},
            cache: this.state.cache || {},
            ...this.state
        };
        
        // Apply theme
        this.applyTheme(this.state.theme);
    }
    
    // Get storage (localStorage with fallback to memory)
    getStorage() {
        try {
            const test = '__storage_test__';
            localStorage.setItem(test, test);
            localStorage.removeItem(test);
            return localStorage;
        } catch (e) {
            // Fallback to memory storage
            return {
                data: {},
                getItem(key) { return this.data[key] || null; },
                setItem(key, value) { this.data[key] = value; },
                removeItem(key) { delete this.data[key]; },
                clear() { this.data = {}; }
            };
        }
    }
    
    // Detect user's preferred theme
    detectTheme() {
        // Check saved preference
        const saved = this.storage.getItem('theme');
        if (saved) return saved;
        
        // Check system preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        
        return 'light';
    }
    
    // Get state value
    get(key, defaultValue = null) {
        const keys = key.split('.');
        let value = this.state;
        
        for (const k of keys) {
            if (value && typeof value === 'object' && k in value) {
                value = value[k];
            } else {
                return defaultValue;
            }
        }
        
        return value;
    }
    
    // Set state value
    set(key, value, persist = true) {
        const keys = key.split('.');
        const lastKey = keys.pop();
        let target = this.state;
        
        // Navigate to the target object
        for (const k of keys) {
            if (!(k in target)) {
                target[k] = {};
            }
            target = target[k];
        }
        
        // Set the value
        const oldValue = target[lastKey];
        target[lastKey] = value;
        
        // Persist to storage
        if (persist) {
            this.persist();
        }
        
        // Notify listeners
        this.notify(key, value, oldValue);
        
        return this;
    }
    
    // Subscribe to state changes
    subscribe(key, callback) {
        if (!this.listeners[key]) {
            this.listeners[key] = [];
        }
        this.listeners[key].push(callback);
        
        // Return unsubscribe function
        return () => {
            this.listeners[key] = this.listeners[key].filter(cb => cb !== callback);
        };
    }
    
    // Notify listeners
    notify(key, newValue, oldValue) {
        if (this.listeners[key]) {
            this.listeners[key].forEach(callback => {
                try {
                    callback(newValue, oldValue);
                } catch (e) {
                    console.error('Error in state listener:', e);
                }
            });
        }
    }
    
    // Persist state to storage
    persist() {
        try {
            this.storage.setItem('app-state', JSON.stringify(this.state));
        } catch (e) {
            console.warn('Failed to persist state:', e);
        }
    }
    
    // Clear state
    clear() {
        this.state = {};
        this.storage.removeItem('app-state');
        this.notify('*', null, null);
    }
    
    // Theme management
    setTheme(theme) {
        this.set('theme', theme);
        this.applyTheme(theme);
        this.storage.setItem('theme', theme);
    }
    
    applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        
        // Update meta theme-color
        const metaTheme = document.querySelector('meta[name="theme-color"]');
        if (metaTheme) {
            const colors = {
                light: '#ffffff',
                dark: '#1f2937',
                orange: '#f97316',
                purple: '#a855f7'
            };
            metaTheme.setAttribute('content', colors[theme] || colors.light);
        }
    }
    
    // Cache management
    setCache(key, value, ttl = 3600000) { // Default 1 hour
        const cacheEntry = {
            value,
            timestamp: Date.now(),
            ttl
        };
        
        this.set(`cache.${key}`, cacheEntry);
    }
    
    getCache(key) {
        const cacheEntry = this.get(`cache.${key}`);
        
        if (!cacheEntry) return null;
        
        // Check if cache is expired
        if (Date.now() - cacheEntry.timestamp > cacheEntry.ttl) {
            this.clearCache(key);
            return null;
        }
        
        return cacheEntry.value;
    }
    
    clearCache(key) {
        if (key) {
            const cache = this.get('cache', {});
            delete cache[key];
            this.set('cache', cache);
        } else {
            this.set('cache', {});
        }
    }
    
    // Preferences management
    setPreference(key, value) {
        this.set(`preferences.${key}`, value);
    }
    
    getPreference(key, defaultValue = null) {
        return this.get(`preferences.${key}`, defaultValue);
    }
    
    // Network status
    updateNetworkStatus() {
        const online = navigator.onLine;
        const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        
        const status = {
            online,
            effectiveType: connection?.effectiveType || 'unknown',
            downlink: connection?.downlink || 0,
            rtt: connection?.rtt || 0,
            saveData: connection?.saveData || false
        };
        
        this.set('network', status, false); // Don't persist network status
        
        return status;
    }
    
    // Performance metrics
    recordMetric(name, value) {
        const metrics = this.get('metrics', {});
        if (!metrics[name]) {
            metrics[name] = [];
        }
        
        metrics[name].push({
            value,
            timestamp: Date.now()
        });
        
        // Keep only last 100 entries
        if (metrics[name].length > 100) {
            metrics[name] = metrics[name].slice(-100);
        }
        
        this.set('metrics', metrics, false); // Don't persist metrics
    }
    
    getMetrics(name) {
        return this.get(`metrics.${name}`, []);
    }
    
    // Feature flags
    isFeatureEnabled(feature) {
        return this.get(`features.${feature}`, false);
    }
    
    enableFeature(feature) {
        this.set(`features.${feature}`, true);
    }
    
    disableFeature(feature) {
        this.set(`features.${feature}`, false);
    }
}

// Create singleton instance
const stateManager = new StateManager();

// Listen to network changes
window.addEventListener('online', () => {
    stateManager.updateNetworkStatus();
    document.body.classList.remove('offline');
});

window.addEventListener('offline', () => {
    stateManager.updateNetworkStatus();
    document.body.classList.add('offline');
});

// Listen to connection changes
if (navigator.connection) {
    navigator.connection.addEventListener('change', () => {
        stateManager.updateNetworkStatus();
    });
}

// Update network status on load
stateManager.updateNetworkStatus();

// Expose globally
window.StateManager = stateManager;

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = StateManager;
}
