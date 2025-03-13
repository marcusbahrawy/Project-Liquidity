<?php
/**
 * Edit Debt
 */

// Include database connection
require_once '../../config/database.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id = (int)$_GET['id'];

// Get debt
$stmt = $pdo->prepare("
    SELECT * FROM debt
    WHERE id = :id
");
$stmt->execute(['id' => $id]);
$debt = $stmt->fetch();

// Redirect if debt not found
if (!$debt) {
    header('Location: index.php');
    exit;
}

// Get debt payments
$stmt = $pdo->prepare("
    SELECT dp.*, o.date, o.description as payment_description
    FROM debt_payments dp
    JOIN outgoing o ON dp.outgoing_id = o.id
    WHERE dp.debt_id = :debt_id
    ORDER BY o.date DESC
");
$stmt->execute(['debt_id' => $id]);
$payments = $stmt->fetchAll();

// Get categories for dropdown
$stmt = $pdo->prepare("
    SELECT * FROM categories
    WHERE type IN ('outgoing', 'both')
    ORDER BY name ASC
");
$stmt->execute();
$categories = $stmt->fetchAll();

// Calculate total paid
$totalPaid = 0;
foreach ($payments as $payment) {
    $totalPaid += $payment['amount'];
}

// Include header
require_once '../../includes/header.php';
?>

<div class="module-header">
    <div class="module-title">
        <h1>Edit Debt</h1>
        <p>Modify loan, mortgage, or other debt details</p>
    </div>
    <div class="module-actions">
        <a href="index.php" class="btn btn-light">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
        
        <a href="../outgoing/add.php?is_debt=1&debt_id=<?php echo $debt['id']; ?>" class="btn btn-success">
            <i class="fas fa-plus"></i> Add Payment
        </a>
    </div>
</div>

<div class="debt-overview">
    <div class="overview-header">
        <h2><?php echo htmlspecialchars($debt['description']); ?></h2>
        <div class="overview-progress">
            <?php 
            $paidPercent = $debt['total_amount'] > 0 ? (($debt['total_amount'] - $debt['remaining_amount']) / $debt['total_amount']) * 100 : 0;
            ?>
            <div class="progress">
                <div class="progress-bar" role="progressbar" style="width: <?php echo $paidPercent; ?>%" aria-valuenow="<?php echo $paidPercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="progress-text">
                <span><?php echo number_format($paidPercent, 1); ?>% paid</span>
                <span><?php echo number_format($debt['remaining_amount'], 2); ?> kr remaining</span>
            </div>
        </div>
    </div>
    
    <div class="overview-stats">
        <div class="stat-item">
            <span class="stat-label">Total Amount</span>
            <span class="stat-value"><?php echo number_format($debt['total_amount'], 2); ?> kr</span>
        </div>
        
        <div class="stat-item">
            <span class="stat-label">Amount Paid</span>
            <span class="stat-value"><?php echo number_format($totalPaid, 2); ?> kr</span>
        </div>
        
        <div class="stat-item">
            <span class="stat-label">Remaining</span>
            <span class="stat-value"><?php echo number_format($debt['remaining_amount'], 2); ?> kr</span>
        </div>
        
        <div class="stat-item">
            <span class="stat-label">Interest Rate</span>
            <span class="stat-value"><?php echo $debt['interest_rate'] ? number_format($debt['interest_rate'], 2) . '%' : 'N/A'; ?></span>
        </div>
        
        <div class="stat-item">
            <span class="stat-label">Start Date</span>
            <span class="stat-value"><?php echo date('M d, Y', strtotime($debt['start_date'])); ?></span>
        </div>
        
        <div class="stat-item">
            <span class="stat-label">End Date</span>
            <span class="stat-value"><?php echo $debt['end_date'] ? date('M d, Y', strtotime($debt['end_date'])) : 'N/A'; ?></span>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header">
                <div class="card-title">Edit Debt</div>
            </div>
            <div class="card-body">
                <form id="debtForm" class="needs-validation ajax-form" action="api.php?action=update" method="post" novalidate>
                    <input type="hidden" name="id" value="<?php echo $debt['id']; ?>">
                    
                    <div class="form-group">
                        <label for="description">Description *</label>
                        <input type="text" id="description" name="description" class="form-control" value="<?php echo htmlspecialchars($debt['description']); ?>" required>
                        <div class="invalid-feedback">Please provide a description.</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" class="form-select">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" data-color="<?php echo htmlspecialchars($category['color']); ?>" <?php echo ($debt['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="total_amount">Total Amount *</label>
                            <input type="number" id="total_amount" name="total_amount" class="form-control" step="0.01" min="0.01" value="<?php echo $debt['total_amount']; ?>" required>
                            <div class="invalid-feedback">Please provide a valid amount greater than 0.</div>
                            <small class="form-text text-muted">Changing this may affect calculated values</small>
                        </div>
                        
                        <div class="form-group col-md-6">
                            <label for="remaining_amount">Remaining Amount *</label>
                            <input type="number" id="remaining_amount" name="remaining_amount" class="form-control" step="0.01" min="0" value="<?php echo $debt['remaining_amount']; ?>" required>
                            <div class="invalid-feedback">Please provide a valid remaining amount.</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="interest_rate">Interest Rate (%)</label>
                            <input type="number" id="interest_rate" name="interest_rate" class="form-control" step="0.01" min="0" value="<?php echo $debt['interest_rate']; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="start_date">Start Date *</label>
                            <input type="date" id="start_date" name="start_date" class="form-control datepicker" value="<?php echo $debt['start_date']; ?>" required>
                            <div class="invalid-feedback">Please select a start date.</div>
                        </div>
                        
                        <div class="form-group col-md-6">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" class="form-control datepicker" value="<?php echo $debt['end_date']; ?>">
                            <small class="form-text text-muted">Leave empty if no specific end date</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($debt['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        
                        <a href="index.php" class="btn btn-light">Cancel</a>
                        
                        <a href="api.php?action=delete&id=<?php echo $debt['id']; ?>" class="btn btn-danger delete-btn" data-name="<?php echo htmlspecialchars($debt['description']); ?>">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Payment History</div>
                <div class="card-actions">
                    <a href="../outgoing/add.php?is_debt=1&debt_id=<?php echo $debt['id']; ?>" class="btn btn-success btn-sm">
                        <i class="fas fa-plus"></i> Add Payment
                    </a>
                </div>
            </div>
            <div class="payment-list">
                <?php if (empty($payments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-money-bill-wave"></i>
                        <p>No payments recorded yet.</p>
                        <a href="../outgoing/add.php?is_debt=1&debt_id=<?php echo $debt['id']; ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-plus"></i> Add First Payment
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                        <div class="payment-item">
                            <div class="payment-date">
                                <div class="date-day"><?php echo date('d', strtotime($payment['date'])); ?></div>
                                <div class="date-month"><?php echo date('M', strtotime($payment['date'])); ?></div>
                                <div class="date-year"><?php echo date('Y', strtotime($payment['date'])); ?></div>
                            </div>
                            <div class="payment-details">
                                <div class="payment-description"><?php echo htmlspecialchars($payment['payment_description']); ?></div>
                                <div class="payment-id">Payment ID: <?php echo $payment['outgoing_id']; ?></div>
                            </div>
                            <div class="payment-amount">
                                <?php echo number_format($payment['amount'], 2); ?> kr
                            </div>
                            <div class="payment-actions">
                                <a href="../outgoing/edit.php?id=<?php echo $payment['outgoing_id']; ?>" class="btn btn-sm btn-primary" data-tooltip="Edit Payment">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validation and constraints
    const totalAmountInput = document.getElementById('total_amount');
    const remainingAmountInput = document.getElementById('remaining_amount');
    
    if (totalAmountInput && remainingAmountInput) {
        // Remaining amount can't be larger than total amount
        totalAmountInput.addEventListener('input', validateAmounts);
        remainingAmountInput.addEventListener('input', validateAmounts);
        
        function validateAmounts() {
            const totalAmount = parseFloat(totalAmountInput.value) || 0;
            const remainingAmount = parseFloat(remainingAmountInput.value) || 0;
            
            if (remainingAmount > totalAmount) {
                remainingAmountInput.setCustomValidity('Remaining amount cannot exceed total amount');
            } else {
                remainingAmountInput.setCustomValidity('');
            }
        }
    }
    
    // Form submission
    document.getElementById('debtForm').addEventListener('submit', function(event) {
        if (!this.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        this.classList.add('was-validated');
    });
});
</script>

<style>
.row {
    display: flex;
    flex-wrap: wrap;
    margin-right: -15px;
    margin-left: -15px;
}

.col-lg-6 {
    position: relative;
    width: 100%;
    padding-right: 15px;
    padding-left: 15px;
}

@media (min-width: 992px) {
    .col-lg-6 {
        flex: 0 0 50%;
        max-width: 50%;
    }
}

.debt-overview {
    background-color: white;
    border-radius: var(--border-radius);
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: var(--card-shadow);
}

.overview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.overview-header h2 {
    font-size: 20px;
    font-weight: 600;
    margin: 0;
    color: var(--dark);
}

.overview-progress {
    width: 40%;
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

.progress-text {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    color: var(--gray);
}

.overview-stats {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
}

.stat-item {
    background-color: var(--gray-light);
    padding: 15px;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
}

.stat-label {
    font-size: 13px;
    color: var(--gray);
    margin-bottom: 5px;
}

.stat-value {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
}

.form-divider {
    margin: 30px 0 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--gray-light);
}

.form-actions {
    margin-top: 30px;
    display: flex;
    gap: 10px;
}

.payment-list {
    max-height: 500px;
    overflow-y: auto;
}

.payment-item {
    display: flex;
    align-items: center;
    padding: 15px 25px;
    border-bottom: 1px solid var(--gray-light);
}

.payment-date {
    background-color: var(--gray-light);
    border-radius: 8px;
    padding: 10px;
    text-align: center;
    margin-right: 15px;
    min-width: 60px;
}

.date-day {
    font-size: 18px;
    font-weight: 600;
    line-height: 1;
    color: var(--dark);
}

.date-month {
    font-size: 12px;
    text-transform: uppercase;
    color: var(--gray);
}

.date-year {
    font-size: 12px;
    color: var(--gray);
}

.payment-details {
    flex-grow: 1;
}

.payment-description {
    font-weight: 500;
    margin-bottom: 3px;
}

.payment-id {
    font-size: 12px;
    color: var(--gray);
}

.payment-amount {
    font-weight: 600;
    margin: 0 15px;
    min-width: 100px;
    text-align: right;
}

.empty-state {
    padding: 40px 25px;
    text-align: center;
}

.empty-state i {
    font-size: 36px;
    color: var(--gray);
    margin-bottom: 10px;
}

.empty-state p {
    color: var(--gray);
    margin-bottom: 15px;
}
</style>

<?php
// Include footer
require_once '../../includes/footer.php';