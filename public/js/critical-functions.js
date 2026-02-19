/**
 * Critical JS Functions
 * Загружаются сразу для работы базового функционала
 * 
 * Эти функции должны быть доступны ДО загрузки основных скриптов
 */

// ============================================================================
// TOAST NOTIFICATIONS
// ============================================================================
window.showToast = function(message, type = 'info') {
    // Создаём контейнер если нет
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;max-width:400px;';
        document.body.appendChild(container);
    }

    // Иконки для типов
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle',
        danger: 'fa-times-circle'
    };

    const colors = {
        success: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
        error: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
        warning: 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
        info: 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)',
        danger: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)'
    };

    // Создаём toast
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.style.cssText = `
        display:flex;align-items:center;gap:12px;padding:14px 18px;
        margin-bottom:10px;border-radius:12px;color:#fff;
        background:${colors[type] || colors.info};
        box-shadow:0 8px 24px rgba(0,0,0,0.2);
        transform:translateX(400px);opacity:0;
        transition:all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    `;
    toast.setAttribute('role', 'alert');

    // Иконка
    const icon = document.createElement('i');
    icon.className = `fas ${icons[type] || icons.info}`;
    icon.style.fontSize = '18px';
    toast.appendChild(icon);

    // Текст
    const text = document.createElement('span');
    text.textContent = message;
    text.style.flex = '1';
    text.style.fontWeight = '500';
    toast.appendChild(text);

    // Кнопка закрытия
    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '×';
    closeBtn.style.cssText = 'background:none;border:none;color:#fff;font-size:24px;cursor:pointer;padding:0;line-height:1;opacity:0.7;transition:opacity 0.2s;';
    closeBtn.onmouseover = () => closeBtn.style.opacity = '1';
    closeBtn.onmouseout = () => closeBtn.style.opacity = '0.7';
    closeBtn.onclick = () => closeToast();
    toast.appendChild(closeBtn);

    container.appendChild(toast);

    // Анимация появления
    requestAnimationFrame(() => {
        toast.style.transform = 'translateX(0)';
        toast.style.opacity = '1';
    });

    // Авто-удаление
    const autoRemove = setTimeout(() => closeToast(), 5000);

    // Функция закрытия
    function closeToast() {
        clearTimeout(autoRemove);
        toast.style.transform = 'translateX(400px)';
        toast.style.opacity = '0';
        setTimeout(() => {
            if (toast.parentElement) toast.remove();
            if (container.children.length === 0 && container.parentElement) container.remove();
        }, 400);
    }
};

// ============================================================================
// CONSOLE WRAPPER (для production)
// ============================================================================
(function() {
    const isProd = document.querySelector('meta[name="environment"]')?.getAttribute('content') === 'prod';
    
    if (isProd) {
        // Отключаем логи в production
        window.console = {
            log: function() {},
            warn: function() {},
            error: function() {},
            info: function() {},
            debug: function() {},
            trace: function() {}
        };
    }
})();

// ============================================================================
// PAGE LOADER
// ============================================================================
window.addEventListener('load', function() {
    // Плавное скрытие лоадера
    setTimeout(() => {
        const loader = document.getElementById('pageLoader');
        if (loader) {
            loader.classList.add('hidden');
            setTimeout(() => {
                loader.style.display = 'none';
                document.body.classList.add('loaded');
            }, 500);
        } else {
            document.body.classList.add('loaded');
        }
    }, 300);
});

// ============================================================================
// FAB MENU TOGGLE
// ============================================================================
window.toggleFabMenu = function() {
    const menu = document.getElementById('fabMenu');
    const backdrop = document.getElementById('fabBackdrop');
    
    if (menu && backdrop) {
        const isShown = menu.classList.contains('show');
        
        if (isShown) {
            menu.classList.remove('show');
            backdrop.classList.remove('show');
        } else {
            menu.classList.add('show');
            backdrop.classList.add('show');
        }
    }
};

// Закрытие FAB меню по Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const menu = document.getElementById('fabMenu');
        const backdrop = document.getElementById('fabBackdrop');
        if (menu && backdrop && menu.classList.contains('show')) {
            menu.classList.remove('show');
            backdrop.classList.remove('show');
        }
    }
});

// ============================================================================
// SERVICE WORKER
// ============================================================================
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js')
            .then(reg => console.log('SW registered:', reg))
            .catch(err => console.log('SW registration failed:', err));
    });
}
