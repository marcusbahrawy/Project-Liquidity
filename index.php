<?php
/**
 * Main Indexx File with Future Data and Dynamic Date Rangeeee
 * Updated with better split transaction handling in dashboard
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

// Default time range in days
$timeRange = 30;

// Get summary data for dashboard
// Get current date
$currentDate = date('Y-m-d');
$endDate = date('Y-m-d', strtotime("+{$timeRange} days"));

// Get initial balance
$initialBalance = getInitialBalance();

// Get transaction sum (all transactions up to current date)
// MODIFIED: Updated to include split items and exclude parent transactions
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COALESCE(SUM(amount), 0) FROM incoming WHERE date <= :current_date_inc 
         AND (parent_id IS NOT NULL OR (parent_id IS NULL AND is_split = 0))) -
        (SELECT COALESCE(SUM(amount), 0) FROM outgoing WHERE date <= :current_date_out 
         AND (parent_id IS NOT NULL OR (parent_id IS NULL AND is_split = 0))) as transaction_sum
");
$stmt->execute([
    'current_date_inc' => $currentDate,
    'current_date_out' => $currentDate
]);
$result = $stmt->fetch();
$transactionSum = $result['transaction_sum'] ?? 0;

// Current balance is initial balance + all transactions
$balance = $initialBalance + $transactionSum;

// Get upcoming income for the next x days
// MODIFIED: Updated to include split items and exclude parent transactions
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM incoming 
    WHERE date BETWEEN :start_date AND :end_date
    AND (parent_id IS NOT NULL OR (parent_id IS NULL AND is_split = 0))
");
$stmt->execute([
    'start_date' => $currentDate,
    'end_date' => $endDate
]);
$income = $stmt->fetch();
$totalIncome = $income['total'] ?? 0;

// Get upcoming expenses for the next x days
// MODIFIED: Updated to include split items and exclude parent transactions
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total 
    FROM outgoing 
    WHERE date BETWEEN :start_date AND :end_date
    AND (parent_id IS NOT NULL OR (parent_id IS NULL AND is_split = 0))
");
$stmt->execute([
    'start_date' => $currentDate,
    'end_date' => $endDate
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

// Get upcoming transactions for the dashboard
$upcoming_transactions_sql = "
    WITH effective_dates AS (
        SELECT 
            'incoming' as type,
            i.id,
            i.description,
            i.amount,
            i.date,
            i.is_split,
            i.is_fixed,
            i.category_id,
            COALESCE(
                (SELECT MAX(date) 
                 FROM incoming 
                 WHERE parent_id = i.id),
                i.date
            ) as effective_date
        FROM incoming i
        WHERE i.parent_id IS NULL
        UNION ALL
        SELECT 
            'outgoing' as type,
            o.id,
            o.description,
            o.amount,
            o.date,
            o.is_split,
            o.is_fixed,
            o.category_id,
            COALESCE(
                (SELECT MAX(date) 
                 FROM outgoing 
                 WHERE parent_id = o.id),
                o.date
            ) as effective_date
        FROM outgoing o
        WHERE o.parent_id IS NULL
    )
    SELECT e.*, c.name as category_name, c.color as category_color
    FROM effective_dates e
    LEFT JOIN categories c ON e.category_id = c.id
    WHERE (e.effective_date >= CURRENT_DATE OR e.is_fixed = 1)
    ORDER BY e.effective_date ASC
    LIMIT 5
";

$stmt = $pdo->prepare($upcoming_transactions_sql);
$stmt->execute();
$upcomingTransactions = $stmt->fetchAll();

// Process transactions to include split items
$organizedTransactions = [];

foreach ($upcomingTransactions as $transaction) {
    // Add the transaction to our organized list
    $organizedTransaction = $transaction;
    
    // If it's a split transaction, fetch its split items
    if ($transaction['is_split']) {
        $splits = [];
        
        if ($transaction['type'] === 'incoming') {
            // Fetch incoming split items
            $splitStmt = $pdo->prepare("
                SELECT i.id, i.description, i.amount, i.date, c.name as category, c.color
                FROM incoming i
                LEFT JOIN categories c ON i.category_id = c.id
                WHERE i.parent_id = :parent_id
                ORDER BY i.amount DESC
            ");
            $splitStmt->execute(['parent_id' => $transaction['id']]);
            $splits = $splitStmt->fetchAll();
        } else {
            // Fetch outgoing split items
            $splitStmt = $pdo->prepare("
                SELECT o.id, o.description, o.amount, o.date, c.name as category, c.color
                FROM outgoing o
                LEFT JOIN categories c ON o.category_id = c.id
                WHERE o.parent_id = :parent_id
                ORDER BY o.amount DESC
            ");
            $splitStmt->execute(['parent_id' => $transaction['id']]);
            $splits = $splitStmt->fetchAll();
        }
        
        $organizedTransaction['splits'] = $splits;
    }
    
    $organizedTransactions[] = $organizedTransaction;
}

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
            <i class="fas fa-arrow-up"></i>
        </div>
        <div class="stat-value"><?php echo number_format($totalIncome, 2); ?> kr</div>
        <div class="stat-label">Upcoming Income</div>
        <div class="stat-trend trend-neutral">
            <i class="fas fa-calendar"></i>
            Next <span class="days-range"><?php echo $timeRange; ?></span> days
        </div>
    </div>
    
    <!-- Expense Card -->
    <div class="stat-card">
        <div class="stat-icon bg-danger text-light">
            <i class="fas fa-arrow-down"></i>
        </div>
        <div class="stat-value"><?php echo number_format($totalExpense, 2); ?> kr</div>
        <div class="stat-label">Upcoming Expenses</div>
        <div class="stat-trend trend-neutral">
            <i class="fas fa-calendar"></i>
            Next <span class="days-range"><?php echo $timeRange; ?></span> days
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

<!-- Settings and Debug Controls -->
<div class="mb-4 text-right">
    <a href="settings.php" class="btn btn-light btn-sm">
        <i class="fas fa-cog"></i> Settings
    </a>
    <button id="clear-cache-btn" class="btn btn-sm btn-light" style="margin-left: 10px;">
        <i class="fas fa-sync"></i> Refresh Data
    </button>
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
    <div class="col-lg-6">
        <!-- Upcoming Income -->
        <div class="upcoming-expenses">
            <div class="upcoming-header">
                <div class="upcoming-title">Upcoming Income <span class="small">(Next <span id="transaction-days"><?php echo $timeRange; ?></span> days)</span></div>
                <div id="income-loading" class="loading-indicator" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i> Loading...
                </div>
            </div>
            
            <div id="income-container" class="expense-list">
                <?php 
                $incomingTransactions = array_filter($organizedTransactions, function($t) {
                    return $t['type'] === 'incoming';
                });
                
                if (empty($incomingTransactions)): ?>
                    <div class="expense-item">
                        <div class="expense-details">
                            <div class="expense-title">No upcoming income</div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($incomingTransactions as $transaction): ?>
                        <?php 
                        $hasChildSplits = isset($transaction['splits']) && !empty($transaction['splits']);
                        ?>
                        <div class="expense-item">
                            <div class="expense-date" <?php echo $hasChildSplits ? 'style="display: none;"' : ''; ?>>
                                <div class="expense-day"><?php echo date('d', strtotime($transaction['date'])); ?></div>
                                <div class="expense-month"><?php echo date('M', strtotime($transaction['date'])); ?></div>
                            </div>
                            <div class="expense-details">
                                <div class="expense-title">
                                    <?php echo htmlspecialchars($transaction['description']); ?>
                                    <?php if ($transaction['is_split']): ?>
                                        <span class="badge badge-info">Split</span>
                                    <?php endif; ?>
                                    <span class="transaction-type-badge type-incoming">Income</span>
                                </div>
                                <div class="expense-category"><?php echo htmlspecialchars($transaction['category'] ?? 'Uncategorized'); ?></div>
                            </div>
                            <div class="expense-amount amount-income">
                                +<?php echo number_format($transaction['amount'], 2); ?> kr
                            </div>
                        </div>
                        
                        <?php if (isset($transaction['splits']) && !empty($transaction['splits'])): ?>
                            <?php foreach ($transaction['splits'] as $split): ?>
                                <div class="expense-item split-item">
                                    <div class="expense-date">
                                        <div class="expense-day"><?php echo date('d', strtotime($split['date'])); ?></div>
                                        <div class="expense-month"><?php echo date('M', strtotime($split['date'])); ?></div>
                                    </div>
                                    <div class="expense-details split-details">
                                        <div class="expense-title">
                                            <i class="fas fa-level-down-alt"></i>
                                            <?php echo htmlspecialchars($split['description']); ?>
                                        </div>
                                    </div>
                                    <div class="expense-amount amount-income">
                                        +<?php echo number_format($split['amount'], 2); ?> kr
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <!-- Upcoming Expenses -->
        <div class="upcoming-expenses">
            <div class="upcoming-header">
                <div class="upcoming-title">Upcoming Expenses <span class="small">(Next <span id="transaction-days"><?php echo $timeRange; ?></span> days)</span></div>
                <div id="expense-loading" class="loading-indicator" style="display: none;">
                    <i class="fas fa-spinner fa-spin"></i> Loading...
                </div>
            </div>
            
            <div id="expense-container" class="expense-list">
                <?php 
                $outgoingTransactions = array_filter($organizedTransactions, function($t) {
                    return $t['type'] === 'outgoing';
                });
                
                if (empty($outgoingTransactions)): ?>
                    <div class="expense-item">
                        <div class="expense-details">
                            <div class="expense-title">No upcoming expenses</div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($outgoingTransactions as $transaction): ?>
                        <?php 
                        $hasChildSplits = isset($transaction['splits']) && !empty($transaction['splits']);
                        ?>
                        <div class="expense-item">
                            <div class="expense-date" <?php echo $hasChildSplits ? 'style="display: none;"' : ''; ?>>
                                <div class="expense-day"><?php echo date('d', strtotime($transaction['date'])); ?></div>
                                <div class="expense-month"><?php echo date('M', strtotime($transaction['date'])); ?></div>
                            </div>
                            <div class="expense-details">
                                <div class="expense-title">
                                    <?php echo htmlspecialchars($transaction['description']); ?>
                                    <?php if ($transaction['is_split']): ?>
                                        <span class="badge badge-info">Split</span>
                                    <?php endif; ?>
                                    <span class="transaction-type-badge type-outgoing">Expense</span>
                                </div>
                                <div class="expense-category"><?php echo htmlspecialchars($transaction['category'] ?? 'Uncategorized'); ?></div>
                            </div>
                            <div class="expense-amount amount-expense">
                                -<?php echo number_format($transaction['amount'], 2); ?> kr
                            </div>
                        </div>
                        
                        <?php if (isset($transaction['splits']) && !empty($transaction['splits'])): ?>
                            <?php foreach ($transaction['splits'] as $split): ?>
                                <div class="expense-item split-item">
                                    <div class="expense-date">
                                        <div class="expense-day"><?php echo date('d', strtotime($split['date'])); ?></div>
                                        <div class="expense-month"><?php echo date('M', strtotime($split['date'])); ?></div>
                                    </div>
                                    <div class="expense-details split-details">
                                        <div class="expense-title">
                                            <i class="fas fa-level-down-alt"></i>
                                            <?php echo htmlspecialchars($split['description']); ?>
                                        </div>
                                    </div>
                                    <div class="expense-amount amount-expense">
                                        -<?php echo number_format($split['amount'], 2); ?> kr
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.text-right {
    text-align: right;
}

/* Transaction type badges */
.transaction-type-badge {
    display: inline-block;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    margin-left: 8px;
}

