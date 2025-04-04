<?php
/**
 * Add Incoming Transaction
 */

// Include database connection
require_once '../../config/database.php';

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
        <h1>Add Incoming Transaction</h1>
        <p>Record a new income or revenue source</p>
    </div>
    <div class="module-actions">
        <a href="index.php" class="btn btn-light">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form id="incomingForm" class="needs-validation ajax-form" action="api.php?action=add" method="post" novalidate>
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
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="is_fixed">Transaction Type</label>
                    <div class="form-check-inline">
                        <input type="radio" id="is_fixed_0" name="is_fixed" value="0" class="form-check-input" checked>
                        <label for="is_fixed_0" class="form-check-label">One-time Income</label>
                    </div>
                    <div class="form-check-inline">
                        <input type="radio" id="is_fixed_1" name="is_fixed" value="1" class="form-check-input">
                        <label for="is_fixed_1" class="form-check-label">Recurring Income</label>
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
            
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
            </div>
            
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
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Transaction
                </button>
                
                <a href="index.php" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize fixed income / recurrence functionality
    const isFixedInputs = document.querySelectorAll('input[name="is_fixed"]');
    const repeatContainer = document.getElementById('repeat-container');
    const repeatIntervalSelect = document.getElementById('repeat_interval');
    const repeatUntilContainer = document.getElementById('repeat-until-container');
    
    // Toggle repeat options based on fixed income selection
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
                <div class="form-group col-md-3">
                    <label for="split_amount_${splitCounter}">Amount (kr)</label>
                    <input type="number" id="split_amount_${splitCounter}" name="splits[${splitCounter}][amount]" class="form-control split-amount" step="0.01" min="0.01">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="split_date_${splitCounter}">Date</label>
                    <input type="date" id="split_date_${splitCounter}" name="splits[${splitCounter}][date]" class="form-control datepicker" value="${document.getElementById('date').value}">
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
</style>

<?php
// Include footer
require_once '../../includes/footer.php';
?>