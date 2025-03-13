<?php
/**
 * Edit Incoming Transaction
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
    SELECT * FROM incoming
    WHERE id = :id
");
$stmt->execute(['id' => $id]);
$transaction = $stmt->fetch();

// Redirect if transaction not found
if (!$transaction) {
    header('Location: index.php');
    exit;
}

// Check if it's a split transaction or a split item
$isSplit = (bool)$transaction['is_split'];
$isSplitItem = !empty($transaction['parent_id']);

// If it's a split item, get the parent transaction
$parentTransaction = null;
if ($isSplitItem) {
    $stmt = $pdo->prepare("
        SELECT * FROM incoming
        WHERE id = :id
    ");
    $stmt->execute(['id' => $transaction['parent_id']]);
    $parentTransaction = $stmt->fetch();
}

// Get splits if it's a split transaction
$splits = [];
if ($isSplit) {
    $stmt = $pdo->prepare("
        SELECT * FROM incoming
        WHERE parent_id = :parent_id
        ORDER BY amount DESC
    ");
    $stmt->execute(['parent_id' => $id]);
    $splits = $stmt->fetchAll();
}

// Get categories for dropdown
$stmt = $pdo->prepare("
    SELECT * FROM categories
    WHERE type IN ('incoming', 'both')
    ORDER BY name ASC
");
$stmt->execute();
$categories = $stmt->fetchAll();

// Include header
require_once '../../includes/header.php';
?>

<div class="module-header">
    <div class="module-title">
        <h1>Edit Incoming Transaction</h1>
        <p>Modify income or revenue source details</p>
    </div>
    <div class="module-actions">
        <a href="index.php" class="btn btn-light">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if ($isSplitItem): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                This is part of a split transaction. Some changes may affect the parent transaction.
            </div>
        <?php endif; ?>
        
        <form id="incomingForm" class="needs-validation ajax-form" action="api.php?action=update" method="post" novalidate>
            <input type="hidden" name="id" value="<?php echo $transaction['id']; ?>">
            
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
                    <input type="date" id="date" name="date" class="form-control datepicker" value="<?php echo $transaction['date']; ?>" required <?php echo $isSplitItem ? '' : ''; ?>>
                    <div class="invalid-feedback">Please select a date.</div>
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
            
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($transaction['notes'] ?? ''); ?></textarea>
            </div>
            
            <?php if (!$isSplitItem && !$isSplit): ?>
                <div class="form-divider">
                    <h3>Split Transaction (Optional)</h3>
                    <p class="text-muted">If you want to split this transaction into multiple categories, add the splits below.</p>
                </div>
                
                <div id="splits-container">
                    <!-- Split items will be added here -->
                </div>
                
                <div class="mb-3">
                    <button type="button" id="add-split" class="btn btn-light btn-sm">
                        <i class="fas fa-plus"></i> Add Split
                    </button>
                </div>
            <?php elseif ($isSplit): ?>
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
                                <div class="form-group col-md-6">
                                    <label>Description</label>
                                    <p class="form-control-static"><?php echo htmlspecialchars($split['description']); ?></p>
                                </div>
                                
                                <div class="form-group col-md-3">
                                    <label>Amount</label>
                                    <p class="form-control-static"><?php echo number_format($split['amount'], 2); ?> kr</p>
                                </div>
                                
                                <div class="form-group col-md-3">
                                    <label>Category</label>
                                    <p class="form-control-static">
                                        <?php
                                        if ($split['category_id']) {
                                            foreach ($categories as $category) {
                                                if ($category['id'] == $split['category_id']) {
                                                    echo htmlspecialchars($category['name']);
                                                    break;
                                                }
                                            }
                                        } else {
                                            echo '<span class="text-muted">Uncategorized</span>';
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label>Date</label>
                                    <p class="form-control-static"><?php echo date('M d, Y', strtotime($split['date'])); ?></p>
                                </div>
                            </div>
                            
                            <?php if ($split['notes']): ?>
                                <div class="form-group">
                                    <label>Notes</label>
                                    <p class="form-control-static"><?php echo nl2br(htmlspecialchars($split['notes'])); ?></p>
                                </div>
                            <?php endif; ?>
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
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                
                <a href="index.php" class="btn btn-light">Cancel</a>
                
                <?php if (!$isSplitItem): ?>
                    <a href="api.php?action=delete&id=<?php echo $transaction['id']; ?>" class="btn btn-danger delete-btn" data-name="<?php echo htmlspecialchars($transaction['description']); ?>">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!$isSplitItem && !$isSplit): ?>
    // Initialize split functionality
    let splitCounter = 0;
    const splitsContainer = document.getElementById('splits-container');
    const addSplitButton = document.getElementById('add-split');
    const mainAmountInput = document.getElementById('amount');
    
    // Add split item
    addSplitButton.addEventListener('click', function() {
        addSplitItem();
    });
    
    function addSplitItem() {
        splitCounter++;
        
        const splitItem = document.createElement('div');
        splitItem.className = 'split-item';
        splitItem.dataset.id = splitCounter;
        splitItem.innerHTML = `
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="split_description_${splitCounter}">Split Description</label>
                    <input type="text" id="split_description_${splitCounter}" name="splits[${splitCounter}][description]" class="form-control">
                </div>
                
                <div class="form-group col-md-3">
                    <label for="split_amount_${splitCounter}">Amount (kr)</label>
                    <input type="number" id="split_amount_${splitCounter}" name="splits[${splitCounter}][amount]" class="form-control split-amount" step="0.01" min="0.01">
                </div>
                
                <div class="form-group col-md-4">
                    <label for="split_category_${splitCounter}">Category</label>
                    <select id="split_category_${splitCounter}" name="splits[${splitCounter}][category_id]" class="form-select">
                        <option value="">-- Select Category --</option>
                        ${Array.from(document.getElementById('category_id').options)
                            .map(opt => opt.value ? `<option value="${opt.value}" data-color="${opt.dataset.color}">${opt.text}</option>` : '')
                            .join('')}
                    </select>
                </div>
                
                <div class="form-group col-md-1">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-danger form-control remove-split" data-id="${splitCounter}">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="split_date_${splitCounter}">Date</label>
                    <input type="date" id="split_date_${splitCounter}" name="splits[${splitCounter}][date]" class="form-control datepicker" value="${document.getElementById('date').value}">
                </div>
            </div>
            
            <div class="form-group">
                <label for="split_notes_${splitCounter}">Notes</label>
                <textarea id="split_notes_${splitCounter}" name="splits[${splitCounter}][notes]" class="form-control" rows="2"></textarea>
            </div>
            
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
    
    // Form submission
    document.getElementById('incomingForm').addEventListener('submit', function(event) {
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

.alert-info i {
    margin-right: 8px;
}
</style>

<?php
// Include footer
require_once '../../includes/footer.php';
?>