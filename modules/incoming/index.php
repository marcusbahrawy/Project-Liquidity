<?php
/**
 * Incoming Transactions View - Updated with Recurring Transaction Support
 */

// Include database connection
require_once '../../config/database.php';

// Include helper functions
require_once '../../includes/functions.php';

// Get filter parameters - keeping only search, sort and order
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';
// Fixed to check if recurring is empty string
$is_recurring = (isset($_GET['recurring']) && $_GET['recurring'] !== '') ? (int)$_GET['recurring'] : null;
// Add archive filter
$show_archive = isset($_GET['archive']) && $_GET['archive'] === '1';

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
        WHERE i.parent_id IS NULL 
        AND (i.description LIKE ? OR i.notes LIKE ?)
    ";
    
    $params = ["%{$search}%", "%{$search}%"];
    
    // Add archive filter
    if ($show_archive) {
        $query .= " AND i.date < CURRENT_DATE AND i.is_fixed = 0";
    } else {
        $query .= " AND (i.date >= CURRENT_DATE OR i.is_fixed = 1)";
    }
    
    // Add order by clause
    $query .= " ORDER BY i.{$sort} {$order}";
    
    // Execute with positional parameters (more reliable)
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
} else {
    // Query without search
    $query = "
        SELECT i.*, c.name as category_name, c.color as category_color
        FROM incoming i
        LEFT JOIN categories c ON i.category_id = c.id
        WHERE i.parent_id IS NULL
    ";
    
    $params = [];
    
    // Add archive filter
    if ($show_archive) {
        $query .= " AND i.date < CURRENT_DATE AND i.is_fixed = 0";
    } else {
        $query .= " AND (i.date >= CURRENT_DATE OR i.is_fixed = 1)";
    }
    
    // Add order by clause
    $query .= " ORDER BY i.{$sort} {$order}";
    
    // Execute with parameters if needed
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
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

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filters-form" class="filters-form" method="GET">
            <div class="form-row">
                <div class="form-group col-md-8">
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
                
                <div class="form-group col-md-4">
                    <label for="recurring">Transaction Type</label>
                    <select id="recurring" name="recurring" class="form-select" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="0" <?php echo isset($is_recurring) && $is_recurring === 0 ? 'selected' : ''; ?>>One-time Income</option>
                        <option value="1" <?php echo isset($is_recurring) && $is_recurring === 1 ? 'selected' : ''; ?>>Recurring Income</option>
                    </select>
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
            <div class="btn-group mr-2">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['archive' => '0'])); ?>" class="btn btn-light btn-sm <?php echo !$show_archive ? 'active' : ''; ?>">
                    <i class="fas fa-clock"></i> Upcoming
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['archive' => '1'])); ?>" class="btn btn-light btn-sm <?php echo $show_archive ? 'active' : ''; ?>">
                    <i class="fas fa-archive"></i> Archive
                </a>
            </div>
            <div class="dropdown">
                <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="exportDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-download"></i> Export
                </button>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="exportDropdown">
                    <a class="dropdown-item" href="api.php?action=export&format=csv<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo isset($is_recurring) ? '&is_fixed=' . $is_recurring : ''; ?>">
                        <i class="fas fa-file-csv"></i> Export as CSV
                    </a>
                    <a class="dropdown-item" href="api.php?action=export&format=pdf<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo isset($is_recurring) ? '&is_fixed=' . $is_recurring : ''; ?>">
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
                        <a href="?sort=date&order=<?php echo ($sort === 'date' && $order === 'DESC') ? 'asc' : 'desc'; echo buildQueryParams(['recurring', 'search']); ?>">
                            Date
                            <?php if ($sort === 'date'): ?>
                                <i class="fas fa-sort-<?php echo ($order === 'DESC') ? 'down' : 'up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="?sort=description&order=<?php echo ($sort === 'description' && $order === 'DESC') ? 'asc' : 'desc'; echo buildQueryParams(['recurring', 'search']); ?>">
                            Description
                            <?php if ($sort === 'description'): ?>
                                <i class="fas fa-sort-<?php echo ($order === 'DESC') ? 'down' : 'up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Category</th>
                    <th>
                        <a href="?sort=amount&order=<?php echo ($sort === 'amount' && $order === 'DESC') ? 'asc' : 'desc'; echo buildQueryParams(['recurring', 'search']); ?>">
                            Amount
                            <?php if ($sort === 'amount'): ?>
                                <i class="fas fa-sort-<?php echo ($order === 'DESC') ? 'down' : 'up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Recurrence</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="7" class="text-center">No incoming transactions found.</td>
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
                                <?php if ($transaction['is_fixed'] && $transaction['repeat_interval'] !== 'none'): ?>
                                    <span class="badge badge-recurring">Recurring</span>
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
                                <?php if ($transaction['is_fixed'] && $transaction['repeat_interval'] !== 'none'): ?>
                                    <span class="badge badge-warning">
                                        <?php 
                                        switch ($transaction['repeat_interval']) {
                                            case 'daily':
                                                echo 'Daily';
                                                break;
                                            case 'weekly':
                                                echo 'Weekly';
                                                break;
                                            case 'monthly':
                                                echo 'Monthly';
                                                break;
                                            case 'quarterly':
                                                echo 'Quarterly';
                                                break;
                                            case 'yearly':
                                                echo 'Yearly';
                                                break;
                                            default:
                                                echo 'None';
                                        }
                                        ?>
                                    </span>
                                    <?php if ($transaction['repeat_until']): ?>
                                        <small class="d-block text-muted">
                                            Until <?php echo date('M d, Y', strtotime($transaction['repeat_until'])); ?>
                                        </small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">One-time</span>
                                <?php endif; ?>
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
                                    <td>-</td>
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
                    <th colspan="3"></th>
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

.col-md-8 {
    flex: 0 0 66.666667%;
    max-width: 66.666667%;
}

.col-md-4 {
    flex: 0 0 33.333333%;
    max-width: 33.333333%;
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

.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.badge-info {
    background-color: rgba(52, 152, 219, 0.2);
    color: var(--primary-dark);
}

.badge-warning {
    background-color: rgba(243, 156, 18, 0.2);
    color: #d35400;
}

.badge-recurring {
    background-color: rgba(142, 68, 173, 0.2);
    color: #8e44ad;
}

.d-block {
    display: block;
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

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
    }
    
    .col-md-8, .col-md-4 {
        flex: 0 0 100%;
        max-width: 100%;
    }
}

/* Button group styles */
.btn-group {
    display: inline-flex;
    vertical-align: middle;
}

.btn-group .btn {
    position: relative;
    flex: 1 1 auto;
    border-radius: 0;
}

.btn-group .btn:first-child {
    border-top-left-radius: 0.25rem;
    border-bottom-left-radius: 0.25rem;
}

.btn-group .btn:last-child {
    border-top-right-radius: 0.25rem;
    border-bottom-right-radius: 0.25rem;
}

.btn-group .btn:not(:first-child) {
    margin-left: -1px;
}

.btn-group .btn.active {
    background-color: var(--gray-light);
    color: var(--dark);
}

.mr-2 {
    margin-right: 0.5rem;
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
