import { Controller } from '@hotwired/stimulus';

export class PwaController extends Controller {
    static values = {
        updateAvailable: { type: Boolean, default: false }
    };

    connect() {
        this.initPwa();
    }

    async initPwa() {
        // Register Service Worker
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/sw.js', {
                    scope: '/'
                });
                
                console.log('SW registered:', registration.scope);

                // Check for updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            this.updateAvailableValue = true;
                            this.showUpdateNotification();
                        }
                    });
                });

                // Check if there's already a controller
                if (registration.active && !navigator.serviceWorker.controller) {
                    this.showUpdateNotification();
                }

            } catch (error) {
                console.error('SW registration failed:', error);
            }
        }

        // Register for push notifications
        if ('Notification' in window && 'PushManager' in window) {
            this.setupPushNotifications();
        }

        // Handle online/offline status
        this.setupNetworkStatus();
    }

    async setupPushNotifications() {
        const permission = Notification.permission;
        
        if (permission === 'default') {
            // Could show a button to request permission
        } else if (permission === 'granted') {
            await this.subscribeToPush();
        }
    }

    async subscribeToPush() {
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array('BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U')
            });

            // Send subscription to server
            await fetch('/api/push/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(subscription),
            });

        } catch (error) {
            console.error('Push subscription failed:', error);
        }
    }

    setupNetworkStatus() {
        const updateOnlineStatus = () => {
            if (navigator.onLine) {
                this.showOnlineIndicator();
            } else {
                this.showOfflineIndicator();
            }
        };

        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        
        // Initial check
        updateOnlineStatus();
    }

    showOnlineIndicator() {
        const indicator = document.getElementById('network-status');
        if (indicator) {
            indicator.classList.remove('show', 'offline');
            indicator.classList.add('online');
            
            setTimeout(() => {
                indicator.classList.remove('show');
            }, 3000);
        }
    }

    showOfflineIndicator() {
        let indicator = document.getElementById('network-status');
        
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'network-status';
            indicator.className = 'network-status offline show';
            indicator.innerHTML = '<i class="fas fa-wifi"></i> <span>Нет подключения к интернету</span>';
            document.body.appendChild(indicator);
        } else {
            indicator.classList.remove('online');
            indicator.classList.add('offline', 'show');
        }
    }

    showUpdateNotification() {
        // Show toast or banner about available update
        const toast = document.createElement('div');
        toast.className = 'pwa-update-toast';
        toast.innerHTML = `
            <div class="pwa-update-content">
                <i class="fas fa-download"></i>
                <span>Доступна новая версия приложения</span>
            </div>
            <button class="pwa-update-btn" data-action="click->pwa#updateApp">Обновить</button>
        `;
        document.body.appendChild(toast);
    }

    updateApp() {
        window.location.reload();
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }

        return outputArray;
    }
}
