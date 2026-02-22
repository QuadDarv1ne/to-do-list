import { Controller } from '@hotwired/stimulus';

export class ThemeController extends Controller {
    static targets = ['toggle', 'icon'];

    connect() {
        this.initTheme();
        this.setupWatcher();
    }

    initTheme() {
        const savedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        const theme = savedTheme || (systemPrefersDark ? 'dark' : 'light');
        this.setTheme(theme);
    }

    setTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        this.updateIcon(theme);
    }

    updateIcon(theme) {
        this.iconTargets.forEach(icon => {
            if (theme === 'dark') {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            } else {
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }
        });
    }

    toggle() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        this.setTheme(newTheme);
    }

    setupWatcher() {
        // Следить за системными изменениями
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!localStorage.getItem('theme')) {
                this.setTheme(e.matches ? 'dark' : 'light');
            }
        });
    }
}
