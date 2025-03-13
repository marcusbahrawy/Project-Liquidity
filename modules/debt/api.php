<?php
/**
 * Debt API
 * 
 * Handles AJAX requests and form submissions for debt management.
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
        addDebt();
        break;
        
    case 'update':
        updateDebt();
        break;
        
    case 'delete':
        deleteDebt();
        break;
        
    case 'get':
        getDebt();
        break;
        
    case 'export':
        exportDebts();
        break;
        
    default:
        jsonResponse(false, 'Invalid action specified');
}

/**
 * Add new debt
 */
function addDebt() {
    global $pdo;
    
    // Check if required fields are provided
    $requiredFields = ['description', 'total_amount', 'start_date'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            jsonResponse(false, "Field '{$field}' is required");
        }
    }
    
    // Validate amount
    if (!is_numeric($_POST['total_amount']) || $_POST['total_amount'] <= 0) {
        jsonResponse(false, 'Total amount must be a positive number');
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Set remaining amount to total amount if not specified
        $remainingAmount = isset($_POST['remaining_amount']) && !empty($_POST['remaining_amount']) 
            ? $_POST['remaining_amount'] 
            : $_POST['total_amount'];
        
        // Validate remaining amount
        if (!is_numeric($remainingAmount) || $remainingAmount < 0) {
            jsonResponse(false, 'Remaining amount must be a non-negative number');
        }
        
        if ($remainingAmount > $_POST['total_amount']) {
            jsonResponse(false, 'Remaining amount cannot exceed total amount');
        }
        
        // Validate interest rate if provided
        $interestRate = null;
        if (isset($_POST['interest_rate']) && !empty($_POST['interest_rate'])) {
            $interestRate = $_POST['interest_rate'];
            if (!is_numeric($interestRate) || $interestRate < 0) {
                jsonResponse(false, 'Interest rate must be a non-negative number');
            }
        }
        
        // Prepare data
        $data = [
            'description' => $_POST['description'],
            'total_amount' => $_POST['total_amount'],
            'remaining_amount' => $remainingAmount,
            'start_date' => $_POST['start_date'],
            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
            'interest_rate' => $interestRate,
            'category_id' => !empty($_POST['category_id']) ? $_POST['category_id'] : null,
            'notes' => !empty($_POST['notes']) ? $_POST['notes'] : null
        ];
        
        // Insert debt
        $stmt = $pdo->prepare("
            INSERT INTO debt (
                description, total_amount, remaining_amount, start_date, 
                end_date, interest_rate, category_id, notes, created_at
            )
            VALUES (
                :description, :total_amount, :remaining_amount, :start_date, 
                :end_date, :interest_rate, :category_id, :notes, NOW()
            )
        ");
        $stmt->execute($data);
        
        $debtId = $pdo->lastInsertId();
        
        // Handle initial payment if provided
        if (isset($_POST['initial_payment']) && !empty($_POST['initial_payment']) && is_numeric($_POST['initial_payment']) && $_POST['initial_payment'] > 0) {
            $paymentAmount = $_POST['initial_payment'];
            $paymentDate = !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
            $paymentDescription = !empty($_POST['payment_description']) ? $_POST['payment_description'] : 'Initial payment';
            
            // Insert outgoing transaction
            $stmt = $pdo->prepare("
                INSERT INTO outgoing (
                    description, amount, date, category_id, 
                    is_debt, notes, created_at
                )
                VALUES (
                    :description, :amount, :date, :category_id, 
                    1, :notes, NOW()
                )
            ");
            $stmt->execute([
                'description' => $paymentDescription,
                'amount' => $paymentAmount,
                'date' => $paymentDate,
                'category_id' => $data['category_id'],
                'notes' => "Initial payment for: " . $data['description']
            ]);
            
            $outgoingId = $pdo->lastInsertId();
            
            // Insert debt payment link
            $stmt = $pdo->prepare("
                INSERT INTO debt_payments (
                    debt_id, outgoing_id, amount, created_at
                )
                VALUES (
                    :debt_id, :outgoing_id, :amount, NOW()
                )
            ");
            $stmt->execute([
                'debt_id' => $debtId,
                'outgoing_id' => $outgoingId,
                'amount' => $paymentAmount
            ]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        jsonResponse(true, 'Debt added successfully', ['redirect' => 'index.php']);
    } catch (PDOException $e) {
        // Rollback transaction
        $pdo->rollBack();
        
        jsonResponse(false, 'Error adding debt: ' . $e->getMessage());
    }
}

/**
 * Update existing debt
 */
function updateDebt() {
    global $pdo;
    
    // Check if ID is provided
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        jsonResponse(false, 'Debt ID is required');
    }
    
    // Get debt
    $stmt = $pdo->prepare("SELECT * FROM debt WHERE id = :id");
    $stmt->execute(['id' => $_POST['id']]);
    $debt = $stmt->fetch();
    
    if (!$debt) {
        jsonResponse(false, 'Debt not found');
    }
    
    // Check if required fields are provided
    $requiredFields = ['description', 'total_amount', 'remaining_amount', 'start_date'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            jsonResponse(false, "Field '{$field}' is required");
        }
    }
    
    // Validate amounts
    if (!is_numeric($_POST['total_amount']) || $_POST['total_amount'] <= 0) {
        jsonResponse(false, 'Total amount must be a positive number');
    }
    
    if (!is_numeric($_POST['remaining_amount']) || $_POST['remaining_amount'] < 0) {
        jsonResponse(false, 'Remaining amount must be a non-negative number');
    }
    
    if ($_POST['remaining_amount'] > $_POST['total_amount']) {
        jsonResponse(false, 'Remaining amount cannot exceed total amount');
    }
    
    // Validate interest rate if provided
    $interestRate = null;
    if (isset($_POST['interest_rate']) && !empty($_POST['interest_rate'])) {
        $interestRate = $_POST['interest_rate'];
        if (!is_numeric($interestRate) || $interestRate < 0) {
            jsonResponse(false, 'Interest rate must be a non-negative number');
        }
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Prepare data
        $data = [
            'id' => $_POST['id'],
            'description' => $_POST['description'],
            'total_amount' => $_POST['total_amount'],
            'remaining_amount' => $_POST['remaining_amount'],
            'start_date' => $_POST['start_date'],
            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
            'interest_rate' => $interestRate,
            'category_id' => !empty($_POST['category_id']) ? $_POST['category_id'] : null,
            'notes' => !empty($_POST['notes']) ? $_POST['notes'] : null
        ];
        
        // Update debt
        $stmt = $pdo->prepare("
            UPDATE debt
            SET description = :description,
                total_amount = :total_amount,
                remaining_amount = :remaining_amount,
                start_date = :start_date,
                end_date = :end_date,
                interest_rate = :interest_rate,
                category_id = :category_id,
                notes = :notes
            WHERE id = :id
        ");
        $stmt->execute($data);
        
        // Commit transaction
        $pdo->commit();
        
        jsonResponse(true, 'Debt updated successfully', ['redirect' => 'index.php']);
    } catch (PDOException $e) {
        // Rollback transaction
        $pdo->rollBack();
        
        jsonResponse(false, 'Error updating debt: ' . $e->getMessage());
    }
}

/**
 * Delete debt
 */
function deleteDebt() {
    global $pdo;
    
    // Check if ID is provided
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        jsonResponse(false, 'Debt ID is required');
    }
    
    $id = (int)$_GET['id'];
    
    try {
        // Get debt
        $stmt = $pdo->prepare("SELECT * FROM debt WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $debt = $stmt->fetch();
        
        if (!$debt) {
            jsonResponse(false, 'Debt not found');
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get all debt payments
        $stmt = $pdo->prepare("
            SELECT dp.*, o.id as outgoing_id
            FROM debt_payments dp
            JOIN outgoing o ON dp.outgoing_id = o.id
            WHERE dp.debt_id = :debt_id
        ");
        $stmt->execute(['debt_id' => $id]);
        $payments = $stmt->fetchAll();
        
        // Delete debt payments and associated outgoing transactions
        if (!empty($payments)) {
            // First delete debt_payments
            $stmt = $pdo->prepare("DELETE FROM debt_payments WHERE debt_id = :debt_id");
            $stmt->execute(['debt_id' => $id]);
            
            // Then delete outgoing transactions (one by one to avoid parameter errors)
            foreach ($payments as $payment) {
                $stmt = $pdo->prepare("DELETE FROM outgoing WHERE id = :id");
                $stmt->execute(['id' => $payment['outgoing_id']]);
            }
        }
        
        // Delete debt
        $stmt = $pdo->prepare("DELETE FROM debt WHERE id = :id");
        $stmt->execute(['id' => $id]);
        
        // Commit transaction
        $pdo->commit();
        
        // If it's an AJAX request, return JSON response
        if (isAjaxRequest()) {
            jsonResponse(true, 'Debt deleted successfully', ['redirect' => 'index.php']);
        } else {
            // Otherwise, redirect to index
            header('Location: index.php');
            exit;
        }
    } catch (PDOException $e) {
        // Rollback transaction
        $pdo->rollBack();
        
        if (isAjaxRequest()) {
            jsonResponse(false, 'Error deleting debt: ' . $e->getMessage());
        } else {
            die('Error deleting debt: ' . $e->getMessage());
        }
    }
}

/**
 * Get debt details
 */
function getDebt() {
    global $pdo;
    
    // Check if ID is provided
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        jsonResponse(false, 'Debt ID is required');
    }
    
    $id = $_GET['id'];
    
    try {
        // Get debt
        $stmt = $pdo->prepare("
            SELECT d.*, c.name as category_name, c.color as category_color
            FROM debt d
            LEFT JOIN categories c ON d.category_id = c.id
            WHERE d.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $debt = $stmt->fetch();
        
        if (!$debt) {
            jsonResponse(false, 'Debt not found');
        }
        
        // Get debt payments
        $stmt = $pdo->prepare("
            SELECT dp.*, o.date, o.description as payment_description, o.notes
            FROM debt_payments dp
            JOIN outgoing o ON dp.outgoing_id = o.id
            WHERE dp.debt_id = :debt_id
            ORDER BY o.date DESC
        ");
        $stmt->execute(['debt_id' => $id]);
        $debt['payments'] = $stmt->fetchAll();
        
        // Calculate totals
        $debt['total_paid'] = array_reduce($debt['payments'], function($carry, $payment) {
            return $carry + $payment['amount'];
        }, 0);
        
        jsonResponse(true, 'Debt retrieved successfully', ['debt' => $debt]);
    } catch (PDOException $e) {
        jsonResponse(false, 'Error retrieving debt: ' . $e->getMessage());
    }
}

/**
 * Export debts
 */
function exportDebts() {
    global $pdo;
    
    // Get filter parameters
    $category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    
    // Build the query
    $query = "
        SELECT d.id, d.description, d.total_amount, d.remaining_amount, 
               d.start_date, d.end_date, d.interest_rate, d.notes,
               c.name as category
        FROM debt d
        LEFT JOIN categories c ON d.category_id = c.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Apply filters
    if ($category_id) {
        $query .= " AND d.category_id = :category_id";
        $params['category_id'] = $category_id;
    }
    
    if ($search) {
        $query .= " AND (d.description LIKE :search OR d.notes LIKE :search)";
        $params['search'] = "%{$search}%";
    }
    
    // Order by remaining amount
    $query .= " ORDER BY d.remaining_amount DESC";
    
    // Get debts
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $debts = $stmt->fetchAll();
    
    // Check format
    $format = isset($_GET['format']) ? $_GET['format'] : 'csv';
    
    switch ($format) {
        case 'csv':
            exportCsv($debts);
            break;
            
        case 'pdf':
            exportPdf($debts);
            break;
            
        default:
            jsonResponse(false, 'Invalid export format');
    }
}

/**
 * Export debts as CSV
 */
function exportCsv($debts) {
    // Set headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="debts.csv"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 encoding in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers
    fputcsv($output, [
        'ID', 'Description', 'Total Amount', 'Remaining Amount', 
        'Start Date', 'End Date', 'Interest Rate', 'Category', 'Notes'
    ]);
    
    // Add data
    foreach ($debts as $debt) {
        fputcsv($output, [
            $debt['id'],
            $debt['description'],
            $debt['total_amount'],
            $debt['remaining_amount'],
            $debt['start_date'],
            $debt['end_date'],
            $debt['interest_rate'],
            $debt['category'] ?? 'Uncategorized',
            $debt['notes']
        ]);
    }
    
    // Close output stream
    fclose($output);
    exit;
}

/**
 * Export debts as PDF
 */
function exportPdf($debts) {
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