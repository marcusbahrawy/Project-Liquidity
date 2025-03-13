<?php
/**
 * Fixed Delete Transaction Function Template
 * 
 * This is a template for the deleteTransaction function 
 * that should be implemented in all API files.
 */

/**
 * Delete transaction
 */
function deleteTransaction() {
    global $pdo;
    
    // Check if ID is provided
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        if (isAjaxRequest()) {
            jsonResponse(false, 'Transaction ID is required');
        } else {
            die('Error: Transaction ID is required');
        }
    }
    
    $id = (int)$_GET['id'];
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get transaction first to verify it exists
        $stmt = $pdo->prepare("SELECT * FROM yourTableName WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $transaction = $stmt->fetch();
        
        if (!$transaction) {
            $pdo->rollBack();
            if (isAjaxRequest()) {
                jsonResponse(false, 'Transaction not found');
            } else {
                die('Error: Transaction not found');
            }
        }
        
        // Check if this is a split transaction with child items
        if (isset($transaction['is_split']) && $transaction['is_split']) {
            // Delete child items first
            $stmt = $pdo->prepare("DELETE FROM yourTableName WHERE parent_id = :parent_id");
            $stmt->execute(['parent_id' => $id]);
        }
        
        // Delete main transaction
        $stmt = $pdo->prepare("DELETE FROM yourTableName WHERE id = :id");
        $stmt->execute(['id' => $id]);
        
        // Commit transaction
        $pdo->commit();
        
        // Handle response
        if (isAjaxRequest()) {
            jsonResponse(true, 'Transaction deleted successfully', ['redirect' => 'index.php']);
        } else {
            // Redirect with success message
            header('Location: index.php?message=Transaction+deleted+successfully');
            exit;
        }
    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        
        // Handle error
        if (isAjaxRequest()) {
            jsonResponse(false, 'Error deleting transaction: ' . $e->getMessage());
        } else {
            die('Error deleting transaction: ' . $e->getMessage());
        }
    }
}

/**
 * Check if request is AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
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