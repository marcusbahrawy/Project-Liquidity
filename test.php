<?php
// Display PHP info
phpinfo();

// Test database connection
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=likviditetilleri_illeris;charset=utf8mb4",
        "likviditetilleri_illeris",
        "Embn(d$tZ!r6",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<p>Database connection successful!</p>";
} catch (PDOException $e) {
    echo "<p>Database connection failed: " . $e->getMessage() . "</p>";
}
?>