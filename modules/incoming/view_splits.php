<?php
/**
 * View Split Transaction Details
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
    SELECT i.*, c.name as category_name, c.color as category_color
    FROM incoming i
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE i.id = :id
");
$stmt->execute(['id' => $id]);
$transaction = $stmt->fetch();

// Redirect if transaction not found or not a split transaction
if (!$transaction || !$transaction['is_split']) {
    header('Location: index.php');
    exit;
}

// Get split items
$stmt = $pdo->prepare("
    SELECT i.*, c.name as category_name, c.color as category_color
    FROM incoming i
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE i.parent_id = :parent_id
    ORDER BY i.amount DESC
");
$stmt->execute(['parent_id' => $id]);
$splits = $stmt->fetchAll();

// Calculate totals
$totalAmount = 0;
foreach ($splits as $split) {
    $totalAmount += $split['amount'];
}

// Include header
require_once '../../includes/header.php';
?>

<div class="module-header">
    <div class="module-title">
        <h1>Split Transaction Details</h1>
        <p>View and manage split transaction components</p>
    </div>
    <div class="module-actions">
        <a href="index.php" class="btn btn-light">
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
                        <span class="meta-value amount-positive"><?php echo number_format($transaction['amount'], 2); ?> kr</span>
                    </div>
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
                <?php if ($totalAmount != $transaction['amount']): ?>
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
                            <td class="text-right amount-positive">
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
                    <th class="text-right amount-positive"><?php echo number_format($totalAmount, 2); ?> kr</th>
                    <th colspan="2"></th>
                </tr>
                <?php if ($totalAmount != $transaction['amount']): ?>
                    <tr class="difference-row">
                        <th colspan="3">Difference</th>
                        <th class="text-right <?php echo ($transaction['amount'] > $totalAmount) ? 'amount-positive' : 'amount-negative'; ?>">
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

.text-warning {
    color: var(--warning);
}

.difference-row th {
    font-style: italic;
    color: var(--warning);
}
</style>

<?php
// Include footer
require_once '../../includes/footer.php';
?>