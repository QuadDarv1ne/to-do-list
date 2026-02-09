// Service Worker for To-Do List App
// Provides offline functionality and caching

const CACHE_NAME = 'todo-app-v1.0';
const urlsToCache = [
    '/',
    '/dashboard',
    '/tasks',
    '/login',
    '/register',
    '/styles/app.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'
];

// Install event - cache essential resources
self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function(cache) {
                console.log('Opened cache');
                return cache.addAll(urlsToCache);
            })
    );
});

// Fetch event - serve cached content when offline
self.addEventListener('fetch', function(event) {
    event.respondWith(
        caches.match(event.request)
            .then(function(response) {
                // Cache hit - return response
                if (response) {
                    return response;
                }
                
                // Clone the request because it's a stream
                const fetchRequest = event.request.clone();
                
                return fetch(fetchRequest).then(
                    function(response) {
                        // Check if we received a valid response
                        if(!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }
                        
                        // Clone the response because it's a stream
                        const responseToCache = response.clone();
                        
                        caches.open(CACHE_NAME)
                            .then(function(cache) {
                                cache.put(event.request, responseToCache);
                            });
                            
                        return response;
                    }
                );
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', function(event) {
    const cacheWhitelist = [CACHE_NAME];
    
    event.waitUntil(
        caches.keys().then(function(cacheNames) {
            return Promise.all(
                cacheNames.map(function(cacheName) {
                    if (cacheWhitelist.indexOf(cacheName) === -1) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

// Handle background sync for task creation
self.addEventListener('sync', function(event) {
    if (event.tag === 'sync-tasks') {
        event.waitUntil(syncTasks());
    }
});

// Sync pending tasks with server
function syncTasks() {
    return new Promise((resolve, reject) => {
        // Get pending tasks from IndexedDB or localStorage
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
            });
        });
        
        Promise.all(promises)
            .then(responses => {
                // Clear pending tasks after successful sync
                localStorage.removeItem('pendingTasks');
                resolve();
            })
            .catch(error => {
                console.error('Sync failed:', error);
                reject(error);
            });
    });
}

// Handle push notifications
self.addEventListener('push', function(event) {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        return;
    }
    
    const data = event.data ? event.data.json() : { title: 'To-Do List', body: 'You have new notifications' };
    
    const title = data.title || 'To-Do List';
    const options = {
        body: data.body || 'You have new notifications',
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        tag: 'todo-notification',
        data: data.url || '/notifications'
    };
    
    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// Handle notification clicks
self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    
    const url = event.notification.data || '/notifications';
    
    event.waitUntil(
        clients.matchAll({ type: 'window' }).then(clientList => {
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }
            
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});