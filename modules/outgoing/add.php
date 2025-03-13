<?php
/**
 * Add Outgoing Transaction
 */

// Include database connection
require_once '../../config/database.php';

// Check if this is a debt transaction
$is_debt = isset($_GET['is_debt']) && $_GET['is_debt'] == 1 ? 1 : 0;

// Get categories for dropdown
$stmt = $pdo->prepare("
    SELECT * FROM categories
    WHERE type IN ('outgoing', 'both')
    ORDER BY name ASC
");
$stmt->execute();
$categories = $stmt->fetchAll();

// If it's a debt transaction, load the debts for dropdown
$debts = [];
if ($is_debt) {
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
        <h1>Add <?php echo $is_debt ? 'Debt Payment' : 'Outgoing Transaction'; ?></h1>
        <p><?php echo $is_debt ? 'Record a new debt or loan payment' : 'Record a new expense or outgoing payment'; ?></p>
    </div>
    <div class="module-actions">
        <a href="index.php<?php echo $is_debt ? '?is_debt=1' : ''; ?>" class="btn btn-light">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form id="outgoingForm" class="needs-validation ajax-form" action="api.php?action=add" method="post" novalidate>
            <input type="hidden" name="is_debt" value="<?php echo $is_debt; ?>">
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="description">Description *</label>
                    <input type="text" id="description" name="description" class="form-control" required>
                    <div class="invalid-feedback">Please provide a description.</div>
                </div>
                
                <div class="form-group col-md-6">
                    <label for="amount">Amount (kr) *</label>
                    <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0.01" required>
                    <div class="invalid-feedback">Please provide a valid amount greater than 0.</div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="date">Date *</label>
                    <input type="date" id="date" name="date" class="form-control datepicker" required>
                    <div class="invalid-feedback">Please select a date.</div>
                </div>
                
                <div class="form-group col-md-6">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" class="form-select">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" data-color="<?php echo htmlspecialchars($category['color']); ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <?php if ($is_debt): ?>
            <div class="form-row">
                <div class="form-group col-md-12">
                    <label for="debt_id">Associated Debt *</label>
                    <select id="debt_id" name="debt_id" class="form-select" required>
                        <option value="">-- Select Debt --</option>
                        <?php foreach ($debts as $debt): ?>
                            <option value="<?php echo $debt['id']; ?>">
                                <?php echo htmlspecialchars($debt['description']); ?> 
                                (Remaining: <?php echo number_format($debt['remaining_amount'], 2); ?> kr)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Please select an associated debt.</div>
                    
                    <?php if (empty($debts)): ?>
                        <div class="alert alert-warning mt-2">
                            <i class="fas fa-exclamation-triangle"></i>
                            No debts found. <a href="../debt/add.php">Add a new debt</a> first.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="is_fixed">Transaction Type</label>
                    <div class="form-check-inline">
                        <input type="radio" id="is_fixed_0" name="is_fixed" value="0" class="form-check-input" checked>
                        <label for="is_fixed_0" class="form-check-label">Variable Cost</label>
                    </div>
                    <div class="form-check-inline">
                        <input type="radio" id="is_fixed_1" name="is_fixed" value="1" class="form-check-input">
                        <label for="is_fixed_1" class="form-check-label">Fixed Cost</label>
                    </div>
                </div>
                
                <div class="form-group col-md-6" id="repeat-container" style="display: none;">
                    <label for="repeat_interval">Repeat Interval</label>
                    <select id="repeat_interval" name="repeat_interval" class="form-select">
                        <option value="none">No Repetition</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row" id="repeat-until-container" style="display: none;">
                <div class="form-group col-md-6">
                    <label for="repeat_until">Repeat Until</label>
                    <input type="date" id="repeat_until" name="repeat_until" class="form-control datepicker">
                    <small class="form-text text-muted">Leave empty for indefinite repetition</small>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
            </div>
            
            <?php if (!$is_debt): ?>
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
            <?php endif; ?>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Transaction
                </button>
                
                <a href="index.php<?php echo $is_debt ? '?is_debt=1' : ''; ?>" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!$is_debt): ?>
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
            } else {
                repeatContainer.style.display = 'none';
                repeatIntervalSelect.value = 'none';
                repeatUntilContainer.style.display = 'none';
            }
        });
    });
    
    // Toggle repeat until field based on repeat interval
    repeatIntervalSelect.addEventListener('change', function() {
        if (this.value !== 'none') {
            repeatUntilContainer.style.display = 'block';
        } else {
            repeatUntilContainer.style.display = 'none';
        }
    });
    
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
        
        // Add event listener to sync main date to split date initially
        const mainDateInput = document.getElementById('date');
        const splitDateInput = document.getElementById(`split_date_${splitCounter}`);
        
        mainDateInput.addEventListener('change', function() {
            if (!splitDateInput.value) {
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
    
    <?php if ($is_debt): ?>
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
                    // Set amount to remaining amount by default
                    const remainingAmount = parseFloat(remainingText[1].replace(/,/g, ''));
                    if (!amountInput.value || amountInput.value === '0') {
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

.split-divider {
    border-top: 1px dashed var(--gray-light);
    margin-top: 10px;
}

.form-actions {
    margin-top: 30px;
    display: flex;
    gap: 10px;
}

.alert-warning {
    background-color: rgba(243, 156, 18, 0.2);
    color: #d35400;
    padding: 15px;
    border-radius: 8px;
}

.alert-warning i {
    margin-right: 8px;
}
</style>

<?php
// Include footer
require_once '../../includes/footer.php';
?>