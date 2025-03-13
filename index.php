<?php
/**
 * Main Index File
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

// Get summary data for dashboard
// Get current month income total
$currentMonth = date('Y-m');
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM incoming 
    WHERE DATE_FORMAT(date, '%Y-%m') = :month
");
$stmt->execute(['month' => $currentMonth]);
$income = $stmt->fetch();
$totalIncome = $income['total'] ?? 0;

// Get current month expense total
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM outgoing 
    WHERE DATE_FORMAT(date, '%Y-%m') = :month
");
$stmt->execute(['month' => $currentMonth]);
$expense = $stmt->fetch();
$totalExpense = $expense['total'] ?? 0;

// Calculate balance
$balance = $totalIncome - $totalExpense;

// Get previous month data for comparison
$prevMonth = date('Y-m', strtotime('-1 month'));
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM incoming 
    WHERE DATE_FORMAT(date, '%Y-%m') = :month
");
$stmt->execute(['month' => $prevMonth]);
$prevIncome = $stmt->fetch();
$prevTotalIncome = $prevIncome['total'] ?? 0;

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM outgoing 
    WHERE DATE_FORMAT(date, '%Y-%m') = :month
");
$stmt->execute(['month' => $prevMonth]);
$prevExpense = $stmt->fetch();
$prevTotalExpense = $prevExpense['total'] ?? 0;

$prevBalance = $prevTotalIncome - $prevTotalExpense;

// Calculate percentage changes (avoid division by zero)
$incomeChange = ($prevTotalIncome > 0) ? (($totalIncome - $prevTotalIncome) / $prevTotalIncome * 100) : 0;
$expenseChange = ($prevTotalExpense > 0) ? (($totalExpense - $prevTotalExpense) / $prevTotalExpense * 100) : 0;
$balanceChange = ($prevBalance != 0) ? (($balance - $prevBalance) / abs($prevBalance) * 100) : 0;

// Get total debt
$stmt = $pdo->prepare("SELECT COALESCE(SUM(remaining_amount), 0) as total FROM debt");
$stmt->execute();
$debtTotal = $stmt->fetch();
$totalDebt = $debtTotal['total'] ?? 0;

// Get recent transactions (both incoming and outgoing)
$stmt = $pdo->prepare("
    (SELECT 'incoming' as type, i.id, i.description, i.amount, i.date, c.name as category, c.color
     FROM incoming i
     LEFT JOIN categories c ON i.category_id = c.id
     WHERE i.parent_id IS NULL
     ORDER BY i.date DESC
     LIMIT 5)
    UNION ALL
    (SELECT 'outgoing' as type, o.id, o.description, o.amount, o.date, c.name as category, c.color
     FROM outgoing o
     LEFT JOIN categories c ON o.category_id = c.id
     WHERE o.parent_id IS NULL AND o.is_debt = 0
     ORDER BY o.date DESC
     LIMIT 5)
    ORDER BY date DESC
    LIMIT 10
");
$stmt->execute();
$recentTransactions = $stmt->fetchAll();

// Get upcoming expenses
$currentDate = date('Y-m-d');
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

// Get category spending breakdown
$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.color, COALESCE(SUM(o.amount), 0) as total
    FROM categories c
    LEFT JOIN outgoing o ON o.category_id = c.id AND DATE_FORMAT(o.date, '%Y-%m') = :month AND o.is_debt = 0
    WHERE c.type IN ('outgoing', 'both')
    GROUP BY c.id, c.name, c.color
    HAVING total > 0
    ORDER BY total DESC
    LIMIT 6
");
$stmt->execute(['month' => $currentMonth]);
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
            <?php echo abs(round($balanceChange, 1)); ?>% from last month
        </div>
    </div>
    
    <!-- Income Card -->
    <div class="stat-card">
        <div class="stat-icon bg-success text-light">
            <i class="fas fa-arrow-down"></i>
        </div>
        <div class="stat-value"><?php echo number_format($totalIncome, 2); ?> kr</div>
        <div class="stat-label">Monthly Income</div>
        <div class="stat-trend <?php echo ($incomeChange >= 0) ? 'trend-up' : 'trend-down'; ?>">
            <i class="fas <?php echo ($incomeChange >= 0) ? 'fa-arrow-up' : 'fa-arrow-down'; ?>"></i>
            <?php echo abs(round($incomeChange, 1)); ?>% from last month
        </div>
    </div>
    
    <!-- Expense Card -->
    <div class="stat-card">
        <div class="stat-icon bg-danger text-light">
            <i class="fas fa-arrow-up"></i>
        </div>
        <div class="stat-value"><?php echo number_format($totalExpense, 2); ?> kr</div>
        <div class="stat-label">Monthly Expenses</div>
        <div class="stat-trend <?php echo ($expenseChange <= 0) ? 'trend-up' : 'trend-down'; ?>">
            <i class="fas <?php echo ($expenseChange <= 0) ? 'fa-arrow-down' : 'fa-arrow-up'; ?>"></i>
            <?php echo abs(round($expenseChange, 1)); ?>% from last month
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
            Updated today
        </div>
    </div>
</div>

<!-- Liquidity Timeline Chart -->
<div class="timeline-chart-container">
    <div class="timeline-chart-header">
        <div class="timeline-chart-title">Liquidity Timeline</div>
        <div class="timeline-chart-actions">
            <select id="timelineRange" class="form-select" style="width: auto; padding: 5px 30px 5px 10px;">
                <option value="30">Last 30 Days</option>
                <option value="90">Last 90 Days</option>
                <option value="180">Last 180 Days</option>
                <option value="365">Last 365 Days</option>
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
                <div class="transactions-title">Recent Transactions</div>
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
                            <div class="transaction-title">No transactions found</div>
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
                <div class="categories-title">Spending by Category</div>
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

<?php
// Include footer
require_once 'includes/footer.php';