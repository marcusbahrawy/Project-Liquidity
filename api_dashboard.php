<?php
/**
 * Dashboard API
 * 
 * Provides data for the dashboard charts and widgets with improved recurring transaction handling
 * and proper split transaction support
 */

// Include database connection
require_once 'config/database.php';

// Check if action is specified
if (!isset($_GET['action'])) {
    jsonResponse(false, 'No action specified');
}

$action = $_GET['action'];

// Handle different actions
switch ($action) {
    case 'timeline':
        getTimelineData();
        break;
        
    case 'stats':
        getDashboardStats();
        break;
        
    case 'transactions':
        getTransactionsData();
        break;
        
    case 'debug':
        debugData();
        break;
        
    default:
        jsonResponse(false, 'Invalid action specified');
}

/**
 * Get initial balance from settings
 */
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

/**
 * Get transactions data for the dashboard
 */
function getTransactionsData() {
    global $pdo;
    
    // Get days parameter - how many days into the future to look
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
    
    // Validate days parameter
    if ($days <= 0 || $days > 365) {
        $days = 30; // Default to 30 days if invalid
    }
    
    try {
        $currentDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime("+$days days"));
        
        // Get transactions for the selected period - fetch parent transactions only
        $stmt = $pdo->prepare("
            (SELECT 'incoming' as type, i.id, i.description, i.amount, i.date, i.is_split, c.name as category, c.color
             FROM incoming i
             LEFT JOIN categories c ON i.category_id = c.id
             WHERE i.date BETWEEN :current_date_inc AND :end_date_inc 
             AND i.parent_id IS NULL
             AND (i.is_fixed = 0 OR i.repeat_interval = 'none')
             ORDER BY i.date ASC
             LIMIT 40)
            UNION ALL
            (SELECT 'outgoing' as type, o.id, o.description, o.amount, o.date, o.is_split, c.name as category, c.color
             FROM outgoing o
             LEFT JOIN categories c ON o.category_id = c.id
             WHERE o.date BETWEEN :current_date_out AND :end_date_out 
             AND o.parent_id IS NULL
             AND (o.is_fixed = 0 OR o.repeat_interval = 'none')
             ORDER BY o.date ASC
             LIMIT 40)
            ORDER BY date ASC
            LIMIT 40
        ");
        $stmt->execute([
            'current_date_inc' => $currentDate,
            'end_date_inc' => $endDate,
            'current_date_out' => $currentDate,
            'end_date_out' => $endDate
        ]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get recurring INCOMING transactions
        $stmt = $pdo->prepare("
            SELECT i.id, i.description, i.amount, i.date, i.is_split, i.repeat_interval, 
                   i.repeat_until, i.category_id, c.name as category, c.color
            FROM incoming i
            LEFT JOIN categories c ON i.category_id = c.id
            WHERE i.is_fixed = 1 
            AND i.repeat_interval != 'none'
            AND i.parent_id IS NULL
            AND (i.repeat_until IS NULL OR i.repeat_until >= :current_date)
        ");
        $stmt->execute(['current_date' => $currentDate]);
        $recurringIncome = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get recurring OUTGOING transactions
        $stmt = $pdo->prepare("
            SELECT o.id, o.description, o.amount, o.date, o.is_split, o.repeat_interval, 
                   o.repeat_until, o.category_id, c.name as category, c.color
            FROM outgoing o
            LEFT JOIN categories c ON o.category_id = c.id
            WHERE o.is_fixed = 1 
            AND o.repeat_interval != 'none'
            AND o.parent_id IS NULL
            AND (o.repeat_until IS NULL OR o.repeat_until >= :current_date)
        ");
        $stmt->execute(['current_date' => $currentDate]);
        $recurringExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add virtual transactions for recurring income
        foreach ($recurringIncome as $income) {
            // Skip if it's a split parent - we'll handle splits separately
            if ($income['is_split'] == 1) {
                continue;
            }
            
            $startDate = $income['date'];
            $endDateForIncome = $income['repeat_until'] ? min($income['repeat_until'], $endDate) : $endDate;
            $interval = $income['repeat_interval'];
            
            // Calculate occurrences using DateTime
            $date = new DateTime($startDate);
            $endDateObj = new DateTime($endDateForIncome);
            $currentDateObj = new DateTime($currentDate);
            
            // If start date is in the past, begin from first occurrence after current date
            if ($date < $currentDateObj) {
                // Advance to first occurrence on or after current date
                while ($date < $currentDateObj) {
                    switch ($interval) {
                        case 'daily':
                            $date->modify('+1 day');
                            break;
                        case 'weekly':
                            $date->modify('+1 week');
                            break;
                        case 'monthly':
                            $date->modify('+1 month');
                            break;
                        case 'quarterly':
                            $date->modify('+3 months');
                            break;
                        case 'yearly':
                            $date->modify('+1 year');
                            break;
                    }
                }
            }
            
            // Create virtual transactions for all occurrences within our range
            while ($date <= $endDateObj) {
                $occurrenceDate = $date->format('Y-m-d');
                
                // Create a virtual transaction for this occurrence
                $virtualTransaction = [
                    'type' => 'incoming',
                    'id' => $income['id'] . '_' . $occurrenceDate, // Create a unique ID
                    'description' => $income['description'] . ' (Recurring)',
                    'amount' => (float)$income['amount'],
                    'date' => $occurrenceDate,
                    'is_split' => 0,
                    'category' => $income['category'],
                    'color' => $income['color'],
                    'is_recurring' => true, // Mark as recurring (virtual)
                    'original_id' => $income['id'] // Store original transaction ID
                ];
                
                $transactions[] = $virtualTransaction;
                
                // Advance to next occurrence
                switch ($interval) {
                    case 'daily':
                        $date->modify('+1 day');
                        break;
                    case 'weekly':
                        $date->modify('+1 week');
                        break;
                    case 'monthly':
                        $date->modify('+1 month');
                        break;
                    case 'quarterly':
                        $date->modify('+3 months');
                        break;
                    case 'yearly':
                        $date->modify('+1 year');
                        break;
                }
            }
        }

        // Add virtual transactions for recurring expenses
        foreach ($recurringExpenses as $expense) {
            // Skip if it's a split parent - we'll handle splits separately
            if ($expense['is_split'] == 1) {
                continue;
            }
            
            $startDate = $expense['date'];
            $endDateForExpense = $expense['repeat_until'] ? min($expense['repeat_until'], $endDate) : $endDate;
            $interval = $expense['repeat_interval'];
            
            // Calculate occurrences using DateTime
            $date = new DateTime($startDate);
            $endDateObj = new DateTime($endDateForExpense);
            $currentDateObj = new DateTime($currentDate);
            
            // If start date is in the past, begin from first occurrence after current date
            if ($date < $currentDateObj) {
                // Advance to first occurrence on or after current date
                while ($date < $currentDateObj) {
                    switch ($interval) {
                        case 'daily':
                            $date->modify('+1 day');
                            break;
                        case 'weekly':
                            $date->modify('+1 week');
                            break;
                        case 'monthly':
                            $date->modify('+1 month');
                            break;
                        case 'quarterly':
                            $date->modify('+3 months');
                            break;
                        case 'yearly':
                            $date->modify('+1 year');
                            break;
                    }
                }
            }
            
            // Create virtual transactions for all occurrences within our range
            while ($date <= $endDateObj) {
                $occurrenceDate = $date->format('Y-m-d');
                
                // Create a virtual transaction for this occurrence
                $virtualTransaction = [
                    'type' => 'outgoing',
                    'id' => $expense['id'] . '_' . $occurrenceDate, // Create a unique ID
                    'description' => $expense['description'] . ' (Recurring)',
                    'amount' => (float)$expense['amount'],
                    'date' => $occurrenceDate,
                    'is_split' => 0,
                    'category' => $expense['category'],
                    'color' => $expense['color'],
                    'is_recurring' => true, // Mark as recurring (virtual)
                    'original_id' => $expense['id'] // Store original transaction ID
                ];
                
                $transactions[] = $virtualTransaction;
                
                // Advance to next occurrence
                switch ($interval) {
                    case 'daily':
                        $date->modify('+1 day');
                        break;
                    case 'weekly':
                        $date->modify('+1 week');
                        break;
                    case 'monthly':
                        $date->modify('+1 month');
                        break;
                    case 'quarterly':
                        $date->modify('+3 months');
                        break;
                    case 'yearly':
                        $date->modify('+1 year');
                        break;
                }
            }
        }
        
        // Sort transactions by date
        usort($transactions, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        // Process transactions to include split items
        $organizedTransactions = [];
        
        foreach ($transactions as $transaction) {
            // Add the transaction to our organized list
            $organizedTransaction = $transaction;
            
            // If it's a split transaction and not a virtual recurring transaction, fetch its split items
            if ($transaction['is_split'] && !isset($transaction['is_recurring'])) {
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
                    $splits = $splitStmt->fetchAll(PDO::FETCH_ASSOC);
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
                    $splits = $splitStmt->fetchAll(PDO::FETCH_ASSOC);
                }
                
                $organizedTransaction['splits'] = $splits;
            }
            
            $organizedTransactions[] = $organizedTransaction;
        }
        
        // Get category spending breakdown
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, c.color, COALESCE(SUM(o.amount), 0) as total
            FROM categories c
            JOIN outgoing o ON o.category_id = c.id 
            WHERE o.date BETWEEN :current_date AND :end_date AND o.is_debt = 0
            AND (o.parent_id IS NOT NULL OR (o.parent_id IS NULL AND o.is_split = 0))
            GROUP BY c.id, c.name, c.color
            HAVING total > 0
            ORDER BY total DESC
            LIMIT 6
        ");
        $stmt->execute([
            'current_date' => $currentDate,
            'end_date' => $endDate
        ]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get summary stats with improved handling of splits
        // Get upcoming income
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
        $result = $stmt->fetch();
        $upcomingIncome = (float)$result['total'];
        
        // Get upcoming expenses
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
        $result = $stmt->fetch();
        $upcomingExpenses = (float)$result['total'];
        
        // Get current balance
        $initialBalance = getInitialBalance();
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
        $transactionSum = (float)$result['transaction_sum'];
        $currentBalance = $initialBalance + $transactionSum;
        
        // Calculate projected balance
        $projectedBalance = $currentBalance + $upcomingIncome - $upcomingExpenses;
        
        $stats = [
            'currentBalance' => $currentBalance,
            'upcomingIncome' => $upcomingIncome,
            'upcomingExpenses' => $upcomingExpenses,
            'projectedBalance' => $projectedBalance,
            'days' => $days
        ];
        
        // Return combined data
        jsonResponse(true, 'Transactions data retrieved successfully', [
            'transactions' => $organizedTransactions,
            'categories' => $categories,
            'stats' => $stats
        ]);
    } catch (PDOException $e) {
        jsonResponse(false, 'Error retrieving transactions data: ' . $e->getMessage());
    }
}

/**
 * Debug data - helpful for troubleshooting
 */
function debugData() {
    global $pdo;
    
    // Get some sample data from the database
    $debug = [];
    
    // Check if we have any incoming transactions
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM incoming");
    $stmt->execute();
    $debug['incoming_count'] = $stmt->fetch()['count'];
    
    // Check if we have any outgoing transactions
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM outgoing");
    $stmt->execute();
    $debug['outgoing_count'] = $stmt->fetch()['count'];
    
    // Check if we have any future incoming transactions
    $currentDate = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM incoming WHERE date >= :current_date");
    $stmt->execute(['current_date' => $currentDate]);
    $debug['future_incoming_count'] = $stmt->fetch()['count'];
    
    // Check if we have any future outgoing transactions
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM outgoing WHERE date >= :current_date");
    $stmt->execute(['current_date' => $currentDate]);
    $debug['future_outgoing_count'] = $stmt->fetch()['count'];
    
    // Check recurring transactions
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM outgoing 
        WHERE is_fixed = 1 AND repeat_interval != 'none'
    ");
    $stmt->execute();
    $debug['outgoing_recurring_count'] = $stmt->fetch()['count'];
    
    // Check recurring income
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM incoming 
        WHERE is_fixed = 1 AND repeat_interval != 'none'
    ");
    $stmt->execute();
    $debug['incoming_recurring_count'] = $stmt->fetch()['count'];
    
    // Get recurring transaction details for inspection
    $stmt = $pdo->prepare("
        SELECT id, description, amount, date, repeat_interval, repeat_until
        FROM outgoing 
        WHERE is_fixed = 1 AND repeat_interval != 'none'
        LIMIT 10
    ");
    $stmt->execute();
    $debug['recurring_expenses'] = $stmt->fetchAll();
    
    // Get recurring income details for inspection
    $stmt = $pdo->prepare("
        SELECT id, description, amount, date, repeat_interval, repeat_until
        FROM incoming 
        WHERE is_fixed = 1 AND repeat_interval != 'none'
        LIMIT 10
    ");
    $stmt->execute();
    $debug['recurring_income'] = $stmt->fetchAll();
    
    // Get initial balance
    $debug['initial_balance'] = getInitialBalance();
    
    // Count splits
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM incoming WHERE parent_id IS NOT NULL");
    $stmt->execute();
    $debug['incoming_splits_count'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM outgoing WHERE parent_id IS NOT NULL");
    $stmt->execute();
    $debug['outgoing_splits_count'] = $stmt->fetch()['count'];
    
    // Count parent transactions
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM incoming WHERE parent_id IS NULL AND is_split = 1");
    $stmt->execute();
    $debug['incoming_parents_count'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM outgoing WHERE parent_id IS NULL AND is_split = 1");
    $stmt->execute();
    $debug['outgoing_parents_count'] = $stmt->fetch()['count'];
    
    // Sample of some data
    $debug['current_date'] = $currentDate;
    $debug['php_version'] = phpversion();
    
    jsonResponse(true, 'Debug data retrieved', $debug);
}

/**
 * Get timeline data for the dashboard chart with improved recurring transaction handling
 */
function getTimelineData() {
    global $pdo;
    
    // Get days parameter - how many days into the future to look
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
    
    // Validate days parameter
    if ($days <= 0 || $days > 365) {
        $days = 30; // Default to 30 days if invalid
    }
    
    try {
        $currentDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime("+$days days"));
        
        // Prepare data structure
        $labels = [];
        $balanceData = [];
        $incomeData = [];
        $expenseData = [];
        
        // Generate dates for the range
        $dateRange = [];
        $current = strtotime($currentDate);
        $end = strtotime($endDate);
        
        while ($current <= $end) {
            $date = date('Y-m-d', $current);
            $dateRange[] = $date;
            $labels[] = date('M d', $current); // Format for display (e.g., "Jan 15")
            
            // Initialize with zeros
            $incomeData[$date] = 0;
            $expenseData[$date] = 0;
            
            $current = strtotime('+1 day', $current);
        }
        
        // Get initial balance
        $initialBalance = getInitialBalance();
        
        // Get current account balance (initial balance + all past transactions)
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
        $transactionSum = (float)$result['transaction_sum'];
        
        // Current balance is initial balance + all transactions
        $currentBalance = $initialBalance + $transactionSum;
        
        // Get incoming transactions for the date range
        // MODIFIED: Include split items and exclude parent transactions
        $stmt = $pdo->prepare("
            SELECT date, SUM(amount) as total 
            FROM incoming 
            WHERE date BETWEEN :start_date AND :end_date
            AND (parent_id IS NOT NULL OR (parent_id IS NULL AND is_split = 0))
            AND (is_fixed = 0 OR repeat_interval = 'none')
            GROUP BY date
        ");
        $stmt->execute([
            'start_date' => $currentDate,
            'end_date' => $endDate
        ]);
        
        $incomingResults = $stmt->fetchAll();
        
        // Add incoming data to the array
        foreach ($incomingResults as $row) {
            if (isset($incomeData[$row['date']])) {
                $incomeData[$row['date']] = (float)$row['total'];
            }
        }
        
        // Handle recurring income transactions
        // Get all recurring income that might occur in our range
        $stmt = $pdo->prepare("
            SELECT id, description, amount, date, repeat_interval, repeat_until
            FROM incoming 
            WHERE is_fixed = 1 
            AND repeat_interval != 'none'
            AND parent_id IS NULL
            AND (repeat_until IS NULL OR repeat_until >= :current_date)
        ");
        $stmt->execute(['current_date' => $currentDate]);
        $recurringIncome = $stmt->fetchAll();
        
        // Debug data for recurring income
        $debugRecurringIncome = [];
        
        foreach ($recurringIncome as $income) {
            $startDate = $income['date'];
            $endDateForIncome = $income['repeat_until'] ? min($income['repeat_until'], $endDate) : $endDate;
            $interval = $income['repeat_interval'];
            $amount = (float)$income['amount'];
            
            // Check if this is a split transaction
            $isSplit = false;
            if ($income['id']) {
                $splitCheckStmt = $pdo->prepare("
                    SELECT is_split FROM incoming WHERE id = :id
                ");
                $splitCheckStmt->execute(['id' => $income['id']]);
                $splitResult = $splitCheckStmt->fetch();
                $isSplit = $splitResult && $splitResult['is_split'] == 1;
            }
            
            // If it's a split transaction, get the splits instead
            if ($isSplit) {
                $splitStmt = $pdo->prepare("
                    SELECT amount FROM incoming WHERE parent_id = :parent_id
                ");
                $splitStmt->execute(['parent_id' => $income['id']]);
                $splits = $splitStmt->fetchAll();
                
                // Don't process this recurring income if it's a split parent
                continue;
            }
            
            // Calculate occurrences using DateTime for better accuracy
            $date = new DateTime($startDate);
            $endDateObj = new DateTime($endDateForIncome);
            $currentDateObj = new DateTime($currentDate);
            
            // For debugging
            $occurrences = [];
            
            // If start date is in the past, begin from first occurrence after current date
            if ($date < $currentDateObj) {
                // Advance to first occurrence on or after current date
                while ($date < $currentDateObj) {
                    switch ($interval) {
                        case 'daily':
                            $date->modify('+1 day');
                            break;
                        case 'weekly':
                            $date->modify('+1 week');
                            break;
                        case 'monthly':
                            $date->modify('+1 month');
                            break;
                        case 'quarterly':
                            $date->modify('+3 months');
                            break;
                        case 'yearly':
                            $date->modify('+1 year');
                            break;
                    }
                }
            }
            
            // Now add all occurrences within our range
            while ($date <= $endDateObj) {
                $occurrenceDate = $date->format('Y-m-d');
                
                // Add to income data if within range
                if (isset($incomeData[$occurrenceDate])) {
                    $incomeData[$occurrenceDate] += $amount;
                    $occurrences[] = $occurrenceDate;
                }
                
                // Advance to next occurrence
                switch ($interval) {
                    case 'daily':
                        $date->modify('+1 day');
                        break;
                    case 'weekly':
                        $date->modify('+1 week');
                        break;
                    case 'monthly':
                        $date->modify('+1 month');
                        break;
                    case 'quarterly':
                        $date->modify('+3 months');
                        break;
                    case 'yearly':
                        $date->modify('+1 year');
                        break;
                }
            }
            
            // Add to debug data
            $debugRecurringIncome[] = [
                'id' => $income['id'],
                'description' => $income['description'],
                'amount' => $amount,
                'start_date' => $startDate,
                'end_date' => $endDateForIncome,
                'interval' => $interval,
                'occurrences' => $occurrences,
                'is_split' => $isSplit
            ];
        }
        
        // Get non-recurring outgoing transactions for the date range
        // MODIFIED: Include split items and exclude parent transactions
        $stmt = $pdo->prepare("
            SELECT date, SUM(amount) as total 
            FROM outgoing 
            WHERE date BETWEEN :start_date AND :end_date
            AND (parent_id IS NOT NULL OR (parent_id IS NULL AND is_split = 0))
            AND (is_fixed = 0 OR repeat_interval = 'none')
            GROUP BY date
        ");
        $stmt->execute([
            'start_date' => $currentDate,
            'end_date' => $endDate
        ]);
        
        $outgoingResults = $stmt->fetchAll();
        
        // Add outgoing data to the array
        foreach ($outgoingResults as $row) {
            if (isset($expenseData[$row['date']])) {
                $expenseData[$row['date']] = (float)$row['total'];
            }
        }
        
        // Handle recurring transactions
        // Get all recurring expenses that might occur in our range
        // For recurring transactions, we still use the parent transaction (is_split=0)
        $stmt = $pdo->prepare("
            SELECT id, description, amount, date, repeat_interval, repeat_until
            FROM outgoing 
            WHERE is_fixed = 1 
            AND repeat_interval != 'none'
            AND parent_id IS NULL
            AND (repeat_until IS NULL OR repeat_until >= :current_date)
        ");
        $stmt->execute(['current_date' => $currentDate]);
        $recurringExpenses = $stmt->fetchAll();
        
        // Debug data for recurring expenses
        $debugRecurring = [];
        
        foreach ($recurringExpenses as $expense) {
            $startDate = $expense['date'];
            $endDateForExpense = $expense['repeat_until'] ? min($expense['repeat_until'], $endDate) : $endDate;
            $interval = $expense['repeat_interval'];
            $amount = (float)$expense['amount'];
            
            // Check if this is a split transaction
            $isSplit = false;
            if ($expense['id']) {
                $splitCheckStmt = $pdo->prepare("
                    SELECT is_split FROM outgoing WHERE id = :id
                ");
                $splitCheckStmt->execute(['id' => $expense['id']]);
                $splitResult = $splitCheckStmt->fetch();
                $isSplit = $splitResult && $splitResult['is_split'] == 1;
            }
            
            // If it's a split transaction, get the splits instead
            if ($isSplit) {
                $splitStmt = $pdo->prepare("
                    SELECT amount FROM outgoing WHERE parent_id = :parent_id
                ");
                $splitStmt->execute(['parent_id' => $expense['id']]);
                $splits = $splitStmt->fetchAll();
                
                // Don't process this recurring expense if it's a split parent
                continue;
            }
            
            // Calculate occurrences using DateTime for better accuracy
            $date = new DateTime($startDate);
            $endDateObj = new DateTime($endDateForExpense);
            $currentDateObj = new DateTime($currentDate);
            
            // For debugging
            $occurrences = [];
            
            // If start date is in the past, begin from first occurrence after current date
            if ($date < $currentDateObj) {
                // Advance to first occurrence on or after current date
                while ($date < $currentDateObj) {
                    switch ($interval) {
                        case 'daily':
                            $date->modify('+1 day');
                            break;
                        case 'weekly':
                            $date->modify('+1 week');
                            break;
                        case 'monthly':
                            $date->modify('+1 month');
                            break;
                        case 'quarterly':
                            $date->modify('+3 months');
                            break;
                        case 'yearly':
                            $date->modify('+1 year');
                            break;
                    }
                }
            }
            
            // Now add all occurrences within our range
            while ($date <= $endDateObj) {
                $occurrenceDate = $date->format('Y-m-d');
                
                // Add to expense data if within range
                if (isset($expenseData[$occurrenceDate])) {
                    $expenseData[$occurrenceDate] += $amount;
                    $occurrences[] = $occurrenceDate;
                }
                
                // Advance to next occurrence
                switch ($interval) {
                    case 'daily':
                        $date->modify('+1 day');
                        break;
                    case 'weekly':
                        $date->modify('+1 week');
                        break;
                    case 'monthly':
                        $date->modify('+1 month');
                        break;
                    case 'quarterly':
                        $date->modify('+3 months');
                        break;
                    case 'yearly':
                        $date->modify('+1 year');
                        break;
                }
            }
            
            // Add to debug data
            $debugRecurring[] = [
                'id' => $expense['id'],
                'description' => $expense['description'],
                'amount' => $amount,
                'start_date' => $startDate,
                'end_date' => $endDateForExpense,
                'interval' => $interval,
                'occurrences' => $occurrences,
                'is_split' => $isSplit
            ];
        }
        
        // Calculate daily balance
        $balance = $currentBalance;
        
        // Convert associative arrays to regular arrays for the chart
        $incomeValues = [];
        $expenseValues = [];
        $balanceValues = [];
        
        foreach ($dateRange as $date) {
            // Get income and expense for this day
            $incomeForDay = $incomeData[$date] ?? 0;
            $expenseForDay = $expenseData[$date] ?? 0;
            
            // Update running balance
            $balance += $incomeForDay - $expenseForDay;
            
            // Store values for the chart
            $incomeValues[] = $incomeForDay;
            $expenseValues[] = $expenseForDay;
            $balanceValues[] = $balance;
        }
        
        // Format data for chart
        $chartData = [
            'labels' => $labels,
            'incomeData' => $incomeValues,
            'expenseData' => $expenseValues,
            'balanceData' => $balanceValues,
            // Debug info
            'debug' => [
                'initialBalance' => $initialBalance,
                'currentBalance' => $currentBalance,
                'transactionSum' => $transactionSum,
                'recurring_expenses' => $debugRecurring,
                'recurring_income' => $debugRecurringIncome,
                'date_range' => [
                    'start' => $currentDate,
                    'end' => $endDate,
                    'days' => $days
                ]
            ]
        ];
        
        jsonResponse(true, 'Timeline data retrieved successfully', $chartData);
    } catch (PDOException $e) {
        jsonResponse(false, 'Error retrieving timeline data: ' . $e->getMessage());
    }
}

/**
 * Get general dashboard statistics
 */
function getDashboardStats() {
    global $pdo;
    
    try {
        $currentDate = date('Y-m-d');
        $currentMonth = date('Y-m');
        
        // Get initial balance
        $initialBalance = getInitialBalance();
        
        // MODIFIED: Updated to include split items and exclude parent transactions
        // Get transaction sum (all transactions up to current date)
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
        $transactionSum = (float)$result['transaction_sum'];
        
        // Current balance is initial balance + all transactions
        $currentBalance = $initialBalance + $transactionSum;
        
        // MODIFIED: Get upcoming non-recurring income for the next 30 days, using split transactions
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM incoming 
            WHERE date BETWEEN :start_date AND :end_date
            AND (parent_id IS NOT NULL OR (parent_id IS NULL AND is_split = 0))
            AND (is_fixed = 0 OR repeat_interval = 'none')
        ");
        $stmt->execute([
            'start_date' => $currentDate,
            'end_date' => date('Y-m-d', strtotime('+30 days'))
        ]);
        $result = $stmt->fetch();
        $upcomingIncomeNonRecurring = (float)$result['total'];
        
        // Get recurring income for the next 30 days
        $upcomingIncomeRecurring = 0;
        $endDate = date('Y-m-d', strtotime('+30 days'));
        
        // For recurring transactions, we only count non-split parent transactions by default
        $stmt = $pdo->prepare("
            SELECT id, description, amount, date, repeat_interval, repeat_until, is_split
            FROM incoming 
            WHERE is_fixed = 1 
            AND repeat_interval != 'none'
            AND parent_id IS NULL
            AND (repeat_until IS NULL OR repeat_until >= :current_date)
        ");
        $stmt->execute(['current_date' => $currentDate]);
        $recurringIncome = $stmt->fetchAll();
        
        foreach ($recurringIncome as $income) {
            $startDate = $income['date'];
            $endDateForIncome = $income['repeat_until'] ? min($income['repeat_until'], $endDate) : $endDate;
            $interval = $income['repeat_interval'];
            
            // When we find a recurring split parent transaction
            if ($income['is_split'] == 1) {
                // Fetch the split children
                $splitStmt = $pdo->prepare("
                    SELECT amount 
                    FROM incoming 
                    WHERE parent_id = :parent_id
                ");
                $splitStmt->execute(['parent_id' => $income['id']]);
                $splitItems = $splitStmt->fetchAll();
                
                // Calculate occurrences for each child
                $date = new DateTime($startDate);
                $endDateObj = new DateTime($endDateForIncome);
                $currentDateObj = new DateTime($currentDate);
                
                // If start date is in the past, begin from first occurrence after current date
                if ($date < $currentDateObj) {
                    // Advance to first occurrence on or after current date
                    while ($date < $currentDateObj) {
                        switch ($interval) {
                            case 'daily':
                                $date->modify('+1 day');
                                break;
                            case 'weekly':
                                $date->modify('+1 week');
                                break;
                            case 'monthly':
                                $date->modify('+1 month');
                                break;
                            case 'quarterly':
                                $date->modify('+3 months');
                                break;
                            case 'yearly':
                                $date->modify('+1 year');
                                break;
                        }
                    }
                }
                
                // Now add all occurrences within our range
                while ($date <= $endDateObj) {
                    foreach ($splitItems as $splitItem) {
                        $upcomingIncomeRecurring += (float)$splitItem['amount'];
                    }
                    
                    // Advance to next occurrence
                    switch ($interval) {
                        case 'daily':
                            $date->modify('+1 day');
                            break;
                        case 'weekly':
                            $date->modify('+1 week');
                            break;
                        case 'monthly':
                            $date->modify('+1 month');
                            break;
                        case 'quarterly':
                            $date->modify('+3 months');
                            break;
                        case 'yearly':
                            $date->modify('+1 year');
                            break;
                    }
                }
                
                // Skip the rest of the loop for this parent transaction
                continue;
            }
            
            // For non-split recurring transactions, continue as before
            $amount = (float)$income['amount'];
            
            // Calculate occurrences using DateTime
            $date = new DateTime($startDate);
            $endDateObj = new DateTime($endDateForIncome);
            $currentDateObj = new DateTime($currentDate);
            
            // If start date is in the past, begin from first occurrence after current date
            if ($date < $currentDateObj) {
                // Advance to first occurrence on or after current date
                while ($date < $currentDateObj) {
                    switch ($interval) {
                        case 'daily':
                            $date->modify('+1 day');
                            break;
                        case 'weekly':
                            $date->modify('+1 week');
                            break;
                        case 'monthly':
                            $date->modify('+1 month');
                            break;
                        case 'quarterly':
                            $date->modify('+3 months');
                            break;
                        case 'yearly':
                            $date->modify('+1 year');
                            break;
                    }
                }
            }
            
            // Now add all occurrences within our range
            while ($date <= $endDateObj) {
                $upcomingIncomeRecurring += $amount;
                
                // Advance to next occurrence
                switch ($interval) {
                    case 'daily':
                        $date->modify('+1 day');
                        break;
                    case 'weekly':
                        $date->modify('+1 week');
                        break;
                    case 'monthly':
                        $date->modify('+1 month');
                        break;
                    case 'quarterly':
                        $date->modify('+3 months');
                        break;
                    case 'yearly':
                        $date->modify('+1 year');
                        break;
                }
            }
        }
        
        // Total upcoming income
        $upcomingIncome = $upcomingIncomeNonRecurring + $upcomingIncomeRecurring;
        
        // MODIFIED: Get upcoming non-recurring expenses for the next 30 days, using split transactions
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM outgoing 
            WHERE date BETWEEN :start_date AND :end_date
            AND (parent_id IS NOT NULL OR (parent_id IS NULL AND is_split = 0))
            AND (is_fixed = 0 OR repeat_interval = 'none')
        ");
        $stmt->execute([
            'start_date' => $currentDate,
            'end_date' => date('Y-m-d', strtotime('+30 days'))
        ]);
        $result = $stmt->fetch();
        $upcomingExpenseNonRecurring = (float)$result['total'];
        
        // Get recurring expenses for the next 30 days
        $upcomingExpenseRecurring = 0;
        $endDate = date('Y-m-d', strtotime('+30 days'));
        
        // For recurring transactions, we only count non-split parent transactions by default
        $stmt = $pdo->prepare("
            SELECT id, description, amount, date, repeat_interval, repeat_until, is_split
            FROM outgoing 
            WHERE is_fixed = 1 
            AND repeat_interval != 'none'
            AND parent_id IS NULL
            AND (repeat_until IS NULL OR repeat_until >= :current_date)
        ");
        $stmt->execute(['current_date' => $currentDate]);
        $recurringExpenses = $stmt->fetchAll();
        
        foreach ($recurringExpenses as $expense) {
            $startDate = $expense['date'];
            $endDateForExpense = $expense['repeat_until'] ? min($expense['repeat_until'], $endDate) : $endDate;
            $interval = $expense['repeat_interval'];
            
            // When we find a recurring split parent transaction
            if ($expense['is_split'] == 1) {
                // Fetch the split children
                $splitStmt = $pdo->prepare("
                    SELECT amount 
                    FROM outgoing 
                    WHERE parent_id = :parent_id
                ");
                $splitStmt->execute(['parent_id' => $expense['id']]);
                $splitItems = $splitStmt->fetchAll();
                
                // Calculate occurrences for each child
                $date = new DateTime($startDate);
                $endDateObj = new DateTime($endDateForExpense);
                $currentDateObj = new DateTime($currentDate);
                
                // If start date is in the past, begin from first occurrence after current date
                if ($date < $currentDateObj) {
                    // Advance to first occurrence on or after current date
                    while ($date < $currentDateObj) {
                        switch ($interval) {
                            case 'daily':
                                $date->modify('+1 day');
                                break;
                            case 'weekly':
                                $date->modify('+1 week');
                                break;
                            case 'monthly':
                                $date->modify('+1 month');
                                break;
                            case 'quarterly':
                                $date->modify('+3 months');
                                break;
                            case 'yearly':
                                $date->modify('+1 year');
                                break;
                        }
                    }
                }
                
                // Now add all occurrences within our range
                while ($date <= $endDateObj) {
                    foreach ($splitItems as $splitItem) {
                        $upcomingExpenseRecurring += (float)$splitItem['amount'];
                    }
                    
                    // Advance to next occurrence
                    switch ($interval) {
                        case 'daily':
                            $date->modify('+1 day');
                            break;
                        case 'weekly':
                            $date->modify('+1 week');
                            break;
                        case 'monthly':
                            $date->modify('+1 month');
                            break;
                        case 'quarterly':
                            $date->modify('+3 months');
                            break;
                        case 'yearly':
                            $date->modify('+1 year');
                            break;
                    }
                }
                
                // Skip the rest of the loop for this parent transaction
                continue;
            }
            
            // For non-split recurring transactions, continue as before
            $amount = (float)$expense['amount'];
            
            // Calculate occurrences using DateTime
            $date = new DateTime($startDate);
            $endDateObj = new DateTime($endDateForExpense);
            $currentDateObj = new DateTime($currentDate);
            
            // If start date is in the past, begin from first occurrence after current date
            if ($date < $currentDateObj) {
                // Advance to first occurrence on or after current date
                while ($date < $currentDateObj) {
                    switch ($interval) {
                        case 'daily':
                            $date->modify('+1 day');
                            break;
                        case 'weekly':
                            $date->modify('+1 week');
                            break;
                        case 'monthly':
                            $date->modify('+1 month');
                            break;
                        case 'quarterly':
                            $date->modify('+3 months');
                            break;
                        case 'yearly':
                            $date->modify('+1 year');
                            break;
                    }
                }
            }
            
            // Now add all occurrences within our range
            while ($date <= $endDateObj) {
                $upcomingExpenseRecurring += $amount;
                
                // Advance to next occurrence
                switch ($interval) {
                    case 'daily':
                        $date->modify('+1 day');
                        break;
                    case 'weekly':
                        $date->modify('+1 week');
                        break;
                    case 'monthly':
                        $date->modify('+1 month');
                        break;
                    case 'quarterly':
                        $date->modify('+3 months');
                        break;
                    case 'yearly':
                        $date->modify('+1 year');
                        break;
                }
            }
        }
        
        // Total upcoming expenses
        $upcomingExpenses = $upcomingExpenseNonRecurring + $upcomingExpenseRecurring;
        
        // Get projected balance after 30 days
        $projectedBalance = $currentBalance + $upcomingIncome - $upcomingExpenses;
        
        // Get total debt
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(remaining_amount), 0) as total FROM debt");
        $stmt->execute();
        $result = $stmt->fetch();
        $totalDebt = (float)$result['total'];
        
        $stats = [
            'initialBalance' => $initialBalance,
            'transactionSum' => $transactionSum,
            'currentBalance' => $currentBalance,
            'upcomingIncome' => $upcomingIncome,
            'upcomingIncomeDetails' => [
                'nonRecurring' => $upcomingIncomeNonRecurring,
                'recurring' => $upcomingIncomeRecurring
            ],
            'upcomingExpenses' => $upcomingExpenses,
            'upcomingExpenseDetails' => [
                'nonRecurring' => $upcomingExpenseNonRecurring,
                'recurring' => $upcomingExpenseRecurring
            ],
            'projectedBalance' => $projectedBalance,
            'totalDebt' => $totalDebt
        ];
        
        jsonResponse(true, 'Dashboard stats retrieved successfully', $stats);
    } catch (PDOException $e) {
        jsonResponse(false, 'Error retrieving dashboard stats: ' . $e->getMessage());
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