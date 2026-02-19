/**
 * Service Worker для PWA
 * Offline поддержка, кэширование, push-уведомления
 * 
 * Версия: 2.0
 * Дата: 19 февраля 2026
 */

const CACHE_NAME = 'crm-cache-v2';
const STATIC_CACHE = 'static-v2';
const DYNAMIC_CACHE = 'dynamic-v2';
const OFFLINE_PAGE = '/offline-page.html';

// Критические ресурсы для кэширования
const CRITICAL_ASSETS = [
    '/',
    '/offline-page.html',
    '/manifest.json',
    '/css/themes-bundle.min.css',
    '/css/components-bundle.min.css',
    '/css/navbar-enhanced.min.css',
    '/css/base-layout.css',
    '/js/critical-functions.js',
    '/js/core-bundle.min.js',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

// ============================================================================
// INSTALL - Кэширование критических ресурсов
// ============================================================================
self.addEventListener('install', (event) => {
    console.log('[SW] Installing Service Worker...');
    
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => {
                console.log('[SW] Caching critical assets');
                return cache.addAll(CRITICAL_ASSETS);
            })
            .then(() => {
                console.log('[SW] Installation complete, skipping waiting');
                return self.skipWaiting();
            })
            .catch((error) => {
                console.error('[SW] Installation failed:', error);
            })
    );
});

// ============================================================================
// ACTIVATE - Очистка старых кэшей
// ============================================================================
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating Service Worker...');
    
    event.waitUntil(
        caches.keys()
            .then((keys) => {
                const oldKeys = keys.filter(key => 
                    key !== STATIC_CACHE && 
                    key !== DYNAMIC_CACHE &&
                    key !== CACHE_NAME
                );
                
                return Promise.all(
                    oldKeys.map(key => {
                        console.log('[SW] Removing old cache:', key);
                        return caches.delete(key);
                    })
                );
            })
            .then(() => {
                console.log('[SW] Activation complete, claiming clients');
                return self.clients.claim();
            })
    );
});

// ============================================================================
// FETCH - Стратегии кэширования
// ============================================================================
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Игнорируем не GET запросы
    if (request.method !== 'GET') {
        return;
    }
    
    // Игнорируем chrome-extension и другие не-http запросы
    if (!url.protocol.startsWith('http')) {
        return;
    }
    
    // Стратегия для API запросов - Network First
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(networkFirst(request));
        return;
    }
    
    // Стратегия для статических ресурсов - Cache First
    if (isStaticAsset(url)) {
        event.respondWith(cacheFirst(request));
        return;
    }
    
    // Стратегия для HTML страниц - Stale While Revalidate
    if (request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(staleWhileRevalidate(request));
        return;
    }
    
    // Default стратегия
    event.respondWith(networkFirst(request));
});

// ============================================================================
// Стратегии кэширования
// ============================================================================

// Cache First - для статических ресурсов
async function cacheFirst(request) {
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
        // Обновляем кэш в фоне
        fetch(request).then(response => {
            if (response && response.status === 200) {
                caches.open(STATIC_CACHE).then(cache => {
                    cache.put(request, response);
                });
            }
        }).catch(() => {
            // Игнорируем ошибки сети
        });
        
        return cachedResponse;
    }
    
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse && networkResponse.status === 200) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.error('[SW] Cache First failed:', error);
        return new Response('Offline', {
            status: 503,
            statusText: 'Service Unavailable'
        });
    }
}

