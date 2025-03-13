/**
 * Incoming Transactions JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize date range pickers
    initializeDateRanges();
    
    // Initialize category filter
    const categoryFilter = document.getElementById('category');
    if (categoryFilter) {
        categoryFilter.addEventListener('change', function() {
            // Submit the form when category changes
            this.form.submit();
        });
    }

    // Initialize dropdown menus if they exist
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const menu = this.nextElementSibling;
            if (menu) {
                menu.classList.toggle('show');
            }
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            const openMenus = document.querySelectorAll('.dropdown-menu.show');
            openMenus.forEach(menu => {
                menu.classList.remove('show');
            });
        });
    });

    // Initialize delete confirmation
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
            }
        });
    });
});

/**
 * Initialize date range pickers
 */
function initializeDateRanges() {
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    if (dateFrom && dateTo) {
        // When date_from changes, update min date for date_to
        dateFrom.addEventListener('change', function() {
            if (this.value) {
                dateTo.min = this.value;
                
                // If date_to is earlier than date_from, update it
                if (dateTo.value && dateTo.value < this.value) {
                    dateTo.value = this.value;
                }
            }
        });
        
        // When date_to changes, update max date for date_from
        dateTo.addEventListener('change', function() {
            if (this.value) {
                dateFrom.max = this.value;
                
                // If date_from is later than date_to, update it
                if (dateFrom.value && dateFrom.value > this.value) {
                    dateFrom.value = this.value;
                }
            }
        });
        
        // Set initial min/max values
        if (dateFrom.value) {
            dateTo.min = dateFrom.value;
        }
        
        if (dateTo.value) {
            dateFrom.max = dateTo.value;
        }
    }
    
    // Add quick date presets if we have the quick-dates element
    const quickDates = document.querySelector('.quick-dates');
    if (quickDates && dateFrom && dateTo) {
        // Current month
        const currentMonthBtn = quickDates.querySelector('.current-month');
        if (currentMonthBtn) {
            currentMonthBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const now = new Date();
                const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
                const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                
                dateFrom.value = formatDate(firstDay);
                dateTo.value = formatDate(lastDay);
                
                // Trigger change events
                dateFrom.dispatchEvent(new Event('change'));
                dateTo.dispatchEvent(new Event('change'));
            });
        }
        
        // Previous month
        const prevMonthBtn = quickDates.querySelector('.prev-month');
        if (prevMonthBtn) {
            prevMonthBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const now = new Date();
                const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                const lastDay = new Date(now.getFullYear(), now.getMonth(), 0);
                
                dateFrom.value = formatDate(firstDay);
                dateTo.value = formatDate(lastDay);
                
                // Trigger change events
                dateFrom.dispatchEvent(new Event('change'));
                dateTo.dispatchEvent(new Event('change'));
            });
        }
        
        // Last 30 days
        const last30DaysBtn = quickDates.querySelector('.last-30-days');
        if (last30DaysBtn) {
            last30DaysBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const now = new Date();
                const thirtyDaysAgo = new Date();
                thirtyDaysAgo.setDate(now.getDate() - 30);
                
                dateFrom.value = formatDate(thirtyDaysAgo);
                dateTo.value = formatDate(now);
                
                // Trigger change events
                dateFrom.dispatchEvent(new Event('change'));
                dateTo.dispatchEvent(new Event('change'));
            });
        }
        
        // Current year
        const currentYearBtn = quickDates.querySelector('.current-year');
        if (currentYearBtn) {
            currentYearBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const now = new Date();
                const firstDay = new Date(now.getFullYear(), 0, 1);
                const lastDay = new Date(now.getFullYear(), 11, 31);
                
                dateFrom.value = formatDate(firstDay);
                dateTo.value = formatDate(lastDay);
                
                // Trigger change events
                dateFrom.dispatchEvent(new Event('change'));
                dateTo.dispatchEvent(new Event('change'));
            });
        }
    }
}

/**
 * Format date to YYYY-MM-DD
 * 
 * @param {Date} date Date object
 * @returns {string} Formatted date string
 */
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    
    return `${year}-${month}-${day}`;
}