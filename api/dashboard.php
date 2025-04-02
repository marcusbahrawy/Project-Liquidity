<?php
/**
 * Dashboard API
 * 
 * Fetch data for dashboard charts and statistics
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
    
    default:
        jsonResponse(false, 'Invalid action specified');
}

/**
 * Get timeline data for liquidity chart
 */
function getTimelineData() {
    global $pdo;
    
    // Get days parameter (default to 30 if not provided)
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
    
    // Validate days
    if ($days <= 0 || $days > 365) {
        $days = 30; // Default to 30 days if invalid
    }
    
    try {
        // Get current date
        $currentDate = date('Y-m-d');
        
        // Calculate end date
        $endDate = date('Y-m-d', strtotime("+{$days} days"));
        
        // Initialize arrays for chart data
        $dates = [];
        $balanceData = [];
        $incomeData = [];
        $expenseData = [];
        
        // Get current balance (sum of all income minus all expenses up to today)
        $stmt = $pdo->prepare("
            SELECT 
                (SELECT COALESCE(SUM(amount), 0) FROM incoming WHERE date <= :current_date AND parent_id IS NULL) -
                (SELECT COALESCE(SUM(amount), 0) FROM outgoing WHERE date <= :current_date AND parent_id IS NULL) as balance
        ");
        $stmt->execute(['current_date' => $currentDate]);
        $result = $stmt->fetch();
        $currentBalance = $result['balance'] ?? 0;
        
        // Generate dates for the next X days
        $dateRange = [];
        for ($i = 0; $i <= $days; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} days"));
            $dateRange[] = $date;
            $dates[] = date('M d', strtotime($date)); // Format for display
            
            // Initialize with zeros
            $incomeData[$date] = 0;
            $expenseData[$date] = 0;
        }
        
        // Get upcoming income
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
        
        $incomeDates = $stmt->fetchAll();
        foreach ($incomeDates as $income) {
            $date = $income['date'];
            $incomeData[$date] = (float)$income['total'];
        }
        
        // Get upcoming expenses
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
        
        $expenseDates = $stmt->fetchAll();
        foreach ($expenseDates as $expense) {
            $date = $expense['date'];
            $expenseData[$date] = (float)$expense['total'];
        }
        
        // Generate recurring expenses for fixed costs
        $stmt = $pdo->prepare("
            SELECT id, description, amount, date, repeat_interval, repeat_until
            FROM outgoing
            WHERE is_fixed = 1 
            AND repeat_interval != 'none'
            AND (repeat_until IS NULL OR repeat_until >= :current_date)
            AND parent_id IS NULL
        ");
        $stmt->execute(['current_date' => $currentDate]);
        $recurringExpenses = $stmt->fetchAll();
        
        foreach ($recurringExpenses as $expense) {
            $startDate = $expense['date'];
            $endDate = $expense['repeat_until'] ? min($expense['repeat_until'], $endDate) : $endDate;
            $interval = $expense['repeat_interval'];
            $amount = (float)$expense['amount'];
            
            // Calculate occurrences based on interval
            $currentOccurrence = $startDate;
            
            while ($currentOccurrence <= $endDate) {
                // Skip past dates
                if ($currentOccurrence >= $currentDate) {
                    // Check if date is in our range
                    if (in_array($currentOccurrence, $dateRange)) {
                        $expenseData[$currentOccurrence] += $amount;
                    }
                }
                
                // Move to next occurrence
                switch ($interval) {
                    case 'daily':
                        $currentOccurrence = date('Y-m-d', strtotime($currentOccurrence . ' + 1 day'));
                        break;
                    case 'weekly':
                        $currentOccurrence = date('Y-m-d', strtotime($currentOccurrence . ' + 1 week'));
                        break;
                    case 'monthly':
                        $currentOccurrence = date('Y-m-d', strtotime($currentOccurrence . ' + 1 month'));
                        break;
                    case 'quarterly':
                        $currentOccurrence = date('Y-m-d', strtotime($currentOccurrence . ' + 3 months'));
                        break;
                    case 'yearly':
                        $currentOccurrence = date('Y-m-d', strtotime($currentOccurrence . ' + 1 year'));
                        break;
                }
            }
        }
        
        // Calculate running balance
        $runningBalance = $currentBalance;
        $formattedIncomeData = [];
        $formattedExpenseData = [];
        $formattedBalanceData = [];
        
        foreach ($dateRange as $date) {
            $income = $incomeData[$date];
            $expense = $expenseData[$date];
            
            $runningBalance += $income - $expense;
            
            $formattedIncomeData[] = $income;
            $formattedExpenseData[] = $expense;
            $formattedBalanceData[] = $runningBalance;
        }
        
        // Send response
        jsonResponse(true, 'Timeline data fetched successfully', [
            'labels' => $dates,
            'balanceData' => $formattedBalanceData,
            'incomeData' => $formattedIncomeData,
            'expenseData' => $formattedExpenseData
        ]);
    } catch (Exception $e) {
        jsonResponse(false, 'Error fetching timeline data: ' . $e->getMessage());
    }
}

/**
 * Get dashboard stats
 */
function getDashboardStats() {
    global $pdo;
    
    // Get days parameter (default to 30 if not provided)
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
    
    // Validate days
    if ($days <= 0 || $days > 365) {
        $days = 30; // Default to 30 days if invalid
    }
    
    try {
        // Get current date
        $currentDate = date('Y-m-d');
        
        // Calculate end date
        $endDate = date('Y-m-d', strtotime("+{$days} days"));
        
        // Get current balance
        $stmt = $pdo->prepare("
            SELECT 
                (SELECT COALESCE(SUM(amount), 0) FROM incoming WHERE date <= :current_date AND parent_id IS NULL) -
                (SELECT COALESCE(SUM(amount), 0) FROM outgoing WHERE date <= :current_date AND parent_id IS NULL) as balance
        ");
        $stmt->execute(['current_date' => $currentDate]);
        $result = $stmt->fetch();
        $currentBalance = $result['balance'] ?? 0;
        
        // Get non-recurring upcoming income
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM incoming
            WHERE date BETWEEN :start_date AND :end_date
            AND parent_id IS NULL
            AND (is_fixed = 0 OR repeat_interval = 'none')
        ");
        $stmt->execute([
            'start_date' => $currentDate,
            'end_date' => $endDate
        ]);
        $result = $stmt->fetch();
        $upcomingIncomeNonRecurring = $result['total'] ?? 0;
        
        // Get recurring income
        $upcomingIncomeRecurring = 0;
        $stmt = $pdo->prepare("
            SELECT id, description, amount, date, repeat_interval, repeat_until
            FROM incoming
            WHERE is_fixed = 1 
            AND repeat_interval != 'none'
            AND (repeat_until IS NULL OR repeat_until >= :current_date)
            AND parent_id IS NULL
        ");
        $stmt->execute(['current_date' => $currentDate]);
        $recurringIncome = $stmt->fetchAll();
        
        foreach ($recurringIncome as $income) {
            $startDate = new DateTime($income['date']);
            $endDateObj = new DateTime($endDate);
            $interval = $income['repeat_interval'];
            $amount = (float)$income['amount'];
            
            if ($income['repeat_until']) {
                $repeatUntil = new DateTime($income['repeat_until']);
                if ($repeatUntil < $endDateObj) {
                    $endDateObj = $repeatUntil;
                }
            }
            
            if ($startDate < new DateTime($currentDate)) {
                // Advance to first occurrence on or after current date
                while ($startDate < new DateTime($currentDate)) {
                    switch ($interval) {
                        case 'daily':
                            $startDate->modify('+1 day');
                            break;
                        case 'weekly':
                            $startDate->modify('+1 week');
                            break;
                        case 'monthly':
                            $startDate->modify('+1 month');
                            break;
                        case 'quarterly':
                            $startDate->modify('+3 months');
                            break;
                        case 'yearly':
                            $startDate->modify('+1 year');
                            break;
                    }
                }
            }
            
            // Now add all occurrences within our range
            while ($startDate <= $endDateObj) {
                $upcomingIncomeRecurring += $amount;
                
                // Advance to next occurrence
                switch ($interval) {
                    case 'daily':
                        $startDate->modify('+1 day');
                        break;
                    case 'weekly':
                        $startDate->modify('+1 week');
                        break;
                    case 'monthly':
                        $startDate->modify('+1 month');
                        break;
                    case 'quarterly':
                        $startDate->modify('+3 months');
                        break;
                    case 'yearly':
                        $startDate->modify('+1 year');
                        break;
                }
            }
        }
        
        // Total upcoming income
        $upcomingIncome = $upcomingIncomeNonRecurring + $upcomingIncomeRecurring;
        
        // Get non-recurring upcoming expenses
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM outgoing
            WHERE date BETWEEN :start_date AND :end_date
            AND parent_id IS NULL
            AND (is_fixed = 0 OR repeat_interval = 'none')
        ");
        $stmt->execute([
            'start_date' => $currentDate,
            'end_date' => $endDate
        ]);
        $result = $stmt->fetch();
        $upcomingExpenseNonRecurring = $result['total'] ?? 0;
        
        // Get recurring expenses
        $upcomingExpenseRecurring = 0;
        $stmt = $pdo->prepare("
            SELECT id, description, amount, date, repeat_interval, repeat_until
            FROM outgoing
            WHERE is_fixed = 1 
            AND repeat_interval != 'none'
            AND (repeat_until IS NULL OR repeat_until >= :current_date)
            AND parent_id IS NULL
        ");
        $stmt->execute(['current_date' => $currentDate]);
        $recurringExpenses = $stmt->fetchAll();
        
        foreach ($recurringExpenses as $expense) {
            $startDate = new DateTime($expense['date']);
            $endDateObj = new DateTime($endDate);
            $interval = $expense['repeat_interval'];
            $amount = (float)$expense['amount'];
            
            if ($expense['repeat_until']) {
                $repeatUntil = new DateTime($expense['repeat_until']);
                if ($repeatUntil < $endDateObj) {
                    $endDateObj = $repeatUntil;
                }
            }
            
            if ($startDate < new DateTime($currentDate)) {
                // Advance to first occurrence on or after current date
                while ($startDate < new DateTime($currentDate)) {
                    switch ($interval) {
                        case 'daily':
                            $startDate->modify('+1 day');
                            break;
                        case 'weekly':
                            $startDate->modify('+1 week');
                            break;
                        case 'monthly':
                            $startDate->modify('+1 month');
                            break;
                        case 'quarterly':
                            $startDate->modify('+3 months');
                            break;
                        case 'yearly':
                            $startDate->modify('+1 year');
                            break;
                    }
                }
            }
            
            // Now add all occurrences within our range
            while ($startDate <= $endDateObj) {
                $upcomingExpenseRecurring += $amount;
                
                // Advance to next occurrence
                switch ($interval) {
                    case 'daily':
                        $startDate->modify('+1 day');
                        break;
                    case 'weekly':
                        $startDate->modify('+1 week');
                        break;
                    case 'monthly':
                        $startDate->modify('+1 month');
                        break;
                    case 'quarterly':
                        $startDate->modify('+3 months');
                        break;
                    case 'yearly':
                        $startDate->modify('+1 year');
                        break;
                }
            }
        }
        
        // Total upcoming expenses
        $upcomingExpenses = $upcomingExpenseNonRecurring + $upcomingExpenseRecurring;
        
        // Get debt total
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(remaining_amount), 0) as total FROM debt");
        $stmt->execute();
        $result = $stmt->fetch();
        $totalDebt = $result['total'] ?? 0;
        
        // Calculate projected balance
        $projectedBalance = $currentBalance + $upcomingIncome - $upcomingExpenses;
        
        // Send response
        jsonResponse(true, 'Dashboard stats fetched successfully', [
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
            'totalDebt' => $totalDebt,
            'period' => "{$days} days"
        ]);
    } catch (Exception $e) {
        jsonResponse(false, 'Error fetching dashboard stats: ' . $e->getMessage());
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