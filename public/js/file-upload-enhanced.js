/**
 * Enhanced File Upload
 * Улучшенная загрузка файлов с drag & drop, превью и прогрессом
 */

class FileUploadEnhanced {
    constructor() {
        this.uploads = new Map();
        this.maxFileSize = 10 * 1024 * 1024; // 10MB
        this.allowedTypes = ['image/*', 'application/pdf', '.doc', '.docx', '.xls', '.xlsx'];
        this.init();
    }

    init() {
        this.enhanceFileInputs();
        this.setupGlobalDragDrop();
    }

    /**
     * Улучшить file inputs
     */
    enhanceFileInputs() {
        const inputs = document.querySelectorAll('input[type="file"][data-enhanced]');
        
        inputs.forEach(input => {
            this.enhanceInput(input);
        });
    }

    /**
     * Улучшить конкретный input
     */
    enhanceInput(input) {
        const wrapper = this.createWrapper(input);
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        this.setupInputEvents(input, wrapper);
    }

    /**
     * Создать обертку
     */
    createWrapper(input) {
        const wrapper = document.createElement('div');
        wrapper.className = 'file-upload-enhanced';
        wrapper.innerHTML = `
            <div class="file-upload-dropzone">
                <div class="file-upload-icon">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <div class="file-upload-text">
                    <strong>Перетащите файлы сюда</strong>
                    <span>или нажмите для выбора</span>
                </div>
                <div class="file-upload-hint">
                    Максимальный размер: ${this.formatFileSize(this.maxFileSize)}
                </div>
            </div>
            <div class="file-upload-preview"></div>
            <div class="file-upload-progress" style="display: none;">
                <div class="progress">
                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                </div>
                <div class="file-upload-progress-text">Загрузка...</div>
            </div>
        `;

        this.addFileUploadStyles();
        return wrapper;
    }

    /**
     * Добавить стили
     */
    addFileUploadStyles() {
        if (document.getElementById('fileUploadEnhancedStyles')) return;

        const style = document.createElement('style');
        style.id = 'fileUploadEnhancedStyles';
        style.textContent = `
            .file-upload-enhanced {
                position: relative;
            }

            .file-upload-enhanced input[type="file"] {
                position: absolute;
                width: 100%;
                height: 100%;
                top: 0;
                left: 0;
                opacity: 0;
                cursor: pointer;
                z-index: 2;
            }

            .file-upload-dropzone {
                border: 2px dashed var(--border);
                border-radius: var(--radius-lg);
                padding: 2rem;
                text-align: center;
                background: var(--bg-body);
                transition: all 0.3s ease;
                position: relative;
                z-index: 1;
            }

            .file-upload-dropzone:hover {
                border-color: var(--primary);
                background: rgba(102, 126, 234, 0.05);
            }

            .file-upload-dropzone.drag-over {
                border-color: var(--primary);
                background: rgba(102, 126, 234, 0.1);
                transform: scale(1.02);
            }

            .file-upload-icon {
                font-size: 3rem;
                color: var(--primary);
                margin-bottom: 1rem;
            }

            .file-upload-text {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                margin-bottom: 0.5rem;
            }

            .file-upload-text strong {
                color: var(--text-primary);
                font-size: 1.125rem;
            }

            .file-upload-text span {
                color: var(--text-secondary);
                font-size: 0.875rem;
            }

            .file-upload-hint {
                color: var(--text-muted);
                font-size: 0.75rem;
            }

            .file-upload-preview {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 1rem;
                margin-top: 1rem;
            }

            .file-preview-item {
                position: relative;
                border: 1px solid var(--border);
                border-radius: var(--radius);
                padding: 0.5rem;
                background: var(--bg-card);
                transition: all 0.2s ease;
            }

            .file-preview-item:hover {
                box-shadow: var(--shadow-hover);
                transform: translateY(-2px);
            }

            .file-preview-image {
                width: 100%;
                height: 100px;
                object-fit: cover;
                border-radius: var(--radius);
                margin-bottom: 0.5rem;
            }

            .file-preview-icon {
                width: 100%;
                height: 100px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 2rem;
                color: var(--primary);
                background: var(--bg-body);
                border-radius: var(--radius);
                margin-bottom: 0.5rem;
            }

            .file-preview-name {
                font-size: 0.75rem;
                color: var(--text-primary);
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                margin-bottom: 0.25rem;
            }

            .file-preview-size {
                font-size: 0.625rem;
                color: var(--text-muted);
            }

            .file-preview-remove {
                position: absolute;
                top: 0.25rem;
                right: 0.25rem;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                background: var(--danger);
                color: white;
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                transition: opacity 0.2s ease;
            }

            .file-preview-item:hover .file-preview-remove {
                opacity: 1;
            }

            .file-preview-remove:hover {
                background: #c82333;
            }

            .file-upload-progress {
                margin-top: 1rem;
            }

            .file-upload-progress-text {
                text-align: center;
                margin-top: 0.5rem;
                font-size: 0.875rem;
                color: var(--text-secondary);
            }

            .progress {
                height: 8px;
                background: var(--bg-body);
                border-radius: 4px;
                overflow: hidden;
            }

            .progress-bar {
                height: 100%;
                background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
                transition: width 0.3s ease;
            }
        `;

        document.head.appendChild(style);
    }

