<?php
/**
 * Database Connection
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'likviditetilleri_illeris');
define('DB_USER', 'likviditetilleri_illeris');
define('DB_PASS', 'Embn(d$tZ!r6');

// Create database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    // Log error to file instead of displaying it (for security)
    error_log('Database Connection Error: ' . $e->getMessage());
    die('Database connection failed. Please check the error log for details.');
}