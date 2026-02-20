/**
 * Calendar Enhanced
 * Modern calendar with theme support and event management
 */

document.addEventListener('DOMContentLoaded', function() {
    initCalendar();
});

function initCalendar() {
    initCalendarNavigation();
    initDaySelection();
    initEventActions();
    initQuickEventAdd();
    initThemeAwareCalendar();
}

/**
 * Calendar Navigation
 */
function initCalendarNavigation() {
    const prevBtn = document.getElementById('calendar-prev');
    const nextBtn = document.getElementById('calendar-next');
    const todayBtn = document.getElementById('calendar-today');
    
    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            navigateMonth(-1);
        });
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            navigateMonth(1);
        });
    }
    
    if (todayBtn) {
        todayBtn.addEventListener('click', function() {
            navigateToToday();
        });
    }
}

/**
 * Navigate month
 */
function navigateMonth(direction) {
    const currentDate = getCurrentCalendarDate();
    const newDate = new Date(currentDate);
    newDate.setMonth(newDate.getMonth() + direction);
    
    loadCalendar(newDate);
}

/**
 * Navigate to today
 */
function navigateToToday() {
    loadCalendar(new Date());
}

/**
 * Get current calendar date
 */
function getCurrentCalendarDate() {
    const titleElement = document.querySelector('.calendar-title');
    if (!titleElement) return new Date();
    
    // Parse date from title (e.g., "Январь 2024")
    const titleText = titleElement.textContent.trim();
    // Implementation depends on your date format
    return new Date();
}

/**
 * Load calendar
 */
function loadCalendar(date) {
    const year = date.getFullYear();
    const month = date.getMonth() + 1;
    
    fetch(`/calendar/data?year=${year}&month=${month}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        updateCalendarView(data);
    })
    .catch(error => {
        console.error('Error loading calendar:', error);
        showToast('Ошибка загрузки календаря', 'error');
    });
}

/**
 * Update calendar view
 */
function updateCalendarView(data) {
    const titleElement = document.querySelector('.calendar-title');
    const gridElement = document.querySelector('.calendar-grid');
    
    if (titleElement) {
        titleElement.textContent = data.title;
    }
    
    if (gridElement && data.days) {
        renderCalendarDays(gridElement, data.days);
    }
}

/**
 * Render calendar days
 */
function renderCalendarDays(container, days) {
    // Clear existing days (keep headers)
    const dayElements = container.querySelectorAll('.calendar-day');
    dayElements.forEach(el => el.remove());
    
    days.forEach(day => {
        const dayElement = createDayElement(day);
        container.appendChild(dayElement);
    });
}

/**
 * Create day element
 */
function createDayElement(day) {
    const div = document.createElement('div');
    div.className = 'calendar-day';
    
    if (day.isToday) div.classList.add('today');
    if (day.isOtherMonth) div.classList.add('other-month');
    
    div.dataset.date = day.date;
    
    let html = `<div class="calendar-day-number">${day.number}</div>`;
    
    if (day.events && day.events.length > 0) {
        html += '<div class="calendar-day-events">';
        day.events.forEach(event => {
            html += `<div class="calendar-event" data-event-id="${event.id}" title="${event.title}">${event.title}</div>`;
        });
        html += '</div>';
    }
    
    div.innerHTML = html;
    
    return div;
}

/**
 * Day Selection
 */
function initDaySelection() {
    document.addEventListener('click', function(e) {
        const day = e.target.closest('.calendar-day');
        if (!day) return;
        
        const date = day.dataset.date;
        if (date) {
            showDayDetails(date);
        }
    });
}

/**
 * Show day details
 */
function showDayDetails(date) {
    fetch(`/calendar/day/${date}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        showDayModal(data);
    })
    .catch(error => {
        console.error('Error loading day details:', error);
        showToast('Ошибка загрузки деталей дня', 'error');
    });
}

/**
 * Show day modal
 */
function showDayModal(data) {
    // Create or update modal
    let modal = document.getElementById('day-details-modal');
    
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'day-details-modal';
        modal.className = 'modal fade';
        document.body.appendChild(modal);
    }
    
    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">${data.title}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    ${data.events && data.events.length > 0 ? `
                        <div class="list-group">
                            ${data.events.map(event => `
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">${event.title}</h6>
                                            <small class="text-muted">${event.time || ''}</small>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <a href="/tasks/${event.id}" class="btn btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="/tasks/${event.id}/edit" class="btn btn-outline-secondary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    ` : '<p class="text-muted">Нет событий на этот день</p>'}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                    <a href="/tasks/new?date=${data.date}" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Добавить задачу
                    </a>
                </div>
            </div>
        </div>
    `;
    
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

/**
 * Event Actions
 */
function initEventActions() {
    document.addEventListener('click', function(e) {
        const event = e.target.closest('.calendar-event');
        if (!event) return;
        
        e.stopPropagation();
        
        const eventId = event.dataset.eventId;
        if (eventId) {
            window.location.href = `/tasks/${eventId}`;
        }
    });
}

/**
 * Quick Event Add
 */
function initQuickEventAdd() {
    const quickAddBtn = document.getElementById('quick-event-add');
    if (!quickAddBtn) return;
    
    quickAddBtn.addEventListener('click', function() {
        showQuickEventForm();
    });
}

/**
 * Show quick event form
 */
function showQuickEventForm() {
    // Implementation depends on your requirements
    window.location.href = '/tasks/new';
}

/**
 * Theme-aware calendar
 */
function initThemeAwareCalendar() {
    window.addEventListener('themechange', function(e) {
        updateCalendarColors(e.detail.theme);
    });
}

/**
 * Update calendar colors
 */
function updateCalendarColors(theme) {
    const calendarDays = document.querySelectorAll('.calendar-day');
    // Days use CSS variables, automatically updated
}

// showToast теперь в utils.js

// Export for use in other scripts
window.CalendarEnhanced = {
    navigateMonth,
    navigateToToday,
    showDayDetails
};
