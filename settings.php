<?php
/**
 * Settings Page
 */

// Include database connection
require_once 'config/database.php';

// Include authentication functions
require_once 'auth/auth.php';

// Redirect to login if not authenticated
if (!isLoggedIn()) {
    header('Location: /auth/login.php');
    exit;
}

// Process form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Update initial balance
if (isset($_POST['initial_balance'])) {
    $initialBalance = filter_var($_POST['initial_balance'], FILTER_VALIDATE_FLOAT);
    
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES ('initial_balance', :insert_value)
        ON DUPLICATE KEY UPDATE setting_value = :update_value
    ");
    $stmt->execute([
        'insert_value' => $initialBalance,
        'update_value' => $initialBalance
    ]);
}
        
        // Update currency
if (isset($_POST['currency'])) {
    $currency = trim($_POST['currency']);
    
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES ('currency', :insert_value)
        ON DUPLICATE KEY UPDATE setting_value = :update_value
    ");
    $stmt->execute([
        'insert_value' => $currency,
        'update_value' => $currency
    ]);
}

// Update date format
if (isset($_POST['date_format'])) {
    $dateFormat = trim($_POST['date_format']);
    
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value)
        VALUES ('date_format', :insert_value)
        ON DUPLICATE KEY UPDATE setting_value = :update_value
    ");
    $stmt->execute([
        'insert_value' => $dateFormat,
        'update_value' => $dateFormat
    ]);
}
        
        // Commit transaction
        $pdo->commit();
        
        $message = 'Settings updated successfully!';
        $messageType = 'success';
    } catch (PDOException $e) {
        // Rollback transaction
        $pdo->rollBack();
        
        $message = 'Error updating settings: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get current settings
$settings = [];

try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $message = 'Error retrieving settings: ' . $e->getMessage();
    $messageType = 'error';
}

// Set defaults for missing settings
if (!isset($settings['initial_balance'])) {
    $settings['initial_balance'] = '0';
}

if (!isset($settings['currency'])) {
    $settings['currency'] = 'NOK';
}

if (!isset($settings['date_format'])) {
    $settings['date_format'] = 'Y-m-d';
}

// Include header
require_once 'includes/header.php';
?>

<div class="module-header">
    <div class="module-title">
        <h1>System Settings</h1>
        <p>Configure application-wide settings</p>
    </div>
    <div class="module-actions">
        <a href="index.php" class="btn btn-light">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?> mb-4">
        <i class="fas fa-<?php echo $messageType === 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <div class="card-title">General Settings</div>
    </div>
    <div class="card-body">
        <form method="post" action="">
            <div class="form-group">
                <label for="initial_balance">Initial Balance</label>
                <div class="input-group">
                    <input type="number" id="initial_balance" name="initial_balance" class="form-control" step="0.01" value="<?php echo htmlspecialchars($settings['initial_balance']); ?>">
                    <div class="input-group-append">
                        <span class="input-group-text"><?php echo htmlspecialchars($settings['currency']); ?></span>
                    </div>
                </div>
                <small class="form-text text-muted">This is the starting balance for your account, before any transactions.</small>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="currency">Currency</label>
                    <input type="text" id="currency" name="currency" class="form-control" value="<?php echo htmlspecialchars($settings['currency']); ?>">
                    <small class="form-text text-muted">Currency code (e.g., NOK, USD, EUR)</small>
                </div>
                
                <div class="form-group col-md-6">
                    <label for="date_format">Date Format</label>
                    <select id="date_format" name="date_format" class="form-select">
                        <option value="Y-m-d" <?php echo $settings['date_format'] === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                        <option value="d-m-Y" <?php echo $settings['date_format'] === 'd-m-Y' ? 'selected' : ''; ?>>DD-MM-YYYY</option>
                        <option value="m/d/Y" <?php echo $settings['date_format'] === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                        <option value="d/m/Y" <?php echo $settings['date_format'] === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                    </select>
                    <small class="form-text text-muted">Format for displaying dates</small>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.form-actions {
    margin-top: 30px;
}

.input-group {
    display: flex;
}

.input-group-append {
    display: flex;
}

.input-group-text {
    display: flex;
    align-items: center;
    padding: 0.375rem 0.75rem;
    font-size: 1rem;
    font-weight: 400;
    line-height: 1.5;
    color: #495057;
    text-align: center;
    white-space: nowrap;
    background-color: #e9ecef;
    border: 1px solid #ced4da;
    border-radius: 0 0.25rem 0.25rem 0;
}
</style>

<?php
// Include footer
require_once 'includes/footer.php';
?>