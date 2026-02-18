/**
 * Service Worker for CRM To-Do List App
 * Advanced PWA with offline support, background sync, and push notifications
 * Version: 2.0.0
 */

const CACHE_NAME = 'crm-todo-v2';
const STATIC_CACHE = 'static-v2';
const DYNAMIC_CACHE = 'dynamic-v2';
const IMAGE_CACHE = 'images-v2';

// App shell - critical resources
const APP_SHELL = [
    '/',
    '/dashboard',
    '/tasks',
    '/login',
    '/offline',
    '/manifest.json',
    '/css/optimized-core.css',
    '/css/header-footer-improved.css',
    '/css/mobile-table-adaptation.css',
    '/css/accessibility-improvements.css',
    '/js/toast-system.js',
    '/js/utils.js',
    '/js/theme-switcher.js',
    '/offline-page.html'
];

// Network timeout for fetch requests
const NETWORK_TIMEOUT = 5000;

// Maximum cache size for dynamic content
const MAX_DYNAMIC_CACHE_SIZE = 50;

// ============================================
// INSTALL EVENT
// ============================================

self.addEventListener('install', (event) => {
    console.log('[SW] Installing Service Worker...');
    
    // Skip waiting to activate immediately
    self.skipWaiting();
    
    event.waitUntil(
        Promise.all([
            // Cache app shell
            caches.open(STATIC_CACHE).then((cache) => {
                console.log('[SW] Caching app shell');
                return cache.addAll(APP_SHELL).catch(err => {
                    console.warn('[SW] Some app shell resources failed to cache:', err);
                });
            }),
            
            // Pre-cache critical images
            caches.open(IMAGE_CACHE).then((cache) => {
                console.log('[SW] Pre-caching images');
                return cache.addAll([
                    '/favicon.ico',
                    '/icons/icon-192x192.png',
                    '/icons/icon-512x512.png'
                ]).catch(err => {
                    console.warn('[SW] Some images failed to cache:', err);
                });
            })
        ]).then(() => {
            console.log('[SW] Installation complete, app shell cached');
        })
    );
});

// ============================================
// ACTIVATE EVENT
// ============================================

self.addEventListener('activate', (event) => {
    console.log('[SW] Activating Service Worker...');
    
    event.waitUntil(
        Promise.all([
            // Clean up old caches
            caches.keys().then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => {
                        if (cacheName !== STATIC_CACHE && 
                            cacheName !== DYNAMIC_CACHE && 
                            cacheName !== IMAGE_CACHE &&
                            cacheName !== CACHE_NAME) {
                            console.log('[SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            }),
            
            // Take control of all pages immediately
            self.clients.claim().then(() => {
                console.log('[SW] Service Worker activated and controlling clients');
                
                // Notify all clients about activation
                self.clients.matchAll().then((clients) => {
                    clients.forEach((client) => {
                        client.postMessage({ type: 'SW_ACTIVATED' });
                    });
                });
            })
        ])
    );
});

// ============================================
// FETCH EVENT - NETWORK FIRST WITH CACHE FALLBACK
// ============================================

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Skip chrome extensions and other non-http(s) requests
    if (!url.protocol.startsWith('http')) {
        return;
    }
    
    // Skip cross-origin requests (except CDN)
    if (url.origin !== self.location.origin && 
        !url.origin.includes('cdn.jsdelivr.net') &&
        !url.origin.includes('cdnjs.cloudflare.com')) {
        return;
    }
    
    // Handle different request types with appropriate strategies
    if (isStaticAsset(request)) {
        // Cache-first for static assets
        event.respondWith(cacheFirst(request, STATIC_CACHE));
    } else if (isImageRequest(request)) {
        // Cache-first for images
        event.respondWith(cacheFirst(request, IMAGE_CACHE));
    } else if (isApiRequest(request)) {
        // Network-first with timeout for API requests
        event.respondWith(networkFirstWithTimeout(request));
    } else if (isNavigationRequest(request)) {
        // Network-first with timeout for navigation
        event.respondWith(networkFirstWithTimeout(request, '/offline-page.html'));
    } else {
        // Stale-while-revalidate for other requests
        event.respondWith(staleWhileRevalidate(request, DYNAMIC_CACHE));
    }
});

// ============================================
// CACHING STRATEGIES
// ============================================

/**
 * Cache-first strategy
 * @param {Request} request - Request object
 * @param {string} cacheName - Cache name
 * @returns {Promise<Response>} Response
 */
async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cachedResponse = await cache.match(request);
    
    if (cachedResponse) {
        console.log('[SW] Cache hit:', request.url);
        return cachedResponse;
    }
    
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.warn('[SW] Fetch failed, no cache:', request.url);
        return new Response('Offline - resource not available', {
            status: 503,
            statusText: 'Service Unavailable'
        });
    }
}

