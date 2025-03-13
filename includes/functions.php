<?php
/**
 * Helper Functions
 * 
 * Common functions used across the application.
 */

/**
 * Build query parameters for links
 * 
 * @param array $params Parameters to include
 * @param array $exclude Parameters to exclude
 * @return string Query string (including starting &)
 */
function buildQueryParams($params, $exclude = []) {
    $query = '';
    
    foreach ($params as $param) {
        if (in_array($param, $exclude)) {
            continue;
        }
        
        if (isset($_GET[$param]) && $_GET[$param] !== '') {
            $query .= '&' . $param . '=' . urlencode($_GET[$param]);
        }
    }
    
    return $query;
}

/**
 * Format amount as currency
 * 
 * @param float $amount Amount to format
 * @param int $decimals Number of decimals
 * @return string Formatted amount
 */
function formatAmount($amount, $decimals = 2) {
    return number_format($amount, $decimals) . ' kr';
}

/**
 * Format date
 * 
 * @param string $date Date string
 * @param string $format Format (short, medium, long, full)
 * @return string Formatted date
 */
function formatDate($date, $format = 'medium') {
    $timestamp = strtotime($date);
    
    switch ($format) {
        case 'short':
            return date('d/m/Y', $timestamp);
            
        case 'medium':
            return date('M d, Y', $timestamp);
            
        case 'long':
            return date('F d, Y', $timestamp);
            
        case 'full':
            return date('l, F d, Y', $timestamp);
            
        default:
            return date('Y-m-d', $timestamp);
    }
}

/**
 * Truncate text
 * 
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $append Text to append if truncated
 * @return string Truncated text
 */
function truncateText($text, $length = 50, $append = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $append;
}

/**
 * Get month name
 * 
 * @param int $month Month number (1-12)
 * @param bool $short Short name
 * @return string Month name
 */
function getMonthName($month, $short = false) {
    $timestamp = mktime(0, 0, 0, $month, 1);
    return date($short ? 'M' : 'F', $timestamp);
}

/**
 * Get months in date range
 * 
 * @param string $startDate Start date
 * @param string $endDate End date
 * @return array Array of year-month strings
 */
function getMonthsInRange($startDate, $endDate) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $interval = DateInterval::createFromDateString('1 month');
    $period = new DatePeriod($start, $interval, $end);
    
    $months = [];
    foreach ($period as $dt) {
        $months[] = $dt->format('Y-m');
    }
    
    // Add the last month
    $months[] = $end->format('Y-m');
    
    return array_unique($months);
}

/**
 * Calculate total for a specific period
 * 
 * @param PDO $pdo Database connection
 * @param string $table Table name (incoming or outgoing)
 * @param string $period Period (day, month, year)
 * @param string $date Reference date
 * @param array $conditions Additional conditions
 * @return float Total amount
 */
function calculateTotal($pdo, $table, $period = 'month', $date = null, $conditions = []) {
    $date = $date ?: date('Y-m-d');
    
    switch ($period) {
        case 'day':
            $where = "DATE(date) = DATE(:date)";
            break;
            
        case 'month':
            $where = "DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(:date, '%Y-%m')";
            break;
            
        case 'year':
            $where = "YEAR(date) = YEAR(:date)";
            break;
            
        default:
            $where = "DATE(date) = DATE(:date)";
    }
    
    // Add additional conditions
    if (!empty($conditions)) {
        foreach ($conditions as $condition) {
            $where .= " AND $condition";
        }
    }
    
    $query = "SELECT COALESCE(SUM(amount), 0) as total FROM $table WHERE $where AND parent_id IS NULL";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['date' => $date]);
    $result = $stmt->fetch();
    
    return $result['total'] ?: 0;
}

/**
 * Calculate balance for a specific period
 * 
 * @param PDO $pdo Database connection
 * @param string $period Period (day, month, year)
 * @param string $date Reference date
 * @return float Balance
 */
function calculateBalance($pdo, $period = 'month', $date = null) {
    $incoming = calculateTotal($pdo, 'incoming', $period, $date);
    $outgoing = calculateTotal($pdo, 'outgoing', $period, $date);
    
    return $incoming - $outgoing;
}

