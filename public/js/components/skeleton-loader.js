/**
 * Skeleton Loading Manager v2.0
 * Automatic skeleton loading for better UX
 */

class SkeletonLoader {
    constructor(options = {}) {
        this.options = {
            animation: 'pulse',
            duration: 1500,
            ...options
        };
        
        this.init();
    }

    init() {
        this.createStyles();
    }

    createStyles() {
        if (document.getElementById('skeleton-styles')) return;

        const styles = document.createElement('style');
        styles.id = 'skeleton-styles';
        styles.textContent = `
            @keyframes skeleton-loading {
                0% { background-position: 200% 0; }
                100% { background-position: -200% 0; }
            }
            
            .skeleton {
                background: linear-gradient(
                    90deg,
                    var(--bg-tertiary) 25%,
                    var(--bg-hover) 50%,
                    var(--bg-tertiary) 75%
                );
                background-size: 200% 100%;
                animation: skeleton-loading ${this.options.duration}ms ease-in-out infinite;
                border-radius: var(--radius, 0.5rem);
                color: transparent !important;
                pointer-events: none;
                user-select: none;
            }
            
            .skeleton-text {
                height: 1rem;
                margin-bottom: var(--spacing-2);
            }
            
            .skeleton-title {
                height: 1.5rem;
                width: 60%;
                margin-bottom: var(--spacing-4);
            }
            
            .skeleton-avatar {
                width: 2.5rem;
                height: 2.5rem;
                border-radius: var(--radius-full, 9999px);
            }
            
            .skeleton-image {
                width: 100%;
                height: 200px;
                border-radius: var(--radius-lg, 0.75rem);
            }
            
            .skeleton-card {
                padding: var(--spacing-4);
            }
            
            .skeleton-button {
                width: 100px;
                height: 38px;
                border-radius: var(--radius-md, 0.5rem);
            }
            
            .skeleton-input {
                width: 100%;
                height: 42px;
                border-radius: var(--radius-lg, 0.75rem);
            }
            
            .skeleton-chart {
                width: 100%;
                height: 300px;
                border-radius: var(--radius-xl, 1rem);
            }
            
            .skeleton-table-row {
                display: flex;
                gap: var(--spacing-4);
                margin-bottom: var(--spacing-3);
            }
            
            .skeleton-table-cell {
                flex: 1;
                height: 1rem;
            }
        `;
        document.head.appendChild(styles);
    }

    show(container, type = 'card', count = 1) {
        if (!container) return;

        container.dataset.loading = 'true';
        container.innerHTML = '';

        for (let i = 0; i < count; i++) {
            const skeleton = this.createSkeleton(type);
            container.appendChild(skeleton);
        }
    }

