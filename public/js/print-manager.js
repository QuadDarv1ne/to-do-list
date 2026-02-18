/**
 * Print Manager
 * Улучшенная печать страниц
 */

class PrintManager {
    constructor() {
        this.printStyles = '';
        this.init();
    }

    init() {
        this.setupPrintButtons();
        this.addPrintStyles();
    }

    setupPrintButtons() {
        // Добавляем кнопки печати
        const printButtons = document.querySelectorAll('[data-print]');
        
        printButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const target = button.dataset.print;
                this.print(target);
            });
        });
    }

    print(selector = null) {
        if (selector) {
            this.printElement(selector);
        } else {
            window.print();
        }
    }

    printElement(selector) {
        const element = document.querySelector(selector);
        if (!element) {
            console.error('Element not found:', selector);
            return;
        }

        // Создаем окно для печати
        const printWindow = window.open('', '_blank');
        
        const html = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Печать</title>
                <style>
                    ${this.getPrintStyles()}
                </style>
            </head>
            <body>
                <div class="print-header">
                    <h1>${document.title}</h1>
                    <p>Дата печати: ${new Date().toLocaleString('ru-RU')}</p>
                </div>
                <div class="print-content">
                    ${element.innerHTML}
                </div>
                <div class="print-footer">
                    <p>© ${new Date().getFullYear()} CRM Task Management</p>
                </div>
                <script>
                    window.onload = function() {
                        window.print();
                        window.onafterprint = function() {
                            window.close();
                        };
                    };
                </script>
            </body>
            </html>
        `;

        printWindow.document.write(html);
        printWindow.document.close();
    }

    getPrintStyles() {
        return `
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: Arial, sans-serif;
                font-size: 12pt;
                line-height: 1.6;
                color: #000;
                background: #fff;
                padding: 20mm;
            }

            .print-header {
                margin-bottom: 20mm;
                border-bottom: 2px solid #000;
                padding-bottom: 10mm;
            }

            .print-header h1 {
                font-size: 18pt;
                margin-bottom: 5mm;
            }

            .print-header p {
                font-size: 10pt;
                color: #666;
            }

            .print-content {
                margin-bottom: 20mm;
            }

            .print-footer {
                margin-top: 20mm;
                border-top: 1px solid #ccc;
                padding-top: 10mm;
                text-align: center;
                font-size: 10pt;
                color: #666;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin: 10mm 0;
            }

            th, td {
                border: 1px solid #ddd;
                padding: 5mm;
                text-align: left;
            }

            th {
                background-color: #f2f2f2;
                font-weight: bold;
            }

            img {
                max-width: 100%;
                height: auto;
            }

            .no-print {
                display: none !important;
            }

            @page {
                size: A4;
                margin: 15mm;
            }

            @media print {
                body {
                    padding: 0;
                }

                .print-header,
                .print-content,
                .print-footer {
                    page-break-inside: avoid;
                }

                a {
                    text-decoration: none;
                    color: #000;
                }

                a[href]:after {
                    content: " (" attr(href) ")";
                    font-size: 9pt;
                    color: #666;
                }
            }
        `;
    }

    addPrintStyles() {
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                /* Скрываем элементы при печати */
                .navbar,
                .sidebar,
                .footer,
                .btn,
                .modal,
                .toast,
                .no-print {
                    display: none !important;
                }

                /* Оптимизация для печати */
                body {
                    background: white;
                    color: black;
                }

                .container {
                    width: 100%;
                    max-width: none;
                }

                /* Разрывы страниц */
                .page-break {
                    page-break-after: always;
                }

                .avoid-break {
                    page-break-inside: avoid;
                }

                /* Таблицы */
                table {
                    border-collapse: collapse;
                }

                th, td {
                    border: 1px solid #ddd;
                    padding: 8px;
                }

                /* Ссылки */
                a {
                    text-decoration: none;
                    color: black;
                }
            }

            /* Кнопка печати */
            .print-button {
                position: fixed;
                bottom: 80px;
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
                font-size: 1.25rem;
                z-index: 999;
                transition: all 0.3s ease;
            }

            .print-button:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
            }

            @media print {
                .print-button {
                    display: none !important;
                }
            }
        `;

        document.head.appendChild(style);
    }

    // Добавить кнопку печати на страницу
    addPrintButton() {
        if (document.querySelector('.print-button')) return;

        const button = document.createElement('button');
        button.className = 'print-button no-print';
        button.innerHTML = '<i class="fas fa-print"></i>';
        button.title = 'Печать (Ctrl+P)';
        button.setAttribute('aria-label', 'Печать страницы');

        button.addEventListener('click', () => {
            window.print();
        });

        document.body.appendChild(button);
    }
}

// Инициализация
document.addEventListener('DOMContentLoaded', function() {
    window.printManager = new PrintManager();
    
    // Добавляем глобальную функцию печати
    window.printPage = (selector) => {
        window.printManager.print(selector);
    };
});

// Экспорт
window.PrintManager = PrintManager;
