/**
 * Time Tracking
 * Отслеживание времени работы над задачами
 */

class TimeTracking {
    constructor() {
        this.activeTask = null;
        this.startTime = null;
        this.totalTime = 0;
        this.timer = null;
        this.widget = null;
        this.sessions = [];
        this.init();
    }

    init() {
        this.loadState();
        this.createWidget();
        this.addTaskButtons();
        
        if (this.activeTask) {
            this.resume();
        }
    }

    createWidget() {
        this.widget = document.createElement('div');
        this.widget.className = 'time-tracking-widget';
        this.widget.style.display = 'none';
        this.widget.innerHTML = `
            <div class="time-tracking-content">
                <div class="time-tracking-task">
                    <i class="fas fa-tasks"></i>
                    <span class="task-name">Нет активной задачи</span>
                </div>
                <div class="time-tracking-timer">
                    <i class="fas fa-clock"></i>
                    <span class="timer-display">00:00:00</span>
                </div>
                <div class="time-tracking-actions">
                    <button class="btn-pause" title="Пауза">
                        <i class="fas fa-pause"></i>
                    </button>
                    <button class="btn-stop" title="Остановить">
                        <i class="fas fa-stop"></i>
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(this.widget);

        // Обработчики
        this.widget.querySelector('.btn-pause').addEventListener('click', () => {
            this.pause();
        });

        this.widget.querySelector('.btn-stop').addEventListener('click', () => {
            this.stop();
        });

        this.addStyles();
    }

    addTaskButtons() {
        // Добавляем кнопки к задачам
        const taskItems = document.querySelectorAll('.task-item, [data-task-id]');
        
        taskItems.forEach(item => {
            if (!item.querySelector('.time-tracking-start')) {
                const button = document.createElement('button');
                button.className = 'time-tracking-start btn btn-sm btn-outline-primary';
                button.innerHTML = '<i class="fas fa-play"></i>';
                button.title = 'Начать отслеживание времени';
                
                const taskId = item.dataset.id || item.dataset.taskId;
                const taskName = item.querySelector('.task-title, h3, h4')?.textContent || 'Задача';
                
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.start(taskId, taskName);
                });

                item.appendChild(button);
            }
        });
    }

    start(taskId, taskName) {
        if (this.activeTask) {
            if (!confirm('Остановить текущую задачу и начать новую?')) {
                return;
            }
            this.stop();
        }

        this.activeTask = { id: taskId, name: taskName };
        this.startTime = Date.now();
        this.totalTime = 0;
        
        this.widget.style.display = 'block';
        this.widget.querySelector('.task-name').textContent = taskName;
        
        this.startTimer();
        this.saveState();

        if (window.showToast) {
            window.showToast(`Отслеживание времени начато: ${taskName}`, 'success');
        }
    }

    pause() {
        if (!this.activeTask || !this.startTime) return;

        this.totalTime += Date.now() - this.startTime;
        this.startTime = null;
        
        this.stopTimer();
        this.saveState();

        this.widget.querySelector('.btn-pause').innerHTML = '<i class="fas fa-play"></i>';
        this.widget.querySelector('.btn-pause').title = 'Продолжить';

        if (window.showToast) {
            window.showToast('Отслеживание времени приостановлено', 'info');
        }
    }

    resume() {
        if (!this.activeTask || this.startTime) return;

        this.startTime = Date.now();
        this.startTimer();
        this.saveState();

        this.widget.querySelector('.btn-pause').innerHTML = '<i class="fas fa-pause"></i>';
        this.widget.querySelector('.btn-pause').title = 'Пауза';
    }

    stop() {
        if (!this.activeTask) return;

        if (this.startTime) {
            this.totalTime += Date.now() - this.startTime;
        }

        // Сохраняем сессию
        this.sessions.push({
            taskId: this.activeTask.id,
            taskName: this.activeTask.name,
            duration: this.totalTime,
            date: new Date().toISOString()
        });

        this.saveSessions();

        const duration = this.formatTime(this.totalTime);
        
        if (window.showToast) {
            window.showToast(`Время работы: ${duration}`, 'success');
        }

        // Отправляем на сервер
        this.sendToServer();

        // Сбрасываем состояние
        this.activeTask = null;
        this.startTime = null;
        this.totalTime = 0;
        
        this.stopTimer();
        this.widget.style.display = 'none';
        this.saveState();
    }

    startTimer() {
        this.stopTimer();
        
        this.timer = setInterval(() => {
            this.updateDisplay();
        }, 1000);
    }

    stopTimer() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    }

    updateDisplay() {
        if (!this.activeTask) return;

        let elapsed = this.totalTime;
        if (this.startTime) {
            elapsed += Date.now() - this.startTime;
        }

        const display = this.widget.querySelector('.timer-display');
        if (display) {
            display.textContent = this.formatTime(elapsed);
        }
    }

    formatTime(ms) {
        const seconds = Math.floor(ms / 1000);
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    async sendToServer() {
        if (!this.activeTask) return;

        try {
            await fetch('/api/time-tracking', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    taskId: this.activeTask.id,
                    duration: this.totalTime,
                    date: new Date().toISOString()
                })
            });
        } catch (error) {
            console.error('Failed to send time tracking data:', error);
        }
    }

    saveState() {
        try {
            localStorage.setItem('timeTracking', JSON.stringify({
                activeTask: this.activeTask,
                startTime: this.startTime,
                totalTime: this.totalTime
            }));
        } catch (e) {
            console.error('Failed to save time tracking state:', e);
        }
    }

    loadState() {
        try {
            const saved = localStorage.getItem('timeTracking');
            if (saved) {
                const state = JSON.parse(saved);
                this.activeTask = state.activeTask;
                this.startTime = state.startTime;
                this.totalTime = state.totalTime;
            }
        } catch (e) {
            console.error('Failed to load time tracking state:', e);
        }
    }

    saveSessions() {
        try {
            localStorage.setItem('timeTrackingSessions', JSON.stringify(this.sessions));
        } catch (e) {
            console.error('Failed to save sessions:', e);
        }
    }

    loadSessions() {
        try {
            const saved = localStorage.getItem('timeTrackingSessions');
            if (saved) {
                this.sessions = JSON.parse(saved);
            }
        } catch (e) {
            console.error('Failed to load sessions:', e);
            this.sessions = [];
        }
    }

    addStyles() {
        if (document.getElementById('timeTrackingStyles')) return;

        const style = document.createElement('style');
        style.id = 'timeTrackingStyles';
        style.textContent = `
            .time-tracking-widget {
                position: fixed;
                top: 80px;
                right: 30px;
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: 12px;
                padding: 1rem;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
                z-index: 993;
                min-width: 300px;
            }

            .time-tracking-content {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }

            .time-tracking-task {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                color: var(--text-primary);
                font-weight: 600;
            }

            .time-tracking-timer {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 1.5rem;
                font-weight: 700;
                color: var(--primary);
                font-family: monospace;
            }

            .time-tracking-actions {
                display: flex;
                gap: 0.5rem;
            }

            .time-tracking-actions button {
                flex: 1;
                padding: 0.5rem;
                border: 1px solid var(--border);
                background: var(--bg-body);
                color: var(--text-primary);
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.2s ease;
            }

            .time-tracking-actions button:hover {
                background: var(--primary);
                color: white;
                border-color: var(--primary);
            }

            .time-tracking-start {
                margin-left: auto;
            }

            @media (max-width: 768px) {
                .time-tracking-widget {
                    top: auto;
                    bottom: 380px;
                    right: 20px;
                    left: 20px;
                    min-width: auto;
                }
            }

            body.focus-mode-active .time-tracking-widget {
                display: none !important;
            }
        `;

        document.head.appendChild(style);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.timeTracking = new TimeTracking();
});

window.TimeTracking = TimeTracking;