    createSkeleton(type) {
        const div = document.createElement('div');
        
        switch (type) {
            case 'card':
                div.className = 'card skeleton-card';
                div.innerHTML = `
                    <div class="skeleton skeleton-title"></div>
                    <div class="skeleton skeleton-text"></div>
                    <div class="skeleton skeleton-text" style="width: 80%"></div>
                    <div class="skeleton skeleton-button" style="margin-top: 1rem"></div>
                `;
                break;
            
            case 'stats':
                div.className = 'stat-card skeleton';
                div.innerHTML = `
                    <div class="skeleton skeleton-avatar" style="margin-bottom: 1rem"></div>
                    <div class="skeleton skeleton-title" style="width: 50%"></div>
                    <div class="skeleton skeleton-text" style="width: 70%"></div>
                `;
                break;
            
            case 'table':
                div.className = 'skeleton-table-row';
                div.innerHTML = `
                    <div class="skeleton skeleton-table-cell" style="flex: 0 0 50px"></div>
                    <div class="skeleton skeleton-table-cell" style="flex: 1"></div>
                    <div class="skeleton skeleton-table-cell" style="flex: 1"></div>
                    <div class="skeleton skeleton-table-cell" style="flex: 0 0 100px"></div>
                `;
                break;
            
            case 'list':
                div.className = 'skeleton';
                div.style.cssText = 'display: flex; gap: 1rem; align-items: center; padding: 1rem;';
                div.innerHTML = `
                    <div class="skeleton skeleton-avatar" style="flex-shrink: 0"></div>
                    <div style="flex: 1">
                        <div class="skeleton skeleton-title" style="width: 80%; margin-bottom: 0.5rem"></div>
                        <div class="skeleton skeleton-text" style="width: 60%"></div>
                    </div>
                `;
                break;
            
            case 'chart':
                div.className = 'card';
                div.innerHTML = `
                    <div class="skeleton skeleton-title" style="margin: 1.5rem"></div>
                    <div class="skeleton skeleton-chart"></div>
                `;
                break;
            
            case 'avatar':
                div.className = 'skeleton skeleton-avatar';
                break;
            
            case 'image':
                div.className = 'skeleton skeleton-image';
                break;
            
            case 'text':
                div.className = 'skeleton skeleton-text';
                break;
            
            case 'title':
                div.className = 'skeleton skeleton-title';
                break;
            
            default:
                div.className = 'skeleton';
        }
        
        return div;
    }

    hide(container) {
        if (!container) return;
        
        delete container.dataset.loading;
        container.querySelectorAll('.skeleton').forEach(el => {
            el.classList.remove('skeleton');
        });
    }

    async load(container, loader, type = 'card', count = 1, minTime = 500) {
        this.show(container, type, count);
        
        const startTime = Date.now();
        const result = await loader();
        const elapsed = Date.now() - startTime;
        
        // Ensure minimum display time for smooth UX
        if (elapsed < minTime) {
            await this.sleep(minTime - elapsed);
        }
        
        this.hide(container);
        
        return result;
    }

    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

/**
 * Lazy Image Loader
 * Optimized image loading with skeleton placeholder
 */

class LazyImageLoader {
    constructor(options = {}) {
        this.options = {
            placeholder: 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg"%3E%3Crect width="100%25" height="100%25" fill="%23e2e8f0"/%3E%3C/svg%3E',
            threshold: 50,
            ...options
        };
        
        this.observer = null;
        this.init();
    }

    init() {
        if ('IntersectionObserver' in window) {
            this.observer = new IntersectionObserver(
                (entries) => entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadImage(entry.target);
                    }
                }),
                { rootMargin: `${this.options.threshold}px` }
            );
            
            document.querySelectorAll('img[data-src]').forEach(img => {
                this.observer.observe(img);
            });
        } else {
            // Fallback for older browsers
            document.querySelectorAll('img[data-src]').forEach(img => {
                this.loadImage(img);
            });
        }
    }

    loadImage(img) {
        const src = img.dataset.src;
        if (!src) return;

        // Set placeholder
        img.src = this.options.placeholder;
        img.classList.add('skeleton');

        // Load actual image
        const tempImg = new Image();
        tempImg.src = src;
        tempImg.onload = () => {
            img.src = src;
            img.classList.remove('skeleton');
            img.classList.add('loaded');
            
            if (this.observer) {
                this.observer.unobserve(img);
            }
        };
        
        img.onerror = () => {
            img.classList.remove('skeleton');
            img.classList.add('error');
        };
    }

    destroy() {
        if (this.observer) {
            this.observer.disconnect();
        }
    }
}

/**
 * Initialize on DOM ready
 */

document.addEventListener('DOMContentLoaded', () => {
    // Global skeleton loader instance
    window.skeletonLoader = new SkeletonLoader();
    
    // Global lazy image loader
    window.lazyImageLoader = new LazyImageLoader();
    
    // Helper function for async loading
    window.loadWithSkeleton = async (container, loader, type, count) => {
        return window.skeletonLoader.load(container, loader, type, count);
    };
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { SkeletonLoader, LazyImageLoader };
}
