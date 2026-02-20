/**
 * Enhanced Navbar Functionality
 * Современная навигация с анимациями и улучшенным UX
 */

(function() {
    'use strict';

    // Navbar scroll effect
    const navbar = document.querySelector('.navbar-enhanced');
    let lastScroll = 0;

    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;

        if (currentScroll > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }

        lastScroll = currentScroll;
    });

    // Active link highlighting
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link-enhanced');

    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && currentPath.startsWith(href) && href !== '/') {
            link.classList.add('active');
        } else if (href === '/' && currentPath === '/') {
            link.classList.add('active');
        }
    });

    // Dropdown animation
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        const menu = dropdown.querySelector('.dropdown-menu-enhanced');
        
        dropdown.addEventListener('show.bs.dropdown', () => {
            menu.style.display = 'block';
            menu.style.opacity = '0';
            menu.style.transform = 'translateY(-10px)';
            
            requestAnimationFrame(() => {
                menu.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                menu.style.opacity = '1';
                menu.style.transform = 'translateY(0)';
            });
        });
        
        dropdown.addEventListener('hide.bs.dropdown', () => {
            menu.style.opacity = '0';
            menu.style.transform = 'translateY(-10px)';
            
            setTimeout(() => {
                menu.style.display = 'none';
            }, 300);
        });
    });

    // FAB Menu functionality
    window.toggleFabMenu = function() {
        const menu = document.getElementById('fabMenu');
        const backdrop = document.getElementById('fabBackdrop');
        const fabButton = document.getElementById('fabButton');
        
        if (menu && backdrop && fabButton) {
            const isOpen = menu.classList.contains('show');
            
            if (isOpen) {
                menu.classList.remove('show');
                backdrop.classList.remove('show');
                fabButton.style.transform = 'rotate(0deg)';
            } else {
                menu.classList.add('show');
                backdrop.classList.add('show');
                fabButton.style.transform = 'rotate(45deg)';
            }
        }
    };

    // Close FAB menu on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const menu = document.getElementById('fabMenu');
            const backdrop = document.getElementById('fabBackdrop');
            const fabButton = document.getElementById('fabButton');
            
            if (menu && menu.classList.contains('show')) {
                menu.classList.remove('show');
                backdrop.classList.remove('show');
                if (fabButton) {
                    fabButton.style.transform = 'rotate(0deg)';
                }
            }
        }
    });

    // Notification count update
    function updateNotificationCount() {
        const badge = document.querySelector('.notification-badge-enhanced');
        if (!badge) return;

        fetch('/notifications/unread-count')
            .then(response => response.json())
            .then(data => {
                if (data.count > 0) {
                    badge.textContent = data.count > 9 ? '9+' : data.count;
                    badge.style.display = 'block';
                } else {
                    badge.style.display = 'none';
                }
            })
            .catch(error => console.error('Error updating notification count:', error));
    }

    // Update notification count every 5 minutes
    if (document.querySelector('.notification-badge-enhanced')) {
        updateNotificationCount();
        setInterval(updateNotificationCount, 300000);
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        // Ignore if user is typing in input/textarea
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return;
        }

        // Ctrl/Cmd + K for search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.querySelector('input[type="search"]');
            if (searchInput) {
                searchInput.focus();
            }
        }

        // Single key shortcuts
        switch(e.key.toLowerCase()) {
            case 't':
                window.location.href = '/task';
                break;
            case 'd':
                window.location.href = '/';
                break;
            case 'k':
                window.location.href = '/kanban';
                break;
            case 'c':
                window.location.href = '/calendar';
                break;
            case 'p':
                window.location.href = '/profile';
                break;
            case 'n':
                window.location.href = '/tasks/new';
                break;
            case '?':
                const modal = new bootstrap.Modal(document.getElementById('keyboardShortcutsModal'));
                modal.show();
                break;
        }
    });

    // Mobile menu close on link click
    const mobileLinks = document.querySelectorAll('.navbar-collapse-enhanced .nav-link-enhanced');
    const navbarToggler = document.querySelector('.navbar-toggler-enhanced');
    const navbarCollapse = document.querySelector('.navbar-collapse-enhanced');

    mobileLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 992 && navbarCollapse.classList.contains('show')) {
                navbarToggler.click();
            }
        });
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== '#' && href !== '#!') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });

    // User avatar initials
    const userAvatar = document.querySelector('.user-avatar-enhanced');
    if (userAvatar && !userAvatar.textContent.trim()) {
        const userName = userAvatar.getAttribute('data-name') || 'User';
        const initials = userName.split(' ')
            .map(word => word[0])
            .join('')
            .toUpperCase()
            .substring(0, 2);
        userAvatar.textContent = initials;
    }

    // Tooltip initialization for navbar items
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

    // Add ripple effect to buttons
    function createRipple(event) {
        const button = event.currentTarget;
        const ripple = document.createElement('span');
        const diameter = Math.max(button.clientWidth, button.clientHeight);
        const radius = diameter / 2;

        ripple.style.width = ripple.style.height = `${diameter}px`;
        ripple.style.left = `${event.clientX - button.offsetLeft - radius}px`;
        ripple.style.top = `${event.clientY - button.offsetTop - radius}px`;
        ripple.classList.add('ripple');

        const rippleElement = button.querySelector('.ripple');
        if (rippleElement) {
            rippleElement.remove();
        }

        button.appendChild(ripple);
    }

    const buttons = document.querySelectorAll('.btn-calendar, .quick-action-btn-enhanced');
    buttons.forEach(button => {
        button.addEventListener('click', createRipple);
    });

    // Add ripple styles
    const style = document.createElement('style');
    style.textContent = `
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple-animation 0.6s ease-out;
            pointer-events: none;
        }
        
        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);

    console.log('✨ Enhanced Navbar initialized');
})();
