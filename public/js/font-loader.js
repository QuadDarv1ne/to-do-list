/**
 * Font Loader - Optimized font loading with FOIT/FOUT prevention
 * Uses Font Loading API with fallbacks
 */

(function() {
    'use strict';
    
    // Check if Font Loading API is supported
    const supportsFontLoading = 'fonts' in document;
    
    // Font configurations
    const fonts = [
        {
            family: 'Font Awesome 6 Free',
            weight: '900',
            style: 'normal',
            url: 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/webfonts/fa-solid-900.woff2',
            format: 'woff2'
        },
        {
            family: 'Font Awesome 6 Free',
            weight: '400',
            style: 'normal',
            url: 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/webfonts/fa-regular-400.woff2',
            format: 'woff2'
        }
    ];
    
    // Load fonts using Font Loading API
    function loadFontsModern() {
        const fontPromises = fonts.map(font => {
            const fontFace = new FontFace(
                font.family,
                `url(${font.url}) format('${font.format}')`,
                {
                    weight: font.weight,
                    style: font.style
                }
            );
            
            return fontFace.load().then(loadedFont => {
                document.fonts.add(loadedFont);
                return loadedFont;
            });
        });
        
        return Promise.all(fontPromises);
    }
    
    // Fallback for browsers without Font Loading API
    function loadFontsFallback() {
        return new Promise((resolve) => {
            // Create a test element
            const testElement = document.createElement('div');
            testElement.style.fontFamily = 'Font Awesome 6 Free';
            testElement.style.position = 'absolute';
            testElement.style.left = '-9999px';
            testElement.textContent = 'test';
            document.body.appendChild(testElement);
            
            // Check if font is loaded
            let attempts = 0;
            const maxAttempts = 50; // 5 seconds max
            
            const checkFont = setInterval(() => {
                attempts++;
                
                // Simple heuristic: check if computed font family includes our font
                const computedFont = window.getComputedStyle(testElement).fontFamily;
                
                if (computedFont.includes('Font Awesome') || attempts >= maxAttempts) {
                    clearInterval(checkFont);
                    document.body.removeChild(testElement);
                    resolve();
                }
            }, 100);
        });
    }
    
    // Main font loading function
    function loadFonts() {
        const startTime = performance.now();
        
        const loadPromise = supportsFontLoading 
            ? loadFontsModern() 
            : loadFontsFallback();
        
        loadPromise
            .then(() => {
                const loadTime = performance.now() - startTime;
                console.log(`Fonts loaded in ${loadTime.toFixed(2)}ms`);
                
                // Add class to indicate fonts are ready
                document.documentElement.classList.add('fonts-loaded');
                
                // Dispatch custom event
                document.dispatchEvent(new CustomEvent('fontsloaded', {
                    detail: { loadTime }
                }));
                
                // Store in sessionStorage to skip loading on subsequent pages
                try {
                    sessionStorage.setItem('fonts-loaded', 'true');
                } catch (e) {
                    // Ignore storage errors
                }
            })
            .catch(error => {
                console.error('Error loading fonts:', error);
                // Still mark as loaded to prevent blocking
                document.documentElement.classList.add('fonts-loaded');
            });
    }
    
    // Check if fonts were already loaded in this session
    function checkFontsCache() {
        try {
            return sessionStorage.getItem('fonts-loaded') === 'true';
        } catch (e) {
            return false;
        }
    }
    
    // Initialize
    function init() {
        // If fonts were already loaded, skip loading
        if (checkFontsCache()) {
            document.documentElement.classList.add('fonts-loaded');
            return;
        }
        
        // Load fonts
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', loadFonts);
        } else {
            loadFonts();
        }
    }
    
    // Start initialization
    init();
    
    // Expose API
    window.FontLoader = {
        load: loadFonts,
        isLoaded: () => document.documentElement.classList.contains('fonts-loaded')
    };
})();
