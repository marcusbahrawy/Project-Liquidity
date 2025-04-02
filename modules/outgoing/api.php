<?php
/**
 * Outgoing API
 * 
 * Handles AJAX requests and form submissions for outgoing transactions.
 */

// Include database connection
require_once '../../config/database.php';

// Check if action is specified
if (!isset($_GET['action'])) {
    jsonResponse(false, 'No action specified');
}

$action = $_GET['action'];

// Handle different actions
switch ($action) {
    case 'add':
        addTransaction();
        break;
        
    case 'update':
        updateTransaction();
        break;
        
    case 'delete':
        deleteTransaction();
        break;
        
    case 'get':
        getTransaction();
        break;
        
    case 'export':
        exportTransactions();
        break;
        
    default:
        jsonResponse(false, 'Invalid action specified');
}

/**
 * Add new transaction
 */
function addTransaction() {
    global $pdo;
    
    // Check if required fields are provided
    $requiredFields = ['description', 'amount', 'date'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            jsonResponse(false, "Field '{$field}' is required");
        }
    }
    
    // Validate amount
    if (!is_numeric($_POST['amount']) || $_POST['amount'] <= 0) {
        jsonResponse(false, 'Amount must be a positive number');
    }
    
    // Check if it's a debt payment
    $is_debt = isset($_POST['is_debt']) && $_POST['is_debt'] == 1;
    
    // If it's a debt payment, validate debt_id
    if ($is_debt && (!isset($_POST['debt_id']) || empty($_POST['debt_id']))) {
        jsonResponse(false, 'Debt ID is required for debt payments');
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Check if we have splits
        $hasSplits = isset($_POST['splits']) && is_array($_POST['splits']) && count($_POST['splits']) > 0;
        
        // Prepare data for fixed costs
        $is_fixed = isset($_POST['is_fixed']) && $_POST['is_fixed'] == 1 ? 1 : 0;
        $repeat_interval = $is_fixed && isset($_POST['repeat_interval']) ? $_POST['repeat_interval'] : 'none';
        $repeat_until = $is_fixed && $repeat_interval !== 'none' && !empty($_POST['repeat_until']) ? $_POST['repeat_until'] : null;
        
        // Prepare main transaction data
        $data = [
            'description' => $_POST['description'],
            'amount' => $_POST['amount'],
            'date' => $_POST['date'],
            'category_id' => !empty($_POST['category_id']) ? $_POST['category_id'] : null,
            'is_fixed' => $is_fixed,
            'repeat_interval' => $repeat_interval,
            'repeat_until' => $repeat_until,
            'notes' => $_POST['notes'] ?? null,
            'is_split' => $hasSplits ? 1 : 0,
            'is_debt' => $is_debt ? 1 : 0
        ];
        
        // Insert main transaction
        $stmt = $pdo->prepare("
            INSERT INTO outgoing (
                description, amount, date, category_id, is_fixed, 
                repeat_interval, repeat_until, notes, is_split, is_debt, created_at
            )
            VALUES (
                :description, :amount, :date, :category_id, :is_fixed, 
                :repeat_interval, :repeat_until, :notes, :is_split, :is_debt, NOW()
            )
        ");
        $stmt->execute($data);
        
        $outgoingId = $pdo->lastInsertId();
        
        // If it's a debt payment, insert into debt_payments table
        if ($is_debt) {
            // Get debt information
            $stmt = $pdo->prepare("SELECT * FROM debt WHERE id = :id");
            $stmt->execute(['id' => $_POST['debt_id']]);
            $debt = $stmt->fetch();
            
            if (!$debt) {
                throw new Exception('Debt not found');
            }
            
            // Insert debt payment
            $stmt = $pdo->prepare("
                INSERT INTO debt_payments (debt_id, outgoing_id, amount, created_at)
                VALUES (:debt_id, :outgoing_id, :amount, NOW())
            ");
            $stmt->execute([
                'debt_id' => $_POST['debt_id'],
                'outgoing_id' => $outgoingId,
                'amount' => $_POST['amount']
            ]);
            
            // Update debt remaining amount
            $remaining = max(0, $debt['remaining_amount'] - $_POST['amount']);
            $stmt = $pdo->prepare("
                UPDATE debt
                SET remaining_amount = :remaining_amount
                WHERE id = :id
            ");
            $stmt->execute([
                'remaining_amount' => $remaining,
                'id' => $_POST['debt_id']
            ]);
        }
        
        // Insert splits if any
        if ($hasSplits) {
            $totalSplitAmount = 0;
            
            foreach ($_POST['splits'] as $split) {
                // Skip if description or amount is empty
                if (empty($split['description']) || empty($split['amount'])) {
                    continue;
                }
                
                // Validate split amount
                if (!is_numeric($split['amount']) || $split['amount'] <= 0) {
                    continue;
                }
                
                $totalSplitAmount += $split['amount'];
                
                // Get the split date or use parent date if not provided
                $splitDate = !empty($split['date']) ? $split['date'] : $_POST['date'];
                
                // Insert split transaction
                $stmt = $pdo->prepare("
                    INSERT INTO outgoing (
                        description, amount, date, category_id, notes, 
                        parent_id, is_debt, is_split, created_at
                    )
                    VALUES (
                        :description, :amount, :date, :category_id, :notes, 
                        :parent_id, :is_debt, 1, NOW()
                    )
                ");
                
                $stmt->execute([
                    'description' => $split['description'],
                    'amount' => $split['amount'],
                    'date' => $splitDate,
                    'category_id' => !empty($split['category_id']) ? $split['category_id'] : null,
                    'notes' => $split['notes'] ?? null,
                    'parent_id' => $outgoingId,
                    'is_debt' => $is_debt ? 1 : 0
                ]);
            }
            
            // Update main transaction amount if needed
            if ($totalSplitAmount > 0 && $totalSplitAmount != $_POST['amount']) {
                $stmt = $pdo->prepare("
                    UPDATE outgoing SET amount = :amount WHERE id = :id
                ");
                $stmt->execute([
                    'amount' => $totalSplitAmount,
                    'id' => $outgoingId
                ]);
                
                // If it's a debt payment, update the debt_payments table
                if ($is_debt) {
                    $stmt = $pdo->prepare("
                        UPDATE debt_payments SET amount = :amount WHERE outgoing_id = :outgoing_id
                    ");
                    $stmt->execute([
                        'amount' => $totalSplitAmount,
                        'outgoing_id' => $outgoingId
                    ]);
                    
                    // Update debt remaining amount with the updated total
                    $remaining = max(0, $debt['remaining_amount'] - $totalSplitAmount);
                    $stmt = $pdo->prepare("
                        UPDATE debt
                        SET remaining_amount = :remaining_amount
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'remaining_amount' => $remaining,
                        'id' => $_POST['debt_id']
                    ]);
                }
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        jsonResponse(true, 'Transaction added successfully', [
            'redirect' => 'index.php' . ($is_debt ? '?is_debt=1' : '')
        ]);
    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        
        jsonResponse(false, 'Error adding transaction: ' . $e->getMessage());
    }
}

/**
 * Update transaction
 */
function updateTransaction() {
    global $pdo;
    
    // Check if ID is provided
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        jsonResponse(false, 'Transaction ID is required');
    }
    
    // Get transaction
    $stmt = $pdo->prepare("SELECT * FROM outgoing WHERE id = :id");
    $stmt->execute(['id' => $_POST['id']]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        jsonResponse(false, 'Transaction not found');
    }
    
    // Check if required fields are provided
    $requiredFields = ['description', 'amount', 'date'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            jsonResponse(false, "Field '{$field}' is required");
        }
    }
    
    // Validate amount
    if (!is_numeric($_POST['amount']) || $_POST['amount'] <= 0) {
        jsonResponse(false, 'Amount must be a positive number');
    }
    
    // Check if it's a debt transaction
    $is_debt = isset($_POST['is_debt']) && $_POST['is_debt'] == 1;
    
    // If it's a debt payment, validate debt_id
    if ($is_debt && (!isset($_POST['debt_id']) || empty($_POST['debt_id'])) && !$transaction['parent_id']) {
        jsonResponse(false, 'Debt ID is required for debt payments');
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Check if we have splits
        $hasSplits = isset($_POST['splits']) && is_array($_POST['splits']) && count($_POST['splits']) > 0;
        
        // Check if this is a split item (has parent_id)
        $isSplitItem = !empty($transaction['parent_id']);
        
        if ($isSplitItem) {
            // Only update description, category, and notes for split items
            $stmt = $pdo->prepare("
                UPDATE outgoing
                SET description = :description, category_id = :category_id, notes = :notes
                WHERE id = :id
            ");
            
            $stmt->execute([
                'description' => $_POST['description'],
                'category_id' => !empty($_POST['category_id']) ? $_POST['category_id'] : null,
                'notes' => $_POST['notes'] ?? null,
                'id' => $_POST['id']
            ]);
            
            // Update parent transaction's amount
            $stmt = $pdo->prepare("
                SELECT SUM(amount) as total
                FROM outgoing
                WHERE parent_id = :parent_id
            ");
            $stmt->execute(['parent_id' => $transaction['parent_id']]);
            $result = $stmt->fetch();
            
            if ($result && $result['total'] > 0) {
                $stmt = $pdo->prepare("
                    UPDATE outgoing
                    SET amount = :amount
                    WHERE id = :id
                ");
                $stmt->execute([
                    'amount' => $result['total'],
                    'id' => $transaction['parent_id']
                ]);
                
                // If parent is a debt payment, update debt_payments
                $stmt = $pdo->prepare("
                    SELECT * FROM outgoing WHERE id = :id
                ");
                $stmt->execute(['id' => $transaction['parent_id']]);
                $parentTx = $stmt->fetch();
                
                if ($parentTx && $parentTx['is_debt']) {
                    // Update debt payment amount
                    $stmt = $pdo->prepare("
                        UPDATE debt_payments
                        SET amount = :amount
                        WHERE outgoing_id = :outgoing_id
                    ");
                    $stmt->execute([
                        'amount' => $result['total'],
                        'outgoing_id' => $transaction['parent_id']
                    ]);
                    
                    // Get debt payment to update debt
                    $stmt = $pdo->prepare("
                        SELECT * FROM debt_payments
                        WHERE outgoing_id = :outgoing_id
                    ");
                    $stmt->execute(['outgoing_id' => $transaction['parent_id']]);
                    $debtPayment = $stmt->fetch();
                    
                    if ($debtPayment) {
                        // Get debt
                        $stmt = $pdo->prepare("
                            SELECT * FROM debt
                            WHERE id = :id
                        ");
                        $stmt->execute(['id' => $debtPayment['debt_id']]);
                        $debt = $stmt->fetch();
                        
                        if ($debt) {
                            // Calculate new remaining amount
                            $originalAmount = $transaction['amount'];
                            $newAmount = $result['total'];
                            $amountDiff = $newAmount - $originalAmount;
                            
                            $remaining = max(0, $debt['remaining_amount'] - $amountDiff);
                            
                            // Update debt
                            $stmt = $pdo->prepare("
                                UPDATE debt
                                SET remaining_amount = :remaining_amount
                                WHERE id = :id
                            ");
                            $stmt->execute([
                                'remaining_amount' => $remaining,
                                'id' => $debt['id']
                            ]);
                        }
                    }
                }
            }
        } else {
            // Update main transaction
            // Prepare data for fixed costs
            $is_fixed = isset($_POST['is_fixed']) && $_POST['is_fixed'] == 1 ? 1 : 0;
            $repeat_interval = $is_fixed && isset($_POST['repeat_interval']) ? $_POST['repeat_interval'] : 'none';
            $repeat_until = $is_fixed && $repeat_interval !== 'none' && !empty($_POST['repeat_until']) ? $_POST['repeat_until'] : null;
            
            $stmt = $pdo->prepare("
                UPDATE outgoing
                SET description = :description, amount = :amount, date = :date, 
                    category_id = :category_id, is_fixed = :is_fixed, 
                    repeat_interval = :repeat_interval, repeat_until = :repeat_until, 
                    notes = :notes, is_split = :is_split
                WHERE id = :id
            ");
            
            $stmt->execute([
                'description' => $_POST['description'],
                'amount' => $_POST['amount'],
                'date' => $_POST['date'],
                'category_id' => !empty($_POST['category_id']) ? $_POST['category_id'] : null,
                'is_fixed' => $is_fixed,
                'repeat_interval' => $repeat_interval,
                'repeat_until' => $repeat_until,
                'notes' => $_POST['notes'] ?? null,
                'is_split' => $hasSplits || $transaction['is_split'] ? 1 : 0,
                'id' => $_POST['id']
            ]);
            
            // If it's a debt payment, update debt_payments and debt
            if ($is_debt) {
                // Check if the debt_id has changed
                $stmt = $pdo->prepare("
                    SELECT * FROM debt_payments
                    WHERE outgoing_id = :outgoing_id
                ");
                $stmt->execute(['outgoing_id' => $_POST['id']]);
                $debtPayment = $stmt->fetch();
                
                if ($debtPayment) {
                    // Calculate payment difference
                    $paymentDiff = $_POST['amount'] - $debtPayment['amount'];
                    
                    // If debt_id has changed or amount has changed
                    if ($debtPayment['debt_id'] != $_POST['debt_id'] || $paymentDiff != 0) {
                        // If debt_id has changed, we need to:
                        // 1. Update the old debt's remaining amount (add back the original payment)
                        // 2. Update the new debt's remaining amount (subtract the new payment)
                        if ($debtPayment['debt_id'] != $_POST['debt_id']) {
                            // Get old debt
                            $stmt = $pdo->prepare("
                                SELECT * FROM debt
                                WHERE id = :id
                            ");
                            $stmt->execute(['id' => $debtPayment['debt_id']]);
                            $oldDebt = $stmt->fetch();
                            
                            if ($oldDebt) {
                                // Add the payment back to old debt
                                $oldRemaining = $oldDebt['remaining_amount'] + $debtPayment['amount'];
                                
                                $stmt = $pdo->prepare("
                                    UPDATE debt
                                    SET remaining_amount = :remaining_amount
                                    WHERE id = :id
                                ");
                                $stmt->execute([
                                    'remaining_amount' => $oldRemaining,
                                    'id' => $oldDebt['id']
                                ]);
                            }
                            
                            // Get new debt
                            $stmt = $pdo->prepare("
                                SELECT * FROM debt
                                WHERE id = :id
                            ");
                            $stmt->execute(['id' => $_POST['debt_id']]);
                            $newDebt = $stmt->fetch();
                            
                            if ($newDebt) {
                                // Deduct the payment from new debt
                                $newRemaining = max(0, $newDebt['remaining_amount'] - $_POST['amount']);
                                
                                $stmt = $pdo->prepare("
                                    UPDATE debt
                                    SET remaining_amount = :remaining_amount
                                    WHERE id = :id
                                ");
                                $stmt->execute([
                                    'remaining_amount' => $newRemaining,
                                    'id' => $newDebt['id']
                                ]);
                            }
                            
                            // Update debt_payment with new debt_id and amount
                            $stmt = $pdo->prepare("
                                UPDATE debt_payments
                                SET debt_id = :debt_id, amount = :amount
                                WHERE outgoing_id = :outgoing_id
                            ");
                            $stmt->execute([
                                'debt_id' => $_POST['debt_id'],
                                'amount' => $_POST['amount'],
                                'outgoing_id' => $_POST['id']
                            ]);
                        } else {
                            // Only amount has changed, update debt remaining
                            $stmt = $pdo->prepare("
                                SELECT * FROM debt
                                WHERE id = :id
                            ");
                            $stmt->execute(['id' => $debtPayment['debt_id']]);
                            $debt = $stmt->fetch();
                            
                            if ($debt) {
                                // Update remaining amount by the difference
                                $remaining = max(0, $debt['remaining_amount'] - $paymentDiff);
                                
                                $stmt = $pdo->prepare("
                                    UPDATE debt
                                    SET remaining_amount = :remaining_amount
                                    WHERE id = :id
                                ");
                                $stmt->execute([
                                    'remaining_amount' => $remaining,
                                    'id' => $debt['id']
                                ]);
                            }
                            
                            // Update payment amount
                            $stmt = $pdo->prepare("
                                UPDATE debt_payments
                                SET amount = :amount
                                WHERE outgoing_id = :outgoing_id
                            ");
                            $stmt->execute([
                                'amount' => $_POST['amount'],
                                'outgoing_id' => $_POST['id']
                            ]);
                        }
                    }
                } else {
                    // New debt payment association
                    // Get debt
                    $stmt = $pdo->prepare("
                        SELECT * FROM debt
                        WHERE id = :id
                    ");
                    $stmt->execute(['id' => $_POST['debt_id']]);
                    $debt = $stmt->fetch();
                    
                    if ($debt) {
                        // Insert debt payment
                        $stmt = $pdo->prepare("
                            INSERT INTO debt_payments (debt_id, outgoing_id, amount, created_at)
                            VALUES (:debt_id, :outgoing_id, :amount, NOW())
                        ");
                        $stmt->execute([
                            'debt_id' => $_POST['debt_id'],
                            'outgoing_id' => $_POST['id'],
                            'amount' => $_POST['amount']
                        ]);
                        
                        // Update debt remaining
                        $remaining = max(0, $debt['remaining_amount'] - $_POST['amount']);
                        
                        $stmt = $pdo->prepare("
                            UPDATE debt
                            SET remaining_amount = :remaining_amount
                            WHERE id = :id
                        ");
                        $stmt->execute([
                            'remaining_amount' => $remaining,
                            'id' => $debt['id']
                        ]);
                    }
                }
            }
            
            // If this transaction already has splits, update their date
            if ($transaction['is_split']) {
                $stmt = $pdo->prepare("
                    UPDATE outgoing
                    SET date = :date
                    WHERE parent_id = :parent_id
                ");
                $stmt->execute([
                    'date' => $_POST['date'],
                    'parent_id' => $_POST['id']
                ]);
            }
            
            // Handle new splits
            if ($hasSplits && !$transaction['is_split']) {
                $totalSplitAmount = 0;
                
                foreach ($_POST['splits'] as $split) {
                    // Skip if description or amount is empty
                    if (empty($split['description']) || empty($split['amount'])) {
                        continue;
                    }
                    
                    // Validate split amount
                    if (!is_numeric($split['amount']) || $split['amount'] <= 0) {
                        continue;
                    }
                    
                    $totalSplitAmount += $split['amount'];
                    
                    // Get the split date or use parent date if not provided
                    $splitDate = !empty($split['date']) ? $split['date'] : $_POST['date'];
                    
                    // Insert split transaction
                    $stmt = $pdo->prepare("
                        INSERT INTO outgoing (
                            description, amount, date, category_id, notes, 
                            parent_id, is_debt, is_split, created_at
                        )
                        VALUES (
                            :description, :amount, :date, :category_id, :notes, 
                            :parent_id, :is_debt, 1, NOW()
                        )
                    ");
                    
                    $stmt->execute([
                        'description' => $split['description'],
                        'amount' => $split['amount'],
                        'date' => $splitDate,
                        'category_id' => !empty($split['category_id']) ? $split['category_id'] : null,
                        'notes' => $split['notes'] ?? null,
                        'parent_id' => $_POST['id'],
                        'is_debt' => $is_debt ? 1 : 0
                    ]);
                }
                
                // Update main transaction amount if needed
                if ($totalSplitAmount > 0 && $totalSplitAmount != $_POST['amount']) {
                    $stmt = $pdo->prepare("
                        UPDATE outgoing SET amount = :amount WHERE id = :id
                    ");
                    $stmt->execute([
                        'amount' => $totalSplitAmount,
                        'id' => $_POST['id']
                    ]);
                    
                    // If it's a debt payment, update debt_payments and debt
                    if ($is_debt) {
                        $stmt = $pdo->prepare("
                            UPDATE debt_payments
                            SET amount = :amount
                            WHERE outgoing_id = :outgoing_id
                        ");
                        $stmt->execute([
                            'amount' => $totalSplitAmount,
                            'outgoing_id' => $_POST['id']
                        ]);
                        
                        // Get debt payment to update debt
                        $stmt = $pdo->prepare("
                            SELECT * FROM debt_payments
                            WHERE outgoing_id = :outgoing_id
                        ");
                        $stmt->execute(['outgoing_id' => $_POST['id']]);
                        $debtPayment = $stmt->fetch();
                        
                        if ($debtPayment) {
                            // Get debt
                            $stmt = $pdo->prepare("
                                SELECT * FROM debt
                                WHERE id = :id
                            ");
                            $stmt->execute(['id' => $debtPayment['debt_id']]);
                            $debt = $stmt->fetch();
                            
                            if ($debt) {
                                // Calculate payment difference
                                $paymentDiff = $totalSplitAmount - $_POST['amount'];
                                $remaining = max(0, $debt['remaining_amount'] - $paymentDiff);
                                
                                // Update debt
                                $stmt = $pdo->prepare("
                                    UPDATE debt
                                    SET remaining_amount = :remaining_amount
                                    WHERE id = :id
                                ");
                                $stmt->execute([
                                    'remaining_amount' => $remaining,
                                    'id' => $debt['id']
                                ]);
                            }
                        }
                    }
                }
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        jsonResponse(true, 'Transaction updated successfully', [
            'redirect' => 'index.php' . ($is_debt ? '?is_debt=1' : '')
        ]);
    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        
        jsonResponse(false, 'Error updating transaction: ' . $e->getMessage());
    }
}

/**
 * Delete transaction
 */
function deleteTransaction() {
    global $pdo;
    
    // Check if ID is provided
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        jsonResponse(false, 'Transaction ID is required');
    }
    
    $id = (int)$_GET['id'];
    
    try {
        // Get transaction
        $stmt = $pdo->prepare("SELECT * FROM outgoing WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $transaction = $stmt->fetch();
        
        if (!$transaction) {
            jsonResponse(false, 'Transaction not found');
        }
        
        // Check if this is a split item
        $isSplitItem = !empty($transaction['parent_id']);
        $is_debt = $transaction['is_debt'] == 1;
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // If it's a debt payment, update debt
        if ($is_debt && !$isSplitItem) {
            // Get debt payment
            $stmt = $pdo->prepare("
                SELECT * FROM debt_payments
                WHERE outgoing_id = :outgoing_id
            ");
            $stmt->execute(['outgoing_id' => $id]);
            $debtPayment = $stmt->fetch();
            
            if ($debtPayment) {
                // Get debt
                $stmt = $pdo->prepare("
                    SELECT * FROM debt
                    WHERE id = :id
                ");
                $stmt->execute(['id' => $debtPayment['debt_id']]);
                $debt = $stmt->fetch();
                
                if ($debt) {
                    // Add payment back to debt
                    $remaining = $debt['remaining_amount'] + $debtPayment['amount'];
                    
                    $stmt = $pdo->prepare("
                        UPDATE debt
                        SET remaining_amount = :remaining_amount
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'remaining_amount' => $remaining,
                        'id' => $debt['id']
                    ]);
                }
                
                // Delete debt payment
                $stmt = $pdo->prepare("
                    DELETE FROM debt_payments
                    WHERE outgoing_id = :outgoing_id
                ");
                $stmt->execute(['outgoing_id' => $id]);
            }
        }
        
        if ($isSplitItem) {
            // Delete split item
            $stmt = $pdo->prepare("DELETE FROM outgoing WHERE id = :id");
            $stmt->execute(['id' => $id]);
            
            // Update parent transaction amount
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) as total
                FROM outgoing
                WHERE parent_id = :parent_id
            ");
            $stmt->execute(['parent_id' => $transaction['parent_id']]);
            $result = $stmt->fetch();
            
            // If no splits left or sum is zero, update is_split flag
            if ($result['total'] == 0) {
                $stmt = $pdo->prepare("
                    UPDATE outgoing
                    SET is_split = 0
                    WHERE id = :id
                ");
                $stmt->execute(['id' => $transaction['parent_id']]);
            } else {
                // Update parent amount
                $stmt = $pdo->prepare("
                    UPDATE outgoing
                    SET amount = :amount
                    WHERE id = :id
                ");
                $stmt->execute([
                    'amount' => $result['total'],
                    'id' => $transaction['parent_id']
                ]);
                
                // If parent is a debt payment, update debt_payments and debt
                $stmt = $pdo->prepare("
                    SELECT * FROM outgoing
                    WHERE id = :id
                ");
                $stmt->execute(['id' => $transaction['parent_id']]);
                $parentTx = $stmt->fetch();
                
                if ($parentTx && $parentTx['is_debt']) {
                    // Get debt payment
                    $stmt = $pdo->prepare("
                        SELECT * FROM debt_payments
                        WHERE outgoing_id = :outgoing_id
                    ");
                    $stmt->execute(['outgoing_id' => $transaction['parent_id']]);
                    $debtPayment = $stmt->fetch();
                    
                    if ($debtPayment) {
                        // Calculate payment difference
                        $paymentDiff = $result['total'] - $debtPayment['amount'];
                        
                        // Update debt payment amount
                        $stmt = $pdo->prepare("
                            UPDATE debt_payments
                            SET amount = :amount
                            WHERE outgoing_id = :outgoing_id
                        ");
                        $stmt->execute([
                            'amount' => $result['total'],
                            'outgoing_id' => $transaction['parent_id']
                        ]);
                        
                        // Get debt
                        $stmt = $pdo->prepare("
                            SELECT * FROM debt
                            WHERE id = :id
                        ");
                        $stmt->execute(['id' => $debtPayment['debt_id']]);
                        $debt = $stmt->fetch();
                        
                        if ($debt) {
                            // Update debt remaining
                            $remaining = max(0, $debt['remaining_amount'] - $paymentDiff);
                            
                            $stmt = $pdo->prepare("
                                UPDATE debt
                                SET remaining_amount = :remaining_amount
                                WHERE id = :id
                            ");
                            $stmt->execute([
                                'remaining_amount' => $remaining,
                                'id' => $debt['id']
                            ]);
                        }
                    }
                }
            }
        } else {
            // Delete main transaction and its splits
            $stmt = $pdo->prepare("DELETE FROM outgoing WHERE id = :id OR parent_id = :parent_id");
            $stmt->execute(['id' => $id, 'parent_id' => $id]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // If it's an AJAX request, return JSON response
        if (isAjaxRequest()) {
            jsonResponse(true, 'Transaction deleted successfully', [
                'redirect' => 'index.php' . ($is_debt ? '?is_debt=1' : '')
            ]);
        } else {
            // Otherwise, redirect to index
            header('Location: index.php' . ($is_debt ? '?is_debt=1' : ''));
            exit;
        }
    } catch (PDOException $e) {
        // Rollback transaction
        $pdo->rollBack();
        
        if (isAjaxRequest()) {
            jsonResponse(false, 'Error deleting transaction: ' . $e->getMessage());
        } else {
            die('Error deleting transaction: ' . $e->getMessage());
        }
    }
}

/**
 * Get transaction details
 */
function getTransaction() {
    global $pdo;
    
    // Check if ID is provided
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        jsonResponse(false, 'Transaction ID is required');
    }
    
    $id = $_GET['id'];
    
    try {
        // Get transaction
        $stmt = $pdo->prepare("
            SELECT o.*, c.name as category_name, c.color as category_color
            FROM outgoing o
            LEFT JOIN categories c ON o.category_id = c.id
            WHERE o.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $transaction = $stmt->fetch();
        
        if (!$transaction) {
            jsonResponse(false, 'Transaction not found');
        }
        
        // If it's a split transaction, get its splits
        if ($transaction['is_split']) {
            $stmt = $pdo->prepare("
                SELECT o.*, c.name as category_name, c.color as category_color
                FROM outgoing o
                LEFT JOIN categories c ON o.category_id = c.id
                WHERE o.parent_id = :parent_id
            ");
            $stmt->execute(['parent_id' => $id]);
            $transaction['splits'] = $stmt->fetchAll();
        }
        
        // If it's a debt payment, get debt info
        if ($transaction['is_debt']) {
            $stmt = $pdo->prepare("
                SELECT dp.*, d.description as debt_description, d.total_amount, d.remaining_amount
                FROM debt_payments dp
                JOIN debt d ON dp.debt_id = d.id
                WHERE dp.outgoing_id = :outgoing_id
            ");
            $stmt->execute(['outgoing_id' => $id]);
            $transaction['debt_payment'] = $stmt->fetch();
        }
        
        jsonResponse(true, 'Transaction retrieved successfully', ['transaction' => $transaction]);
    } catch (Exception $e) {
        jsonResponse(false, 'Error retrieving transaction: ' . $e->getMessage());
    }
}

/**
 * Export transactions
 */
function exportTransactions() {
    global $pdo;
    
    // Get filter parameters
    $category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $is_fixed = isset($_GET['is_fixed']) ? (int)$_GET['is_fixed'] : null;
    $is_debt = isset($_GET['is_debt']) ? (int)$_GET['is_debt'] : 0;
    
    // Build the query
    $query = "
        SELECT o.id, o.description, o.amount, o.date, o.notes, o.is_fixed, o.is_split,
               o.repeat_interval, o.repeat_until, c.name as category
        FROM outgoing o
        LEFT JOIN categories c ON o.category_id = c.id
        WHERE o.parent_id IS NULL AND o.is_debt = :is_debt
    ";
    
    $params = ['is_debt' => $is_debt];
    
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
    
    // Order by date
    $query .= " ORDER BY o.date DESC";
    
    // Get transactions
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    // Check format
    $format = isset($_GET['format']) ? $_GET['format'] : 'csv';
    
    switch ($format) {
        case 'csv':
            exportCsv($transactions, $is_debt);
            break;
            
        case 'pdf':
            exportPdf($transactions, $is_debt);
            break;
            
        default:
            jsonResponse(false, 'Invalid export format');
    }
}

/**
 * Export transactions as CSV
 */
function exportCsv($transactions, $is_debt) {
    $filename = $is_debt ? 'debt_payments.csv' : 'outgoing_transactions.csv';
    
    // Set headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 encoding in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers
    if ($is_debt) {
        fputcsv($output, ['ID', 'Description', 'Amount', 'Date', 'Category', 'Notes', 'Split']);
    } else {
        fputcsv($output, ['ID', 'Description', 'Amount', 'Date', 'Category', 'Fixed', 'Recurring', 'Notes', 'Split']);
    }
    
    // Add data
    foreach ($transactions as $transaction) {
        if ($is_debt) {
            fputcsv($output, [
                $transaction['id'],
                $transaction['description'],
                $transaction['amount'],
                $transaction['date'],
                $transaction['category'] ?? 'Uncategorized',
                $transaction['notes'],
                $transaction['is_split'] ? 'Yes' : 'No'
            ]);
        } else {
            fputcsv($output, [
                $transaction['id'],
                $transaction['description'],
                $transaction['amount'],
                $transaction['date'],
                $transaction['category'] ?? 'Uncategorized',
                $transaction['is_fixed'] ? 'Yes' : 'No',
                $transaction['repeat_interval'] !== 'none' ? ucfirst($transaction['repeat_interval']) : 'No',
                $transaction['notes'],
                $transaction['is_split'] ? 'Yes' : 'No'
            ]);
        }
    }
    
    // Close output stream
    fclose($output);
    exit;
}

/**
 * Export transactions as PDF
 */
function exportPdf($transactions, $is_debt) {
    // This is a simple example, in a real application you would use a PDF library like FPDF or TCPDF
    // For now, we'll just display a message
    echo 'PDF export is not implemented yet. Please use CSV export.';
    exit;
}

/**
 * Check if request is AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Send JSON response
 */
function jsonResponse($success, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}