<?php
/**
 * Categories API
 * 
 * Handles AJAX requests and form submissions for category management.
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
        addCategory();
        break;
        
    case 'update':
        updateCategory();
        break;
        
    case 'delete':
        deleteCategory();
        break;
        
    case 'get':
        getCategory();
        break;
        
    default:
        jsonResponse(false, 'Invalid action specified');
}

/**
 * Add new category
 */
function addCategory() {
    global $pdo;
    
    // Check if required fields are provided
    $requiredFields = ['name', 'type', 'color'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            jsonResponse(false, "Field '{$field}' is required");
        }
    }
    
    // Validate type (incoming, outgoing, both)
    $validTypes = ['incoming', 'outgoing', 'both'];
    if (!in_array($_POST['type'], $validTypes)) {
        jsonResponse(false, 'Invalid category type');
    }
    
    // Validate color format (hex code)
    if (!preg_match('/^#[a-f0-9]{6}$/i', $_POST['color'])) {
        jsonResponse(false, 'Invalid color format (must be hex code like #3498db)');
    }
    
    try {
        // Check if category name already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM categories WHERE name = :name");
        $stmt->execute(['name' => $_POST['name']]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            jsonResponse(false, 'A category with this name already exists');
        }
        
        // Insert new category
        $stmt = $pdo->prepare("
            INSERT INTO categories (name, type, color, created_at)
            VALUES (:name, :type, :color, NOW())
        ");
        
        $stmt->execute([
            'name' => $_POST['name'],
            'type' => $_POST['type'],
            'color' => $_POST['color']
        ]);
        
        jsonResponse(true, 'Category added successfully');
    } catch (PDOException $e) {
        jsonResponse(false, 'Error adding category: ' . $e->getMessage());
    }
}

/**
 * Update existing category
 */
function updateCategory() {
    global $pdo;
    
    // Check if ID is provided
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        jsonResponse(false, 'Category ID is required');
    }
    
    // Check if required fields are provided
    $requiredFields = ['name', 'type', 'color'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            jsonResponse(false, "Field '{$field}' is required");
        }
    }
    
    // Validate type (incoming, outgoing, both)
    $validTypes = ['incoming', 'outgoing', 'both'];
    if (!in_array($_POST['type'], $validTypes)) {
        jsonResponse(false, 'Invalid category type');
    }
    
    // Validate color format (hex code)
    if (!preg_match('/^#[a-f0-9]{6}$/i', $_POST['color'])) {
        jsonResponse(false, 'Invalid color format (must be hex code like #3498db)');
    }
    
    try {
        // Check if category exists
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id");
        $stmt->execute(['id' => $_POST['id']]);
        $category = $stmt->fetch();
        
        if (!$category) {
            jsonResponse(false, 'Category not found');
        }
        
        // Check if another category with the same name exists (excluding this one)
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM categories WHERE name = :name AND id != :id");
        $stmt->execute([
            'name' => $_POST['name'],
            'id' => $_POST['id']
        ]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            jsonResponse(false, 'Another category with this name already exists');
        }
        
        // Update category
        $stmt = $pdo->prepare("
            UPDATE categories 
            SET name = :name, type = :type, color = :color
            WHERE id = :id
        ");
        
        $stmt->execute([
            'name' => $_POST['name'],
            'type' => $_POST['type'],
            'color' => $_POST['color'],
            'id' => $_POST['id']
        ]);
        
        jsonResponse(true, 'Category updated successfully');
    } catch (PDOException $e) {
        jsonResponse(false, 'Error updating category: ' . $e->getMessage());
    }
}

/**
 * Delete category
 */
function deleteCategory() {
    global $pdo;
    
    // Check if ID is provided
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        jsonResponse(false, 'Category ID is required');
    }
    
    $id = (int)$_GET['id'];
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Check if category is used in transactions
        $tables = ['incoming', 'outgoing', 'debt'];
        $isUsed = false;
        
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM {$table} WHERE category_id = :category_id");
            $stmt->execute(['category_id' => $id]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                $isUsed = true;
                break;
            }
        }
        
        if ($isUsed) {
            // Option 1: Don't allow deletion of used categories
            // $pdo->rollBack();
            // jsonResponse(false, 'Cannot delete this category because it is used in transactions');
            
            // Option 2: Set category_id to NULL where this category is used
            foreach ($tables as $table) {
                $stmt = $pdo->prepare("UPDATE {$table} SET category_id = NULL WHERE category_id = :category_id");
                $stmt->execute(['category_id' => $id]);
            }
        }
        
        // Delete category
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->execute(['id' => $id]);
        
        // Commit transaction
        $pdo->commit();
        
        jsonResponse(true, 'Category deleted successfully');
    } catch (PDOException $e) {
        // Rollback transaction
        $pdo->rollBack();
        
        jsonResponse(false, 'Error deleting category: ' . $e->getMessage());
    }
}

/**
 * Get category details
 */
function getCategory() {
    global $pdo;
    
    // Check if ID is provided
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        jsonResponse(false, 'Category ID is required');
    }
    
    $id = (int)$_GET['id'];
    
    try {
        // Get category
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $category = $stmt->fetch();
        
        if (!$category) {
            jsonResponse(false, 'Category not found');
        }
        
        jsonResponse(true, 'Category retrieved successfully', ['category' => $category]);
    } catch (PDOException $e) {
        jsonResponse(false, 'Error retrieving category: ' . $e->getMessage());
    }
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