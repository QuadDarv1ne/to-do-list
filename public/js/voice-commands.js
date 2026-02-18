/**
 * Voice Commands
 * Голосовое управление приложением
 */

class VoiceCommands {
    constructor() {
        this.recognition = null;
        this.isListening = false;
        this.commands = new Map();
        this.button = null;
        this.init();
    }

    init() {
        if (!this.checkSupport()) {
            console.log('Voice recognition not supported');
            return;
        }

        this.setupRecognition();
        this.registerCommands();
        this.createButton();
    }

    checkSupport() {
        return 'webkitSpeechRecognition' in window || 'SpeechRecognition' in window;
    }

    setupRecognition() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        this.recognition = new SpeechRecognition();
        
        this.recognition.lang = 'ru-RU';
        this.recognition.continuous = false;
        this.recognition.interimResults = false;

        this.recognition.onstart = () => {
            this.isListening = true;
            this.updateButton();
            this.showListening();
        };

        this.recognition.onend = () => {
            this.isListening = false;
            this.updateButton();
            this.hideListening();
        };

        this.recognition.onresult = (event) => {
            const transcript = event.results[0][0].transcript.toLowerCase();
            this.processCommand(transcript);
        };

        this.recognition.onerror = (event) => {
            console.error('Speech recognition error:', event.error);
            this.isListening = false;
            this.updateButton();
            this.hideListening();
        };
    }

    registerCommands() {
        // Навигация
        this.commands.set('главная', () => window.location.href = '/');
        this.commands.set('панель', () => window.location.href = '/dashboard');
        this.commands.set('задачи', () => window.location.href = '/task');
        this.commands.set('канбан', () => window.location.href = '/kanban');
        this.commands.set('календарь', () => window.location.href = '/calendar');
        this.commands.set('профиль', () => window.location.href = '/profile');
        this.commands.set('настройки', () => window.location.href = '/settings');

        // Действия
        this.commands.set('новая задача', () => {
            const btn = document.getElementById('quick-task-fab');
            if (btn) btn.click();
        });

        this.commands.set('поиск', () => {
            const search = document.querySelector('input[type="search"]');
            if (search) search.focus();
        });

        // Темы
        this.commands.set('светлая тема', () => {
            if (window.themeManager) {
                window.themeManager.setTheme('light');
            }
        });

        this.commands.set('тёмная тема', () => {
            if (window.themeManager) {
                window.themeManager.setTheme('dark');
            }
        });

        // Помощь
        this.commands.set('помощь', () => {
            this.showHelp();
        });

        this.commands.set('команды', () => {
            this.showHelp();
        });
    }

    processCommand(transcript) {
        console.log('Voice command:', transcript);

        let commandFound = false;

        // Ищем точное совпадение
        if (this.commands.has(transcript)) {
            this.commands.get(transcript)();
            commandFound = true;
        } else {
            // Ищем частичное совпадение
            for (const [command, action] of this.commands.entries()) {
                if (transcript.includes(command)) {
                    action();
                    commandFound = true;
                    break;
                }
            }
        }

        if (commandFound) {
            this.showSuccess(`Команда "${transcript}" выполнена`);
        } else {
            this.showError(`Команда "${transcript}" не распознана`);
        }
    }

    start() {
        if (!this.recognition) return;
        
        try {
            this.recognition.start();
        } catch (error) {
            console.error('Failed to start recognition:', error);
        }
    }

    stop() {
        if (!this.recognition) return;
        
        try {
            this.recognition.stop();
        } catch (error) {
            console.error('Failed to stop recognition:', error);
        }
    }

    toggle() {
        if (this.isListening) {
            this.stop();
        } else {
            this.start();
        }
    }

    createButton() {
        this.button = document.createElement('button');
        this.button.className = 'voice-command-button';
        this.button.innerHTML = '<i class="fas fa-microphone"></i>';
        this.button.title = 'Голосовые команды (V)';
        this.button.setAttribute('aria-label', 'Голосовые команды');

        this.button.addEventListener('click', () => {
            this.toggle();
        });

        document.body.appendChild(this.button);

        // Горячая клавиша V
        document.addEventListener('keydown', (e) => {
            if (e.key === 'v' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                    return;
                }
                e.preventDefault();
                this.toggle();
            }
        });

        this.addStyles();
    }

    updateButton() {
        if (!this.button) return;

        if (this.isListening) {
            this.button.classList.add('listening');
        } else {
            this.button.classList.remove('listening');
        }
    }

    showListening() {
        const indicator = document.createElement('div');
        indicator.className = 'voice-listening-indicator';
        indicator.innerHTML = `
            <div class="voice-listening-content">
                <div class="voice-listening-icon">
                    <i class="fas fa-microphone"></i>
                </div>
                <p>Слушаю...</p>
            </div>
        `;
        document.body.appendChild(indicator);
    }

    hideListening() {
        const indicator = document.querySelector('.voice-listening-indicator');
        if (indicator) {
            indicator.remove();
        }
    }

    showHelp() {
        const commands = Array.from(this.commands.keys());
        const helpText = `
            <h5>Доступные голосовые команды:</h5>
            <ul>
                ${commands.map(cmd => `<li>${cmd}</li>`).join('')}
            </ul>
        `;

        if (window.showToast) {
            // Создаем модальное окно
            const modal = document.createElement('div');
            modal.className = 'voice-help-modal';
            modal.innerHTML = `
                <div class="voice-help-content">
                    <div class="voice-help-header">
                        <h4>Голосовые команды</h4>
                        <button class="voice-help-close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="voice-help-body">
                        ${helpText}
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            modal.querySelector('.voice-help-close').addEventListener('click', () => {
                modal.remove();
            });

            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.remove();
                }
            });
        }
    }

    showSuccess(message) {
        if (window.showToast) {
            window.showToast(message, 'success');
        }
    }

    showError(message) {
        if (window.showToast) {
            window.showToast(message, 'error');
        }
    }

    addStyles() {
        if (document.getElementById('voiceCommandsStyles')) return;

        const style = document.createElement('style');
        style.id = 'voiceCommandsStyles';
        style.textContent = `
            .voice-command-button {
                position: fixed;
                bottom: 100px;
                right: 30px;
                width: 50px;
                height: 50px;
                border-radius: 50%;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.125rem;
                z-index: 998;
                transition: all 0.3s ease;
            }

            .voice-command-button:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
            }

            .voice-command-button.listening {
                animation: pulse 1.5s infinite;
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            }

            @keyframes pulse {
                0%, 100% {
                    transform: scale(1);
                    box-shadow: 0 4px 12px rgba(240, 147, 251, 0.4);
                }
                50% {
                    transform: scale(1.1);
                    box-shadow: 0 6px 20px rgba(240, 147, 251, 0.6);
                }
            }

            .voice-listening-indicator {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(0, 0, 0, 0.8);
                border-radius: 16px;
                padding: 2rem;
                z-index: 10000;
                animation: fadeIn 0.3s ease;
            }

            .voice-listening-content {
                text-align: center;
                color: white;
            }

            .voice-listening-icon {
                font-size: 3rem;
                margin-bottom: 1rem;
                animation: pulse 1.5s infinite;
            }

            .voice-help-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10001;
            }

            .voice-help-content {
                background: var(--bg-card);
                border-radius: 12px;
                max-width: 500px;
                width: 90%;
                max-height: 80vh;
                overflow-y: auto;
            }

            .voice-help-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1rem 1.5rem;
                border-bottom: 1px solid var(--border);
            }

            .voice-help-header h4 {
                margin: 0;
                color: var(--text-primary);
            }

            .voice-help-close {
                background: none;
                border: none;
                color: var(--text-secondary);
                cursor: pointer;
                font-size: 1.25rem;
                padding: 4px;
            }

            .voice-help-body {
                padding: 1.5rem;
            }

            .voice-help-body ul {
                list-style: none;
                padding: 0;
            }

            .voice-help-body li {
                padding: 0.5rem;
                margin-bottom: 0.5rem;
                background: var(--bg-body);
                border-radius: 6px;
                color: var(--text-primary);
            }

            @media (max-width: 768px) {
                .voice-command-button {
                    bottom: 90px;
                    right: 20px;
                    width: 46px;
                    height: 46px;
                }
            }
        `;

        document.head.appendChild(style);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.voiceCommands = new VoiceCommands();
    
    // Глобальная функция
    window.startVoiceCommand = () => {
        if (window.voiceCommands) {
            window.voiceCommands.start();
        }
    };
});

window.VoiceCommands = VoiceCommands;
