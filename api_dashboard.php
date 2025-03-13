<?php
/**
 * Dashboard API
 * 
 * Provides data for the dashboard charts and widgets
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
    
    // Get initial balance
    $debug['initial_balance'] = getInitialBalance();
    
    // Sample of some data
    $debug['current_date'] = $currentDate;
    $debug['php_version'] = phpversion();
    
    jsonResponse(true, 'Debug data retrieved', $debug);
}

/**
 * Get timeline data for the dashboard chart
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
        $transactionSum = (float)$result['transaction_sum'];
        
        // Current balance is initial balance + all transactions
        $currentBalance = $initialBalance + $transactionSum;
        
        // Get incoming transactions for the date range
        $stmt = $pdo->prepare("
            SELECT date, SUM(amount) as total 
            FROM incoming 
            WHERE date BETWEEN :start_date AND :end_date
            AND parent_id IS NULL
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
        
        // Get outgoing transactions for the date range
        $stmt = $pdo->prepare("
            SELECT date, SUM(amount) as total 
            FROM outgoing 
            WHERE date BETWEEN :start_date AND :end_date
            AND parent_id IS NULL
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
        
        // Calculate daily balance
        $balance = $currentBalance;
        
        // Convert associative arrays to regular arrays for the chart
        $incomeValues = [];
        $expenseValues = [];
        
        foreach ($dateRange as $date) {
            // Update running balance
            $incomeForDay = $incomeData[$date] ?? 0;
            $expenseForDay = $expenseData[$date] ?? 0;
            $balance += $incomeForDay - $expenseForDay;
            
            // Store values for the chart
            $balanceData[] = $balance;
            $incomeValues[] = $incomeForDay;
            $expenseValues[] = $expenseForDay;
        }
        
        // Format data for chart
        $chartData = [
            'labels' => $labels,
            'incomeData' => $incomeValues,
            'expenseData' => $expenseValues,
            'balanceData' => $balanceData,
            // Debug info
            'initialBalance' => $initialBalance,
            'currentBalance' => $currentBalance,
            'transactionSum' => $transactionSum
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
        $transactionSum = (float)$result['transaction_sum'];
        
        // Current balance is initial balance + all transactions
        $currentBalance = $initialBalance + $transactionSum;
        
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
        $result = $stmt->fetch();
        $upcomingIncome = (float)$result['total'];
        
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
        $result = $stmt->fetch();
        $upcomingExpenses = (float)$result['total'];
        
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
            'upcomingExpenses' => $upcomingExpenses,
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