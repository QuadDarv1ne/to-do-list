/**
 * Scroll to Top
 * Кнопка прокрутки наверх
 */

class ScrollToTop {
    constructor() {
        this.button = null;
        this.threshold = 300;
        this.init();
    }

    init() {
        this.createButton();
        this.bindEvents();
    }

    createButton() {
        this.button = document.createElement('button');
        this.button.className = 'scroll-to-top-button';
        this.button.innerHTML = '<i class="fas fa-arrow-up"></i>';
        this.button.title = 'Наверх';
        this.button.setAttribute('aria-label', 'Прокрутить наверх');
        this.button.style.display = 'none';

        this.button.addEventListener('click', () => {
            this.scrollToTop();
        });

        document.body.appendChild(this.button);
        this.addStyles();
    }

    bindEvents() {
        window.addEventListener('scroll', () => {
            this.toggleVisibility();
        });
    }

    toggleVisibility() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > this.threshold) {
            this.button.style.display = 'flex';
        } else {
            this.button.style.display = 'none';
        }
    }

    scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    addStyles() {
        if (document.getElementById('scrollToTopStyles')) return;

        const style = document.createElement('style');
        style.id = 'scrollToTopStyles';
        style.textContent = `
            .scroll-to-top-button {
                position: fixed;
                bottom: 320px;
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
                z-index: 994;
                transition: all 0.2s ease;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .scroll-to-top-button:hover {
                background: var(--primary);
                color: white;
                transform: scale(1.05) translateY(-2px);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            }

            @media (max-width: 768px) {
                .scroll-to-top-button {
                    bottom: 300px;
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
    window.scrollToTop = new ScrollToTop();
});

window.ScrollToTop = ScrollToTop;
