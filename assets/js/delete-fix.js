/**
 * Delete Functionality Fix
 * This script fixes deletion across all modules
 */

document.addEventListener('DOMContentLoaded', function() {
    // Find all delete buttons across the application
    const deleteButtons = document.querySelectorAll('.delete-btn, [data-action="delete"]');
    
    deleteButtons.forEach(button => {
        // Remove any existing event listeners to avoid duplicates
        button.replaceWith(button.cloneNode(true));
        
        // Get the fresh reference after cloning
        const newButton = button.parentNode.lastChild;
        
        // Add click event listener
        newButton.addEventListener('click', function(event) {
            // Prevent the default link behavior
            event.preventDefault();
            
            // Get the item name for confirmation message
            const itemName = this.getAttribute('data-name') || 'this item';
            
            // Ask for confirmation
            if (confirm(`Are you sure you want to delete ${itemName}? This action cannot be undone.`)) {
                // Get the delete URL
                const deleteUrl = this.getAttribute('href');
                
                // If no URL, show error
                if (!deleteUrl) {
                    alert('Error: Delete URL not found.');
                    return;
                }
                
                // Create a form to submit the delete request as POST
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = deleteUrl;
                form.style.display = 'none';
                
                // Add CSRF token if exists
                const csrfToken = document.querySelector('meta[name="csrf-token"]');
                if (csrfToken) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'csrf_token';
                    input.value = csrfToken.getAttribute('content');
                    form.appendChild(input);
                }
                
                // Add to document and submit
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
    
    console.log('Delete functionality enhanced for', deleteButtons.length, 'buttons');
});