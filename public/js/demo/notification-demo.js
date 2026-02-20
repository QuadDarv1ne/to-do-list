/**
 * Демонстрация системы уведомлений
 */

document.addEventListener('DOMContentLoaded', function() {
    // Примеры использования
    const examples = [
        {
            type: 'success',
            message: 'Задача успешно создана!',
            duration: 5000
        },
        {
            type: 'error',
            message: 'Ошибка при сохранении данных',
            duration: 7000
        },
        {
            type: 'warning',
            message: 'Внимание! Срок выполнения задачи истекает через 2 часа',
            duration: 6000
        },
        {
            type: 'info',
            message: 'У вас 3 новых уведомления',
            duration: 4000
        }
    ];

    // Кнопки для демонстрации
    const demoButtons = document.querySelectorAll('[data-notification-demo]');
    
    demoButtons.forEach(button => {
        button.addEventListener('click', function() {
            const type = this.dataset.notificationDemo;
            const message = this.dataset.message || `Это ${type} уведомление`;
            const duration = parseInt(this.dataset.duration) || 5000;
            
            if (window.notify && window.notify[type]) {
                window.notify[type](message, duration);
            }
        });
    });

    // Автоматическая демонстрация при загрузке (опционально)
    const autoDemo = document.querySelector('[data-auto-demo]');
    if (autoDemo) {
        setTimeout(() => {
            window.notify?.info('Добро пожаловать! Система уведомлений активна', 3000);
        }, 1000);
    }

    // Демо последовательности уведомлений
    const sequenceBtn = document.querySelector('[data-notification-sequence]');
    if (sequenceBtn) {
        sequenceBtn.addEventListener('click', function() {
            examples.forEach((example, index) => {
                setTimeout(() => {
                    if (window.notify && window.notify[example.type]) {
                        window.notify[example.type](example.message, example.duration);
                    }
                }, index * 800);
            });
        });
    }

    // Тест множественных уведомлений
    const multipleBtn = document.querySelector('[data-notification-multiple]');
    if (multipleBtn) {
        multipleBtn.addEventListener('click', function() {
            for (let i = 1; i <= 6; i++) {
                setTimeout(() => {
                    const types = ['success', 'error', 'warning', 'info'];
                    const type = types[Math.floor(Math.random() * types.length)];
                    window.notify?.[type](`Уведомление #${i}`, 5000);
                }, i * 200);
            }
        });
    }

    // Очистка всех уведомлений
    const clearBtn = document.querySelector('[data-notification-clear]');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (window.notificationSystem) {
                window.notificationSystem.clear();
                window.notify?.info('Все уведомления очищены', 2000);
            }
        });
    }

    // Переключение звука
    const soundToggle = document.querySelector('[data-notification-sound-toggle]');
    if (soundToggle) {
        const soundEnabled = localStorage.getItem('notificationSound') !== 'false';
        soundToggle.checked = soundEnabled;
        
        soundToggle.addEventListener('change', function() {
            localStorage.setItem('notificationSound', this.checked ? 'true' : 'false');
            window.notify?.info(
                this.checked ? 'Звук уведомлений включен' : 'Звук уведомлений выключен',
                2000
            );
        });
    }
});

// Глобальные функции для быстрого тестирования из консоли
window.testNotifications = {
    success: (msg = 'Успешно!') => window.notify?.success(msg),
    error: (msg = 'Ошибка!') => window.notify?.error(msg),
    warning: (msg = 'Внимание!') => window.notify?.warning(msg),
    info: (msg = 'Информация') => window.notify?.info(msg),
    
    // Уведомление с заголовком
    withTitle: () => window.notify?.success('Данные сохранены', 5000, {
        title: 'Успешно'
    }),
    
    // Уведомление с действиями
    withActions: () => window.notify?.warning('Вы уверены, что хотите удалить этот элемент?', 0, {
        title: 'Подтверждение',
        actions: [
            {
                label: 'Удалить',
                primary: true,
                onClick: () => console.log('Удалено!')
            },
            {
                label: 'Отмена',
                onClick: () => console.log('Отменено')
            }
        ]
    }),
    
    // Confirm диалог
    confirm: () => window.notify?.confirm(
        'Вы действительно хотите выполнить это действие?',
        () => console.log('Подтверждено!'),
        () => console.log('Отменено')
    ),
    
    // Loading индикатор
    loading: () => {
        const notification = window.notify?.loading('Загрузка данных...');
        setTimeout(() => {
            window.notificationSystem?.remove(notification);
            window.notify?.success('Данные загружены!');
        }, 3000);
    },
    
    // Promise обработка
    promise: () => {
        const fakePromise = new Promise((resolve, reject) => {
            setTimeout(() => {
                Math.random() > 0.5 ? resolve('OK') : reject('Error');
            }, 2000);
        });
        
        window.notify?.promise(fakePromise, {
            loading: 'Отправка данных...',
            success: 'Данные успешно отправлены!',
            error: 'Ошибка при отправке данных'
        });
    },
    
    // Все типы
    all: () => {
        window.notify?.success('Успешная операция');
        setTimeout(() => window.notify?.error('Произошла ошибка'), 500);
        setTimeout(() => window.notify?.warning('Предупреждение'), 1000);
        setTimeout(() => window.notify?.info('Информационное сообщение'), 1500);
    },
    
    clear: () => window.notificationSystem?.clear()
};

console.log('💡 Notification Demo загружен! Используйте window.testNotifications для тестирования');
console.log('Примеры:');
console.log('  testNotifications.success()');
console.log('  testNotifications.withTitle()');
console.log('  testNotifications.withActions()');
console.log('  testNotifications.confirm()');
console.log('  testNotifications.loading()');
console.log('  testNotifications.promise()');
console.log('  testNotifications.all()');
