/**
 * Enhanced Calendar Functionality
 * Fixes navigation issues and adds interactive features
 */

class CalendarManager {
    constructor() {
        this.calendar = null;
        this.allEvents = [];
        this.activeFilters = {
            priority: ['high', 'medium', 'low'],
            status: ['pending', 'in_progress', 'completed']
        };
        this.init();
    }

    init() {
        if (document.getElementById('calendar')) {
            this.initializeCalendar();
            this.setupFilters();
            this.setupKeyboardShortcuts();
            this.setupCustomNavigation();
        }
    }

    initializeCalendar() {
        const calendarEl = document.getElementById('calendar');
        
        this.calendar = new FullCalendar.Calendar(calendarEl, {
            locale: 'ru',
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            buttonText: {
                today: 'Сегодня',
                month: 'Месяц',
                week: 'Неделя',
                day: 'День',
                list: 'Список'
            },
            editable: true,
            droppable: true,
            selectable: true,
            selectMirror: true,
            dayMaxEvents: true,
            weekNumbers: true,
            weekText: 'Нед',
            allDayText: 'Весь день',
            noEventsText: 'Нет задач для отображения',
            firstDay: 1,
            height: 'auto',
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            },
            
            // Event handlers
            eventDrop: (info) => this.handleEventDrop(info),
            eventClick: (info) => this.handleEventClick(info),
            eventDidMount: (info) => this.handleEventMount(info),
            select: (info) => this.handleDateSelect(info),
            datesSet: (info) => this.handleDatesSet(info),
            
            // Load events
            events: (info, successCallback, failureCallback) => {
                this.loadEvents(info, successCallback, failureCallback);
            }
        });

        this.calendar.render();
        
