<?php
/**
 * Incoming Transactions View
 */

// Include database connection
require_once '../../config/database.php';

// Include helper functions
require_once '../../includes/functions.php';

// Get filter parameters
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Build the query
$query = "
    SELECT i.*, c.name as category_name, c.color as category_color
    FROM incoming i
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE i.parent_id IS NULL
";

$params = [];

// Apply filters
if ($category_id) {
    $query .= " AND i.category_id = :category_id";
    $params['category_id'] = $category_id;
}

if ($date_from) {
    $query .= " AND i.date >= :date_from";
    $params['date_from'] = $date_from;
}

if ($date_to) {
    $query .= " AND i.date <= :date_to";
    $params['date_to'] = $date_to;
}

if ($search) {
    $query .= " AND (i.description LIKE :search OR i.notes LIKE :search)";
    $params['search'] = "%{$search}%";
}

// Apply sorting
$query .= " ORDER BY i.{$sort} {$order}";

// Get transactions
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get categories for filter dropdown
$stmt = $pdo->prepare("
    SELECT * FROM categories
    WHERE type IN ('incoming', 'both')
    ORDER BY name ASC
");
$stmt->execute();
$categories = $stmt->fetchAll();

// Get total incoming for filtered period
$total_query = "
    SELECT SUM(amount) as total
    FROM incoming
    WHERE parent_id IS NULL
";

$total_params = [];

if ($category_id) {
    $total_query .= " AND category_id = :category_id";
    $total_params['category_id'] = $category_id;
}

if ($date_from) {
    $total_query .= " AND date >= :date_from";
    $total_params['date_from'] = $date_from;
}

if ($date_to) {
    $total_query .= " AND date <= :date_to";
    $total_params['date_to'] = $date_to;
}

if ($search) {
    $total_query .= " AND (description LIKE :search OR notes LIKE :search)";
    $total_params['search'] = "%{$search}%";
}

$stmt = $pdo->prepare($total_query);
$stmt->execute($total_params);
$total = $stmt->fetch();
$total_amount = $total['total'] ?? 0;

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
                <div class="form-group col-md-3">
                    <label for="date_from">From Date</label>
                    <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from ?? ''); ?>">
                </div>
                
                <div class="form-group col-md-3">
                    <label for="date_to">To Date</label>
                    <input type="date" id="date_to" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to ?? ''); ?>">
                </div>
                
                <div class="form-group col-md-3">
                    <label for="category">Category</label>
                    <select id="category" name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo ($category_id == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group col-md-3">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Search description or notes..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
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

<!-- Summary & Stats -->
<div class="stats-row mb-4">
    <div class="stat-card">
        <div class="stat-value"><?php echo number_format($total_amount, 2); ?> kr</div>
        <div class="stat-label">Total Income</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-value"><?php echo count($transactions); ?></div>
        <div class="stat-label">Transactions</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-value">
            <?php
            $avg = count($transactions) > 0 ? $total_amount / count($transactions) : 0;
            echo number_format($avg, 2);
            ?> kr
        </div>
        <div class="stat-label">Average Transaction</div>
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
                    <a class="dropdown-item" href="api.php?action=export&format=csv<?php echo buildQueryParams(['category', 'date_from', 'date_to', 'search']); ?>">
                        <i class="fas fa-file-csv"></i> Export as CSV
                    </a>
                    <a class="dropdown-item" href="api.php?action=export&format=pdf<?php echo buildQueryParams(['category', 'date_from', 'date_to', 'search']); ?>">
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
                        <a href="?sort=date&order=<?php echo ($sort === 'date' && $order === 'DESC') ? 'asc' : 'desc'; echo buildQueryParams(['category', 'date_from', 'date_to', 'search']); ?>">
                            Date
                            <?php if ($sort === 'date'): ?>
                                <i class="fas fa-sort-<?php echo ($order === 'DESC') ? 'down' : 'up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="?sort=description&order=<?php echo ($sort === 'description' && $order === 'DESC') ? 'asc' : 'desc'; echo buildQueryParams(['category', 'date_from', 'date_to', 'search']); ?>">
                            Description
                            <?php if ($sort === 'description'): ?>
                                <i class="fas fa-sort-<?php echo ($order === 'DESC') ? 'down' : 'up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Category</th>
                    <th>
                        <a href="?sort=amount&order=<?php echo ($sort === 'amount' && $order === 'DESC') ? 'asc' : 'desc'; echo buildQueryParams(['category', 'date_from', 'date_to', 'search']); ?>">
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
                            // Get split transactions
                            $stmt = $pdo->prepare("
                                SELECT i.*, c.name as category_name, c.color as category_color
                                FROM incoming i
                                LEFT JOIN categories c ON i.category_id = c.id
                                WHERE i.parent_id = :parent_id
                                ORDER BY i.amount DESC
                            ");
                            $stmt->execute(['parent_id' => $transaction['id']]);
                            $splits = $stmt->fetchAll();
                            ?>
                            
                            <?php foreach ($splits as $split): ?>
                                <tr class="split-row">
                                    <td></td>
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

.col-md-3 {
    flex: 0 0 25%;
    max-width: 25%;
}

.filters-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
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