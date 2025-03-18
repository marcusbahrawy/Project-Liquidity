<?php
/**
 * Edit Outgoing Transaction
 */

// Include database connection
require_once '../../config/database.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id = (int)$_GET['id'];

// Get transaction
$stmt = $pdo->prepare("
    SELECT o.*, c.name as category_name, c.color as category_color
    FROM outgoing o
    LEFT JOIN categories c ON o.category_id = c.id
    WHERE o.id = :id
");
$stmt->execute(['id' => $id]);
$transaction = $stmt->fetch();

// Redirect if transaction not found
if (!$transaction) {
    header('Location: index.php');
    exit;
}

// Check if it's a debt transaction
$is_debt = $transaction['is_debt'] == 1;

// Check if it's a split transaction or a split item
$isSplit = (bool)$transaction['is_split'];
$isSplitItem = !empty($transaction['parent_id']);

// If it's a split item, get the parent transaction
$parentTransaction = null;
if ($isSplitItem) {
    $stmt = $pdo->prepare("
        SELECT * FROM outgoing
        WHERE id = :id
    ");
    $stmt->execute(['id' => $transaction['parent_id']]);
    $parentTransaction = $stmt->fetch();
    
    // Inherit debt status from parent
    $is_debt = $parentTransaction['is_debt'] == 1;
}

// Get splits if it's a split transaction
$splits = [];
if ($isSplit) {
    $stmt = $pdo->prepare("
        SELECT * FROM outgoing
        WHERE parent_id = :parent_id
        ORDER BY amount DESC
    ");
    $stmt->execute(['parent_id' => $id]);
    $splits = $stmt->fetchAll();
}

// Get categories for dropdown
$stmt = $pdo->prepare("
    SELECT * FROM categories
    WHERE type IN ('outgoing', 'both')
    ORDER BY name ASC
");
$stmt->execute();
$categories = $stmt->fetchAll();

// If it's a debt transaction, load the debt information
$debt_payment = null;
$debts = [];
if ($is_debt) {
    // Get debt payment info
    $stmt = $pdo->prepare("
        SELECT dp.*, d.description as debt_description, d.remaining_amount
        FROM debt_payments dp
        JOIN debt d ON dp.debt_id = d.id
        WHERE dp.outgoing_id = :outgoing_id
    ");
    $stmt->execute(['outgoing_id' => $id]);
    $debt_payment = $stmt->fetch();
    
    // Get all debts for dropdown
    $stmt = $pdo->prepare("
        SELECT * FROM debt
        ORDER BY description ASC
    ");
    $stmt->execute();
    $debts = $stmt->fetchAll();
}

// Include header
require_once '../../includes/header.php';
?>

<div class="module-header">
    <div class="module-title">
        <h1>Edit <?php echo $is_debt ? 'Debt Payment' : 'Outgoing Transaction'; ?></h1>
        <p><?php echo $is_debt ? 'Modify debt or loan payment details' : 'Modify expense or outgoing payment details'; ?></p>
    </div>
    <div class="module-actions">
        <a href="index.php<?php echo $is_debt ? '?is_debt=1' : ''; ?>" class="btn btn-light">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if ($isSplitItem): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                This is part of a split transaction.
            </div>
        <?php endif; ?>
        
        <form id="outgoingForm" class="needs-validation ajax-form" action="api.php?action=update" method="post" novalidate>
            <input type="hidden" name="id" value="<?php echo $transaction['id']; ?>">
            <input type="hidden" name="is_debt" value="<?php echo $is_debt ? 1 : 0; ?>">
            
            <?php if (!$isSplitItem): ?>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="description">Description *</label>
                    <input type="text" id="description" name="description" class="form-control" value="<?php echo htmlspecialchars($transaction['description']); ?>" required>
                    <div class="invalid-feedback">Please provide a description.</div>
                </div>
                
                <div class="form-group col-md-6">
                    <label for="amount">Amount (kr) *</label>
                    <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0.01" value="<?php echo $transaction['amount']; ?>" required <?php echo $isSplitItem ? 'readonly' : ''; ?>>
                    <div class="invalid-feedback">Please provide a valid amount greater than 0.</div>
                    <?php if ($isSplitItem): ?>
                        <small class="form-text text-muted">Amount cannot be changed for individual split items. Edit the parent transaction instead.</small>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="date">Date *</label>
                    <input type="date" id="date" name="date" class="form-control datepicker" value="<?php echo $transaction['date']; ?>" required <?php echo $isSplitItem ? 'readonly' : ''; ?>>
                    <div class="invalid-feedback">Please select a date.</div>
                    <?php if ($isSplitItem): ?>
                        <small class="form-text text-muted">Date cannot be changed for individual split items. Edit the parent transaction instead.</small>
                    <?php endif; ?>
                </div>
                
                <div class="form-group col-md-6">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" class="form-select">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" data-color="<?php echo htmlspecialchars($category['color']); ?>" <?php echo ($transaction['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <?php if ($is_debt && !$isSplitItem): ?>
            <div class="form-row">
                <div class="form-group col-md-12">
                    <label for="debt_id">Associated Debt *</label>
                    <select id="debt_id" name="debt_id" class="form-select" required>
                        <option value="">-- Select Debt --</option>
                        <?php foreach ($debts as $debt): ?>
                            <option value="<?php echo $debt['id']; ?>" <?php echo ($debt_payment && $debt_payment['debt_id'] == $debt['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($debt['description']); ?> 
                                (Remaining: <?php echo number_format($debt['remaining_amount'], 2); ?> kr)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Please select an associated debt.</div>
                </div>
            </div>
            <?php elseif (!$is_debt && !$isSplitItem): ?>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="is_fixed">Transaction Type</label>
                    <div class="form-check-inline">
                        <input type="radio" id="is_fixed_0" name="is_fixed" value="0" class="form-check-input" <?php echo !$transaction['is_fixed'] ? 'checked' : ''; ?>>
                        <label for="is_fixed_0" class="form-check-label">Variable Cost</label>
                    </div>
                    <div class="form-check-inline">
                        <input type="radio" id="is_fixed_1" name="is_fixed" value="1" class="form-check-input" <?php echo $transaction['is_fixed'] ? 'checked' : ''; ?>>
                        <label for="is_fixed_1" class="form-check-label">Fixed Cost</label>
                    </div>
                </div>
                
                <div class="form-group col-md-6" id="repeat-container" style="display: <?php echo $transaction['is_fixed'] ? 'block' : 'none'; ?>;">
                    <label for="repeat_interval">Repeat Interval</label>
                    <select id="repeat_interval" name="repeat_interval" class="form-select">
                        <option value="none" <?php echo $transaction['repeat_interval'] === 'none' ? 'selected' : ''; ?>>No Repetition</option>
                        <option value="daily" <?php echo $transaction['repeat_interval'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                        <option value="weekly" <?php echo $transaction['repeat_interval'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                        <option value="monthly" <?php echo $transaction['repeat_interval'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="quarterly" <?php echo $transaction['repeat_interval'] === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                        <option value="yearly" <?php echo $transaction['repeat_interval'] === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row" id="repeat-until-container" style="display: <?php echo ($transaction['is_fixed'] && $transaction['repeat_interval'] !== 'none') ? 'block' : 'none'; ?>;">
                <div class="form-group col-md-6">
                    <label for="repeat_until">Repeat Until</label>
                    <input type="date" id="repeat_until" name="repeat_until" class="form-control datepicker" value="<?php echo $transaction['repeat_until']; ?>">
                    <small class="form-text text-muted">Leave empty for indefinite repetition</small>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($transaction['notes'] ?? ''); ?></textarea>
            </div>
            <?php else: ?>
            <!-- For split items, only show amount and date fields -->
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="amount">Amount (kr) *</label>
                    <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0.01" value="<?php echo $transaction['amount']; ?>" required>
                    <div class="invalid-feedback">Please provide a valid amount greater than 0.</div>
                </div>
                
                <div class="form-group col-md-6">
                    <label for="date">Date *</label>
                    <input type="date" id="date" name="date" class="form-control datepicker" value="<?php echo $transaction['date']; ?>" required>
                    <div class="invalid-feedback">Please select a date.</div>
                </div>
            </div>
            
            <!-- Add hidden fields to maintain data structure -->
            <input type="hidden" name="description" value="<?php echo htmlspecialchars($transaction['description']); ?>">
            <input type="hidden" name="category_id" value="<?php echo $transaction['category_id']; ?>">
            <input type="hidden" name="notes" value="<?php echo htmlspecialchars($transaction['notes'] ?? ''); ?>">
            <?php endif; ?>
            
            <?php if (!$is_debt && !$isSplitItem && !$isSplit): ?>
            <div class="form-divider">
                <h3>Split Transaction (Optional)</h3>
                <p class="text-muted">If you want to split this transaction into multiple parts, add the splits below.</p>
            </div>
            
            <div id="splits-container">
                <!-- Split items will be added here -->
            </div>
            
            <div class="mb-3">
                <button type="button" id="add-split" class="btn btn-light btn-sm">
                    <i class="fas fa-plus"></i> Add Split
                </button>
            </div>
            <?php elseif (!$is_debt && $isSplit): ?>
            <div class="form-divider">
                <h3>Split Items</h3>
                <p class="text-muted">This transaction is split into the following items.</p>
            </div>
            
            <div id="splits-container">
                <?php foreach ($splits as $index => $split): ?>
                    <div class="split-item" data-id="<?php echo $split['id']; ?>">
                        <div class="split-header">
                            <h4>Split #<?php echo $index + 1; ?></h4>
                            <a href="edit.php?id=<?php echo $split['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-edit"></i> Edit Split
                            </a>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label>Amount</label>
                                <p class="form-control-static"><?php echo number_format($split['amount'], 2); ?> kr</p>
                            </div>
                            
                            <div class="form-group col-md-3">
                                <label>Date</label>
                                <p class="form-control-static"><?php echo date('M d, Y', strtotime($split['date'])); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="split-summary">
                <div class="summary-item">
                    <span class="summary-label">Total Amount:</span>
                    <span class="summary-value"><?php echo number_format($transaction['amount'], 2); ?> kr</span>
                </div>
                
                <div class="summary-item">
                    <span class="summary-label">Splits Total:</span>
                    <span class="summary-value">
                        <?php
                        $splitsTotal = array_reduce($splits, function($carry, $item) {
                            return $carry + $item['amount'];
                        }, 0);
                        echo number_format($splitsTotal, 2);
                        ?> kr
                    </span>
                </div>
                
                <?php if ($splitsTotal != $transaction['amount']): ?>
                    <div class="summary-item difference">
                        <span class="summary-label">Difference:</span>
                        <span class="summary-value">
                            <?php echo number_format($transaction['amount'] - $splitsTotal, 2); ?> kr
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Transaction Actions -->
            <div class="transaction-actions">
                <?php if (!$transaction['is_archived']): ?>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="javascript:void(0)" class="btn btn-danger delete-transaction" data-id="<?php echo $transaction['id']; ?>">
                    <i class="fas fa-trash"></i> Delete Transaction
                </a>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> This transaction is archived and cannot be modified.
                </div>
                <?php endif; ?>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!$is_debt && !$isSplitItem): ?>
    // Initialize fixed cost / recurrence functionality
    const isFixedInputs = document.querySelectorAll('input[name="is_fixed"]');
    const repeatContainer = document.getElementById('repeat-container');
    const repeatIntervalSelect = document.getElementById('repeat_interval');
    const repeatUntilContainer = document.getElementById('repeat-until-container');
    
    // Toggle repeat options based on fixed cost selection
    isFixedInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.value === '1' && this.checked) {
                repeatContainer.style.display = 'block';
                toggleRepeatUntil();
            } else {
                repeatContainer.style.display = 'none';
                repeatUntilContainer.style.display = 'none';
            }
        });
    });
    
    // Toggle repeat until field based on repeat interval
    repeatIntervalSelect.addEventListener('change', toggleRepeatUntil);
    
    function toggleRepeatUntil() {
        if (repeatIntervalSelect.value !== 'none') {
            repeatUntilContainer.style.display = 'block';
        } else {
            repeatUntilContainer.style.display = 'none';
        }
    }
    
    <?php if (!$isSplit): ?>
    // Initialize split functionality
    let splitCounter = 0;
    const splitsContainer = document.getElementById('splits-container');
    const addSplitButton = document.getElementById('add-split');
    const mainAmountInput = document.getElementById('amount');
    const mainDateInput = document.getElementById('date');
    
    // Add split item
    addSplitButton.addEventListener('click', function() {
        addSplitItem();
    });
    
    function addSplitItem() {
        splitCounter++;
        
        // Get the current main date value
        const currentMainDate = mainDateInput.value || "<?php echo date('Y-m-d'); ?>";
        
        const splitItem = document.createElement('div');
        splitItem.className = 'split-item';
        splitItem.dataset.id = splitCounter;
        splitItem.innerHTML = `
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="split_amount_${splitCounter}">Amount (kr)</label>
                    <input type="number" id="split_amount_${splitCounter}" name="splits[${splitCounter}][amount]" class="form-control split-amount" step="0.01" min="0.01">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="split_date_${splitCounter}">Date</label>
                    <input type="date" id="split_date_${splitCounter}" name="splits[${splitCounter}][date]" class="form-control datepicker" value="${currentMainDate}">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-2">
                    <button type="button" class="btn btn-danger form-control remove-split" data-id="${splitCounter}">
                        <i class="fas fa-trash"></i> Delete Split
                    </button>
                </div>
            </div>
            
            <!-- Hidden fields with default values to maintain API compatibility -->
            <input type="hidden" name="splits[${splitCounter}][description]" value="Split ${splitCounter}">
            <input type="hidden" name="splits[${splitCounter}][category_id]" value="">
            <input type="hidden" name="splits[${splitCounter}][notes]" value="">
            
            <div class="split-divider"></div>
        `;
        
        splitsContainer.appendChild(splitItem);
        
        // Add event listener to remove button
        splitItem.querySelector('.remove-split').addEventListener('click', function() {
            splitsContainer.removeChild(splitItem);
            updateSplitTotals();
        });
        
        // Add event listener to amount input
        splitItem.querySelector('.split-amount').addEventListener('input', updateSplitTotals);
        
        // Ensure the split date stays in sync with main date changes
        const splitDateInput = document.getElementById(`split_date_${splitCounter}`);
        mainDateInput.addEventListener('change', function() {
            if (splitDateInput) {
                splitDateInput.value = this.value;
            }
        });
        
        updateSplitTotals();
    }
    
    // Update split totals
    function updateSplitTotals() {
        const mainAmount = parseFloat(mainAmountInput.value) || 0;
        let splitTotal = 0;
        
        // Calculate total of all splits
        document.querySelectorAll('.split-amount').forEach(input => {
            splitTotal += parseFloat(input.value) || 0;
        });
        
        // Display warning if split total exceeds main amount
        const warningEl = document.getElementById('split-warning');
        
        if (splitTotal > mainAmount && mainAmount > 0) {
            if (!warningEl) {
                const warning = document.createElement('div');
                warning.id = 'split-warning';
                warning.className = 'alert alert-warning mt-3';
                warning.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i>
                    Split total (${splitTotal.toFixed(2)} kr) exceeds the main transaction amount (${mainAmount.toFixed(2)} kr).
                `;
                splitsContainer.parentNode.insertBefore(warning, splitsContainer);
            } else {
                warningEl.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i>
                    Split total (${splitTotal.toFixed(2)} kr) exceeds the main transaction amount (${mainAmount.toFixed(2)} kr).
                `;
            }
        } else if (warningEl) {
            warningEl.remove();
        }
    }
    
    // Listen for changes in main amount
    mainAmountInput.addEventListener('input', updateSplitTotals);
    <?php endif; ?>
    <?php endif; ?>
    
    <?php if ($is_debt && !$isSplitItem): ?>
    // Initialize debt selection
    const debtSelect = document.getElementById('debt_id');
    const amountInput = document.getElementById('amount');
    
    if (debtSelect) {
        debtSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                // Extract remaining amount from option text
                const remainingText = selectedOption.textContent.match(/Remaining: ([0-9,]+\.[0-9]+) kr/);
                if (remainingText && remainingText[1]) {
                    // Offer to update amount if it's not already set
                    const remainingAmount = parseFloat(remainingText[1].replace(/,/g, ''));
                    if (confirm(`Do you want to set the payment amount to the remaining balance of ${remainingAmount.toFixed(2)} kr?`)) {
                        amountInput.value = remainingAmount.toFixed(2);
                    }
                }
            }
        });
    }
    <?php endif; ?>
    
    // Form submission
    document.getElementById('outgoingForm').addEventListener('submit', function(event) {
        if (!this.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        this.classList.add('was-validated');
    });
});
</script>

<style>
.form-divider {
    margin: 30px 0 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--gray-light);
}

.form-divider h3 {
    font-size: 18px;
    font-weight: 500;
    margin-bottom: 5px;
}

.form-check-inline {
    display: inline-flex;
    align-items: center;
    margin-right: 15px;
}

.form-check-input {
    margin-right: 5px;
}

.form-check-label {
    margin-bottom: 0;
}

.split-item {
    background-color: rgba(236, 240, 241, 0.3);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}

.split-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.split-header h4 {
    font-size: 16px;
    font-weight: 500;
    margin: 0;
}

.split-divider {
    border-top: 1px dashed var(--gray-light);
    margin-top: 10px;
}

.form-control-static {
    padding: 7px 0;
    margin-bottom: 0;
    min-height: 34px;
}

.split-summary {
    background-color: var(--gray-light);
    border-radius: 8px;
    padding: 15px;
    margin-top: 20px;
    margin-bottom: 20px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
}

.summary-label {
    font-weight: 500;
}

.summary-value {
    font-weight: 600;
}

.difference {
    border-top: 1px dashed var(--gray);
    padding-top: 10px;
    margin-top: 5px;
}

.form-actions {
    margin-top: 30px;
    display: flex;
    gap: 10px;
}

.alert-info {
    background-color: rgba(52, 152, 219, 0.2);
    color: var(--primary-dark);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-info i, .alert-warning i {
    margin-right: 8px;
}

.alert-warning {
    background-color: rgba(243, 156, 18, 0.2);
    color: #d35400;
    padding: 15px;
    border-radius: 8px;
}
</style>

<?php
// Include footer
require_once '../../includes/footer.php';
?>