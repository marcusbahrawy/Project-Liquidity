<?php
/**
 * Debt Management View - Simplified
 */

// Include database connection
require_once '../../config/database.php';

// Include helper functions
require_once '../../includes/functions.php';

// Get filter parameters - keeping only search, sort and order
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'remaining_amount';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Initialize debts array
$debts = [];
$total_debt = 0;
$total_remaining = 0;

// Build the query based on whether we're searching or not
if (!empty($search)) {
    // Query with search (using positional parameters)
    $query = "
        SELECT d.*, c.name as category_name, c.color as category_color
        FROM debt d
        LEFT JOIN categories c ON d.category_id = c.id
        WHERE (d.description LIKE ? OR d.notes LIKE ?)
        ORDER BY d.{$sort} {$order}
    ";
    
    // Execute with positional parameters
    $stmt = $pdo->prepare($query);
    $searchParam = "%{$search}%";
    $stmt->execute([$searchParam, $searchParam]);
    $debts = $stmt->fetchAll();
} else {
    // Query without search
    $query = "
        SELECT d.*, c.name as category_name, c.color as category_color
        FROM debt d
        LEFT JOIN categories c ON d.category_id = c.id
        ORDER BY d.{$sort} {$order}
    ";
    
    // Execute without parameters
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $debts = $stmt->fetchAll();
}

// Calculate totals
if (!empty($debts)) {
    foreach ($debts as $debt) {
        $total_debt += $debt['total_amount'];
        $total_remaining += $debt['remaining_amount'];
    }
}

$total_paid = $total_debt - $total_remaining;

// Include header
require_once '../../includes/header.php';
?>

<!-- Debt Module Content -->
<div class="module-header">
    <div class="module-title">
        <h1>Debt Management</h1>
        <p>Track and manage your loans, mortgages, and other debts</p>
    </div>
    <div class="module-actions">
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Debt
        </a>
        
        <a href="../outgoing/index.php?is_debt=1" class="btn btn-secondary">
            <i class="fas fa-money-bill-wave"></i> View Debt Payments
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

<!-- Debts Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Debt Overview</div>
        <div class="card-actions">
            <div class="dropdown">
                <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="exportDropdown" data-toggle="dropdown">
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
                        <a href="?sort=description&order=<?php echo ($sort === 'description' && $order === 'DESC') ? 'asc' : 'desc'; echo $search ? '&search=' . urlencode($search) : ''; ?>">
                            Description
                            <?php if ($sort === 'description'): ?>
                                <i class="fas fa-sort-<?php echo ($order === 'DESC') ? 'down' : 'up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Category</th>
                    <th>
                        <a href="?sort=start_date&order=<?php echo ($sort === 'start_date' && $order === 'DESC') ? 'asc' : 'desc'; echo $search ? '&search=' . urlencode($search) : ''; ?>">
                            Start Date
                            <?php if ($sort === 'start_date'): ?>
                                <i class="fas fa-sort-<?php echo ($order === 'DESC') ? 'down' : 'up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="?sort=total_amount&order=<?php echo ($sort === 'total_amount' && $order === 'DESC') ? 'asc' : 'desc'; echo $search ? '&search=' . urlencode($search) : ''; ?>">
                            Total Amount
                            <?php if ($sort === 'total_amount'): ?>
                                <i class="fas fa-sort-<?php echo ($order === 'DESC') ? 'down' : 'up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="?sort=remaining_amount&order=<?php echo ($sort === 'remaining_amount' && $order === 'DESC') ? 'asc' : 'desc'; echo $search ? '&search=' . urlencode($search) : ''; ?>">
                            Remaining
                            <?php if ($sort === 'remaining_amount'): ?>
                                <i class="fas fa-sort-<?php echo ($order === 'DESC') ? 'down' : 'up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Progress</th>
                    <th>
                        <a href="?sort=interest_rate&order=<?php echo ($sort === 'interest_rate' && $order === 'DESC') ? 'asc' : 'desc'; echo $search ? '&search=' . urlencode($search) : ''; ?>">
                            Interest Rate
                            <?php if ($sort === 'interest_rate'): ?>
                                <i class="fas fa-sort-<?php echo ($order === 'DESC') ? 'down' : 'up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($debts)): ?>
                    <tr>
                        <td colspan="8" class="text-center">No debts found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($debts as $debt): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($debt['description']); ?></strong>
                                <?php if (!empty($debt['notes'])): ?>
                                    <div class="small text-muted"><?php echo htmlspecialchars(truncateText($debt['notes'], 50)); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($debt['category_name']): ?>
                                    <span class="category-badge" style="background-color: <?php echo htmlspecialchars($debt['category_color']); ?>">
                                        <?php echo htmlspecialchars($debt['category_name']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Uncategorized</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($debt['start_date'])); ?></td>
                            <td class="text-right font-weight-bold">
                                <?php echo number_format($debt['total_amount'], 2); ?> kr
                            </td>
                            <td class="text-right amount-negative">
                                <?php echo number_format($debt['remaining_amount'], 2); ?> kr
                            </td>
                            <td>
                                <?php 
                                $paidPercent = $debt['total_amount'] > 0 ? (($debt['total_amount'] - $debt['remaining_amount']) / $debt['total_amount']) * 100 : 0;
                                ?>
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $paidPercent; ?>%" aria-valuenow="<?php echo $paidPercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="small text-center"><?php echo round($paidPercent); ?>% paid</div>
                            </td>
                            <td>
                                <?php if ($debt['interest_rate']): ?>
                                    <?php echo number_format($debt['interest_rate'], 2); ?>%
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="view.php?id=<?php echo $debt['id']; ?>" class="btn btn-sm btn-info" data-tooltip="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <a href="edit.php?id=<?php echo $debt['id']; ?>" class="btn btn-sm btn-primary" data-tooltip="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <a href="../outgoing/add.php?is_debt=1&debt_id=<?php echo $debt['id']; ?>" class="btn btn-sm btn-success" data-tooltip="Add Payment">
                                        <i class="fas fa-plus"></i>
                                    </a>
                                    
                                    <a href="api.php?action=delete&id=<?php echo $debt['id']; ?>" class="btn btn-sm btn-danger delete-btn" data-name="<?php echo htmlspecialchars($debt['description']); ?>" data-tooltip="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3">Total</th>
                    <th class="text-right"><?php echo number_format($total_debt, 2); ?> kr</th>
                    <th class="text-right amount-negative"><?php echo number_format($total_remaining, 2); ?> kr</th>
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

.module-actions {
    display: flex;
    gap: 10px;
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

.amount-negative {
    color: var(--danger);
    font-weight: 500;
}

.progress {
    height: 8px;
    background-color: var(--gray-light);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 5px;
}

.progress-bar {
    background-color: var(--secondary);
}

.font-weight-bold {
    font-weight: 600;
}

.small {
    font-size: 85%;
}

.text-center {
    text-align: center;
}

.text-right {
    text-align: right;
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
    .module-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .module-actions {
        margin-top: 15px;
    }
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