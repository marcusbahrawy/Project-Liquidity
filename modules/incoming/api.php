<?php
/**
 * Incoming API
 * 
 * Handles AJAX requests and form submissions for incoming transactions.
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
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Check if we have splits
        $hasSplits = isset($_POST['splits']) && is_array($_POST['splits']) && count($_POST['splits']) > 0;
        
        // Prepare data for recurring income
        $is_fixed = isset($_POST['is_fixed']) && $_POST['is_fixed'] == 1 ? 1 : 0;
        $repeat_interval = $is_fixed && isset($_POST['repeat_interval']) ? $_POST['repeat_interval'] : 'none';
        $repeat_until = $is_fixed && $repeat_interval !== 'none' && !empty($_POST['repeat_until']) ? $_POST['repeat_until'] : null;
        
        // Prepare main transaction data
        $data = [
            'description' => $_POST['description'],
            'amount' => $_POST['amount'],
            'date' => $_POST['date'],
            'category_id' => !empty($_POST['category_id']) ? $_POST['category_id'] : null,
            'notes' => $_POST['notes'] ?? null,
            'is_split' => $hasSplits ? 1 : 0,
            'is_fixed' => $is_fixed,
            'repeat_interval' => $repeat_interval,
            'repeat_until' => $repeat_until
        ];
        
        // Insert main transaction
        $stmt = $pdo->prepare("
            INSERT INTO incoming (
                description, amount, date, category_id, notes, is_split, 
                is_fixed, repeat_interval, repeat_until, created_at
            )
            VALUES (
                :description, :amount, :date, :category_id, :notes, :is_split,
                :is_fixed, :repeat_interval, :repeat_until, NOW()
            )
        ");
        $stmt->execute($data);
        
        $parentId = $pdo->lastInsertId();
        
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
                    INSERT INTO incoming (
                        description, amount, date, category_id, notes, 
                        parent_id, created_at
                    )
                    VALUES (
                        :description, :amount, :date, :category_id, :notes, 
                        :parent_id, NOW()
                    )
                ");
                
                $stmt->execute([
                    'description' => $split['description'],
                    'amount' => $split['amount'],
                    'date' => $splitDate, // Use the specific date for this split
                    'category_id' => !empty($split['category_id']) ? $split['category_id'] : null,
                    'notes' => $split['notes'] ?? null,
                    'parent_id' => $parentId
                ]);
            }
            
            // Update main transaction amount if needed
            if ($totalSplitAmount > 0 && $totalSplitAmount != $_POST['amount']) {
                $stmt = $pdo->prepare("
                    UPDATE incoming SET amount = :amount WHERE id = :id
                ");
                $stmt->execute([
                    'amount' => $totalSplitAmount,
                    'id' => $parentId
                ]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        jsonResponse(true, 'Transaction added successfully', ['redirect' => 'index.php']);
    } catch (PDOException $e) {
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
    $stmt = $pdo->prepare("SELECT * FROM incoming WHERE id = :id");
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
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Check if we have splits
        $hasSplits = isset($_POST['splits']) && is_array($_POST['splits']) && count($_POST['splits']) > 0;
        
        // Check if this is a split item (has parent_id)
        $isSplitItem = !empty($transaction['parent_id']);
        
        if ($isSplitItem) {
            // Only update description, category, date and notes for split items
            $stmt = $pdo->prepare("
                UPDATE incoming
                SET description = :description, category_id = :category_id, notes = :notes, date = :date
                WHERE id = :id
            ");
            
            $stmt->execute([
                'description' => $_POST['description'],
                'category_id' => !empty($_POST['category_id']) ? $_POST['category_id'] : null,
                'notes' => $_POST['notes'] ?? null,
                'date' => $_POST['date'], // Allow updating date for split items
                'id' => $_POST['id']
            ]);
            
            // Update parent transaction's amount
            $stmt = $pdo->prepare("
                SELECT SUM(amount) as total
                FROM incoming
                WHERE parent_id = :parent_id
            ");
            $stmt->execute(['parent_id' => $transaction['parent_id']]);
            $result = $stmt->fetch();
            
            if ($result && isset($result['total']) && $result['total'] > 0) {
                $stmt = $pdo->prepare("
                    UPDATE incoming
                    SET amount = :amount
                    WHERE id = :id
                ");
                $stmt->execute([
                    'amount' => $result['total'],
                    'id' => $transaction['parent_id']
                ]);
            }
        } else {
            // Prepare data for recurring income
            $is_fixed = isset($_POST['is_fixed']) && $_POST['is_fixed'] == 1 ? 1 : 0;
            $repeat_interval = $is_fixed && isset($_POST['repeat_interval']) ? $_POST['repeat_interval'] : 'none';
            $repeat_until = $is_fixed && $repeat_interval !== 'none' && !empty($_POST['repeat_until']) ? $_POST['repeat_until'] : null;
            
            // Update main transaction
            $stmt = $pdo->prepare("
                UPDATE incoming
                SET description = :description, amount = :amount, date = :date, 
                    category_id = :category_id, notes = :notes, is_split = :is_split,
                    is_fixed = :is_fixed, repeat_interval = :repeat_interval, repeat_until = :repeat_until
                WHERE id = :id
            ");
            
            $stmt->execute([
                'description' => $_POST['description'],
                'amount' => $_POST['amount'],
                'date' => $_POST['date'],
                'category_id' => !empty($_POST['category_id']) ? $_POST['category_id'] : null,
                'notes' => $_POST['notes'] ?? null,
                'is_split' => $hasSplits || $transaction['is_split'] ? 1 : 0,
                'is_fixed' => $is_fixed,
                'repeat_interval' => $repeat_interval,
                'repeat_until' => $repeat_until,
                'id' => $_POST['id']
            ]);
            
            // If this transaction already has splits, update their date
            if ($transaction['is_split']) {
                $stmt = $pdo->prepare("
                    UPDATE incoming
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
                        INSERT INTO incoming (description, amount, date, category_id, notes, parent_id, created_at)
                        VALUES (:description, :amount, :date, :category_id, :notes, :parent_id, NOW())
                    ");
                    
                    $stmt->execute([
                        'description' => $split['description'],
                        'amount' => $split['amount'],
                        'date' => $splitDate,
                        'category_id' => !empty($split['category_id']) ? $split['category_id'] : null,
                        'notes' => $split['notes'] ?? null,
                        'parent_id' => $_POST['id']
                    ]);
                }
                
                // Update main transaction amount if needed
                if ($totalSplitAmount > 0 && $totalSplitAmount != $_POST['amount']) {
                    $stmt = $pdo->prepare("
                        UPDATE incoming SET amount = :amount WHERE id = :id
                    ");
                    $stmt->execute([
                        'amount' => $totalSplitAmount,
                        'id' => $_POST['id']
                    ]);
                }
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        jsonResponse(true, 'Transaction updated successfully', ['redirect' => 'index.php']);
    } catch (PDOException $e) {
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
        $stmt = $pdo->prepare("SELECT * FROM incoming WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $transaction = $stmt->fetch();
        
        if (!$transaction) {
            jsonResponse(false, 'Transaction not found');
        }
        
        // Check if this is a split item
        $isSplitItem = !empty($transaction['parent_id']);
        
        // Begin transaction
        $pdo->beginTransaction();
        
        if ($isSplitItem) {
            // Delete split item
            $stmt = $pdo->prepare("DELETE FROM incoming WHERE id = :id");
            $stmt->execute(['id' => $id]);
            
            // Update parent transaction's amount
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) as total
                FROM incoming
                WHERE parent_id = :parent_id
            ");
            $stmt->execute(['parent_id' => $transaction['parent_id']]);
            $result = $stmt->fetch();
            
            if ($result) {
                // If no splits left or sum is zero, update is_split flag
                if ($result['total'] == 0) {
                    $stmt = $pdo->prepare("
                        UPDATE incoming
                        SET is_split = 0
                        WHERE id = :id
                    ");
                    $stmt->execute(['id' => $transaction['parent_id']]);
                } else {
                    // Update parent amount
                    $stmt = $pdo->prepare("
                        UPDATE incoming
                        SET amount = :amount
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'amount' => $result['total'],
                        'id' => $transaction['parent_id']
                    ]);
                }
            }
        } else {
            // Delete main transaction and its splits
            $stmt = $pdo->prepare("DELETE FROM incoming WHERE id = :id OR parent_id = :parent_id");
            $stmt->execute(['id' => $id, 'parent_id' => $id]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // If it's an AJAX request, return JSON response
        if (isAjaxRequest()) {
            jsonResponse(true, 'Transaction deleted successfully', ['redirect' => 'index.php']);
        } else {
            // Otherwise, redirect to index
            header('Location: index.php');
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
            SELECT i.*, c.name as category_name, c.color as category_color
            FROM incoming i
            LEFT JOIN categories c ON i.category_id = c.id
            WHERE i.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $transaction = $stmt->fetch();
        
        if (!$transaction) {
            jsonResponse(false, 'Transaction not found');
        }
        
        // If it's a split transaction, get its splits
        if ($transaction['is_split']) {
            $stmt = $pdo->prepare("
                SELECT i.*, c.name as category_name, c.color as category_color
                FROM incoming i
                LEFT JOIN categories c ON i.category_id = c.id
                WHERE i.parent_id = :parent_id
            ");
            $stmt->execute(['parent_id' => $id]);
            $transaction['splits'] = $stmt->fetchAll();
        }
        
        jsonResponse(true, 'Transaction retrieved successfully', ['transaction' => $transaction]);
    } catch (PDOException $e) {
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
    
    // Build the query
    $query = "
        SELECT i.id, i.description, i.amount, i.date, i.notes, i.is_split,
               i.is_fixed, i.repeat_interval, i.repeat_until, c.name as category
        FROM incoming i
        LEFT JOIN categories c ON i.category_id = c.id
        WHERE i.parent_id IS NULL
    ";
    
    $params = [];
    
    // Apply filters
    if ($category_id) {
        $query .= " AND i.category_id = :category_id";
        $params['category_id'] = $category_id;
    }
    
    if ($date_from) {
        $query .= " AND i.date >= :date_from";
        $params['date_from'] = $date_from;
    }
    
    if ($date_to) {
        $query .= " AND i.date <= :date_to";
        $params['date_to'] = $date_to;
    }
    
    if ($search) {
        $query .= " AND (i.description LIKE :search OR i.notes LIKE :search)";
        $params['search'] = "%{$search}%";
    }
    
    if (isset($is_fixed)) {
        $query .= " AND i.is_fixed = :is_fixed";
        $params['is_fixed'] = $is_fixed;
    }
    
    // Order by date
    $query .= " ORDER BY i.date DESC";
    
    // Get transactions
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    // Check format
    $format = isset($_GET['format']) ? $_GET['format'] : 'csv';
    
    switch ($format) {
        case 'csv':
            exportCsv($transactions);
            break;
            
        case 'pdf':
            exportPdf($transactions);
            break;
            
        default:
            jsonResponse(false, 'Invalid export format');
    }
}

/**
 * Export transactions as CSV
 */
function exportCsv($transactions) {
    // Set headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="incoming_transactions.csv"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, ['ID', 'Description', 'Amount', 'Date', 'Category', 'Recurring', 'Repeat Interval', 'Notes', 'Split']);
    
    // Add data
    foreach ($transactions as $transaction) {
        $recurring = $transaction['is_fixed'] ? 'Yes' : 'No';
        $repeatInterval = $transaction['repeat_interval'] !== 'none' ? ucfirst($transaction['repeat_interval']) : 'N/A';
        
        fputcsv($output, [
            $transaction['id'],
            $transaction['description'],
            $transaction['amount'],
            $transaction['date'],
            $transaction['category'] ?? 'Uncategorized',
            $recurring,
            $repeatInterval,
            $transaction['notes'],
            $transaction['is_split'] ? 'Yes' : 'No'
        ]);
    }
    
    // Close output stream
    fclose($output);
    exit;
}

/**
 * Export transactions as PDF
 */
function exportPdf($transactions) {
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