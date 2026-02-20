/**
 * Cursor Effect - Красивый эффект круга за курсором
 */

(function() {
    'use strict';
    
    // Проверка на мобильное устройство
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    const hasTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    
    if (isMobile || hasTouch) {
        return; // Не запускаем на мобильных
    }
    
    // Создаем элементы курсора
    const cursorCircle = document.createElement('div');
    cursorCircle.className = 'cursor-circle';
    
    const cursorDot = document.createElement('div');
    cursorDot.className = 'cursor-dot';
    
    document.body.appendChild(cursorCircle);
    document.body.appendChild(cursorDot);
    document.body.classList.add('cursor-active');
    
    let mouseX = 0;
    let mouseY = 0;
    let circleX = 0;
    let circleY = 0;
    let dotX = 0;
    let dotY = 0;
    
    // Отслеживание движения мыши
    document.addEventListener('mousemove', (e) => {
        mouseX = e.clientX;
        mouseY = e.clientY;
        
        // Показываем курсор
        if (!cursorCircle.classList.contains('active')) {
            cursorCircle.classList.add('active');
            cursorDot.classList.add('active');
        }
    });
    
    // Клик мыши
    document.addEventListener('mousedown', () => {
        cursorCircle.classList.add('clicking');
        cursorDot.classList.add('clicking');
    });
    
    document.addEventListener('mouseup', () => {
        cursorCircle.classList.remove('clicking');
        cursorDot.classList.remove('clicking');
    });
    
    // Скрываем при выходе курсора
    document.addEventListener('mouseleave', () => {
        cursorCircle.classList.remove('active');
        cursorDot.classList.remove('active');
    });
    
    // Плавная анимация с использованием requestAnimationFrame
    function animate() {
        // Плавное следование за курсором (easing)
        const speed = 0.15;
        const dotSpeed = 0.25;
        
        circleX += (mouseX - circleX) * speed;
        circleY += (mouseY - circleY) * speed;
        
        dotX += (mouseX - dotX) * dotSpeed;
        dotY += (mouseY - dotY) * dotSpeed;
        
        cursorCircle.style.left = circleX + 'px';
        cursorCircle.style.top = circleY + 'px';
        
        cursorDot.style.left = dotX + 'px';
        cursorDot.style.top = dotY + 'px';
        
        requestAnimationFrame(animate);
    }
    
    animate();
    
})();