/**
 * Generate a color based on value
 * 
 * @param float $value Value to convert to color
 * @param float $min Minimum value
 * @param float $max Maximum value
 * @return string HEX color
 */
function valueToColor($value, $min, $max) {
    // Normalize value between 0 and 1
    $normalized = ($value - $min) / ($max - $min);
    
    // Clamp normalized value
    $normalized = max(0, min(1, $normalized));
    
    // Green to red gradient
    $r = floor(255 * $normalized);
    $g = floor(255 * (1 - $normalized));
    $b = 0;
    
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/**
 * Generate a random color
 * 
 * @return string HEX color
 */
function randomColor() {
    $colors = [
        '#3498db', // Blue
        '#2ecc71', // Green
        '#e74c3c', // Red
        '#f39c12', // Orange
        '#9b59b6', // Purple
        '#1abc9c', // Turquoise
        '#d35400', // Pumpkin
        '#27ae60', // Nephritis
        '#c0392b', // Pomegranate
        '#16a085', // Green Sea
        '#8e44ad', // Wisteria
        '#f1c40f'  // Sunflower
    ];
    
    return $colors[array_rand($colors)];
}

/**
 * Check if a string is a valid date
 * 
 * @param string $date Date string
 * @param string $format Date format
 * @return bool True if valid date
 */
function isValidDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Get the first day of the month
 * 
 * @param string $date Reference date
 * @return string First day of month
 */
function getFirstDayOfMonth($date = null) {
    $date = $date ?: date('Y-m-d');
    return date('Y-m-01', strtotime($date));
}

/**
 * Get the last day of the month
 * 
 * @param string $date Reference date
 * @return string Last day of month
 */
function getLastDayOfMonth($date = null) {
    $date = $date ?: date('Y-m-d');
    return date('Y-m-t', strtotime($date));
}

/**
 * Format a category with color
 * 
 * @param string $name Category name
 * @param string $color Category color
 * @return string HTML for category badge
 */
function formatCategory($name, $color) {
    if (empty($name)) {
        return '<span class="text-muted">Uncategorized</span>';
    }
    
    return sprintf(
        '<span class="category-badge" style="background-color: %s">%s</span>',
        htmlspecialchars($color),
        htmlspecialchars($name)
    );
}

/**
 * Sanitize input
 * 
 * @param string $input Input to sanitize
 * @return string Sanitized input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Create pagination links
 * 
 * @param int $total Total items
 * @param int $perPage Items per page
 * @param int $currentPage Current page
 * @param string $url Base URL
 * @return string HTML for pagination
 */
function createPagination($total, $perPage, $currentPage, $url = '?') {
    $totalPages = ceil($total / $perPage);
    
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<div class="pagination">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<a href="' . $url . 'page=' . ($currentPage - 1) . '" class="pagination-item">&laquo; Previous</a>';
    } else {
        $html .= '<span class="pagination-item disabled">&laquo; Previous</span>';
    }
    
    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    if ($startPage > 1) {
        $html .= '<a href="' . $url . 'page=1" class="pagination-item">1</a>';
        if ($startPage > 2) {
            $html .= '<span class="pagination-ellipsis">&hellip;</span>';
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $currentPage) {
            $html .= '<span class="pagination-item active">' . $i . '</span>';
        } else {
            $html .= '<a href="' . $url . 'page=' . $i . '" class="pagination-item">' . $i . '</a>';
        }
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<span class="pagination-ellipsis">&hellip;</span>';
        }
        $html .= '<a href="' . $url . 'page=' . $totalPages . '" class="pagination-item">' . $totalPages . '</a>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . $url . 'page=' . ($currentPage + 1) . '" class="pagination-item">Next &raquo;</a>';
    } else {
        $html .= '<span class="pagination-item disabled">Next &raquo;</span>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Get pagination offset
 * 
 * @param int $page Current page
 * @param int $perPage Items per page
 * @return int Offset
 */
function getPaginationOffset($page, $perPage) {
    return ($page - 1) * $perPage;
}