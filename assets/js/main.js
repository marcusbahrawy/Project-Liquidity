/**
 * Main JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize mobile menu toggle
    initializeMobileMenu();
    
    // Initialize form validation
    initializeFormValidation();
    
    // Initialize datepickers
    initializeDatepickers();
    
    // Initialize category color pickers
    initializeColorPickers();
    
    // Handle AJAX forms
    handleAjaxForms();
    
    // Handle delete confirmations
    handleDeleteConfirmations();
});

/**
 * Initialize Bootstrap-like tooltips
 */
function initializeTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    
    tooltips.forEach(element => {
        element.addEventListener('mouseover', function() {
            const tooltipText = this.getAttribute('data-tooltip');
            
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.innerHTML = tooltipText;
            
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            const tooltipRect = tooltip.getBoundingClientRect();
            
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltipRect.width / 2) + 'px';
            tooltip.style.top = rect.top - tooltipRect.height - 10 + 'px';
            
            this.addEventListener('mouseout', function() {
                tooltip.remove();
            });
        });
    });
}

/**
 * Initialize mobile menu toggle
 */
function initializeMobileMenu() {
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('sidebar-mobile-open');
        });
    }
}

/**
 * Initialize form validation
 */
function initializeFormValidation() {
    const forms = document.querySelectorAll('form.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        });
    });
}

/**
 * Initialize datepickers
 */
function initializeDatepickers() {
    const datepickers = document.querySelectorAll('.datepicker');
    
    datepickers.forEach(input => {
        // Simple date input enhancements
        input.type = 'date';
        
        // Set default value to today if empty
        if (!input.value) {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            input.value = `${year}-${month}-${day}`;
        }
    });
}

/**
 * Initialize category color pickers
 */
function initializeColorPickers() {
    const colorPickers = document.querySelectorAll('.color-picker');
    
    colorPickers.forEach(picker => {
        const input = picker.querySelector('input[type="color"]');
        const preview = picker.querySelector('.color-preview');
        
        if (input && preview) {
            // Update preview on load
            preview.style.backgroundColor = input.value;
            
            // Update preview on change
            input.addEventListener('input', function() {
                preview.style.backgroundColor = this.value;
            });
            
            // Open color picker on preview click
            preview.addEventListener('click', function() {
                input.click();
            });
        }
    });
}

/**
 * Handle AJAX forms
 */
function handleAjaxForms() {
    const ajaxForms = document.querySelectorAll('form.ajax-form');
    
    ajaxForms.forEach(form => {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            
            // Get form data
            const formData = new FormData(form);
            
            // Get submit URL
            const url = form.getAttribute('action');
            
            // Show loading state
            const submitBtn = form.querySelector('[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
            
            // Send AJAX request
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Reset button
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
                
                // Handle response
                if (data.success) {
                    // Show success message
                    showNotification(data.message || 'Operation completed successfully', 'success');
                    
                    // Reset form if needed
                    if (data.resetForm) {
                        form.reset();
                    }
                    
                    // Redirect if needed
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1000);
                    }
                    
                    // Refresh page if needed
                    if (data.refresh) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                } else {
                    // Show error message
                    showNotification(data.message || 'An error occurred', 'error');
                }
            })
            .catch(error => {
                // Reset button
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
                
                // Show error message
                showNotification('An error occurred while processing your request', 'error');
                console.error('Error:', error);
            });
        });
    });
}

/**
 * Handle delete confirmations
 */
function handleDeleteConfirmations() {
    const deleteButtons = document.querySelectorAll('.delete-btn');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault();
            
            const url = this.getAttribute('href');
            const itemName = this.getAttribute('data-name') || 'this item';
            
            if (confirm(`Are you sure you want to delete ${itemName}? This action cannot be undone.`)) {
                window.location.href = url;
            }
        });
    });
}

/**
 * Show notification
 * 
 * @param {string} message Notification message
 * @param {string} type Notification type (success, error, warning, info)
 */
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    // Set icon based on type
    let icon = 'info-circle';
    if (type === 'success') icon = 'check-circle';
    if (type === 'error') icon = 'exclamation-circle';
    if (type === 'warning') icon = 'exclamation-triangle';
    
    // Set notification content
    notification.innerHTML = `
        <div class="notification-icon">
            <i class="fas fa-${icon}"></i>
        </div>
        <div class="notification-content">${message}</div>
        <div class="notification-close">
            <i class="fas fa-times"></i>
        </div>
    `;
    
    // Add to notifications container (create if not exists)
    let container = document.querySelector('.notifications-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'notifications-container';
        document.body.appendChild(container);
    }
    
    container.appendChild(notification);
    
    // Add animation class after a small delay (for animation)
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Close button event
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.addEventListener('click', () => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    });
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }
    }, 5000);
}

/**
 * Format number as currency
 * 
 * @param {number} amount Amount to format
 * @param {string} currency Currency code (default: NOK)
 * @returns {string} Formatted currency string
 */
function formatCurrency(amount, currency = 'NOK') {
    return new Intl.NumberFormat('no-NO', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2
    }).format(amount);
}

/**
 * Format date
 * 
 * @param {string|Date} date Date to format
 * @param {string} format Format string (default: 'short')
 * @returns {string} Formatted date string
 */
function formatDate(date, format = 'short') {
    const dateObj = (typeof date === 'string') ? new Date(date) : date;
    
    if (format === 'short') {
        return dateObj.toLocaleDateString('no-NO');
    } else if (format === 'long') {
        return dateObj.toLocaleDateString('no-NO', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    } else if (format === 'datetime') {
        return dateObj.toLocaleDateString('no-NO', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    return dateObj.toLocaleDateString('no-NO');
}

document.addEventListener('DOMContentLoaded', function() {
    // Find all delete buttons
    const deleteButtons = document.querySelectorAll('.delete-btn');
    
    deleteButtons.forEach(button => {
        // Remove existing click handlers to avoid conflicts
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);
        
        // Add our click handler
        newButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get item name for the confirmation message
            const itemName = this.getAttribute('data-name') || 'this item';
            const deleteUrl = this.getAttribute('href');
            
            // Show confirmation dialog
            if (confirm(`Are you sure you want to delete ${itemName}? This action cannot be undone.`)) {
                // Redirect to the delete URL
                window.location.href = deleteUrl;
            }
        });
    });
    
    // Add support for the direct API calls using fetch
    const apiDeleteLinks = document.querySelectorAll('[data-action="delete"]');
    apiDeleteLinks.forEach(link => {
        // Remove existing click handlers
        const newLink = link.cloneNode(true);
        link.parentNode.replaceChild(newLink, link);
        
        // Add our click handler
        newLink.addEventListener('click', function(e) {
            e.preventDefault();
            
            const itemName = this.getAttribute('data-name') || 'this item';
            const deleteUrl = this.getAttribute('href') || this.getAttribute('data-url');
            
            if (!deleteUrl) {
                console.error('Delete URL not found');
                return;
            }
            
            if (confirm(`Are you sure you want to delete ${itemName}? This action cannot be undone.`)) {
                // Use fetch API for AJAX deletion
                fetch(deleteUrl, {
                    method: 'GET', // Change to POST if your API expects POST
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // If redirect URL is provided, go there
                        if (data.data && data.data.redirect) {
                            window.location.href = data.data.redirect;
                        } else {
                            // Otherwise reload the page
                            window.location.reload();
                        }
                    } else {
                        alert('Error: ' + (data.message || 'Failed to delete item'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        });
    });
});