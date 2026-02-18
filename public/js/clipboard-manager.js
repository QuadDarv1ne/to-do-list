/**
 * Clipboard Manager
 * Управление буфером обмена
 */

class ClipboardManager {
    constructor() {
        this.init();
    }

    init() {
        this.setupCopyButtons();
        this.setupCodeBlocks();
    }

    setupCopyButtons() {
        // Добавляем кнопки копирования к элементам с data-copy
        document.querySelectorAll('[data-copy]').forEach(element => {
            this.addCopyButton(element);
        });
    }

    setupCodeBlocks() {
        // Добавляем кнопки копирования к блокам кода
        document.querySelectorAll('pre code, .code-block').forEach(block => {
            this.addCopyButton(block);
        });
    }

    addCopyButton(element) {
        if (element.querySelector('.copy-button')) return;

        const button = document.createElement('button');
        button.className = 'copy-button';
        button.innerHTML = '<i class="fas fa-copy"></i>';
        button.title = 'Копировать';
        button.setAttribute('aria-label', 'Копировать в буфер обмена');

        button.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            const text = element.dataset.copy || element.textContent;
            await this.copy(text);
            
            // Визуальная обратная связь
            button.innerHTML = '<i class="fas fa-check"></i>';
            button.classList.add('copied');
            
            setTimeout(() => {
                button.innerHTML = '<i class="fas fa-copy"></i>';
                button.classList.remove('copied');
            }, 2000);
        });

        // Позиционируем кнопку
        const wrapper = document.createElement('div');
        wrapper.className = 'copy-wrapper';
        element.parentNode.insertBefore(wrapper, element);
        wrapper.appendChild(element);
        wrapper.appendChild(button);
    }

    async copy(text) {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
                this.showSuccess('Скопировано в буфер обмена');
            } else {
                // Fallback для старых браузеров
                this.copyFallback(text);
            }
        } catch (error) {
            console.error('Copy error:', error);
            this.showError('Ошибка копирования');
        }
    }

    copyFallback(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        
        try {
            document.execCommand('copy');
            this.showSuccess('Скопировано в буфер обмена');
        } catch (error) {
            console.error('Copy fallback error:', error);
            this.showError('Ошибка копирования');
        }
        
        document.body.removeChild(textarea);
    }

    async paste() {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                const text = await navigator.clipboard.readText();
                return text;
            }
        } catch (error) {
            console.error('Paste error:', error);
            return null;
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
        if (document.getElementById('clipboardManagerStyles')) return;

        const style = document.createElement('style');
        style.id = 'clipboardManagerStyles';
        style.textContent = `
            .copy-wrapper {
                position: relative;
                display: inline-block;
            }

            .copy-button {
                position: absolute;
                top: 8px;
                right: 8px;
                padding: 6px 10px;
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: 6px;
                color: var(--text-secondary);
                cursor: pointer;
                font-size: 0.875rem;
                transition: all 0.2s ease;
                z-index: 10;
            }

            .copy-button:hover {
                background: var(--bg-hover);
                color: var(--text-primary);
                border-color: var(--primary);
            }

            .copy-button.copied {
                background: var(--success);
                color: white;
                border-color: var(--success);
            }

            pre {
                position: relative;
                padding-right: 50px;
            }
        `;

        document.head.appendChild(style);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.clipboardManager = new ClipboardManager();
    window.clipboardManager.addStyles();
    
    // Глобальные функции
    window.copyToClipboard = async (text) => {
        await window.clipboardManager.copy(text);
    };
    
    window.pasteFromClipboard = async () => {
        return await window.clipboardManager.paste();
    };
});

window.ClipboardManager = ClipboardManager;