/**
 * Network-first with timeout strategy
 * @param {Request} request - Request object
 * @param {string} fallbackUrl - Fallback URL for offline
 * @returns {Promise<Response>} Response
 */
async function networkFirstWithTimeout(request, fallbackUrl = '/offline-page.html') {
    const timeoutPromise = new Promise((_, reject) => {
        setTimeout(() => reject(new Error('Network timeout')), NETWORK_TIMEOUT);
    });
    
    try {
        const networkResponse = await Promise.race([
            fetch(request),
            timeoutPromise
        ]);
        
        // Cache successful responses
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
            
            // Limit cache size
            await limitCacheSize(DYNAMIC_CACHE, MAX_DYNAMIC_CACHE_SIZE);
        }
        
        return networkResponse;
    } catch (error) {
        console.log('[SW] Network failed, trying cache:', request.url);
        
        const cache = await caches.open(DYNAMIC_CACHE);
        const cachedResponse = await cache.match(request);
        
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return fallback for navigation requests
        if (request.mode === 'navigate') {
            const fallback = await caches.match(fallbackUrl);
            if (fallback) {
                return fallback;
            }
        }
        
        return new Response('Offline - resource not available', {
            status: 503,
            statusText: 'Service Unavailable'
        });
    }
}

/**
 * Stale-while-revalidate strategy
 * @param {Request} request - Request object
 * @param {string} cacheName - Cache name
 * @returns {Promise<Response>} Response
 */
async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cachedResponse = await cache.match(request);
    
    const fetchPromise = fetch(request).then((networkResponse) => {
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    }).catch(() => cachedResponse);
    
    return cachedResponse || fetchPromise;
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Check if request is for static asset
 * @param {Request} request - Request object
 * @returns {boolean} Is static asset
 */
function isStaticAsset(request) {
    const url = new URL(request.url);
    return url.pathname.match(/\.(css|js|woff2?|ttf|eot)$/i) !== null;
}

/**
 * Check if request is for image
 * @param {Request} request - Request object
 * @returns {boolean} Is image request
 */
function isImageRequest(request) {
    return request.destination === 'image' || 
           new URL(request.url).pathname.match(/\.(png|jpg|jpeg|gif|svg|webp|ico)$/i) !== null;
}

/**
 * Check if request is API request
 * @param {Request} request - Request object
 * @returns {boolean} Is API request
 */
function isApiRequest(request) {
    return request.url.includes('/api/');
}

/**
 * Check if request is navigation request
 * @param {Request} request - Request object
 * @returns {boolean} Is navigation request
 */
function isNavigationRequest(request) {
    return request.mode === 'navigate';
}

/**
 * Limit cache size
 * @param {string} cacheName - Cache name
 * @param {number} maxSize - Maximum size
 */
async function limitCacheSize(cacheName, maxSize) {
    const cache = await caches.open(cacheName);
    const keys = await cache.keys();
    
    if (keys.length > maxSize) {
        await cache.delete(keys[0]);
    }
}

// ============================================
// BACKGROUND SYNC
// ============================================

self.addEventListener('sync', (event) => {
    console.log('[SW] Sync event:', event.tag);
    
    if (event.tag === 'sync-tasks') {
        event.waitUntil(syncTasks());
    } else if (event.tag === 'sync-offline-actions') {
        event.waitUntil(syncOfflineActions());
    }
});

