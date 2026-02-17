// Service Worker for To-Do List App
// Provides offline functionality and caching (relaxed mode)

const CACHE_NAME = 'todo-app-v1.1';
const urlsToCache = [
    '/',
    '/dashboard',
    '/tasks'
];

// Install event - cache essential resources (non-blocking)
self.addEventListener('install', function(event) {
    // Skip waiting to activate immediately
    self.skipWaiting();
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function(cache) {
                console.log('Opened cache');
                // Use addAll with error handling to not block on failures
                return cache.addAll(urlsToCache).catch(err => {
                    console.warn('Some resources failed to cache:', err);
                    // Continue anyway
                });
            })
    );
});

// Fetch event - network first, fallback to cache (relaxed strategy)
self.addEventListener('fetch', function(event) {
    // Skip non-GET requests
    if (event.request.method !== 'GET') {
        return;
    }
    
    // Skip chrome extensions and other non-http(s) requests
    if (!event.request.url.startsWith('http')) {
        return;
    }
    
    event.respondWith(
        // Try network first
        fetch(event.request)
            .then(function(response) {
                // Check if valid response
                if (!response || response.status !== 200) {
                    return response;
                }
                
                // Only cache same-origin requests
                if (event.request.url.startsWith(self.location.origin)) {
                    const responseToCache = response.clone();
                    
                    caches.open(CACHE_NAME)
                        .then(function(cache) {
                            cache.put(event.request, responseToCache).catch(err => {
                                console.warn('Failed to cache:', err);
                            });
                        });
                }
                
                return response;
            })
            .catch(function(error) {
                // Network failed, try cache
                return caches.match(event.request)
                    .then(function(response) {
                        if (response) {
                            console.log('Serving from cache:', event.request.url);
                            return response;
                        }
                        
                        // If not in cache and network failed, return error
                        console.warn('No cache match for:', event.request.url);
                        return new Response('Offline - resource not available', {
                            status: 503,
                            statusText: 'Service Unavailable',
                            headers: new Headers({
                                'Content-Type': 'text/plain'
                            })
                        });
                    });
            })
    );
});

// Activate event - clean up old caches and take control immediately
self.addEventListener('activate', function(event) {
    // Take control of all pages immediately
    event.waitUntil(
        Promise.all([
            // Clean up old caches
            caches.keys().then(function(cacheNames) {
                return Promise.all(
                    cacheNames.map(function(cacheName) {
                        if (cacheName !== CACHE_NAME) {
                            console.log('Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            }),
            // Take control immediately
            self.clients.claim()
        ])
    );
});

// Handle background sync for task creation (optional)
self.addEventListener('sync', function(event) {
    if (event.tag === 'sync-tasks') {
        event.waitUntil(
            syncTasks().catch(err => {
                console.warn('Sync failed, will retry:', err);
            })
        );
    }
});

// Sync pending tasks with server (non-blocking)
function syncTasks() {
    return new Promise((resolve, reject) => {
        try {
            // Get pending tasks from localStorage
            const pendingTasks = JSON.parse(localStorage.getItem('pendingTasks') || '[]');
            
            if (pendingTasks.length === 0) {
                resolve();
                return;
            }
            
            // Send each pending task to server
            const promises = pendingTasks.map(task => {
                return fetch('/api/tasks', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(task)
                }).catch(err => {
                    console.warn('Task sync failed:', err);
                    return null; // Don't fail entire sync
                });
            });
            
            Promise.all(promises)
                .then(responses => {
                    // Clear only successfully synced tasks
                    const successCount = responses.filter(r => r && r.ok).length;
                    console.log(`Synced ${successCount}/${pendingTasks.length} tasks`);
                    
                    if (successCount === pendingTasks.length) {
                        localStorage.removeItem('pendingTasks');
                    }
                    
                    resolve();
                })
                .catch(error => {
                    console.error('Sync error:', error);
                    reject(error);
                });
        } catch (error) {
            console.error('Sync exception:', error);
            reject(error);
        }
    });
}

// Handle push notifications (optional, non-blocking)
self.addEventListener('push', function(event) {
    // Check if notifications are supported and permitted
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        console.log('Notifications not permitted');
        return;
    }
    
    try {
        const data = event.data ? event.data.json() : { 
            title: 'To-Do List', 
            body: 'У вас новые уведомления' 
        };
        
        const title = data.title || 'To-Do List';
        const options = {
            body: data.body || 'У вас новые уведомления',
            icon: '/favicon.ico',
            badge: '/favicon.ico',
            tag: 'todo-notification',
            data: data.url || '/notifications',
            requireInteraction: false // Don't force user interaction
        };
        
        event.waitUntil(
            self.registration.showNotification(title, options)
                .catch(err => {
                    console.warn('Failed to show notification:', err);
                })
        );
    } catch (error) {
        console.error('Push notification error:', error);
    }
});

// Handle notification clicks (optional)
self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    
    const url = event.notification.data || '/notifications';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(clientList => {
                // Try to focus existing window
                for (let i = 0; i < clientList.length; i++) {
                    const client = clientList[i];
                    if (client.url.includes(self.location.origin) && 'focus' in client) {
                        return client.focus().then(() => client.navigate(url));
                    }
                }
                
                // Open new window if no existing window found
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
            .catch(err => {
                console.warn('Failed to handle notification click:', err);
            })
    );
});