        // Fix button click handlers
        this.fixNavigationButtons();
    }

    fixNavigationButtons() {
        // Ensure all FullCalendar buttons work properly
        setTimeout(() => {
            const buttons = document.querySelectorAll('.fc-button');
            buttons.forEach(button => {
                // Remove any conflicting event listeners
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);
                
                // Add proper click handler
                newButton.addEventListener('click', (e) => {
                    e.stopPropagation();
                    
                    if (newButton.classList.contains('fc-prev-button')) {
                        this.calendar.prev();
                    } else if (newButton.classList.contains('fc-next-button')) {
                        this.calendar.next();
                    } else if (newButton.classList.contains('fc-today-button')) {
                        this.calendar.today();
                    } else if (newButton.classList.contains('fc-dayGridMonth-button')) {
                        this.calendar.changeView('dayGridMonth');
                    } else if (newButton.classList.contains('fc-timeGridWeek-button')) {
                        this.calendar.changeView('timeGridWeek');
                    } else if (newButton.classList.contains('fc-timeGridDay-button')) {
                        this.calendar.changeView('timeGridDay');
                    } else if (newButton.classList.contains('fc-listWeek-button')) {
                        this.calendar.changeView('listWeek');
                    }
                });
            });
        }, 500);
    }

    setupCustomNavigation() {
        // Add custom navigation controls
        const header = document.querySelector('.calendar-header');
        if (!header) return;

        const navControls = document.createElement('div');
        navControls.className = 'custom-calendar-nav';
        navControls.innerHTML = `
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-light" id="customPrevBtn">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" class="btn btn-outline-light" id="customTodayBtn">
                    Сегодня
                </button>
                <button type="button" class="btn btn-outline-light" id="customNextBtn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        `;

        // Add event listeners
        document.addEventListener('click', (e) => {
            if (e.target.closest('#customPrevBtn')) {
                this.calendar.prev();
                this.updateTitle();
            } else if (e.target.closest('#customNextBtn')) {
                this.calendar.next();
                this.updateTitle();
            } else if (e.target.closest('#customTodayBtn')) {
                this.calendar.today();
                this.updateTitle();
            }
        });
    }

    updateTitle() {
        // Update custom title if needed
        const title = this.calendar.view.title;
        console.log('Calendar view:', title);
    }

    async loadEvents(info, successCallback, failureCallback) {
        try {
            const response = await fetch(`/calendar/events?start=${info.startStr}&end=${info.endStr}`);
            const data = await response.json();
            
            this.allEvents = data;
            this.updateStats(data);
            this.updateCounts(data);
            
            const filteredEvents = this.filterEvents(data);
            successCallback(filteredEvents);
        } catch (error) {
            console.error('Error loading events:', error);
            failureCallback(error);
            this.showNotification('Ошибка загрузки событий', 'error');
        }
    }

    filterEvents(events) {
        return events.filter(event => {
            const priority = event.extendedProps?.priority || 'low';
            const status = event.extendedProps?.status || 'pending';
            
            return this.activeFilters.priority.includes(priority) && 
                   this.activeFilters.status.includes(status);
        });
    }

    async handleEventDrop(info) {
        const taskId = info.event.id;
        const newDate = info.event.start.toISOString().split('T')[0];
        
        try {
            const response = await fetch('/calendar/update-date', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    taskId: taskId,
                    newDate: newDate
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Дата задачи обновлена', 'success');
            } else {
                info.revert();
                this.showNotification(data.message || 'Ошибка при обновлении даты', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            info.revert();
            this.showNotification('Ошибка при обновлении даты', 'error');
        }
    }

    handleEventClick(info) {
        info.jsEvent.preventDefault();
        
        if (info.event.url) {
            window.location.href = info.event.url;
        }
    }

    handleEventMount(info) {
        const priority = info.event.extendedProps?.priority || 'low';
        const status = info.event.extendedProps?.status || 'pending';
        
        // Add custom classes
        info.el.classList.add(`priority-${priority}`);
        info.el.classList.add(`status-${status}`);
        
        // Add tooltip
        const tooltip = `
${info.event.title}
Приоритет: ${this.getPriorityLabel(priority)}
Статус: ${this.getStatusLabel(status)}
        `.trim();
        
        info.el.title = tooltip;
        
        // Add animation
        info.el.style.animation = 'fadeIn 0.3s ease-out';
        
        // Add hover effect
        info.el.addEventListener('mouseenter', () => {
            info.el.style.transform = 'scale(1.05)';
            info.el.style.zIndex = '100';
        });
        
        info.el.addEventListener('mouseleave', () => {
            info.el.style.transform = 'scale(1)';
            info.el.style.zIndex = 'auto';
        });
    }

    handleDateSelect(info) {
        const selectedDate = info.startStr;
        
        if (confirm(`Создать новую задачу на ${selectedDate}?`)) {
            window.location.href = `/tasks/new?date=${selectedDate}`;
        }
        
        this.calendar.unselect();
    }

    handleDatesSet(info) {
        // Called when the view changes
        this.fixNavigationButtons();
    }

    setupFilters() {
        const filterChips = document.querySelectorAll('.filter-chip');
        
        filterChips.forEach(chip => {
            const checkbox = chip.querySelector('input[type="checkbox"]');
            
            if (checkbox) {
                checkbox.addEventListener('change', () => {
                    const filterType = checkbox.dataset.filter;
                    const filterValue = checkbox.dataset.value;
                    
                    if (checkbox.checked) {
                        chip.classList.add('active');
                        if (!this.activeFilters[filterType].includes(filterValue)) {
                            this.activeFilters[filterType].push(filterValue);
                        }
                    } else {
                        chip.classList.remove('active');
                        this.activeFilters[filterType] = this.activeFilters[filterType].filter(v => v !== filterValue);
                    }
                    
                    this.calendar.refetchEvents();
                });
            }
        });
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ignore if typing in input
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }
            
            switch(e.key.toLowerCase()) {
                case 't':
                    e.preventDefault();
                    this.calendar.today();
                    break;
                case 'arrowleft':
                    e.preventDefault();
                    this.calendar.prev();
                    break;
                case 'arrowright':
                    e.preventDefault();
                    this.calendar.next();
                    break;
                case 'm':
                    e.preventDefault();
                    this.calendar.changeView('dayGridMonth');
                    break;
                case 'w':
                    e.preventDefault();
                    this.calendar.changeView('timeGridWeek');
                    break;
                case 'd':
                    e.preventDefault();
                    this.calendar.changeView('timeGridDay');
                    break;
                case 'l':
                    e.preventDefault();
                    this.calendar.changeView('listWeek');
                    break;
            }
        });
    }

    updateStats(events) {
        const total = events.length;
        const pending = events.filter(e => e.extendedProps?.status === 'pending').length;
        const inProgress = events.filter(e => e.extendedProps?.status === 'in_progress').length;
        const completed = events.filter(e => e.extendedProps?.status === 'completed').length;
        
        this.animateNumber('total-tasks', total);
        this.animateNumber('pending-tasks', pending);
        this.animateNumber('progress-tasks', inProgress);
        this.animateNumber('completed-tasks', completed);
    }

    updateCounts(events) {
        const high = events.filter(e => e.extendedProps?.priority === 'high').length;
        const medium = events.filter(e => e.extendedProps?.priority === 'medium').length;
        const low = events.filter(e => e.extendedProps?.priority === 'low').length;
        
        const pending = events.filter(e => e.extendedProps?.status === 'pending').length;
        const inProgress = events.filter(e => e.extendedProps?.status === 'in_progress').length;
        const completed = events.filter(e => e.extendedProps?.status === 'completed').length;
        
        this.setElementText('count-high', high);
        this.setElementText('count-medium', medium);
        this.setElementText('count-low', low);
        this.setElementText('count-pending', pending);
        this.setElementText('count-in-progress', inProgress);
        this.setElementText('count-completed', completed);
    }

    setElementText(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    }

    animateNumber(elementId, targetNumber) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        const duration = 1000;
        const start = parseInt(element.textContent) || 0;
        const increment = (targetNumber - start) / (duration / 16);
        let current = start;
        
        const timer = setInterval(() => {
            current += increment;
            if ((increment > 0 && current >= targetNumber) || (increment < 0 && current <= targetNumber)) {
                element.textContent = targetNumber;
                clearInterval(timer);
            } else {
                element.textContent = Math.round(current);
            }
        }, 16);
    }

    getPriorityLabel(priority) {
        const labels = {
            'high': 'Высокий',
            'medium': 'Средний',
            'low': 'Низкий'
        };
        return labels[priority] || priority;
    }

    getStatusLabel(status) {
        const labels = {
            'pending': 'В ожидании',
            'in_progress': 'В процессе',
            'completed': 'Завершено'
        };
        return labels[status] || status;
    }

    showNotification(message, type = 'info') {
        const container = this.getToastContainer();
        
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            </div>
            <div class="toast-message">${message}</div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        container.appendChild(toast);
        
        // Animate in
        setTimeout(() => toast.classList.add('show'), 10);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    getToastContainer() {
        let container = document.getElementById('toast-container');
        
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                gap: 10px;
            `;
            document.body.appendChild(container);
        }
        
        return container;
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new CalendarManager();
    });
} else {
    new CalendarManager();
}

// Add required CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    /* Fix FullCalendar button styles */
    .fc-button {
        cursor: pointer !important;
        pointer-events: auto !important;
    }

    .fc-button:hover {
        opacity: 0.9 !important;
    }

    .fc-button:active {
        transform: scale(0.95) !important;
    }

    /* Toast Notifications */
    .toast-notification {
        display: flex;
        align-items: center;
        gap: 12px;
        background: white;
        padding: 16px;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        min-width: 300px;
        max-width: 400px;
        opacity: 0;
        transform: translateX(400px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .toast-notification.show {
        opacity: 1;
        transform: translateX(0);
    }

    .toast-icon {
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .toast-success {
        border-left: 4px solid #28a745;
    }

    .toast-success .toast-icon {
        color: #28a745;
    }

    .toast-error {
        border-left: 4px solid #dc3545;
    }

    .toast-error .toast-icon {
        color: #dc3545;
    }

    .toast-info {
        border-left: 4px solid #17a2b8;
    }

    .toast-info .toast-icon {
        color: #17a2b8;
    }

    .toast-message {
        flex: 1;
        font-weight: 500;
        color: #212529;
    }

    .toast-close {
        background: none;
        border: none;
        color: #6c757d;
        cursor: pointer;
        padding: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: all 0.2s ease;
    }

    .toast-close:hover {
        background: #f8f9fa;
        color: #212529;
    }

    /* Custom navigation controls */
    .custom-calendar-nav {
        margin-top: 1rem;
    }

    .custom-calendar-nav .btn-group {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border-radius: 8px;
        overflow: hidden;
    }

    .custom-calendar-nav .btn {
        border: none;
        padding: 0.625rem 1.25rem;
        font-weight: 600;
    }
`;
document.head.appendChild(style);
