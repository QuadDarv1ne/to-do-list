/**
 * Reading Progress
 * Индикатор прогресса чтения страницы
 */

class ReadingProgress {
    constructor() {
        this.bar = null;
        this.init();
    }

    init() {
        this.createBar();
        this.bindEvents();
    }

    createBar() {
        this.bar = document.createElement('div');
        this.bar.className = 'reading-progress-bar';
        document.body.appendChild(this.bar);
        this.addStyles();
    }

    bindEvents() {
        window.addEventListener('scroll', () => {
            this.updateProgress();
        });

        window.addEventListener('resize', () => {
            this.updateProgress();
        });

        // Начальное обновление
        this.updateProgress();
    }

    updateProgress() {
        const windowHeight = window.innerHeight;
        const documentHeight = document.documentElement.scrollHeight;
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        const scrollPercent = (scrollTop / (documentHeight - windowHeight)) * 100;
        const progress = Math.min(Math.max(scrollPercent, 0), 100);
        
        this.bar.style.width = `${progress}%`;
    }

    addStyles() {
        if (document.getElementById('readingProgressStyles')) return;

        const style = document.createElement('style');
        style.id = 'readingProgressStyles';
        style.textContent = `
            .reading-progress-bar {
                position: fixed;
                top: 0;
                left: 0;
                height: 3px;
                background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
                z-index: 10006;
                transition: width 0.1s ease;
                box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
            }

            body.focus-mode-active .reading-progress-bar {
                display: none;
            }
        `;

        document.head.appendChild(style);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.readingProgress = new ReadingProgress();
});

window.ReadingProgress = ReadingProgress;
