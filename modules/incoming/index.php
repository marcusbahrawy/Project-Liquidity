<?php
/**
 * Incoming Transactions View - Simplified with Robust Search Fix and Split Date Display
 */

// Include database connection
require_once '../../config/database.php';

// Include helper functions
require_once '../../includes/functions.php';

// Get filter parameters - keeping only search, sort and order
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Initialize transactions array
$transactions = [];
$total_amount = 0;

// Build the query
if (!empty($search)) {
    // Query with search
    $query = "
        SELECT i.*, c.name as category_name, c.color as category_color
        FROM incoming i
        LEFT JOIN categories c ON i.category_id = c.id
        WHERE i.parent_id IS NULL AND (i.description LIKE ? OR i.notes LIKE ?)
        ORDER BY i.{$sort} {$order}
    ";
    
    // Execute with positional parameters (more reliable)
    $stmt = $pdo->prepare($query);
    $searchParam = "%{$search}%";
    $stmt->execute([$searchParam, $searchParam]);
    $transactions = $stmt->fetchAll();
} else {
    // Query without search
    $query = "
        SELECT i.*, c.name as category_name, c.color as category_color
        FROM incoming i
        LEFT JOIN categories c ON i.category_id = c.id
        WHERE i.parent_id IS NULL
        ORDER BY i.{$sort} {$order}
    ";
    
    // Execute without parameters
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $transactions = $stmt->fetchAll();
}

// Calculate total from fetched transactions
if (!empty($transactions)) {
    foreach ($transactions as $transaction) {
        $total_amount += $transaction['amount'];
    }
}

// Include header
require_once '../../includes/header.php';
?>

<!-- Incoming Module Content -->
<div class="module-header">
    <div class="module-title">
        <h1>Incoming Transactions</h1>
        <p>Manage your income and revenue sources</p>
    </div>
    <div class="module-actions">
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Income
        </a>
    </div>
</div>

<!-- Simplified Filters - Only Search -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filters-form" class="filters-form" method="GET">
            <div class="form-row">
                <div class="form-group col-md-12">
                    <label for="search">Search</label>
                    <div class="search-container">
                        <input type="text" id="search" name="search" class="form-control" placeholder="Search description or notes..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <?php if ($search): ?>
                            <a href="index.php" class="btn btn-light">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
            <input type="hidden" name="order" value="<?php echo htmlspecialchars($order); ?>">
        </form>
    </div>
</div>

