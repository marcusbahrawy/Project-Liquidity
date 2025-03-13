<?php
/**
 * Add Debt
 */

// Include database connection
require_once '../../config/database.php';

// Get categories for dropdown
$stmt = $pdo->prepare("
    SELECT * FROM categories
    WHERE type IN ('outgoing', 'both')
    ORDER BY name ASC
");
$stmt->execute();
$categories = $stmt->fetchAll();

// Include header
require_once '../../includes/header.php';
?>

<div class="module-header">
    <div class="module-title">
        <h1>Add New Debt</h1>
        <p>Record a new loan, mortgage, or other debt</p>
    </div>
    <div class="module-actions">
        <a href="index.php" class="btn btn-light">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form id="debtForm" class="needs-validation ajax-form" action="api.php?action=add" method="post" novalidate>
            <div class="form-row">
                <div class="form-group col-md-8">
                    <label for="description">Description *</label>
                    <input type="text" id="description" name="description" class="form-control" required>
                    <div class="invalid-feedback">Please provide a description.</div>
                </div>
                
                <div class="form-group col-md-4">
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
                <div class="form-group col-md-4">
                    <label for="total_amount">Total Amount *</label>
                    <input type="number" id="total_amount" name="total_amount" class="form-control" step="0.01" min="0.01" required>
                    <div class="invalid-feedback">Please provide a valid amount greater than 0.</div>
                </div>
                
                <div class="form-group col-md-4">
                    <label for="remaining_amount">Remaining Amount *</label>
                    <input type="number" id="remaining_amount" name="remaining_amount" class="form-control" step="0.01" min="0">
                    <div class="invalid-feedback">Please provide a valid remaining amount.</div>
                    <small class="form-text text-muted">Leave empty to use the total amount</small>
                </div>
                
                <div class="form-group col-md-4">
                    <label for="interest_rate">Interest Rate (%)</label>
                    <input type="number" id="interest_rate" name="interest_rate" class="form-control" step="0.01" min="0">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="start_date">Start Date *</label>
                    <input type="date" id="start_date" name="start_date" class="form-control datepicker" required>
                    <div class="invalid-feedback">Please select a start date.</div>
                </div>
                
                <div class="form-group col-md-4">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control datepicker">
                    <small class="form-text text-muted">Leave empty if no specific end date</small>
                </div>
            </div>
            
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="form-divider">
                <h3>Initial Payment (Optional)</h3>
                <p class="text-muted">If you already made a payment, you can record it here</p>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="initial_payment">Payment Amount</label>
                    <input type="number" id="initial_payment" name="initial_payment" class="form-control" step="0.01" min="0">
                </div>
                
                <div class="form-group col-md-4">
                    <label for="payment_date">Payment Date</label>
                    <input type="date" id="payment_date" name="payment_date" class="form-control datepicker">
                </div>
                
                <div class="form-group col-md-4">
                    <label for="payment_description">Payment Description</label>
                    <input type="text" id="payment_description" name="payment_description" class="form-control" placeholder="Initial payment">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Debt
                </button>
                
                <a href="index.php" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update remaining amount when total amount changes
    const totalAmountInput = document.getElementById('total_amount');
    const remainingAmountInput = document.getElementById('remaining_amount');
    
    if (totalAmountInput && remainingAmountInput) {
        totalAmountInput.addEventListener('input', function() {
            if (!remainingAmountInput.value || parseFloat(remainingAmountInput.value) === 0) {
                remainingAmountInput.value = this.value;
            }
        });
    }
    
    // Initial payment can't be larger than total amount
    const initialPaymentInput = document.getElementById('initial_payment');
    
    if (initialPaymentInput && totalAmountInput) {
        initialPaymentInput.addEventListener('input', function() {
            const totalAmount = parseFloat(totalAmountInput.value) || 0;
            const initialPayment = parseFloat(this.value) || 0;
            
            if (initialPayment > totalAmount) {
                this.setCustomValidity('Initial payment cannot exceed total amount');
            } else {
                this.setCustomValidity('');
                
                // Update remaining amount based on initial payment
                if (totalAmount > 0 && initialPayment > 0) {
                    remainingAmountInput.value = (totalAmount - initialPayment).toFixed(2);
                }
            }
        });
        
        // Also update validation when total amount changes
        totalAmountInput.addEventListener('input', function() {
            const totalAmount = parseFloat(this.value) || 0;
            const initialPayment = parseFloat(initialPaymentInput.value) || 0;
            
            if (initialPayment > totalAmount) {
                initialPaymentInput.setCustomValidity('Initial payment cannot exceed total amount');
            } else {
                initialPaymentInput.setCustomValidity('');
            }
        });
    }
    
    // Ensure payment date has a default value if payment amount is provided
    const paymentDateInput = document.getElementById('payment_date');
    
    if (paymentDateInput && initialPaymentInput) {
        initialPaymentInput.addEventListener('input', function() {
            if (parseFloat(this.value) > 0 && !paymentDateInput.value) {
                // Set default to today
                const today = new Date();
                const year = today.getFullYear();
                const month = String(today.getMonth() + 1).padStart(2, '0');
                const day = String(today.getDate()).padStart(2, '0');
                paymentDateInput.value = `${year}-${month}-${day}`;
            }
        });
    }
    
    // Form submission
    document.getElementById('debtForm').addEventListener('submit', function(event) {
        if (!this.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        // Ensure remaining amount has a value
        if (!remainingAmountInput.value && totalAmountInput.value) {
            remainingAmountInput.value = totalAmountInput.value;
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

.form-actions {
    margin-top: 30px;
    display: flex;
    gap: 10px;
}

@media (max-width: 768px) {
    .col-md-4, .col-md-8 {
        flex: 0 0 100%;
        max-width: 100%;
    }
}
</style>

<?php
// Include footer
require_once '../../includes/footer.php';