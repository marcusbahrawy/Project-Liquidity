<?php
/**
 * Logout Page
 */

// Include authentication functions
require_once 'auth.php';

// Log out the user
logoutUser();

// Redirect to login page
header('Location: /auth/login.php');
exit;