    /**
     * Настроить события input
     */
    setupInputEvents(input, wrapper) {
        const dropzone = wrapper.querySelector('.file-upload-dropzone');
        const preview = wrapper.querySelector('.file-upload-preview');

        // Выбор файлов
        input.addEventListener('change', (e) => {
            this.handleFiles(e.target.files, input, wrapper);
        });

        // Drag & Drop
        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('drag-over');
        });

        dropzone.addEventListener('dragleave', () => {
            dropzone.classList.remove('drag-over');
        });

        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('drag-over');
            this.handleFiles(e.dataTransfer.files, input, wrapper);
        });
    }

    /**
     * Обработать файлы
     */
    handleFiles(files, input, wrapper) {
        const preview = wrapper.querySelector('.file-upload-preview');
        const validFiles = [];

        Array.from(files).forEach(file => {
            // Проверка размера
            if (file.size > this.maxFileSize) {
                this.showToast(`Файл ${file.name} слишком большой (максимум ${this.formatFileSize(this.maxFileSize)})`, 'error');
                return;
            }

            // Проверка типа
            if (!this.isAllowedType(file)) {
                this.showToast(`Тип файла ${file.name} не поддерживается`, 'error');
                return;
            }

            validFiles.push(file);
            this.addFilePreview(file, preview, input);
        });

        // Автоматическая загрузка если указан URL
        const uploadUrl = input.dataset.uploadUrl;
        if (uploadUrl && validFiles.length > 0) {
            this.uploadFiles(validFiles, uploadUrl, wrapper);
        }
    }

    /**
     * Проверить тип файла
     */
    isAllowedType(file) {
        return this.allowedTypes.some(type => {
            if (type.endsWith('/*')) {
                const category = type.split('/')[0];
                return file.type.startsWith(category + '/');
            }
            return file.type === type || file.name.endsWith(type);
        });
    }

    /**
     * Добавить превью файла
     */
    addFilePreview(file, container, input) {
        const item = document.createElement('div');
        item.className = 'file-preview-item';
        item.dataset.fileName = file.name;

        // Превью для изображений
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                item.innerHTML = `
                    <img src="${e.target.result}" class="file-preview-image" alt="${file.name}">
                    <div class="file-preview-name" title="${file.name}">${file.name}</div>
                    <div class="file-preview-size">${this.formatFileSize(file.size)}</div>
                    <button type="button" class="file-preview-remove">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                this.setupRemoveButton(item, input);
            };
            reader.readAsDataURL(file);
        } else {
            // Иконка для других файлов
            const icon = this.getFileIcon(file);
            item.innerHTML = `
                <div class="file-preview-icon">
                    <i class="fas fa-${icon}"></i>
                </div>
                <div class="file-preview-name" title="${file.name}">${file.name}</div>
                <div class="file-preview-size">${this.formatFileSize(file.size)}</div>
                <button type="button" class="file-preview-remove">
                    <i class="fas fa-times"></i>
                </button>
            `;
            this.setupRemoveButton(item, input);
        }

        container.appendChild(item);
    }

    /**
     * Настроить кнопку удаления
     */
    setupRemoveButton(item, input) {
        const removeBtn = item.querySelector('.file-preview-remove');
        removeBtn.addEventListener('click', () => {
            item.remove();
            
            // Очистить input если нет превью
            const preview = input.closest('.file-upload-enhanced').querySelector('.file-upload-preview');
            if (preview.children.length === 0) {
                input.value = '';
            }
        });
    }

    /**
     * Получить иконку файла
     */
    getFileIcon(file) {
        const ext = file.name.split('.').pop().toLowerCase();
        const icons = {
            'pdf': 'file-pdf',
            'doc': 'file-word',
            'docx': 'file-word',
            'xls': 'file-excel',
            'xlsx': 'file-excel',
            'ppt': 'file-powerpoint',
            'pptx': 'file-powerpoint',
            'zip': 'file-archive',
            'rar': 'file-archive',
            'txt': 'file-alt',
            'csv': 'file-csv'
        };
        return icons[ext] || 'file';
    }

    /**
     * Загрузить файлы
     */
    async uploadFiles(files, url, wrapper) {
        const progressContainer = wrapper.querySelector('.file-upload-progress');
        const progressBar = progressContainer.querySelector('.progress-bar');
        const progressText = progressContainer.querySelector('.file-upload-progress-text');

        progressContainer.style.display = 'block';

        const formData = new FormData();
        files.forEach((file, index) => {
            formData.append(`files[${index}]`, file);
        });

        try {
            const xhr = new XMLHttpRequest();

            // Прогресс загрузки
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percent = (e.loaded / e.total) * 100;
                    progressBar.style.width = percent + '%';
                    progressText.textContent = `Загрузка... ${Math.round(percent)}%`;
                }
            });

            // Завершение загрузки
            xhr.addEventListener('load', () => {
                if (xhr.status === 200) {
                    progressText.textContent = 'Загрузка завершена!';
                    this.showToast('Файлы успешно загружены', 'success');
                    
                    setTimeout(() => {
                        progressContainer.style.display = 'none';
                        progressBar.style.width = '0%';
                    }, 2000);

                    // Вызываем callback если есть
                    const callback = wrapper.querySelector('input[type="file"]').dataset.onUpload;
                    if (callback && typeof window[callback] === 'function') {
                        window[callback](JSON.parse(xhr.responseText));
                    }
                } else {
                    throw new Error('Upload failed');
                }
            });

            // Ошибка загрузки
            xhr.addEventListener('error', () => {
                progressText.textContent = 'Ошибка загрузки';
                this.showToast('Ошибка загрузки файлов', 'error');
            });

            xhr.open('POST', url);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(formData);

        } catch (error) {
            console.error('Upload error:', error);
            progressText.textContent = 'Ошибка загрузки';
            this.showToast('Ошибка загрузки файлов', 'error');
        }
    }

    /**
     * Настроить глобальный drag & drop
     */
    setupGlobalDragDrop() {
        let dragCounter = 0;

        document.addEventListener('dragenter', (e) => {
            dragCounter++;
            if (dragCounter === 1) {
                this.showDropOverlay();
            }
        });

        document.addEventListener('dragleave', (e) => {
            dragCounter--;
            if (dragCounter === 0) {
                this.hideDropOverlay();
            }
        });

        document.addEventListener('drop', (e) => {
            dragCounter = 0;
            this.hideDropOverlay();
        });

        document.addEventListener('dragover', (e) => {
            e.preventDefault();
        });
    }

    /**
     * Показать оверлей для drop
     */
    showDropOverlay() {
        let overlay = document.getElementById('global-drop-overlay');
        
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'global-drop-overlay';
            overlay.className = 'global-drop-overlay';
            overlay.innerHTML = `
                <div class="global-drop-content">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <div>Отпустите файлы для загрузки</div>
                </div>
            `;
            document.body.appendChild(overlay);

            if (!document.getElementById('globalDropOverlayStyles')) {
                const style = document.createElement('style');
                style.id = 'globalDropOverlayStyles';
                style.textContent = `
                    .global-drop-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(102, 126, 234, 0.9);
                    z-index: 99999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    pointer-events: none;
                }

                .global-drop-content {
                    color: white;
                    text-align: center;
                    font-size: 2rem;
                }

                .global-drop-content i {
                    font-size: 4rem;
                    margin-bottom: 1rem;
                    display: block;
                }
                `;
                document.head.appendChild(style);
            }
        }

        overlay.style.display = 'flex';
    }

    /**
     * Скрыть оверлей
     */
    hideDropOverlay() {
        const overlay = document.getElementById('global-drop-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    /**
     * Форматировать размер файла
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    /**
     * Показать уведомление
     */
    showToast(message, type = 'info') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        }
    }
}

// Инициализация
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.fileUploadEnhanced = new FileUploadEnhanced();
    });
} else {
    window.fileUploadEnhanced = new FileUploadEnhanced();
}

// Экспорт
window.FileUploadEnhanced = FileUploadEnhanced;
