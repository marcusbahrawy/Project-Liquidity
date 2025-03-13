<?php
/**
 * Outgoing Transactions View
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
$is_fixed = isset($_GET['is_fixed']) ? (int)$_GET['is_fixed'] : null;
$is_debt = isset($_GET['is_debt']) ? (int)$_GET['is_debt'] : 0; // Default not showing debt
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Current date for future projections
$currentDate = date('Y-m-d');

// Build the query
$query = "
    SELECT o.*, c.name as category_name, c.color as category_color
    FROM outgoing o
    LEFT JOIN categories c ON o.category_id = c.id
    WHERE o.parent_id IS NULL
";

$params = [];

// Apply filters
if ($category_id) {
    $query .= " AND o.category_id = :category_id";
    $params['category_id'] = $category_id;
}

if ($date_from) {
    $query .= " AND o.date >= :date_from";
    $params['date_from'] = $date_from;
}

if ($date_to) {
    $query .= " AND o.date <= :date_to";
    $params['date_to'] = $date_to;
}

if ($search) {
    $query .= " AND (o.description LIKE :search OR o.notes LIKE :search)";
    $params['search'] = "%{$search}%";
}

if (isset($is_fixed)) {
    $query .= " AND o.is_fixed = :is_fixed";
    $params['is_fixed'] = $is_fixed;
}

// Filter debt/non-debt transactions
$query .= " AND o.is_debt = :is_debt";
$params['is_debt'] = $is_debt;

// Apply sorting
$query .= " ORDER BY o.{$sort} {$order}";

// Get transactions
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get categories for filter dropdown
$stmt = $pdo->prepare("
    SELECT * FROM categories
    WHERE type IN ('outgoing', 'both')
    ORDER BY name ASC
");
$stmt->execute();
$categories = $stmt->fetchAll();

// Calculate totals including future recurring transactions
$total_amount = 0;
$fixed_amount = 0;
$variable_amount = 0;
$total_with_recurring = 0;
$fixed_with_recurring = 0;

// Function to calculate recurring transaction projections
function calculateRecurringTransactions($transaction, $date_from = null, $date_to = null, $max_projections = 36) {
    global $currentDate;
    
    // Skip if not a recurring transaction
    if (!$transaction['is_fixed'] || $transaction['repeat_interval'] === 'none') {
        return [];
    }
    
    // Initialize results array
    $projections = [];
    
    // Determine start date for projections (greater than the original transaction date)
    $startDate = date('Y-m-d', strtotime($transaction['date'] . ' + 1 day'));
    
    // Apply date_from filter if provided
    if ($date_from && $date_from > $startDate) {
        $startDate = $date_from;
    }
    
    // Determine end date for projections
    $endDate = $transaction['repeat_until'] ?? date('Y-m-d', strtotime('+3 years'));
    
    // Apply date_to filter if provided
    if ($date_to && $date_to < $endDate) {
        $endDate = $date_to;
    }
    
    // Calculate the first projection date after the start date
    $currentOccurrence = $transaction['date'];
    
    // Move to the first occurrence after startDate
    while ($currentOccurrence < $startDate) {
        // Calculate next occurrence based on interval
        switch ($transaction['repeat_interval']) {
            case 'daily':
                $currentOccurrence = date('Y-m-d', strtotime($currentOccurrence . ' + 1 day'));
                break;
            case 'weekly':
                $currentOccurrence = date('Y-m-d', strtotime($currentOccurrence . ' + 1 week'));
                break;
            case 'monthly':
                $currentOccurrence = date('Y-m-d', strtotime($currentOccurrence . ' + 1 month'));
                break;
            case 'quarterly':
                $currentOccurrence = date('Y-m-d', strtotime($currentOccurrence . ' + 3 months'));
                break;
            case 'yearly':
                $currentOccurrence = date('Y-m-d', strtotime($currentOccurrence . ' + 1 year'));
                break;
        }
    }
    
    // Generate projections until end date (with a maximum to prevent infinite loops)
    $count = 0;
    while ($currentOccurrence <= $endDate && $count < $max_projections) {
        // Skip the original transaction date
        if ($currentOccurrence != $transaction['date']) {
            // Create a projection entry
            $projection = [
                'date' => $currentOccurrence,
                'amount' => $transaction['amount'],
                'is_projection' => true,
                'is_fixed' => $transaction['is_fixed']
            ];
            
            $projections[] = $projection;
        }
        
        // Calculate next occurrence based on interval
        switch ($transaction['repeat_interval']) {
            case 'daily':
                $currentOccurrence = date('Y-m-d', strtotime($currentOccurrence . ' + 1 day'));
                break;
            case 'weekly':
                $currentOccurrence = date('Y-m-d', strtotime($currentOccurrence . ' + 1 week'));
                break;
            case 'monthly':
                $currentOccurrence = date('Y-m-d', strtotime($currentOccurrence . ' + 1 month'));
                break;
            case 'quarterly':
                $currentOccurrence = date('Y-m-d', strtotime($currentOccurrence . ' + 3 months'));
                break;
            case 'yearly':
                $currentOccurrence = date('Y-m-d', strtotime($currentOccurrence . ' + 1 year'));
                break;
        }
        
        $count++;
    }
    
    return $projections;
}

// Calculate totals and recurring projections
foreach ($transactions as $transaction) {
    // Add to the regular total
    $total_amount += $transaction['amount'];
    
    // Add to fixed or variable totals
    if ($transaction['is_fixed']) {
        $fixed_amount += $transaction['amount'];
    } else {
        $variable_amount += $transaction['amount'];
    }
    
    // Calculate recurring transactions for future projections
    if ($transaction['is_fixed'] && $transaction['repeat_interval'] != 'none') {
        // Get a reasonable projection date (1 year ahead)
        $projection_end = date('Y-m-d', strtotime('+1 year'));
        
        // If there's a specific repeat_until date, use that instead
        if ($transaction['repeat_until'] && $transaction['repeat_until'] < $projection_end) {
            $projection_end = $transaction['repeat_until'];
        }
        
        // If date_to is specified, use that as the limit
        if ($date_to && $date_to < $projection_end) {
            $projection_end = $date_to;
        }
        
        // Calculate projections
        $projections = calculateRecurringTransactions($transaction, $date_from, $projection_end);
        
        // Add projections to the totals
        foreach ($projections as $projection) {
            $total_with_recurring += $projection['amount'];
            if ($projection['is_fixed']) {
                $fixed_with_recurring += $projection['amount'];
            }
        }
    }
}

// Total including recurring projections
$total_with_recurring += $total_amount;
$fixed_with_recurring += $fixed_amount;

// Include header
require_once '../../includes/header.php';
?>

<!-- Outgoing Module Content -->
<div class="module-header">
    <div class="module-title">
        <h1><?php echo $is_debt ? 'Debt Payments' : 'Outgoing Transactions'; ?></h1>
        <p><?php echo $is_debt ? 'Manage your debt payments and loans' : 'Manage your expenses and outgoing payments'; ?></p>
    </div>
    <div class="module-actions">
        <a href="add.php<?php echo $is_debt ? '?is_debt=1' : ''; ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add <?php echo $is_debt ? 'Debt Payment' : 'Expense'; ?>
        </a>
        
        <?php if (!$is_debt): ?>
            <a href="?is_debt=1" class="btn btn-secondary">
                <i class="fas fa-credit-card"></i> View Debt Payments
            </a>
        <?php else: ?>
            <a href="?is_debt=0" class="btn btn-secondary">
                <i class="fas fa-arrow-up"></i> View Regular Expenses
            </a>
            
            <a href="../debt/index.php" class="btn btn-info">
                <i class="fas fa-money-bill-wave"></i> Manage Debts
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filters-form" class="filters-form" method="GET">
            <input type="hidden" name="is_debt" value="<?php echo $is_debt; ?>">
            
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
            
            <?php if (!$is_debt): ?>
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="is_fixed">Transaction Type</label>
                    <select id="is_fixed" name="is_fixed" class="form-select">
                        <option value="">All Types</option>
                        <option value="1" <?php echo (isset($is_fixed) && $is_fixed == 1) ? 'selected' : ''; ?>>Fixed Costs</option>
                        <option value="0" <?php echo (isset($is_fixed) && $is_fixed == 0) ? 'selected' : ''; ?>>Variable Costs</option>
                    </select>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="filters-actions">
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                <input type="hidden" name="order" value="<?php echo htmlspecialchars($order); ?>">
                
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                
                <a href="?is_debt=<?php echo $is_debt; ?>" class="btn btn-light btn-sm">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
                
                <div class="quick-dates">
                    <a href="#" class="current-month">This Month</a>
                    <a href="#" class="prev-month">Last Month</a>
                    <a href="#" class="last-30-days">Last 30 Days</a>
                    <a href="#" class="current-year">This Year</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Summary & Stats -->
<div class="stats-row mb-4">
    <div class="stat-card">
        <div class="stat-value"><?php echo number_format($total_amount, 2); ?> kr</div>
        <div class="stat-label">Current <?php echo $is_debt ? 'Debt Payments' : 'Expenses'; ?></div>
    </div>
    
    <?php if (!$is_debt): ?>
    <div class="stat-card">
        <div class="stat-value"><?php echo number_format($total_with_recurring, 2); ?> kr</div>
        <div class="stat-label">With Future Recurrences</div>
        <div class="stat-trend">
            <i class="fas fa-calendar-alt"></i>
            Includes future recurring transactions
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-value"><?php echo number_format($fixed_amount, 2); ?> kr</div>
        <div class="stat-label">Fixed Costs</div>
        <div class="stat-percent"><?php echo $total_amount > 0 ? round(($fixed_amount / $total_amount) * 100) : 0; ?>%</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-value"><?php echo number_format($variable_amount, 2); ?> kr</div>
        <div class="stat-label">Variable Costs</div>
        <div class="stat-percent"><?php echo $total_amount > 0 ? round(($variable_amount / $total_amount) * 100) : 0; ?>%</div>
    </div>
    <?php endif; ?>
</div>

<!-- Recurring Info Alert -->
<?php if (!$is_debt && $total_with_recurring > $total_amount): ?>
<div class="alert alert-info mb-4">
    <i class="fas fa-info-circle"></i>
    <strong>Recurring Transactions:</strong> Your totals, charts, and projections include <?php echo number_format($total_with_recurring - $total_amount, 2); ?> kr 
    in future recurring transactions that are shown only once in the list below.
</div>
<?php endif; ?>

<!-- Transactions Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><?php echo $is_debt ? 'Debt Payments' : 'Outgoing Transactions'; ?> (<?php echo count($transactions); ?>)</div>
        <div class="card-actions">
            <div class="dropdown">
                <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="exportDropdown" data-toggle="dropdown">
                    <i class="fas fa-download"></i> Export
                </button>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="exportDropdown">
                    <a class="dropdown-item" href="api.php?action=export&format=csv&is_debt=<?php echo $is_debt; echo buildQueryParams(['category', 'date_from', 'date_to', 'search', 'is_fixed']); ?>">
                        <i class="fas fa-file-csv"></i> Export as CSV
                    </a>
                    <a class="dropdown-item" href="api.php?action=export&format=pdf&is_debt=<?php echo $is_debt; echo buildQueryParams(['category', 'date_from', 'date_to', 'search', 'is_fixed']); ?>">
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
                        <a href="?is_debt=<?php echo $is_debt; ?>&sort=date&order=<?php echo ($sort === 'date' && $order === 'DESC') ? 'asc' : 'desc'; echo buildQueryParams(['category', 'date_from', 'date_to', 'search', 'is_fixed']); ?>">
                            Date
                            <?php if ($sort === 'date'): ?>
                                <i class="fas fa-sort-<?php echo ($order === 'DESC') ? 'down' : 'up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="?is_debt=<?php echo $is_debt; ?>&sort=description&order=<?php echo ($sort === 'description' && $order === 'DESC') ? 'asc' : 'desc'; echo buildQueryParams(['category', 'date_from', 'date_to', 'search', 'is_fixed']); ?>">
                            Description
                            <?php if ($sort === 'description'): ?>
                                <i class="fas fa-sort-<?php echo ($order === 'DESC') ? 'down' : 'up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Category</th>
                    <?php if (!$is_debt): ?>
                    <th>Type</th>
                    <?php endif; ?>
                    <th>
                        <a href="?is_debt=<?php echo $is_debt; ?>&sort=amount&order=<?php echo ($sort === 'amount' && $order === 'DESC') ? 'asc' : 'desc'; echo buildQueryParams(['category', 'date_from', 'date_to', 'search', 'is_fixed']); ?>">
                            Amount
                            <?php if ($sort === 'amount'): ?>
                                <i class="fas fa-sort-<?php echo ($order === 'DESC') ? 'down' : 'up'; ?>"></i>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Recurrence</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="<?php echo $is_debt ? '6' : '7'; ?>" class="text-center">No transactions found.</td>
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
                            <?php if (!$is_debt): ?>
                            <td>
                                <?php if ($transaction['is_fixed']): ?>
                                    <span class="badge badge-primary">Fixed</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Variable</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td class="text-right amount-negative">
                                <?php echo number_format($transaction['amount'], 2); ?> kr
                            </td>
                            <td>
                                <?php if ($transaction['repeat_interval'] !== 'none'): ?>
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
                                        }
                                        ?>
                                    </span>
                                    <?php if ($transaction['repeat_until']): ?>
                                        <small class="text-muted d-block">
                                            Until <?php echo date('M d, Y', strtotime($transaction['repeat_until'])); ?>
                                        </small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">One-time</span>
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
                                SELECT o.*, c.name as category_name, c.color as category_color
                                FROM outgoing o
                                LEFT JOIN categories c ON o.category_id = c.id
                                WHERE o.parent_id = :parent_id
                                ORDER BY o.amount DESC
                            ");
                            $stmt->execute(['parent_id' => $transaction['id']]);
                            $splits = $stmt->fetchAll();
                            ?>
                            
                            <?php foreach ($splits as $index => $split): ?>
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
                                    <?php if (!$is_debt): ?>
                                    <td></td>
                                    <?php endif; ?>
                                    <td class="text-right amount-negative">
                                        <?php echo number_format($split['amount'], 2); ?> kr
                                    </td>
                                    <td></td>
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
                    <th colspan="<?php echo $is_debt ? '4' : '5'; ?>">Current Total (Displayed Transactions)</th>
                    <th class="text-right amount-negative"><?php echo number_format($total_amount, 2); ?> kr</th>
                    <th colspan="2"></th>
                </tr>
                <?php if (!$is_debt && $total_with_recurring > $total_amount): ?>
                <tr>
                    <th colspan="<?php echo $is_debt ? '4' : '5'; ?>">Projected Total (Including Recurring Transactions)</th>
                    <th class="text-right amount-negative"><?php echo number_format($total_with_recurring, 2); ?> kr</th>
                    <th colspan="2"></th>
                </tr>
                <?php endif; ?>
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
    flex-wrap: wrap;
    gap: 10px;
    justify-content: space-between;
    align-items: center;
}

.quick-dates {
    display: flex;
    gap: 10px;
}

.quick-dates a {
    color: var(--primary);
    font-size: 13px;
    text-decoration: none;
}

.quick-dates a:hover {
    text-decoration: underline;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.stat-card {
    background-color: white;
    border-radius: var(--border-radius);
    padding: 20px 25px;
    box-shadow: var(--card-shadow);
}

.stat-card .stat-value {
    font-size: 24px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 5px;
}

.stat-card .stat-label {
    color: var(--gray);
    font-size: 14px;
}

.stat-card .stat-percent {
    margin-top: 5px;
    font-size: 13px;
    font-weight: 500;
}

.stat-card .stat-trend {
    display: flex;
    align-items: center;
    font-size: 13px;
    margin-top: 5px;
    color: var(--gray);
}

.stat-card .stat-trend i {
    margin-right: 5px;
}

.category-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    color: white;
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

.badge-primary {
    background-color: rgba(52, 152, 219, 0.2);
    color: var(--primary-dark);
}

.badge-secondary {
    background-color: rgba(149, 165, 166, 0.2);
    color: #7f8c8d;
}

.badge-warning {
    background-color: rgba(243, 156, 18, 0.2);
    color: #d35400;
}

.badge-recurring {
    background-color: rgba(142, 68, 173, 0.2);
    color: #8e44ad;
}

.amount-negative {
    color: var(--danger);
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

/* Alert styles */
.alert-info {
    background-color: rgba(52, 152, 219, 0.1);
    border-left: 4px solid var(--primary);
    color: var(--primary-dark);
    padding: 15px;
    border-radius: 4px;
}

.alert-info i {
    margin-right: 8px;
}

.d-block {
    display: block;
}

@media (max-width: 768px) {
    .module-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .module-actions {
        margin-top: 15px;
    }
    
    .col-md-3 {
        flex: 0 0 100%;
        max-width: 100%;
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