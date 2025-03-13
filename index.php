<?php
/**
 * Main Index File with Future Data
 * 
 * This redirects to the dashboard or serves as the dashboard itself.
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

// Get initial balance from settings
function getInitialBalance() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT setting_value FROM settings 
            WHERE setting_key = 'initial_balance'
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return (float)$result['setting_value'];
        }
    } catch (PDOException $e) {
        // If there's an error or the settings table doesn't exist yet
        return 0;
    }
    
    return 0; // Default to 0 if not found
}

// Get summary data for dashboard
// Get current date
$currentDate = date('Y-m-d');

// Get initial balance
$initialBalance = getInitialBalance();

// Get transaction sum (all transactions up to current date)
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COALESCE(SUM(amount), 0) FROM incoming WHERE date <= :current_date_inc AND parent_id IS NULL) -
        (SELECT COALESCE(SUM(amount), 0) FROM outgoing WHERE date <= :current_date_out AND parent_id IS NULL) as transaction_sum
");
$stmt->execute([
    'current_date_inc' => $currentDate,
    'current_date_out' => $currentDate
]);
$result = $stmt->fetch();
$transactionSum = $result['transaction_sum'] ?? 0;

// Current balance is initial balance + all transactions
$balance = $initialBalance + $transactionSum;

// Get upcoming income for the next 30 days
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM incoming 
    WHERE date BETWEEN :start_date AND :end_date
    AND parent_id IS NULL
");
$stmt->execute([
    'start_date' => $currentDate,
    'end_date' => date('Y-m-d', strtotime('+30 days'))
]);
$income = $stmt->fetch();
$totalIncome = $income['total'] ?? 0;

// Get upcoming expenses for the next 30 days
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM outgoing 
    WHERE date BETWEEN :start_date AND :end_date
    AND parent_id IS NULL
");
$stmt->execute([
    'start_date' => $currentDate,
    'end_date' => date('Y-m-d', strtotime('+30 days'))
]);
$expense = $stmt->fetch();
$totalExpense = $expense['total'] ?? 0;

// Calculate projected balance
$projectedBalance = $balance + $totalIncome - $totalExpense;

// Calculate percentage change
$balanceChange = ($balance != 0) ? (($projectedBalance - $balance) / abs($balance) * 100) : 0;

// Get total debt
$stmt = $pdo->prepare("SELECT COALESCE(SUM(remaining_amount), 0) as total FROM debt");
$stmt->execute();
$debtTotal = $stmt->fetch();
$totalDebt = $debtTotal['total'] ?? 0;

// Get upcoming transactions (both incoming and outgoing)
$stmt = $pdo->prepare("
    (SELECT 'incoming' as type, i.id, i.description, i.amount, i.date, c.name as category, c.color
     FROM incoming i
     LEFT JOIN categories c ON i.category_id = c.id
     WHERE i.date >= :current_date_inc AND i.parent_id IS NULL
     ORDER BY i.date ASC
     LIMIT 5)
    UNION ALL
    (SELECT 'outgoing' as type, o.id, o.description, o.amount, o.date, c.name as category, c.color
     FROM outgoing o
     LEFT JOIN categories c ON o.category_id = c.id
     WHERE o.date >= :current_date_out AND o.parent_id IS NULL AND o.is_debt = 0
     ORDER BY o.date ASC
     LIMIT 5)
    ORDER BY date ASC
    LIMIT 10
");
$stmt->execute([
    'current_date_inc' => $currentDate,
    'current_date_out' => $currentDate
]);
$recentTransactions = $stmt->fetchAll();

// Get upcoming expenses specifically
$stmt = $pdo->prepare("
    SELECT o.id, o.description, o.amount, o.date, c.name as category, c.color
    FROM outgoing o
    LEFT JOIN categories c ON o.category_id = c.id
    WHERE o.date >= :currentDate AND o.is_debt = 0 AND o.parent_id IS NULL
    ORDER BY o.date ASC
    LIMIT 5
");
$stmt->execute(['currentDate' => $currentDate]);
$upcomingExpenses = $stmt->fetchAll();

// Get category spending breakdown for upcoming expenses
$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.color, COALESCE(SUM(o.amount), 0) as total
    FROM categories c
    JOIN outgoing o ON o.category_id = c.id 
    WHERE o.date >= :current_date AND o.is_debt = 0 AND o.parent_id IS NULL
    GROUP BY c.id, c.name, c.color
    HAVING total > 0
    ORDER BY total DESC
    LIMIT 6
");
$stmt->execute(['current_date' => $currentDate]);
$categorySpending = $stmt->fetchAll();

// Include header
require_once 'includes/header.php';
?>

<!-- Dashboard Content -->
<h1 class="content-title mb-4">Dashboard</h1>

<!-- Stats Cards -->
<div class="stats-container">
    <!-- Balance Card -->
    <div class="stat-card">
        <div class="stat-icon bg-primary text-light">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-value"><?php echo number_format($balance, 2); ?> kr</div>
        <div class="stat-label">Current Balance</div>
        <div class="stat-trend <?php echo ($balanceChange >= 0) ? 'trend-up' : 'trend-down'; ?>">
            <i class="fas <?php echo ($balanceChange >= 0) ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i>
            <?php echo abs(round($balanceChange, 1)); ?>% projected change
        </div>
    </div>
    
    <!-- Income Card -->
    <div class="stat-card">
        <div class="stat-icon bg-success text-light">
            <i class="fas fa-arrow-down"></i>
        </div>
        <div class="stat-value"><?php echo number_format($totalIncome, 2); ?> kr</div>
        <div class="stat-label">Upcoming Income</div>
        <div class="stat-trend trend-neutral">
            <i class="fas fa-calendar"></i>
            Next 30 days
        </div>
    </div>
    
    <!-- Expense Card -->
    <div class="stat-card">
        <div class="stat-icon bg-danger text-light">
            <i class="fas fa-arrow-up"></i>
        </div>
        <div class="stat-value"><?php echo number_format($totalExpense, 2); ?> kr</div>
        <div class="stat-label">Upcoming Expenses</div>
        <div class="stat-trend trend-neutral">
            <i class="fas fa-calendar"></i>
            Next 30 days
        </div>
    </div>
    
    <!-- Debt Card -->
    <div class="stat-card">
        <div class="stat-icon bg-warning text-light">
            <i class="fas fa-credit-card"></i>
        </div>
        <div class="stat-value"><?php echo number_format($totalDebt, 2); ?> kr</div>
        <div class="stat-label">Total Debt</div>
        <div class="stat-trend trend-neutral">
            <i class="fas fa-calendar-alt"></i>
            Current total
        </div>
    </div>
</div>

<!-- Settings Link -->
<div class="mb-4 text-right">
    <a href="settings.php" class="btn btn-light btn-sm">
        <i class="fas fa-cog"></i> Settings
    </a>
</div>

<!-- Liquidity Timeline Chart -->
<div class="timeline-chart-container">
    <div class="timeline-chart-header">
        <div class="timeline-chart-title">Liquidity Timeline</div>
        <div class="timeline-chart-actions">
            <select id="timelineRange" class="form-select" style="width: auto; padding: 5px 30px 5px 10px;">
                <option value="30">Next 30 Days</option>
                <option value="60">Next 60 Days</option>
                <option value="90">Next 90 Days</option>
            </select>
        </div>
    </div>
    <canvas id="liquidityChart" class="timeline-chart-canvas"></canvas>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Recent Transactions -->
        <div class="recent-transactions">
            <div class="transactions-header">
                <div class="transactions-title">Upcoming Transactions</div>
                <div class="transactions-nav">
                    <a href="#" class="active" data-filter="all">All</a>
                    <a href="#" data-filter="incoming">Income</a>
                    <a href="#" data-filter="outgoing">Expenses</a>
                </div>
            </div>
            
            <div class="transaction-list">
                <?php if (empty($recentTransactions)): ?>
                    <div class="transaction-item">
                        <div class="transaction-details">
                            <div class="transaction-title">No upcoming transactions found</div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentTransactions as $transaction): ?>
                        <div class="transaction-item" data-type="<?php echo $transaction['type']; ?>">
                            <div class="transaction-icon <?php echo $transaction['type']; ?>">
                                <i class="fas fa-<?php echo ($transaction['type'] === 'incoming') ? 'arrow-down' : 'arrow-up'; ?>"></i>
                            </div>
                            <div class="transaction-details">
                                <div class="transaction-title"><?php echo htmlspecialchars($transaction['description']); ?></div>
                                <div class="transaction-category"><?php echo htmlspecialchars($transaction['category'] ?? 'Uncategorized'); ?></div>
                            </div>
                            <div class="transaction-amount amount-<?php echo $transaction['type']; ?>">
                                <?php echo ($transaction['type'] === 'incoming' ? '+' : '-'); ?><?php echo number_format($transaction['amount'], 2); ?> kr
                            </div>
                            <div class="transaction-date">
                                <?php echo date('M d, Y', strtotime($transaction['date'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="transactions-footer">
                <a href="/modules/incoming/index.php">View All Incoming</a> | 
                <a href="/modules/outgoing/index.php">View All Outgoing</a>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Upcoming Expenses -->
        <div class="upcoming-expenses">
            <div class="upcoming-header">
                <div class="upcoming-title">Upcoming Expenses</div>
            </div>
            
            <div class="expense-list">
                <?php if (empty($upcomingExpenses)): ?>
                    <div class="expense-item">
                        <div class="expense-details">
                            <div class="expense-title">No upcoming expenses</div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($upcomingExpenses as $expense): ?>
                        <div class="expense-item">
                            <div class="expense-date">
                                <div class="expense-day"><?php echo date('d', strtotime($expense['date'])); ?></div>
                                <div class="expense-month"><?php echo date('M', strtotime($expense['date'])); ?></div>
                            </div>
                            <div class="expense-details">
                                <div class="expense-title"><?php echo htmlspecialchars($expense['description']); ?></div>
                                <div class="expense-category"><?php echo htmlspecialchars($expense['category'] ?? 'Uncategorized'); ?></div>
                            </div>
                            <div class="expense-amount">
                                -<?php echo number_format($expense['amount'], 2); ?> kr
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Categories Spending -->
        <div class="categories-chart">
            <div class="categories-header">
                <div class="categories-title">Upcoming Expenses by Category</div>
            </div>
            
            <div class="categories-container">
                <div class="categories-donut">
                    <canvas id="categoriesChart"></canvas>
                </div>
                
                <div class="categories-legend">
                    <?php foreach ($categorySpending as $category): ?>
                        <div class="legend-item">
                            <div class="legend-color" style="background-color: <?php echo htmlspecialchars($category['color']); ?>"></div>
                            <div class="legend-name"><?php echo htmlspecialchars($category['name']); ?></div>
                            <div class="legend-value"><?php echo number_format($category['total'], 2); ?> kr</div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($categorySpending)): ?>
                        <div class="legend-item">
                            <div class="legend-name">No category data available</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.text-right {
    text-align: right;
}
</style>

<?php
// Include footer
require_once 'includes/footer.php';
?>