/**
 * Image Optimizer - Lazy loading, WebP support, responsive images
 * Optimizes image loading for better performance
 */

(function() {
    'use strict';
    
    class ImageOptimizer {
        constructor() {
            this.supportsWebP = false;
            this.supportsLazyLoading = 'loading' in HTMLImageElement.prototype;
            this.observer = null;
            this.init();
        }
        
        // Initialize
        async init() {
            await this.checkWebPSupport();
            this.setupLazyLoading();
            this.optimizeExistingImages();
        }
        
        // Check WebP support
        checkWebPSupport() {
            return new Promise((resolve) => {
                const webP = new Image();
                webP.onload = webP.onerror = () => {
                    this.supportsWebP = webP.height === 2;
                    if (this.supportsWebP) {
                        document.documentElement.classList.add('webp');
                    }
                    resolve(this.supportsWebP);
                };
                webP.src = 'data:image/webp;base64,UklGRjoAAABXRUJQVlA4IC4AAACyAgCdASoCAAIALmk0mk0iIiIiIgBoSygABc6WWgAA/veff/0PP8bA//LwYAAA';
            });
        }
        
        // Setup lazy loading
        setupLazyLoading() {
            if (this.supportsLazyLoading) {
                // Native lazy loading supported
                this.enableNativeLazyLoading();
            } else {
                // Use Intersection Observer
                this.setupIntersectionObserver();
            }
        }
        
        // Enable native lazy loading
        enableNativeLazyLoading() {
            const images = document.querySelectorAll('img[data-src]');
            images.forEach(img => {
                img.loading = 'lazy';
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                
                if (img.dataset.srcset) {
                    img.srcset = img.dataset.srcset;
                    img.removeAttribute('data-srcset');
                }
            });
        }
        
        // Setup Intersection Observer for lazy loading
        setupIntersectionObserver() {
            const options = {
                root: null,
                rootMargin: '50px',
                threshold: 0.01
            };
            
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadImage(entry.target);
                        this.observer.unobserve(entry.target);
                    }
                });
            }, options);
            
            // Observe all images with data-src
            const images = document.querySelectorAll('img[data-src]');
            images.forEach(img => this.observer.observe(img));
        }
        
        // Load image
        loadImage(img) {
            const src = img.dataset.src;
            const srcset = img.dataset.srcset;
            
            if (!src) return;
            
            // Create a temporary image to preload
            const tempImg = new Image();
            
            tempImg.onload = () => {
                img.src = src;
                if (srcset) {
                    img.srcset = srcset;
                }
                img.classList.add('loaded');
                img.removeAttribute('data-src');
                img.removeAttribute('data-srcset');
            };
            
            tempImg.onerror = () => {
                console.error('Failed to load image:', src);
                img.classList.add('error');
            };
            
            tempImg.src = src;
            if (srcset) {
                tempImg.srcset = srcset;
            }
        }
        
        // Optimize existing images
        optimizeExistingImages() {
            const images = document.querySelectorAll('img:not([data-src])');
            
            images.forEach(img => {
                // Add decoding attribute
                if (!img.hasAttribute('decoding')) {
                    img.decoding = 'async';
                }
                
                // Add loading attribute if not present
                if (!img.hasAttribute('loading') && this.supportsLazyLoading) {
                    img.loading = 'lazy';
                }
                
                // Convert to WebP if supported
                if (this.supportsWebP && img.dataset.webp) {
                    img.src = img.dataset.webp;
                }
            });
        }
        
        // Generate responsive image srcset
        generateSrcset(basePath, sizes = [320, 640, 960, 1280, 1920]) {
            return sizes.map(size => {
                const ext = basePath.split('.').pop();
                const path = basePath.replace(`.${ext}`, `_${size}.${ext}`);
                return `${path} ${size}w`;
            }).join(', ');
        }
        
        // Preload critical images
        preloadImage(src, as = 'image') {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.as = as;
            link.href = src;
            document.head.appendChild(link);
        }
        
        // Convert image to WebP (client-side)
        async convertToWebP(img) {
            if (!this.supportsWebP) return null;
            
            try {
                const canvas = document.createElement('canvas');
                canvas.width = img.naturalWidth;
                canvas.height = img.naturalHeight;
                
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                
                return new Promise((resolve) => {
                    canvas.toBlob((blob) => {
                        resolve(blob);
                    }, 'image/webp', 0.8);
                });
            } catch (e) {
                console.error('Failed to convert to WebP:', e);
                return null;
            }
        }
        
        // Get optimal image size based on viewport
        getOptimalSize(img) {
            const dpr = window.devicePixelRatio || 1;
            const width = img.clientWidth * dpr;
            
            // Round up to nearest standard size
            const sizes = [320, 640, 960, 1280, 1920, 2560];
            return sizes.find(size => size >= width) || sizes[sizes.length - 1];
        }
        
        // Monitor new images added to DOM
        observeNewImages() {
            const mutationObserver = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeName === 'IMG') {
                            this.processImage(node);
                        } else if (node.querySelectorAll) {
                            const images = node.querySelectorAll('img');
                            images.forEach(img => this.processImage(img));
                        }
                    });
                });
            });
            
            mutationObserver.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
        
        // Process single image
        processImage(img) {
            if (img.dataset.src) {
                if (this.observer) {
                    this.observer.observe(img);
                } else {
                    this.loadImage(img);
                }
            } else {
                this.optimizeExistingImages();
            }
        }
        
        // Get image loading stats
        getStats() {
            const images = document.querySelectorAll('img');
            const loaded = document.querySelectorAll('img.loaded').length;
            const total = images.length;
            const pending = document.querySelectorAll('img[data-src]').length;
            
            return {
                total,
                loaded,
                pending,
                percentage: total > 0 ? Math.round((loaded / total) * 100) : 0
            };
        }
    }
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.ImageOptimizer = new ImageOptimizer();
        });
    } else {
        window.ImageOptimizer = new ImageOptimizer();
    }
})();