// Network First - для API и динамического контента
async function networkFirst(request) {
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse && networkResponse.status === 200) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('[SW] Network failed, trying cache:', request.url);
        
        const cachedResponse = await caches.match(request);
        
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Если это запрос на навигацию, возвращаем offline страницу
        if (request.headers.get('accept')?.includes('text/html')) {
            return caches.match(OFFLINE_PAGE);
        }
        
        return new Response(JSON.stringify({
            error: 'Offline',
            message: 'No connection and resource not in cache'
        }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

// Stale While Revalidate - для HTML страниц
async function staleWhileRevalidate(request) {
    const cache = await caches.open(DYNAMIC_CACHE);
    const cachedResponse = await cache.match(request);
    
    const fetchPromise = fetch(request).then((networkResponse) => {
        if (networkResponse && networkResponse.status === 200) {
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    }).catch(() => {
        // Если сеть недоступна, возвращаем кэш или offline страницу
        return cachedResponse || caches.match(OFFLINE_PAGE);
    });
    
    return cachedResponse || fetchPromise;
}

// ============================================================================
// Вспомогательные функции
// ============================================================================

// Проверка на статический ресурс
function isStaticAsset(url) {
    const staticExtensions = [
        '.css', '.js', '.jpg', '.jpeg', '.png', '.gif', '.svg',
        '.webp', '.ico', '.woff', '.woff2', '.ttf', '.eot',
        '.json', '.xml', '.txt'
    ];
    
    const pathname = url.pathname.toLowerCase();
    return staticExtensions.some(ext => pathname.endsWith(ext)) ||
           url.hostname.includes('cdn.jsdelivr.net') ||
           url.hostname.includes('cdnjs.cloudflare.com');
}

// ============================================================================
// Push уведомления
// ============================================================================
self.addEventListener('push', (event) => {
    console.log('[SW] Push received:', event);
    
    let data = {};
    
    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            data = { title: 'Уведомление', body: event.data.text() };
        }
    }
    
    const title = data.title || 'CRM Tasks';
    const options = {
        body: data.body || 'Новое уведомление',
        icon: data.icon || '/icons/icon-192x192.png',
        badge: data.badge || '/icons/icon-72x72.png',
        image: data.image,
        data: data.data || {},
        actions: data.actions || [
            { action: 'view', title: 'Просмотр' },
            { action: 'dismiss', title: 'Закрыть' }
        ],
        tag: data.tag || 'default',
        requireInteraction: data.requireInteraction || false,
        silent: data.silent || false
    };
    
    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// ============================================================================
// Обработка кликов по уведомлениям
// ============================================================================
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification click:', event);
    
    event.notification.close();
    
    if (event.action === 'dismiss') {
        return;
    }
    
    const urlToOpen = event.notification.data?.url || '/dashboard';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((windowClients) => {
                // Проверяем, есть ли уже открытая вкладка
                for (let client of windowClients) {
                    if (client.url === urlToOpen && 'focus' in client) {
                        return client.focus();
                    }
                }
                
                // Открываем новую вкладку
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});

// ============================================================================
// Фоновая синхронизация
// ============================================================================
self.addEventListener('sync', (event) => {
    console.log('[SW] Sync event:', event.tag);
    
    if (event.tag === 'sync-tasks') {
        event.waitUntil(syncTasks());
    }
    
    if (event.tag === 'sync-notifications') {
        event.waitUntil(syncNotifications());
    }
});

async function syncTasks() {
    console.log('[SW] Syncing tasks...');
    // Логика синхронизации задач
}

async function syncNotifications() {
    console.log('[SW] Syncing notifications...');
    // Логика синхронизации уведомлений
}

// ============================================================================
// Сообщения от клиентов
// ============================================================================
self.addEventListener('message', (event) => {
    console.log('[SW] Message received:', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(
            caches.keys().then(keys => {
                return Promise.all(
                    keys.map(key => caches.delete(key))
                );
            }).then(() => {
                return self.clients.matchAll().then(clients => {
                    clients.forEach(client => {
                        client.postMessage({ type: 'CACHE_CLEARED' });
                    });
                });
            })
        );
    }
    
    if (event.data && event.data.type === 'GET_CACHE_STATUS') {
        event.waitUntil(
            getCacheStatus().then(status => {
                event.ports[0].postMessage(status);
            })
        );
    }
});

async function getCacheStatus() {
    const keys = await caches.keys();
    const cacheInfo = {};
    
    for (const key of keys) {
        const cache = await caches.open(key);
        const requests = await cache.keys();
        cacheInfo[key] = requests.length;
    }
    
    return {
        caches: cacheInfo,
        version: '2.0',
        timestamp: new Date().toISOString()
    };
}

console.log('[SW] Service Worker loaded');
