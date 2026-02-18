/**
 * Fullscreen Mode
 * Полноэкранный режим
 */

class FullscreenMode {
    constructor() {
        this.isFullscreen = false;
        this.button = null;
        this.init();
    }

    init() {
        this.createButton();
        this.bindEvents();
    }

    createButton() {
        this.button = document.createElement('button');
        this.button.className = 'fullscreen-button';
        this.button.innerHTML = '<i class="fas fa-expand"></i>';
        this.button.title = 'Полноэкранный режим (F11)';
        this.button.setAttribute('aria-label', 'Переключить полноэкранный режим');

        this.button.addEventListener('click', () => {
            this.toggle();
        });

        document.body.appendChild(this.button);
        this.addStyles();
    }

    bindEvents() {
        // Отслеживаем изменения полноэкранного режима
        document.addEventListener('fullscreenchange', () => {
            this.isFullscreen = !!document.fullscreenElement;
            this.updateButton();
        });

        document.addEventListener('webkitfullscreenchange', () => {
            this.isFullscreen = !!document.webkitFullscreenElement;
            this.updateButton();
        });

        // F11 для переключения
        document.addEventListener('keydown', (e) => {
            if (e.key === 'F11') {
                e.preventDefault();
                this.toggle();
            }
        });
    }

    async toggle() {
        if (this.isFullscreen) {
            await this.exit();
        } else {
            await this.enter();
        }
    }

    async enter() {
        try {
            const elem = document.documentElement;
            
            if (elem.requestFullscreen) {
                await elem.requestFullscreen();
            } else if (elem.webkitRequestFullscreen) {
                await elem.webkitRequestFullscreen();
            } else if (elem.msRequestFullscreen) {
                await elem.msRequestFullscreen();
            }
        } catch (error) {
            console.error('Fullscreen error:', error);
        }
    }

    async exit() {
        try {
            if (document.exitFullscreen) {
                await document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                await document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                await document.msExitFullscreen();
            }
        } catch (error) {
            console.error('Exit fullscreen error:', error);
        }
    }

    updateButton() {
        if (this.isFullscreen) {
            this.button.innerHTML = '<i class="fas fa-compress"></i>';
            this.button.title = 'Выйти из полноэкранного режима (F11)';
        } else {
            this.button.innerHTML = '<i class="fas fa-expand"></i>';
            this.button.title = 'Полноэкранный режим (F11)';
        }
    }

    addStyles() {
        if (document.getElementById('fullscreenModeStyles')) return;

        const style = document.createElement('style');
        style.id = 'fullscreenModeStyles';
        style.textContent = `
            .fullscreen-button {
                position: fixed;
                bottom: 220px;
                right: 30px;
                width: 44px;
                height: 44px;
                border-radius: 50%;
                background: var(--bg-card);
                border: 1px solid var(--border);
                color: var(--text-primary);
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1rem;
                z-index: 996;
                transition: all 0.2s ease;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .fullscreen-button:hover {
                background: var(--primary);
                color: white;
                transform: scale(1.05);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            }

            @media (max-width: 768px) {
                .fullscreen-button {
                    bottom: 200px;
                    right: 20px;
                    width: 40px;
                    height: 40px;
                }
            }
        `;

        document.head.appendChild(style);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.fullscreenMode = new FullscreenMode();
});

window.FullscreenMode = FullscreenMode;