.type-incoming {
    background-color: rgba(46, 204, 113, 0.2);
    color: var(--secondary-dark);
}

.type-outgoing {
    background-color: rgba(231, 76, 60, 0.2);
    color: #c0392b;
}

/* Transaction amounts */
.amount-income {
    color: var(--success);
    font-weight: 600;
}

.amount-expense {
    color: var(--danger);
    font-weight: 600;
}

/* Modified layout for full width */
.col-lg-6 {
    flex: 0 0 50%;
    max-width: 50%;
    padding: 0 15px;
}

.upcoming-expenses {
    height: 100%;
    background-color: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    margin-bottom: 20px;
}

/* Loading indicator */
.loading-indicator {
    color: var(--gray);
    font-size: 14px;
}

.loading-indicator i {
    margin-right: 5px;
}

/* Header with loading indicator */
.upcoming-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
}

.small {
    font-size: 0.85em;
    font-weight: normal;
    color: var(--gray);
}

/* Split transaction styling */
.split-details {
    padding-left: 20px; /* Indentation for splits */
}

.split-item {
    background-color: rgba(236, 240, 241, 0.5); /* Light background to distinguish splits */
    border-top: none; /* Remove top border to make it look connected */
}

.split-item .expense-title i {
    margin-right: 5px;
    color: var(--gray);
}

