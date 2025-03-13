/**
 * Categories JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // DOM elements
    const modal = document.getElementById('categoryModal');
    const categoryForm = document.getElementById('categoryForm');
    const categoryModalTitle = document.getElementById('categoryModalTitle');
    const addCategoryBtn = document.getElementById('addCategoryBtn');
    const saveCategoryBtn = document.getElementById('saveCategoryBtn');
    const colorPicker = document.getElementById('color');
    const colorPreview = document.querySelector('.color-preview');
    
    // Initialize category filter
    const categoryTypeFilter = document.getElementById('type');
    if (categoryTypeFilter) {
        categoryTypeFilter.addEventListener('change', function() {
            // Submit the form when type changes
            this.form.submit();
        });
    }
    
    // Open modal for adding a new category
    if (addCategoryBtn) {
        addCategoryBtn.addEventListener('click', function() {
            categoryModalTitle.textContent = 'Add New Category';
            categoryForm.reset();
            categoryForm.querySelector('#category_id').value = '';
            // Set default color
            colorPreview.style.backgroundColor = colorPicker.value;
            showModal();
        });
    }
    
    // Update color preview when color is selected
    if (colorPicker && colorPreview) {
        colorPicker.addEventListener('input', function() {
            colorPreview.style.backgroundColor = this.value;
        });
        
        // Click on color preview to open color picker
        colorPreview.addEventListener('click', function() {
            colorPicker.click();
        });
    }
    
    // Handle editing a category
    document.querySelectorAll('.edit-category').forEach(button => {
        button.addEventListener('click', function() {
            // Get category data from data attributes
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const type = this.getAttribute('data-type');
            const color = this.getAttribute('data-color');
            
            // Set modal title and form values
            categoryModalTitle.textContent = 'Edit Category';
            categoryForm.querySelector('#category_id').value = id;
            categoryForm.querySelector('#name').value = name;
            categoryForm.querySelector('#type').value = type;
            categoryForm.querySelector('#color').value = color;
            colorPreview.style.backgroundColor = color;
            
            showModal();
        });
    });
    
    // Handle deleting a category
    document.querySelectorAll('.delete-category').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            
            if (confirm(`Are you sure you want to delete the category "${name}"? This will remove the category from any transactions using it.`)) {
                // Send DELETE request to API
                fetch(`api.php?action=delete&id=${id}`, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload the page to show updated list
                        alert('Category deleted successfully');
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the category');
                });
            }
        });
    });
    
    // Save category (add or update)
    if (saveCategoryBtn) {
        saveCategoryBtn.addEventListener('click', function() {
            if (!categoryForm.checkValidity()) {
                // Highlight required fields
                categoryForm.classList.add('was-validated');
                return;
            }
            
            // Get form data
            const formData = new FormData(categoryForm);
            const id = formData.get('id');
            
            // Determine action (add or update)
            const action = id ? 'update' : 'add';
            
            // Send request to API
            fetch(`api.php?action=${action}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload the page to show updated list
                    alert('Category saved successfully');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the category');
            });
        });
    }
    
    // Close modal functions
    document.querySelectorAll('[data-dismiss="modal"]').forEach(button => {
        button.addEventListener('click', hideModal);
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            hideModal();
        }
    });
    
    // Show modal function
    function showModal() {
        if (!modal) return;
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    // Hide modal function
    function hideModal() {
        if (!modal) return;
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
    
    // Initialize validation
    categoryForm.addEventListener('submit', function(event) {
        event.preventDefault();
        saveCategoryBtn.click();
    });
});