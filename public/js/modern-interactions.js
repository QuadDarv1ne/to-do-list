/**
 * Modern Interactions
 * Enhanced UI interactions for modern theme
 */

class ModernInteractions {
    constructor() {
        this.init();
    }

    init() {
        this.initParallaxEffect();
        this.initMagneticButtons();
        this.initCardTilt();
        this.initProgressAnimations();
        this.initCountUpAnimations();
        this.initScrollReveal();
        this.initCursorFollower();
    }

    /**
     * Parallax effect for hero sections
     */
    initParallaxEffect() {
        const parallaxElements = document.querySelectorAll('[data-parallax]');
        
        if (parallaxElements.length === 0) return;

        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            
            parallaxElements.forEach(el => {
                const speed = el.dataset.parallax || 0.5;
                const yPos = -(scrolled * speed);
                el.style.transform = `translateY(${yPos}px)`;
            });
        });
    }

    /**
     * Magnetic effect for buttons
     */
    initMagneticButtons() {
        const magneticButtons = document.querySelectorAll('[data-magnetic]');
        
        magneticButtons.forEach(button => {
            button.addEventListener('mousemove', (e) => {
                const rect = button.getBoundingClientRect();
                const x = e.clientX - rect.left - rect.width / 2;
                const y = e.clientY - rect.top - rect.height / 2;
                
                const strength = button.dataset.magnetic || 0.3;
                
                button.style.transform = `translate(${x * strength}px, ${y * strength}px)`;
            });
            
            button.addEventListener('mouseleave', () => {
                button.style.transform = 'translate(0, 0)';
            });
        });
    }

    /**
     * 3D tilt effect for cards
     */
    initCardTilt() {
        const tiltCards = document.querySelectorAll('[data-tilt]');
        
        tiltCards.forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = (y - centerY) / 10;
                const rotateY = (centerX - x) / 10;
                
                card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`;
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale3d(1, 1, 1)';
            });
        });
    }

    /**
     * Animated progress bars
     */
    initProgressAnimations() {
        const progressBars = document.querySelectorAll('[data-progress]');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const bar = entry.target;
                    const targetWidth = bar.dataset.progress;
                    
                    setTimeout(() => {
                        bar.style.width = targetWidth + '%';
                    }, 100);
                    
                    observer.unobserve(bar);
                }
            });
        }, { threshold: 0.5 });
        
        progressBars.forEach(bar => observer.observe(bar));
    }

    /**
     * Count up animation for numbers
     */
    initCountUpAnimations() {
        const countElements = document.querySelectorAll('[data-count]');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const element = entry.target;
                    const target = parseInt(element.dataset.count);
                    const duration = parseInt(element.dataset.duration) || 2000;
                    const start = 0;
                    const increment = target / (duration / 16);
                    
                    let current = start;
                    
                    const timer = setInterval(() => {
                        current += increment;
                        
                        if (current >= target) {
                            element.textContent = target;
                            clearInterval(timer);
                        } else {
                            element.textContent = Math.floor(current);
                        }
                    }, 16);
                    
                    observer.unobserve(element);
                }
            });
        }, { threshold: 0.5 });
        
        countElements.forEach(el => observer.observe(el));
    }

    /**
     * Scroll reveal animations
     */
    initScrollReveal() {
        const revealElements = document.querySelectorAll('[data-reveal]');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const element = entry.target;
                    const animation = element.dataset.reveal || 'fade-up';
                    
                    element.classList.add('revealed', `reveal-${animation}`);
                    observer.unobserve(element);
                }
            });
        }, { 
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });
        
        revealElements.forEach(el => {
            el.style.opacity = '0';
            observer.observe(el);
        });
    }

    /**
     * Custom cursor follower
     */
    initCursorFollower() {
        if (window.innerWidth < 768) return; // Skip on mobile
        
        const cursor = document.createElement('div');
        cursor.className = 'cursor-follower';
        document.body.appendChild(cursor);
        
        let mouseX = 0;
        let mouseY = 0;
        let cursorX = 0;
        let cursorY = 0;
        
        document.addEventListener('mousemove', (e) => {
            mouseX = e.clientX;
            mouseY = e.clientY;
        });
        
        const animate = () => {
            const dx = mouseX - cursorX;
            const dy = mouseY - cursorY;
            
            cursorX += dx * 0.1;
            cursorY += dy * 0.1;
            
            cursor.style.left = cursorX + 'px';
            cursor.style.top = cursorY + 'px';
            
            requestAnimationFrame(animate);
        };
        
        animate();
        
        // Expand on hover
        document.querySelectorAll('a, button, [role="button"]').forEach(el => {
            el.addEventListener('mouseenter', () => cursor.classList.add('expanded'));
            el.addEventListener('mouseleave', () => cursor.classList.remove('expanded'));
        });
    }
}

/**
 * Smooth Page Transitions
 */
class PageTransitions {
    constructor() {
        this.init();
    }

    init() {
        this.setupTransitions();
    }

    setupTransitions() {
        // Add page enter animation
        document.body.classList.add('page-enter');
        
        setTimeout(() => {
            document.body.classList.remove('page-enter');
        }, 300);
        
        // Handle link clicks for smooth transitions
        document.querySelectorAll('a:not([target="_blank"])').forEach(link => {
            link.addEventListener('click', (e) => {
                const href = link.getAttribute('href');
                
                // Skip if it's a hash link or external
                if (!href || href.startsWith('#') || href.startsWith('http')) return;
                
                e.preventDefault();
                
                document.body.classList.add('page-exit');
                
                setTimeout(() => {
                    window.location.href = href;
                }, 300);
            });
        });
    }
}

/**
 * Enhanced Form Interactions
 */
class FormEnhancements {
    constructor() {
        this.init();
    }

    init() {
        this.initFloatingLabels();
        this.initPasswordToggle();
        this.initFileUploadPreview();
        this.initCharacterCounter();
    }

    /**
     * Floating labels for inputs
     */
    initFloatingLabels() {
        const inputs = document.querySelectorAll('.form-floating input, .form-floating textarea');
        
        inputs.forEach(input => {
            // Check on load
            if (input.value) {
                input.classList.add('has-value');
            }
            
            // Check on input
            input.addEventListener('input', () => {
                if (input.value) {
                    input.classList.add('has-value');
                } else {
                    input.classList.remove('has-value');
                }
            });
        });
    }

    /**
     * Password visibility toggle
     */
    initPasswordToggle() {
        const passwordInputs = document.querySelectorAll('input[type="password"]');
        
        passwordInputs.forEach(input => {
            const wrapper = document.createElement('div');
            wrapper.className = 'password-toggle-wrapper';
            
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);
            
            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'password-toggle-btn';
            toggle.innerHTML = '<i class="fas fa-eye"></i>';
            toggle.setAttribute('aria-label', 'Toggle password visibility');
            
            wrapper.appendChild(toggle);
            
            toggle.addEventListener('click', () => {
                const type = input.type === 'password' ? 'text' : 'password';
                input.type = type;
                
                const icon = toggle.querySelector('i');
                icon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
            });
        });
    }

    /**
     * File upload preview
     */
    initFileUploadPreview() {
        const fileInputs = document.querySelectorAll('input[type="file"][data-preview]');
        
        fileInputs.forEach(input => {
            input.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (!file) return;
                
                const previewId = input.dataset.preview;
                const preview = document.getElementById(previewId);
                
                if (!preview) return;
                
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    
                    reader.onload = (e) => {
                        preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="max-width: 100%; border-radius: 8px;">`;
                    };
                    
                    reader.readAsDataURL(file);
                } else {
                    preview.innerHTML = `<div class="file-info"><i class="fas fa-file"></i> ${file.name}</div>`;
                }
            });
        });
    }

    /**
     * Character counter for textareas
     */
    initCharacterCounter() {
        const textareas = document.querySelectorAll('textarea[data-max-length]');
        
        textareas.forEach(textarea => {
            const maxLength = parseInt(textarea.dataset.maxLength);
            
            const counter = document.createElement('div');
            counter.className = 'character-counter';
            counter.textContent = `0 / ${maxLength}`;
            
            textarea.parentNode.appendChild(counter);
            
            textarea.addEventListener('input', () => {
                const length = textarea.value.length;
                counter.textContent = `${length} / ${maxLength}`;
                
                if (length > maxLength * 0.9) {
                    counter.classList.add('warning');
                } else {
                    counter.classList.remove('warning');
                }
                
                if (length >= maxLength) {
                    counter.classList.add('danger');
                } else {
                    counter.classList.remove('danger');
                }
            });
        });
    }
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.modernInteractions = new ModernInteractions();
        window.pageTransitions = new PageTransitions();
        window.formEnhancements = new FormEnhancements();
    });
} else {
    window.modernInteractions = new ModernInteractions();
    window.pageTransitions = new PageTransitions();
    window.formEnhancements = new FormEnhancements();
}