.badge-info {
    background-color: rgba(52, 152, 219, 0.2);
    color: var(--primary-dark);
    display: inline-block;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    margin-left: 5px;
}

/* Ensure equal height for both containers */
.row {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -15px;
}

/* Make sure the expense list takes full height */
.expense-list {
    height: calc(100% - 60px); /* Subtract header height */
    overflow-y: auto;
}
</style>

<!-- Custom Script for Dynamic Date Range with Split Transaction Support -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get the timeline range dropdown
    const timelineRange = document.getElementById('timelineRange');
    
    // Automatically refresh data when dashboard loads
    const days = document.getElementById('timelineRange').value;
    updateDashboardData(days);
    
    // Add clear cache button functionality
    document.getElementById('clear-cache-btn').addEventListener('click', function() {
        // Force a clean reload of data
        const days = document.getElementById('timelineRange').value;
        const cacheBuster = new Date().getTime();
        
        // Show loading indicators
        document.getElementById('income-loading').style.display = 'block';
        document.getElementById('expense-loading').style.display = 'block';
        
        console.log("Forcing data refresh...");
        
        fetch(`/api_dashboard.php?action=transactions&days=${days}&_=${cacheBuster}`)
            .then(response => {
                console.log(`API response status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    console.log(`Refreshed data - received ${data.data.transactions.length} transactions`);
                    updateTransactionsList(data.data.transactions);
                    updateStatsCards(data.data.stats);
                } else {
                    console.error('Error fetching transactions:', data.message);
                }
                
                // Hide loading indicators
                document.getElementById('income-loading').style.display = 'none';
                document.getElementById('expense-loading').style.display = 'none';
            })
            .catch(error => {
                console.error('Error fetching transactions:', error);
                document.getElementById('income-loading').style.display = 'none';
                document.getElementById('expense-loading').style.display = 'none';
            });
    });
    
    // Add event listener to update transactions when range changes
    if (timelineRange) {
        timelineRange.addEventListener('change', function() {
            const days = parseInt(this.value, 10);
            updateDashboardData(days);
        });
    }
    
    // Function to update transaction data based on selected days
    function updateDashboardData(days) {
        // Update days display
        document.querySelectorAll('.days-range').forEach(el => {
            el.textContent = days;
        });
        document.getElementById('transaction-days').textContent = days;
        
        // Show loading indicators
        document.getElementById('income-loading').style.display = 'block';
        document.getElementById('expense-loading').style.display = 'block';
        
        // Add a cache-busting parameter to prevent browser caching
        const cacheBuster = new Date().getTime();
        
        // Log what we're fetching
        console.log(`Fetching transactions for ${days} days`);
        
        // Fetch updated transactions
        fetch(`/api_dashboard.php?action=transactions&days=${days}&_=${cacheBuster}`)
            .then(response => {
                // Log response status
                console.log(`API response status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Log number of transactions
                    console.log(`Received ${data.data.transactions.length} transactions`);
                    
                    // Update transaction UI
                    updateTransactionsList(data.data.transactions);
                    
                    // Update stats cards
                    updateStatsCards(data.data.stats);
                } else {
                    console.error('Error fetching transactions:', data.message);
                }
                
                // Hide loading indicators
                document.getElementById('income-loading').style.display = 'none';
                document.getElementById('expense-loading').style.display = 'none';
            })
            .catch(error => {
                console.error('Error fetching transactions:', error);
                document.getElementById('income-loading').style.display = 'none';
                document.getElementById('expense-loading').style.display = 'none';
            });
    }
    
    // Update transactions list in UI with split support
    function updateTransactionsList(transactions) {
        // Split transactions into income and expenses
        const incomeTransactions = transactions.filter(t => t.type === 'incoming');
        const expenseTransactions = transactions.filter(t => t.type === 'outgoing');
        
        // Update income container
        updateTransactionContainer('income-container', incomeTransactions, 'income');
        
        // Update expense container
        updateTransactionContainer('expense-container', expenseTransactions, 'expense');
    }
    
    // Helper function to update a specific transaction container
    function updateTransactionContainer(containerId, transactions, type) {
        const container = document.getElementById(containerId);
        
        // Clear current content
        container.innerHTML = '';
        
        if (transactions.length === 0) {
            container.innerHTML = `
                <div class="expense-item">
                    <div class="expense-details">
                        <div class="expense-title">No upcoming ${type === 'income' ? 'income' : 'expenses'}</div>
                    </div>
                </div>
            `;
            return;
        }
        
        // Add transactions
        transactions.forEach(transaction => {
            const date = new Date(transaction.date);
            const day = date.getDate().toString().padStart(2, '0');
            const month = date.toLocaleString('default', { month: 'short' });
            
            // Add the main transaction
            container.innerHTML += `
                <div class="expense-item">
                    <div class="expense-date">
                        <div class="expense-day">${day}</div>
                        <div class="expense-month">${month}</div>
                    </div>
                    <div class="expense-details">
                        <div class="expense-title">
                            ${escapeHtml(transaction.description)}
                            ${transaction.is_split ? '<span class="badge badge-info">Split</span>' : ''}
                            <span class="transaction-type-badge type-${type}">
                                ${type === 'income' ? 'Income' : 'Expense'}
                            </span>
                        </div>
                        <div class="expense-category">${escapeHtml(transaction.category || 'Uncategorized')}</div>
                    </div>
                    <div class="expense-amount amount-${type}">
                        ${type === 'income' ? '+' : '-'}${formatNumber(transaction.amount)} kr
                    </div>
                </div>
            `;
            
            // Add split items if any
            if (transaction.splits && transaction.splits.length > 0) {
                // Hide the date on the parent transaction
                const parentItems = container.querySelectorAll('.expense-item');
                const parentItem = parentItems[parentItems.length - 1]; // Get the last added item (parent)
                if (parentItem) {
                    const dateElement = parentItem.querySelector('.expense-date');
                    if (dateElement) {
                        dateElement.style.display = 'none';
                    }
                }
                
                transaction.splits.forEach(split => {
                    // Create a separate date display for the split item
                    const splitDate = new Date(split.date);
                    const splitDay = splitDate.getDate().toString().padStart(2, '0');
                    const splitMonth = splitDate.toLocaleString('default', { month: 'short' });
                    
                    container.innerHTML += `
                        <div class="expense-item split-item">
                            <div class="expense-date">
                                <div class="expense-day">${splitDay}</div>
                                <div class="expense-month">${splitMonth}</div>
                            </div>
                            <div class="expense-details split-details">
                                <div class="expense-title">
                                    <i class="fas fa-level-down-alt"></i>
                                    ${escapeHtml(split.description)}
                                </div>
                            </div>
                            <div class="expense-amount amount-${type}">
                                ${type === 'income' ? '+' : '-'}${formatNumber(split.amount)} kr
                            </div>
                        </div>
                    `;
                });
            }
        });
    }
    
    // Update stats cards with new data
    function updateStatsCards(stats) {
        if (!stats) return;
        
        // Update income card
        const incomeValue = document.querySelector('.stat-card:nth-child(2) .stat-value');
        if (incomeValue) {
            incomeValue.textContent = formatNumber(stats.upcomingIncome) + ' kr';
        }
        
        // Update expense card
        const expenseValue = document.querySelector('.stat-card:nth-child(3) .stat-value');
        if (expenseValue) {
            expenseValue.textContent = formatNumber(stats.upcomingExpenses) + ' kr';
        }
        
        // Update balance trend
        const balanceTrend = document.querySelector('.stat-card:nth-child(1) .stat-trend');
        if (balanceTrend && stats.projectedBalance !== undefined && stats.currentBalance !== undefined) {
            const projectedChange = stats.projectedBalance - stats.currentBalance;
            const percentChange = stats.currentBalance !== 0 ? (projectedChange / Math.abs(stats.currentBalance) * 100).toFixed(1) : 0;
            
            balanceTrend.className = 'stat-trend ' + (projectedChange >= 0 ? 'trend-up' : 'trend-down');
            balanceTrend.innerHTML = `
                <i class="fas ${projectedChange >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'}"></i>
                ${Math.abs(percentChange)}% projected change
            `;
        }
    }
    
    // Utility function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Format number with thousand separators
    function formatNumber(value) {
        return new Intl.NumberFormat('no-NO', { 
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(value);
    }
});
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>
