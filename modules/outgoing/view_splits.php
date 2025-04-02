<?php
/**
 * View Split Transaction Details for Outgoing
 */

// Include database connection
require_once '../../config/database.php';

// Include helper functions
require_once '../../includes/functions.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id = (int)$_GET['id'];

// Get main transaction
$stmt = $pdo->prepare("
    SELECT o.*, c.name as category_name, c.color as category_color
    FROM outgoing o
    LEFT JOIN categories c ON o.category_id = c.id
    WHERE o.id = :id
");
$stmt->execute(['id' => $id]);
$transaction = $stmt->fetch();

// Redirect if transaction not found or not a split transaction
if (!$transaction || !$transaction['is_split']) {
    header('Location: index.php');
    exit;
}

// Check if this is a debt transaction
$is_debt = $transaction['is_debt'] == 1;

// Get debt info if applicable
$debt_payment = null;
if ($is_debt) {
    $stmt = $pdo->prepare("
        SELECT dp.*, d.description as debt_description, d.remaining_amount, d.total_amount
        FROM debt_payments dp
        JOIN debt d ON dp.debt_id = d.id
        WHERE dp.outgoing_id = :outgoing_id
    ");
    $stmt->execute(['outgoing_id' => $id]);
    $debt_payment = $stmt->fetch();
}

// Get split items
$stmt = $pdo->prepare("
    SELECT o.*, c.name as category_name, c.color as category_color,
           (SELECT COUNT(*) FROM outgoing WHERE parent_id = o.id) as has_children
    FROM outgoing o
    LEFT JOIN categories c ON o.category_id = c.id
    WHERE o.parent_id = :parent_id
    ORDER BY o.amount DESC
");
$stmt->execute(['parent_id' => $id]);
$splits = $stmt->fetchAll();

// Validate split transactions
$hasInvalidSplits = false;
foreach ($splits as $split) {
    if ($split['has_children'] > 0) {
        $hasInvalidSplits = true;
        break;
    }
}

// Calculate totals
$totalAmount = 0;
foreach ($splits as $split) {
    $totalAmount += $split['amount'];
}

// Validate total amount matches parent transaction
$amountMismatch = abs($totalAmount - $transaction['amount']) > 0.01;

// Include header
require_once '../../includes/header.php';
?>

<div class="module-header">
    <div class="module-title">
        <h1>Split Transaction Details</h1>
        <p>View and manage split <?php echo $is_debt ? 'debt payment' : 'expense'; ?> components</p>
    </div>
    <div class="module-actions">
        <a href="index.php<?php echo $is_debt ? '?is_debt=1' : ''; ?>" class="btn btn-light">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
        
        <a href="edit.php?id=<?php echo $transaction['id']; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Edit Transaction
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <div class="card-title">Transaction Overview</div>
    </div>
    <div class="card-body">
        <div class="transaction-header">
            <div class="transaction-info">
                <h2><?php echo htmlspecialchars($transaction['description']); ?></h2>
                <div class="transaction-meta">
                    <div class="meta-item">
                        <span class="meta-label">Date:</span>
                        <span class="meta-value"><?php echo date('F d, Y', strtotime($transaction['date'])); ?></span>
                    </div>
                    
                    <div class="meta-item">
                        <span class="meta-label">Category:</span>
                        <span class="meta-value">
                            <?php if ($transaction['category_name']): ?>
                                <span class="category-badge" style="background-color: <?php echo htmlspecialchars($transaction['category_color']); ?>">
                                    <?php echo htmlspecialchars($transaction['category_name']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Uncategorized</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="meta-item">
                        <span class="meta-label">Total Amount:</span>
                        <span class="meta-value amount-negative"><?php echo number_format($transaction['amount'], 2); ?> kr</span>
                    </div>
                    
                    <?php if (!$is_debt): ?>
                    <div class="meta-item">
                        <span class="meta-label">Type:</span>
                        <span class="meta-value">
                            <?php if ($transaction['is_fixed']): ?>
                                <span class="badge badge-primary">Fixed Cost</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Variable Cost</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <?php if ($transaction['is_fixed'] && $transaction['repeat_interval'] !== 'none'): ?>
                    <div class="meta-item">
                        <span class="meta-label">Recurrence:</span>
                        <span class="meta-value">
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
                                <small class="d-block text-muted">
                                    Until <?php echo date('M d, Y', strtotime($transaction['repeat_until'])); ?>
                                </small>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($is_debt && $debt_payment): ?>
                    <div class="meta-item">
                        <span class="meta-label">Associated Debt:</span>
                        <span class="meta-value">
                            <a href="../debt/view.php?id=<?php echo $debt_payment['debt_id']; ?>" class="debt-link">
                                <?php echo htmlspecialchars($debt_payment['debt_description']); ?>
                            </a>
                        </span>
                    </div>
                    
                    <div class="meta-item">
                        <span class="meta-label">Debt Remaining:</span>
                        <span class="meta-value amount-negative"><?php echo number_format($debt_payment['remaining_amount'], 2); ?> kr</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($transaction['notes'])): ?>
            <div class="transaction-notes">
                <h3>Notes</h3>
                <p><?php echo nl2br(htmlspecialchars($transaction['notes'])); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Split Items (<?php echo count($splits); ?>)</div>
        <div class="card-actions">
            <span class="badge badge-primary">
                Total: <?php echo number_format($totalAmount, 2); ?> kr
                <?php if ($amountMismatch): ?>
                    <span class="text-warning">(Difference: <?php echo number_format($transaction['amount'] - $totalAmount, 2); ?> kr)</span>
                <?php endif; ?>
            </span>
        </div>
    </div>
    
    <div class="table-container">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Amount</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($splits)): ?>
                    <tr>
                        <td colspan="6" class="text-center">No split items found. This transaction may be incorrectly marked as split.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($splits as $split): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($split['date'])); ?></td>
                            <td>Split Item</td>
                            <td>-</td>
                            <td class="text-right amount-negative">
                                <?php echo number_format($split['amount'], 2); ?> kr
                            </td>
                            <td>-</td>
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
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3">Total</th>
                    <th class="text-right amount-negative"><?php echo number_format($totalAmount, 2); ?> kr</th>
                    <th colspan="2"></th>
                </tr>
                <?php if ($amountMismatch): ?>
                    <tr class="difference-row">
                        <th colspan="3">Difference</th>
                        <th class="text-right <?php echo ($transaction['amount'] > $totalAmount) ? 'amount-negative' : 'amount-positive'; ?>">
                            <?php echo number_format($transaction['amount'] - $totalAmount, 2); ?> kr
                        </th>
                        <th colspan="2"></th>
                    </tr>
                <?php endif; ?>
            </tfoot>
        </table>
    </div>
</div>

<style>
.transaction-header {
    margin-bottom: 20px;
}

.transaction-header h2 {
    font-size: 22px;
    font-weight: 600;
    margin-bottom: 10px;
    color: var(--dark);
}

.transaction-meta {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 10px;
}

.meta-item {
    display: flex;
    flex-direction: column;
}

.meta-label {
    font-size: 12px;
    color: var(--gray);
    margin-bottom: 3px;
}

.meta-value {
    font-weight: 500;
}

.transaction-notes {
    background-color: rgba(236, 240, 241, 0.3);
    border-radius: 8px;
    padding: 15px;
    margin-top: 20px;
}

.transaction-notes h3 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 10px;
    color: var(--dark);
}

.text-right {
    text-align: right;
}

.amount-positive {
    color: var(--success);
    font-weight: 500;
}

.amount-negative {
    color: var(--danger);
    font-weight: 500;
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
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 600;
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

.text-warning {
    color: var(--warning);
}

.difference-row th {
    font-style: italic;
    color: var(--warning);
}

.d-block {
    display: block;
}

.debt-link {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.debt-link:hover {
    text-decoration: underline;
}
</style>

<?php
// Include footer
require_once '../../includes/footer.php';
?>