<!-- Transactions Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Incoming Transactions</div>
        <div class="card-actions">
            <div class="dropdown">
                <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="exportDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-download"></i> Export
                </button>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="exportDropdown">
                    <a class="dropdown-item" href="api.php?action=export&format=csv<?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                        <i class="fas fa-file-csv"></i> Export as CSV
                    </a>
                    <a class="dropdown-item" href="api.php?action=export&format=pdf<?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                        <i class="fas fa-file-pdf"></i> Export as PDF
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="table-container">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>
                        <a href="?sort=date&order=<?php echo ($sort === 'date' && $order === 'DESC') ? 'asc' : 'desc'; echo buildQueryParams(['type', 'search']); ?>">
                            Date
                            <?php if ($sort === 'date'): ?>
                                <i class="fas fa-sort-<?php echo ($order === 'DESC') ? 'down' : 'up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="?sort=description&order=<?php echo ($sort === 'description' && $order === 'DESC') ? 'asc' : 'desc'; echo buildQueryParams(['type', 'search']); ?>">
                            Description
                            <?php if ($sort === 'description'): ?>
                                <i class="fas fa-sort-<?php echo ($order === 'DESC') ? 'down' : 'up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Category</th>
                    <th>
                        <a href="?sort=amount&order=<?php echo ($sort === 'amount' && $order === 'DESC') ? 'asc' : 'desc'; echo buildQueryParams(['type', 'search']); ?>">
                            Amount
                            <?php if ($sort === 'amount'): ?>
                                <i class="fas fa-sort-<?php echo ($order === 'DESC') ? 'down' : 'up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="6" class="text-center">No incoming transactions found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr<?php echo $transaction['is_split'] ? ' class="has-splits"' : ''; ?>>
                            <td><?php echo date('M d, Y', strtotime($transaction['date'])); ?></td>
                            <td>
                                <?php echo htmlspecialchars($transaction['description']); ?>
                                <?php if ($transaction['is_split']): ?>
                                    <span class="badge badge-info">Split</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($transaction['category_name']): ?>
                                    <span class="category-badge" style="background-color: <?php echo htmlspecialchars($transaction['category_color']); ?>">
                                        <?php echo htmlspecialchars($transaction['category_name']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Uncategorized</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right amount-positive">
                                <?php echo number_format($transaction['amount'], 2); ?> kr
                            </td>
                            <td>
                                <?php if (!empty($transaction['notes'])): ?>
                                    <?php 
                                    $notes = htmlspecialchars($transaction['notes']);
                                    echo (strlen($notes) > 50) ? substr($notes, 0, 50) . '...' : $notes; 
                                    ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <?php if ($transaction['is_split']): ?>
                                        <a href="view_splits.php?id=<?php echo $transaction['id']; ?>" class="btn btn-sm btn-info" data-tooltip="View Splits">
                                            <i class="fas fa-sitemap"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="edit.php?id=<?php echo $transaction['id']; ?>" class="btn btn-sm btn-primary" data-tooltip="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <a href="api.php?action=delete&id=<?php echo $transaction['id']; ?>" class="btn btn-sm btn-danger delete-btn" data-name="<?php echo htmlspecialchars($transaction['description']); ?>" data-tooltip="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        
                        <?php if ($transaction['is_split']): ?>
                            <?php
                            // Get split transactions using positional parameters
                            $stmt = $pdo->prepare("
                                SELECT i.*, c.name as category_name, c.color as category_color
                                FROM incoming i
                                LEFT JOIN categories c ON i.category_id = c.id
                                WHERE i.parent_id = ?
                                ORDER BY i.amount DESC
                            ");
                            $stmt->execute([$transaction['id']]);
                            $splits = $stmt->fetchAll();
                            ?>
                            
                            <?php foreach ($splits as $split): ?>
                                <tr class="split-row">
                                    <td><?php echo date('M d, Y', strtotime($split['date'])); ?></td>
                                    <td class="split-item">
                                        <i class="fas fa-level-down-alt"></i>
                                        <?php echo htmlspecialchars($split['description']); ?>
                                    </td>
                                    <td>
                                        <?php if ($split['category_name']): ?>
                                            <span class="category-badge" style="background-color: <?php echo htmlspecialchars($split['category_color']); ?>">
                                                <?php echo htmlspecialchars($split['category_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Uncategorized</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right amount-positive">
                                        <?php echo number_format($split['amount'], 2); ?> kr
                                    </td>
                                    <td>
                                        <?php if (!empty($split['notes'])): ?>
                                            <?php 
                                            $notes = htmlspecialchars($split['notes']);
                                            echo (strlen($notes) > 50) ? substr($notes, 0, 50) . '...' : $notes; 
                                            ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="edit.php?id=<?php echo $split['id']; ?>" class="btn btn-sm btn-primary" data-tooltip="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3">Total</th>
                    <th class="text-right amount-positive"><?php echo number_format($total_amount, 2); ?> kr</th>
                    <th colspan="2"></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Custom CSS for this module -->
<style>
.module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.module-title h1 {
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 5px;
    color: var(--dark);
}

.module-title p {
    color: var(--gray);
    margin: 0;
}

.filters-form {
    margin-bottom: 0;
}

.search-container {
    display: flex;
    gap: 10px;
}

.search-container .form-control {
    flex-grow: 1;
}

.form-row {
    display: flex;
    flex-wrap: wrap;
    margin-right: -10px;
    margin-left: -10px;
}

.form-group {
    padding-right: 10px;
    padding-left: 10px;
    margin-bottom: 15px;
}

.col-md-12 {
    flex: 0 0 100%;
    max-width: 100%;
}

.category-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    color: white;
}

.amount-positive {
    color: var(--success);
    font-weight: 500;
}

.has-splits {
    border-bottom: none !important;
}

.split-row {
    background-color: rgba(236, 240, 241, 0.5) !important;
}

.split-item {
    padding-left: 10px;
}

.split-item i {
    margin-right: 5px;
    color: var(--gray);
}

/* Dropdown styles */
.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-toggle::after {
    display: inline-block;
    margin-left: 5px;
    vertical-align: middle;
    content: "";
    border-top: 5px solid;
    border-right: 5px solid transparent;
    border-bottom: 0;
    border-left: 5px solid transparent;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    z-index: 1000;
    display: none;
    min-width: 10rem;
    padding: 0.5rem 0;
    margin: 0.125rem 0 0;
    font-size: 0.875rem;
    color: var(--dark);
    text-align: left;
    list-style: none;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid rgba(0, 0, 0, 0.15);
    border-radius: 0.25rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.175);
}

.dropdown-menu.show {
    display: block;
}

.dropdown-item {
    display: block;
    width: 100%;
    padding: 0.5rem 1.5rem;
    clear: both;
    font-weight: 400;
    color: var(--dark);
    text-align: inherit;
    white-space: nowrap;
    background-color: transparent;
    border: 0;
}

.dropdown-item:hover, .dropdown-item:focus {
    color: var(--dark);
    text-decoration: none;
    background-color: var(--gray-light);
}

.dropdown-item i {
    margin-right: 8px;
}
</style>

<script>
// Dropdown toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const dropdownToggle = document.querySelector('.dropdown-toggle');
    const dropdownMenu = document.querySelector('.dropdown-menu');
    
    if (dropdownToggle && dropdownMenu) {
        dropdownToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });
        
        document.addEventListener('click', function() {
            dropdownMenu.classList.remove('show');
        });
    }
});
</script>

<?php
// Include footer
require_once '../../includes/footer.php';
?>