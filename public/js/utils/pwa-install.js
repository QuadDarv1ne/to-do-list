/**
 * PWA Install Prompt & Offline Support
 * Установка приложения и офлайн-поддержка
 */

(function() {
    'use strict';

    class PWAInstall {
        constructor(options = {}) {
            this.options = {
                promptDelay: 3000,
                storageKey: 'pwa_install_dismissed',
                ...options
            };

            this.deferredPrompt = null;
            this.isInstalled = this.checkIfInstalled();

            this.init();
        }

        /**
         * Initialize PWA features
         */
        init() {
            // Register Service Worker
            this.registerServiceWorker();

            // Setup install prompt
            this.setupInstallPrompt();

            // Handle online/offline events
            this.setupOnlineOfflineHandlers();

            // Check if app is running as PWA
            this.checkPWAMode();

            // Show install prompt if eligible
            if (!this.isInstalled && !this.hasDismissed()) {
                setTimeout(() => this.showInstallPrompt(), this.options.promptDelay);
            }
        }

        /**
         * Register Service Worker
         */
        async registerServiceWorker() {
            if ('serviceWorker' in navigator) {
                try {
                    const registration = await navigator.serviceWorker.register('/service-worker.js', {
                        scope: '/'
                    });

                    if (window.logger) window.logger.log('[PWA] Service Worker registered:', registration.scope);

                    // Check for updates
                    registration.addEventListener('updatefound', () => {
                        const newWorker = registration.installing;
                        
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                this.showUpdatePrompt();
                            }
                        });
                    });

                    // Handle messages from SW
                    navigator.serviceWorker.addEventListener('message', (event) => {
                        this.handleSWMessage(event.data);
                    });

                } catch (error) {
                    console.error('[PWA] Service Worker registration failed:', error);
                }
            }
        }

        /**
         * Setup beforeinstallprompt handler
         */
        setupInstallPrompt() {
            window.addEventListener('beforeinstallprompt', (e) => {
                if (window.logger) window.logger.log('[PWA] beforeinstallprompt event fired');
                
                // Prevent Chrome from showing prompt automatically
                e.preventDefault();
                
                // Store the event for later use
                this.deferredPrompt = e;
                
                // Update UI to show install button
                this.showInstallButton();
            });

            // Handle app installed event
            window.addEventListener('appinstalled', () => {
                if (window.logger) window.logger.log('[PWA] App installed successfully');
                this.deferredPrompt = null;
                this.isInstalled = true;
                this.hideInstallPrompt();
                localStorage.setItem(this.options.storageKey, 'true');
            });
        }

        /**
         * Setup online/offline event handlers
         */
        setupOnlineOfflineHandlers() {
            window.addEventListener('online', () => {
                if (window.logger) window.logger.log('[PWA] Connection restored');
                this.showConnectionToast('online');
                this.syncOfflineData();
            });

            window.addEventListener('offline', () => {
                if (window.logger) window.logger.log('[PWA] Connection lost');
                this.showConnectionToast('offline');
            });
        }

        /**
         * Check if app is running as PWA
         */
        checkPWAMode() {
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches;
            const isStandaloneWindow = window.navigator.standalone === true;
            
            if (isStandalone || isStandaloneWindow) {
                if (window.logger) window.logger.log('[PWA] Running as standalone PWA');
                document.body.classList.add('pwa-mode');
            } else {
                if (window.logger) window.logger.log('[PWA] Running in browser');
            }
        }

        /**
         * Check if app is installed
         * @returns {boolean} Is installed
         */
        checkIfInstalled() {
            return window.matchMedia('(display-mode: standalone)').matches ||
                   window.navigator.standalone === true ||
                   localStorage.getItem(this.options.storageKey) === 'true';
        }

        /**
         * Check if user dismissed install prompt
         * @returns {boolean} Has dismissed
         */
        hasDismissed() {
            return localStorage.getItem(this.options.storageKey) === 'true';
        }

        /**
         * Show install button
         */
        showInstallButton() {
            // Check if button already exists
            if (document.querySelector('.pwa-install-btn')) {
                return;
            }

            const installBtn = document.createElement('button');
            installBtn.className = 'btn btn-primary pwa-install-btn';
            installBtn.innerHTML = `
                <i class="fas fa-download me-2"></i>
                Установить приложение
            `;
            installBtn.addEventListener('click', () => this.promptInstall());

            // Add to navbar or header
            const navbar = document.querySelector('.navbar-nav.ms-auto');
            if (navbar) {
                const li = document.createElement('li');
                li.className = 'nav-item';
                li.appendChild(installBtn);
                navbar.insertBefore(li, navbar.firstChild);
            }
        }

        /**
         * Show install prompt modal
         */
        showInstallPrompt() {
            if (this.deferredPrompt || this.canInstall()) {
                const modal = document.createElement('div');
                modal.className = 'modal fade pwa-install-modal';
                modal.id = 'pwaInstallModal';
                modal.tabIndex = '-1';
                modal.innerHTML = `
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-mobile-alt me-2"></i>
                                    Установить приложение
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center mb-4">
                                    <i class="fas fa-download fa-3x text-primary mb-3"></i>
                                    <h4>CRM Задачи всегда под рукой</h4>
                                    <p class="text-muted">
                                        Установите наше приложение для быстрого доступа и работы в офлайн-режиме
                                    </p>
                                </div>
                                
                                <div class="list-group mb-4">
                                    <div class="list-group-item d-flex align-items-center gap-3">
                                        <i class="fas fa-bolt text-warning fa-lg"></i>
                                        <div>
                                            <strong>Быстрый доступ</strong>
                                            <p class="mb-0 text-muted small">Запускайте приложение в один клик</p>
                                        </div>
                                    </div>
                                    <div class="list-group-item d-flex align-items-center gap-3">
                                        <i class="fas fa-wifi text-success fa-lg"></i>
                                        <div>
                                            <strong>Офлайн-режим</strong>
                                            <p class="mb-0 text-muted small">Работайте без интернета</p>
                                        </div>
                                    </div>
                                    <div class="list-group-item d-flex align-items-center gap-3">
                                        <i class="fas fa-bell text-info fa-lg"></i>
                                        <div>
                                            <strong>Уведомления</strong>
                                            <p class="mb-0 text-muted small">Получайте push-уведомления</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" onclick="PWAInstall.dismiss()">
                                    Позже
                                </button>
                                <button type="button" class="btn btn-primary" onclick="PWAInstall.install()">
                                    <i class="fas fa-download me-2"></i>
                                    Установить
                                </button>
                            </div>
                        </div>
                    </div>
                `;

                document.body.appendChild(modal);
                const bsModal = new bootstrap.Modal(modal);
                bsModal.show();
            }
        }

        /**
         * Check if can install (iOS Safari)
         * @returns {boolean} Can install
         */
        canInstall() {
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
            const isStandalone = window.navigator.standalone === true;
            
            return isIOS && !isStandalone;
        }

        /**
         * Prompt user to install
         */
        async promptInstall() {
            // Close modal if open
            const modal = document.getElementById('pwaInstallModal');
            if (modal) {
                const bsModal = bootstrap.Modal.getInstance(modal);
                bsModal.hide();
            }

            if (this.deferredPrompt) {
                // Android/Desktop - use beforeinstallprompt
                this.deferredPrompt.prompt();
                
                const { outcome } = await this.deferredPrompt.userChoice;
                if (window.logger) window.logger.log('[PWA] User choice:', outcome);
                
                if (outcome === 'accepted' && window.logger) {
                    window.logger.log('[PWA] User accepted install prompt');
                }
                
                this.deferredPrompt = null;
            } else if (this.canInstall()) {
                // iOS - show manual instructions
                this.showIOSInstallInstructions();
            }
        }

        /**
         * Show iOS install instructions
         */
        showIOSInstallInstructions() {
            const modal = document.createElement('div');
            modal.className = 'modal fade pwa-install-modal';
            modal.id = 'pwaIOSModal';
            modal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fab fa-apple me-2"></i>
                                Установка на iOS
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <p class="mb-4">Чтобы установить приложение на iOS:</p>
                            
                            <div class="text-start">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <span class="badge bg-primary rounded-circle" style="width: 32px; height: 32px;">1</span>
                                    <span>Нажмите кнопку "Поделиться"</span>
                                    <i class="fas fa-share-square fa-lg text-primary"></i>
                                </div>
                                
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <span class="badge bg-primary rounded-circle" style="width: 32px; height: 32px;">2</span>
                                    <span>Выберите "На экран «Домой»"</span>
                                    <i class="fas fa-plus-square fa-lg text-primary"></i>
                                </div>
                                
                                <div class="d-flex align-items-center gap-3">
                                    <span class="badge bg-primary rounded-circle" style="width: 32px; height: 32px;">3</span>
                                    <span>Нажмите "Добавить"</span>
                                    <i class="fas fa-check-square fa-lg text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }

        /**
         * Dismiss install prompt
         */
        dismiss() {
            localStorage.setItem(this.options.storageKey, 'true');
            this.hideInstallPrompt();
        }

        /**
         * Hide install prompt
         */
        hideInstallPrompt() {
            const btn = document.querySelector('.pwa-install-btn');
            if (btn) {
                btn.remove();
            }
            
            const modal = document.getElementById('pwaInstallModal');
            if (modal) {
                modal.remove();
            }
        }

        /**
         * Show update prompt
         */
        showUpdatePrompt() {
            const toast = document.createElement('div');
            toast.className = 'toast position-fixed bottom-0 end-0 m-3';
            toast.role = 'alert';
            toast.innerHTML = `
                <div class="toast-header">
                    <i class="fas fa-sync fa-spin me-2 text-primary"></i>
                    <strong class="me-auto">Доступно обновление</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    Новая версия приложения готова. 
                    <button class="btn btn-sm btn-primary ms-2" onclick="location.reload()">
                        Обновить
                    </button>
                </div>
            `;

            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast, { autohide: false });
            bsToast.show();
        }

        /**
         * Show connection toast
         * @param {string} status - 'online' or 'offline'
         */
        showConnectionToast(status) {
            const toast = document.createElement('div');
            toast.className = 'toast position-fixed top-0 start-50 translate-middle-x m-3';
            toast.role = 'alert';
            
            const isSuccess = status === 'online';
            
            toast.innerHTML = `
                <div class="toast-header bg-${isSuccess ? 'success' : 'warning'} text-white">
                    <i class="fas fa-${isSuccess ? 'wifi' : 'wifi-slash'} me-2"></i>
                    <strong class="me-auto">${isSuccess ? 'Подключение восстановлено' : 'Нет подключения'}</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                ${!isSuccess ? `
                    <div class="toast-body">
                        Вы можете просматривать кэшированные страницы
                        <a href="/offline-page.html" class="btn btn-sm btn-outline-warning ms-2">
                            Офлайн-режим
                        </a>
                    </div>
                ` : ''}
            `;

            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
            bsToast.show();
        }

        /**
         * Sync offline data
         */
        async syncOfflineData() {
            if ('serviceWorker' in navigator && 'sync' in window.registration) {
                try {
                    await navigator.serviceWorker.ready;
                    await window.registration.sync.register('sync-offline-actions');
                    if (window.logger) window.logger.log('[PWA] Offline data synced');
                } catch (error) {
                    if (window.logger) window.logger.log('[PWA] Sync not available:', error);
                }
            }
        }

        /**
         * Handle Service Worker messages
         * @param {Object} data - Message data
         */
        handleSWMessage(data) {
            console.log('[PWA] Message from SW:', data);
            
            switch (data.type) {
                case 'TASKS_SYNCED':
                    showToast(`Синхронизировано задач: ${data.count}`, 'success');
                    break;
                case 'CACHE_CLEARED':
                    console.log('[PWA] Cache cleared');
                    break;
            }
        }

        /**
         * Install app (exposed to global scope)
         */
        install() {
            this.promptInstall();
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.PWAInstall = new PWAInstall();
        });
    } else {
        window.PWAInstall = new PWAInstall();
    }

    // Expose methods to global scope for onclick handlers
    window.PWAInstall = window.PWAInstall || {};
    window.PWAInstall.install = () => window.PWAInstall.promptInstall();
    window.PWAInstall.dismiss = () => window.PWAInstall.dismiss();

})();