/**
 * Sync pending tasks with server
 */
async function syncTasks() {
    console.log('[SW] Syncing tasks...');
    
    try {
        const pendingTasks = JSON.parse(localStorage.getItem('pendingTasks') || '[]');
        
        if (pendingTasks.length === 0) {
            console.log('[SW] No pending tasks to sync');
            return;
        }
        
        const promises = pendingTasks.map(task => {
            return fetch('/api/tasks', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(task)
            });
        });
        
        const responses = await Promise.all(promises);
        const successCount = responses.filter(r => r.ok).length;
        
        if (successCount === pendingTasks.length) {
            localStorage.removeItem('pendingTasks');
            console.log('[SW] All tasks synced successfully');
            
            // Notify client
            self.clients.matchAll().then(clients => {
                clients.forEach(client => {
                    client.postMessage({ type: 'TASKS_SYNCED', count: successCount });
                });
            });
        }
    } catch (error) {
        console.error('[SW] Task sync failed:', error);
        throw error; // Retry
    }
}

/**
 * Sync offline actions (generic)
 */
async function syncOfflineActions() {
    console.log('[SW] Syncing offline actions...');
    
    try {
        const offlineActions = JSON.parse(localStorage.getItem('offlineActions') || '[]');
        
        if (offlineActions.length === 0) {
            return;
        }
        
        for (const action of offlineActions) {
            try {
                await fetch(action.url, {
                    method: action.method,
                    headers: action.headers,
                    body: action.body
                });
                
                // Remove successful action
                offlineActions.splice(offlineActions.indexOf(action), 1);
            } catch (error) {
                console.warn('[SW] Action sync failed:', error);
            }
        }
        
        localStorage.setItem('offlineActions', JSON.stringify(offlineActions));
    } catch (error) {
        console.error('[SW] Offline actions sync failed:', error);
        throw error;
    }
}

// ============================================
// PUSH NOTIFICATIONS
// ============================================

self.addEventListener('push', (event) => {
    console.log('[SW] Push received');
    
    if (!self.Notification || self.Notification.permission !== 'granted') {
        return;
    }
    
    let data = {};
    
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        console.warn('[SW] Failed to parse push data:', e);
    }
    
    const title = data.title || 'CRM Задачи';
    const options = {
        body: data.body || 'У вас новые уведомления',
        icon: '/icons/icon-192x192.png',
        badge: '/icons/icon-96x96.png',
        tag: data.tag || 'todo-notification',
        data: { url: data.url || '/notifications' },
        requireInteraction: false,
        silent: false,
        actions: [
            { action: 'view', title: 'Просмотреть' },
            { action: 'dismiss', title: 'Закрыть' }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// ============================================
// NOTIFICATION CLICK
// ============================================

self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification click:', event.action);
    
    event.notification.close();
    
    if (event.action === 'dismiss') {
        return;
    }
    
    const urlToOpen = event.notification.data?.url || '/dashboard';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Try to focus existing window
                for (const client of clientList) {
                    if (client.url.includes(self.location.origin) && 'focus' in client) {
                        client.focus();
                        client.navigate(urlToOpen);
                        return;
                    }
                }
                
                // Open new window
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});

// ============================================
// MESSAGE HANDLING
// ============================================

self.addEventListener('message', (event) => {
    console.log('[SW] Message received:', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'CACHE_URLS') {
        event.waitUntil(
            caches.open(DYNAMIC_CACHE).then((cache) => {
                return cache.addAll(event.data.urls);
            })
        );
    }
    
    if (event.data && event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(
            caches.keys().then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => caches.delete(cacheName))
                );
            }).then(() => {
                self.clients.matchAll().then(clients => {
                    clients.forEach(client => {
                        client.postMessage({ type: 'CACHE_CLEARED' });
                    });
                });
            })
        );
    }
});

console.log('[SW] Service Worker loaded');
