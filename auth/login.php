<?php
/**
 * Login Page
 */

// Include database connection
require_once '../config/database.php';
require_once 'auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        if (authenticateUser($email, $password)) {
            header('Location: /index.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Liquidity Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3498db;
            --primary-dark: #2980b9;
            --secondary: #2ecc71;
            --dark: #2c3e50;
            --light: #ecf0f1;
            --danger: #e74c3c;
            --warning: #f39c12;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #3498db, #2c3e50);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        
        .login-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
            width: 400px;
            padding: 40px;
            text-align: center;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .login-logo {
            margin-bottom: 30px;
        }
        
        .login-logo h1 {
            color: var(--dark);
            font-weight: 300;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .login-logo p {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-size: 14px;
        }
        
        .input-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 15px;
            transition: border 0.3s;
        }
        
        .input-group input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .login-button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            width: 100%;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .login-button:hover {
            background-color: var(--primary-dark);
        }
        
        .error-message {
            color: var(--danger);
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .login-footer {
            margin-top: 30px;
            color: #7f8c8d;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <h1>Likviditet</h1>
            <p>Liquidity Management Tool</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="input-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required autocomplete="email">
            </div>
            
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="login-button">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        
        <div class="login-footer">
            &copy; <?php echo date('Y'); ?> Illeris. All rights reserved.
        </div>
    </div>
</body>
</html>