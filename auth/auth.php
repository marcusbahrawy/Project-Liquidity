<?php
/**
 * Authentication Functions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Authenticate user with email and password
 *
 * @param string $email User email
 * @param string $password User password (plain text)
 * @return bool Authentication success
 */
function authenticateUser($email, $password) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, email, password, name FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['logged_in'] = true;
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log('Authentication Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if user is logged in
 *
 * @return bool Login status
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Logout user
 *
 * @return void
 */
function logoutUser() {
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy the session
    session_destroy();
}

/**
 * Redirect if not logged in
 *
 * @param string $redirect_url URL to redirect to
 * @return void
 */
function requireLogin($redirect_url = '/auth/login.php') {
    if (!isLoggedIn()) {
        header("Location: $redirect_url");
        exit;
    }
}

/**
 * Get current user ID
 *
 * @return int|null User ID or null if not logged in
 */
function getCurrentUserId() {
    return isLoggedIn() ? $_SESSION['user_id'] : null;
}