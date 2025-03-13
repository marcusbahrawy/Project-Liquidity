<?php
/**
 * Categories Management
 */

// Include database connection
require_once '../../config/database.php';

// Include helper functions
require_once '../../includes/functions.php';

// Get filter parameters
$type = isset($_GET['type']) ? $_GET['type'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Build the query
$query = "SELECT * FROM categories WHERE 1=1";
$params = [];

// Apply filters
if ($type) {
    $query .= " AND type = :type";
    $params['type'] = $type;
}

if ($search) {
    $query .= " AND (name LIKE :search)";
    $params['search'] = "%{$search}%";
}

// Apply sorting
$query .= " ORDER BY {$sort} {$order}";

// Get categories
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$categories = $stmt->fetchAll();

// Include header
require_once '../../includes/header.php';
?>

<!-- Categories Module Content -->
<div class="module-header">
    <div class="module-title">
        <h1>Categories Management</h1>
        <p>Create, edit, and delete transaction categories</p>
    </div>
    <div class="module-actions">
        <button type="button" class="btn btn-primary" id="addCategoryBtn">
            <i class="fas fa-plus"></i> Add New Category
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filters-form" class="filters-form" method="GET">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="type">Category Type</label>
                    <select id="type" name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="incoming" <?php echo ($type === 'incoming') ? 'selected' : ''; ?>>Incoming</option>
                        <option value="outgoing" <?php echo ($type === 'outgoing') ? 'selected' : ''; ?>>Outgoing</option>
                        <option value="both" <?php echo ($type === 'both') ? 'selected' : ''; ?>>Both</option>
                    </select>
                </div>
                
                <div class="form-group col-md-6">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Search category name..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                </div>
            </div>
            
            <div class="filters-actions">
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                <input type="hidden" name="order" value="<?php echo htmlspecialchars($order); ?>">
                
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                
                <a href="index.php" class="btn btn-light btn-sm">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Categories Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Categories</div>
    </div>
    
    <div class="table-container">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>
                        <a href="?sort=name&order=<?php echo ($sort === 'name' && $order === 'DESC') ? 'asc' : 'desc'; echo buildQueryParams(['type', 'search']); ?>">
                            Name
                            <?php if ($sort === 'name'): ?>
                                <i class="fas fa-sort-<?php echo ($order === 'DESC') ? 'down' : 'up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="?sort=type&order=<?php echo ($sort === 'type' && $order === 'DESC') ? 'asc' : 'desc'; echo buildQueryParams(['type', 'search']); ?>">
                            Type
                            <?php if ($sort === 'type'): ?>
                                <i class="fas fa-sort-<?php echo ($order === 'DESC') ? 'down' : 'up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Color</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="4" class="text-center">No categories found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($category['name']); ?></td>
                            <td>
                                <?php 
                                    $typeText = ucfirst($category['type']);
                                    $typeClass = '';
                                    
                                    if ($category['type'] === 'incoming') {
                                        $typeClass = 'badge-success';
                                    } elseif ($category['type'] === 'outgoing') {
                                        $typeClass = 'badge-danger';
                                    } else {
                                        $typeClass = 'badge-info';
                                    }
                                ?>
                                <span class="badge <?php echo $typeClass; ?>"><?php echo $typeText; ?></span>
                            </td>
                            <td>
                                <div class="color-sample" style="background-color: <?php echo htmlspecialchars($category['color']); ?>"></div>
                                <span class="color-code"><?php echo htmlspecialchars($category['color']); ?></span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button type="button" class="btn btn-sm btn-primary edit-category" 
                                            data-id="<?php echo $category['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                            data-type="<?php echo $category['type']; ?>"
                                            data-color="<?php echo htmlspecialchars($category['color']); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button type="button" class="btn btn-sm btn-danger delete-category" 
                                            data-id="<?php echo $category['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($category['name']); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Category Modal -->
<div class="modal" id="categoryModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalTitle">Add New Category</h5>
                <button type="button" class="modal-close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="categoryForm">
                    <input type="hidden" id="category_id" name="id" value="">
                    
                    <div class="form-group">
                        <label for="name">Category Name *</label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="type">Category Type *</label>
                        <select id="type" name="type" class="form-select" required>
                            <option value="incoming">Incoming</option>
                            <option value="outgoing">Outgoing</option>
                            <option value="both">Both</option>
                        </select>
                        <small class="form-text text-muted">
                            "Incoming" for income categories, "Outgoing" for expense categories, or "Both" for categories used in both.
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="color">Color *</label>
                        <div class="color-picker">
                            <input type="color" id="color" name="color" class="form-control" value="#3498db" required>
                            <div class="color-preview" style="background-color: #3498db;"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveCategoryBtn">Save Category</button>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS for this module -->
<style>
.color-sample {
    display: inline-block;
    width: 24px;
    height: 24px;
    border-radius: 4px;
    margin-right: 10px;
    vertical-align: middle;
}

.color-code {
    font-family: monospace;
    color: var(--gray);
}

.color-picker {
    display: flex;
    align-items: center;
}

.color-picker input[type="color"] {
    width: 60px;
    height: 40px;
    padding: 0;
    margin-right: 10px;
    border: 1px solid var(--gray-light);
    cursor: pointer;
}

.color-preview {
    width: 40px;
    height: 40px;
    border-radius: 6px;
    border: 1px solid var(--gray-light);
    cursor: pointer;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    overflow: auto;
}

.modal.show {
    display: block;
}

.modal-dialog {
    position: relative;
    width: auto;
    margin: 1.75rem auto;
    max-width: 500px;
}

.modal-content {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    background-color: #fff;
    border-radius: 0.3rem;
    outline: 0;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    border-bottom: 1px solid var(--gray-light);
}

.modal-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.modal-close {
    background: transparent;
    border: 0;
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
    color: var(--gray);
    cursor: pointer;
}

.modal-body {
    position: relative;
    flex: 1 1 auto;
    padding: 1rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    padding: 1rem;
    border-top: 1px solid var(--gray-light);
    gap: 0.5rem;
}
</style>

<!-- Custom JavaScript for Category Management -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // DOM elements
    const modal = document.getElementById('categoryModal');
    const categoryForm = document.getElementById('categoryForm');
    const categoryModalTitle = document.getElementById('categoryModalTitle');
    const addCategoryBtn = document.getElementById('addCategoryBtn');
    const saveCategoryBtn = document.getElementById('saveCategoryBtn');
    const colorPicker = document.getElementById('color');
    const colorPreview = document.querySelector('.color-preview');
    
    // Open modal for adding a new category
    addCategoryBtn.addEventListener('click', function() {
        categoryModalTitle.textContent = 'Add New Category';
        categoryForm.reset();
        categoryForm.querySelector('#category_id').value = '';
        showModal();
    });
    
    // Update color preview when color is selected
    colorPicker.addEventListener('input', function() {
        colorPreview.style.backgroundColor = this.value;
    });
    
    // Click on color preview to open color picker
    colorPreview.addEventListener('click', function() {
        colorPicker.click();
    });
    
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
            
            if (confirm(`Are you sure you want to delete the category "${name}"?`)) {
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
    saveCategoryBtn.addEventListener('click', function() {
        if (!categoryForm.checkValidity()) {
            alert('Please fill out all required fields');
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
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    
    // Hide modal function
    function hideModal() {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
});
</script>

<?php
// Include footer
require_once '../../includes/footer.php';
?>