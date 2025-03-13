<?php
/**
 * Header Template
 */

// Include database connection if not already included
if (!isset($pdo)) {
    require_once __DIR__ . '/../config/database.php';
}

// Include authentication functions
require_once __DIR__ . '/../auth/auth.php';

// Check if user is logged in
requireLogin();

// Get current page for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Function to check if menu item is active
function isActive($path, $exact = false) {
    global $current_page, $current_dir;
    
    if ($exact) {
        return $current_page === $path;
    }
    
    return $current_dir === $path || $current_page === $path;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Likviditet - Liquidity Management</title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="/assets/img/favicon.ico" type="image/x-icon">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    
    <!-- Custom CSS -->
    <style>
    :root {
        /* Primary Colors */
        --primary: #3498db;
        --primary-light: #5dade2;
        --primary-dark: #2980b9;
        
        /* Secondary Colors */
        --secondary: #2ecc71;
        --secondary-light: #58d68d;
        --secondary-dark: #27ae60;
        
        /* Neutral Colors */
        --dark: #2c3e50;
        --gray-dark: #34495e;
        --gray: #95a5a6;
        --gray-light: #ecf0f1;
        --light: #f8f9fa;
        
        /* Action Colors */
        --info: #3498db;
        --success: #2ecc71;
        --warning: #f39c12;
        --danger: #e74c3c;
        
        /* Fonts */
        --font-primary: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        
        /* Dimensions */
        --sidebar-width: 260px;
        --header-height: 70px;
        --border-radius: 10px;
        --card-shadow: 0 4px 20px rgba(44, 62, 80, .1);
        --transition-speed: 0.3s;
    }

    /* Base Styles */
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: var(--font-primary);
        font-size: 15px;
        color: var(--gray-dark);
        line-height: 1.5;
        background-color: #f5f7fa;
        overflow-x: hidden;
    }

    a {
        text-decoration: none;
        color: var(--primary);
        transition: color var(--transition-speed);
    }

    a:hover {
        color: var(--primary-dark);
    }

    ul {
        list-style: none;
    }

    button, input, select, textarea {
        font-family: var(--font-primary);
        font-size: 15px;
    }

    /* Layout */
    .app-container {
        display: flex;
        height: 100vh;
        overflow: hidden;
    }

    /* Sidebar */
    .sidebar {
        width: var(--sidebar-width);
        background-color: var(--dark);
        color: var(--light);
        display: flex;
        flex-direction: column;
        transition: width var(--transition-speed);
        overflow-y: auto;
        overflow-x: hidden;
        z-index: 100;
    }

    .sidebar-header {
        padding: 25px 20px;
        text-align: center;
    }

    .sidebar-header h1 {
        font-size: 24px;
        font-weight: 600;
        margin-bottom: 5px;
        color: white;
    }

    .sidebar-header p {
        font-size: 12px;
        opacity: 0.7;
    }

    .sidebar-nav {
        flex-grow: 1;
        padding: 10px 0;
    }

    .sidebar-nav a {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        color: var(--gray-light);
        border-left: 3px solid transparent;
        transition: all var(--transition-speed);
    }

    .sidebar-nav a:hover {
        background-color: rgba(255, 255, 255, 0.1);
        color: white;
    }

    .sidebar-nav a.active {
        background-color: rgba(255, 255, 255, 0.1);
        border-left-color: var(--primary);
        color: white;
    }

    .sidebar-nav i {
        margin-right: 12px;
        font-size: 18px;
        width: 25px;
        text-align: center;
    }

    .sidebar-divider {
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        margin: 15px 20px;
    }

    .sidebar-footer {
        padding: 15px 20px;
        font-size: 12px;
        opacity: 0.5;
        text-align: center;
    }

    /* Main Content */
    .main-content {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .main-header {
        height: var(--header-height);
        background-color: white;
        border-bottom: 1px solid var(--gray-light);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 30px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    }

    .header-search {
        display: flex;
        align-items: center;
        max-width: 300px;
        width: 100%;
    }

    .header-search input {
        border: none;
        background: transparent;
        width: 100%;
        padding: 10px;
        outline: none;
    }

    .header-search i {
        color: var(--gray);
        margin-right: 10px;
    }

    .header-profile {
        display: flex;
        align-items: center;
    }

    .profile-name {
        margin-right: 15px;
        font-weight: 500;
    }

    .profile-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: var(--primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .content {
        flex-grow: 1;
        padding: 30px;
        overflow-y: auto;
    }

    /* Card Styles */
    .card {
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        margin-bottom: 25px;
        overflow: hidden;
    }

    .card-header {
        padding: 20px 25px;
        border-bottom: 1px solid var(--gray-light);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .card-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--dark);
        margin: 0;
    }

    .card-actions {
        display: flex;
        gap: 10px;
    }

    .card-body {
        padding: 25px;
    }

    .card-footer {
        padding: 15px 25px;
        border-top: 1px solid var(--gray-light);
        background-color: rgba(236, 240, 241, 0.3);
    }

    /* Button Styles */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 10px 20px;
        border-radius: 5px;
        border: none;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn i {
        margin-right: 8px;
    }

    .btn-primary {
        background-color: var(--primary);
        color: white;
    }

    .btn-primary:hover {
        background-color: var(--primary-dark);
        color: white;
    }

    .btn-success {
        background-color: var(--success);
        color: white;
    }

    .btn-success:hover {
        background-color: var(--secondary-dark);
        color: white;
    }

    .btn-warning {
        background-color: var(--warning);
        color: white;
    }

    .btn-warning:hover {
        background-color: #e67e22;
        color: white;
    }

    .btn-danger {
        background-color: var(--danger);
        color: white;
    }

    .btn-danger:hover {
        background-color: #c0392b;
        color: white;
    }

    .btn-light {
        background-color: var(--gray-light);
        color: var(--gray-dark);
    }

    .btn-light:hover {
        background-color: #d5dbdb;
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 13px;
    }

    .btn-lg {
        padding: 12px 24px;
        font-size: 16px;
    }

    /* Form Styles */
    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--gray-dark);
    }

    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid var(--gray-light);
        border-radius: 5px;
        background-color: white;
        transition: border-color 0.2s;
    }

    .form-control:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
    }

    .form-select {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid var(--gray-light);
        border-radius: 5px;
        background-color: white;
        appearance: none;
        background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-chevron-down"><polyline points="6 9 12 15 18 9"></polyline></svg>');
        background-repeat: no-repeat;
        background-position: right 15px center;
    }

    .form-select:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
    }

    /* Table Styles */
    .table-container {
        overflow-x: auto;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
    }

    .table th,
    .table td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid var(--gray-light);
    }

    .table th {
        font-weight: 600;
        color: var(--dark);
        background-color: rgba(236, 240, 241, 0.5);
    }

    .table tr:hover {
        background-color: rgba(236, 240, 241, 0.3);
    }

    .table-striped tbody tr:nth-child(odd) {
        background-color: rgba(236, 240, 241, 0.2);
    }

    .table-actions {
        display: flex;
        gap: 8px;
    }

    /* Badge Styles */
    .badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }

    .badge-primary {
        background-color: rgba(52, 152, 219, 0.2);
        color: var(--primary-dark);
    }

    .badge-success {
        background-color: rgba(46, 204, 113, 0.2);
        color: var(--secondary-dark);
    }

    .badge-warning {
        background-color: rgba(243, 156, 18, 0.2);
        color: #d35400;
    }

    .badge-danger {
        background-color: rgba(231, 76, 60, 0.2);
        color: #c0392b;
    }

    .badge-info {
        background-color: rgba(52, 152, 219, 0.2);
        color: var(--primary-dark);
    }

    /* Alert Styles */
    .alert {
        padding: 15px 20px;
        border-radius: var(--border-radius);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
    }

    .alert i {
        margin-right: 12px;
        font-size: 18px;
    }

    .alert-success {
        background-color: rgba(46, 204, 113, 0.2);
        color: var(--secondary-dark);
    }

    .alert-warning {
        background-color: rgba(243, 156, 18, 0.2);
        color: #d35400;
    }

    .alert-danger {
        background-color: rgba(231, 76, 60, 0.2);
        color: #c0392b;
    }

    .alert-info {
        background-color: rgba(52, 152, 219, 0.2);
        color: var(--primary-dark);
    }

    /* Utilities */
    .text-primary { color: var(--primary); }
    .text-success { color: var(--success); }
    .text-warning { color: var(--warning); }
    .text-danger { color: var(--danger); }
    .text-info { color: var(--info); }
    .text-dark { color: var(--dark); }
    .text-gray { color: var(--gray); }
    .text-light { color: var(--gray-light); }

    .bg-primary { background-color: var(--primary); }
    .bg-success { background-color: var(--success); }
    .bg-warning { background-color: var(--warning); }
    .bg-danger { background-color: var(--danger); }
    .bg-info { background-color: var(--info); }
    .bg-dark { background-color: var(--dark); }
    .bg-gray { background-color: var(--gray); }
    .bg-light { background-color: var(--gray-light); }

    .mb-0 { margin-bottom: 0; }
    .mb-1 { margin-bottom: 5px; }
    .mb-2 { margin-bottom: 10px; }
    .mb-3 { margin-bottom: 15px; }
    .mb-4 { margin-bottom: 25px; }
    .mb-5 { margin-bottom: 40px; }

    .mt-0 { margin-top: 0; }
    .mt-1 { margin-top: 5px; }
    .mt-2 { margin-top: 10px; }
    .mt-3 { margin-top: 15px; }
    .mt-4 { margin-top: 25px; }
    .mt-5 { margin-top: 40px; }

    .d-flex { display: flex; }
    .align-center { align-items: center; }
    .justify-between { justify-content: space-between; }
    .flex-wrap { flex-wrap: wrap; }
    .gap-2 { gap: 10px; }
    .gap-3 { gap: 15px; }

    .w-100 { width: 100%; }
    .h-100 { height: 100%; }

    /* Responsive Utilities */
    @media (max-width: 992px) {
        .sidebar {
            width: 80px;
        }
        
        .sidebar-header h1 {
            display: none;
        }
        
        .sidebar-header p {
            display: none;
        }
        
        .sidebar-nav a span {
            display: none;
        }
        
        .sidebar-nav i {
            margin-right: 0;
            font-size: 20px;
        }
        
        .sidebar-footer {
            display: none;
        }
    }

    @media (max-width: 768px) {
        .content {
            padding: 20px;
        }
        
        .main-header {
            padding: 0 20px;
        }
        
        .header-search {
            display: none;
        }
    }
    </style>
    
    <?php if ($current_dir === 'dashboard' || $current_page === 'index.php'): ?>
    <style>
    /* Dashboard Specific Styles */

    /* Dashboard Stats */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }

    .stat-card {
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        padding: 25px;
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(44, 62, 80, .15);
    }

    .stat-card .stat-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        margin-bottom: 15px;
        font-size: 24px;
    }

    .stat-card .stat-value {
        font-size: 28px;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .stat-card .stat-label {
        color: var(--gray);
        font-size: 14px;
    }

    .stat-card .stat-trend {
        display: flex;
        align-items: center;
        font-size: 13px;
        margin-top: 15px;
    }

    .stat-card .trend-up {
        color: var(--success);
    }

    .stat-card .trend-down {
        color: var(--danger);
    }

    .stat-card .trend-neutral {
        color: var(--gray);
    }

    .stat-card .stat-trend i {
        margin-right: 5px;
    }

    /* Timeline Chart */
    .timeline-chart-container {
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        padding: 25px;
        margin-bottom: 30px;
    }

    .timeline-chart-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }

    .timeline-chart-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--dark);
    }

    .timeline-chart-actions {
        display: flex;
        gap: 10px;
    }

    .timeline-chart-canvas {
        height: 350px;
        width: 100%;
    }

    /* Recent Transactions */
    .recent-transactions {
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        overflow: hidden;
        margin-bottom: 25px;
    }

    .transactions-header {
        padding: 20px 25px;
        border-bottom: 1px solid var(--gray-light);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .transactions-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--dark);
    }

    .transactions-nav {
        display: flex;
        gap: 15px;
    }

    .transactions-nav a {
        color: var(--gray);
        font-size: 14px;
        transition: color 0.2s;
    }

    .transactions-nav a:hover,
    .transactions-nav a.active {
        color: var(--primary);
    }

    .transaction-list {
        max-height: 400px;
        overflow-y: auto;
    }

    .transaction-item {
        display: flex;
        align-items: center;
        padding: 15px 25px;
        border-bottom: 1px solid var(--gray-light);
        transition: background-color 0.2s;
    }

    .transaction-item:hover {
        background-color: rgba(236, 240, 241, 0.3);
    }

    .transaction-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        font-size: 16px;
    }

    .transaction-icon.incoming {
        background-color: rgba(46, 204, 113, 0.2);
        color: var(--secondary-dark);
    }

    .transaction-icon.outgoing {
        background-color: rgba(231, 76, 60, 0.2);
        color: #c0392b;
    }

    .transaction-details {
        flex-grow: 1;
    }

    .transaction-title {
        font-weight: 500;
        margin-bottom: 3px;
    }

    .transaction-category {
        font-size: 13px;
        color: var(--gray);
    }

    .transaction-amount {
        font-weight: 600;
        white-space: nowrap;
    }

    .amount-incoming {
        color: var(--success);
    }

    .amount-outgoing {
        color: var(--danger);
    }

    .transaction-date {
        font-size: 13px;
        color: var(--gray);
        white-space: nowrap;
        margin-left: 20px;
    }

    .transactions-footer {
        padding: 15px 25px;
        border-top: 1px solid var(--gray-light);
        text-align: center;
    }

    .transactions-footer a {
        font-size: 14px;
    }

    /* Upcoming Expenses */
    .upcoming-expenses {
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        margin-bottom: 25px;
    }

    .upcoming-header {
        padding: 20px 25px;
        border-bottom: 1px solid var(--gray-light);
    }

    .upcoming-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--dark);
    }

    .expense-list {
        padding: 0;
    }

    .expense-item {
        display: flex;
        align-items: center;
        padding: 15px 25px;
        border-bottom: 1px solid var(--gray-light);
    }

    .expense-date {
        width: 60px;
        height: 60px;
        border-radius: 10px;
        background-color: rgba(236, 240, 241, 0.5);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
    }

    .expense-day {
        font-size: 20px;
        font-weight: 600;
        line-height: 1;
    }

    .expense-month {
        font-size: 12px;
        text-transform: uppercase;
    }

    .expense-details {
        flex-grow: 1;
    }

    .expense-title {
        font-weight: 500;
        margin-bottom: 3px;
    }

    .expense-category {
        font-size: 13px;
        color: var(--gray);
    }

    .expense-amount {
        font-weight: 600;
        color: var(--danger);
    }

    /* Categories Distribution */
    .categories-chart {
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        padding: 25px;
    }

    .categories-header {
        margin-bottom: 20px;
    }

    .categories-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--dark);
    }

    .categories-container {
        display: flex;
        align-items: center;
    }

    .categories-donut {
        flex: 0 0 50%;
        height: 250px;
    }

    .categories-legend {
        flex: 0 0 50%;
        padding-left: 25px;
    }

    .legend-item {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }

    .legend-color {
        width: 12px;
        height: 12px;
        border-radius: 2px;
        margin-right: 10px;
    }

    .legend-name {
        flex-grow: 1;
        font-size: 14px;
    }

    .legend-value {
        font-weight: 500;
    }

    /* Row styling */
    .row {
        display: flex;
        flex-wrap: wrap;
        margin-right: -15px;
        margin-left: -15px;
    }

    .col-lg-4, .col-lg-8 {
        position: relative;
        width: 100%;
        padding-right: 15px;
        padding-left: 15px;
    }

    @media (min-width: 992px) {
        .col-lg-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
        }
        
        .col-lg-8 {
            flex: 0 0 66.666667%;
            max-width: 66.666667%;
        }
    }

    /* Add this to make sure canvas has a defined height */
    #liquidityChart {
        height: 350px !important;
        max-height: 350px !important;
    }

    #categoriesChart {
        height: 250px !important;
        max-height: 250px !important;
    }

    /* Add responsive adjustments */
    @media (max-width: 768px) {
        .stats-container {
            grid-template-columns: 1fr;
        }
        
        .categories-container {
            flex-direction: column;
        }
        
        .categories-donut,
        .categories-legend {
            flex: 0 0 100%;
            padding-left: 0;
        }
        
        .categories-legend {
            margin-top: 20px;
        }
        
        .transaction-date {
            display: none;
        }
        
        .expense-date {
            width: 50px;
            height: 50px;
        }
    }

    .content-title {
        font-size: 24px;
        font-weight: 600;
        margin-bottom: 25px;
        color: var(--dark);
    }

    .text-light {
        color: white !important;
    }

    .bg-primary {
        background-color: var(--primary) !important;
    }

    .bg-success {
        background-color: var(--success) !important;
    }

    .bg-danger {
        background-color: var(--danger) !important;
    }

    .bg-warning {
        background-color: var(--warning) !important;
    }
    </style>
    <?php endif; ?>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1>Likviditet</h1>
                <p>Liquidity Management</p>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="/index.php" class="<?php echo (isActive('index.php', true) || isActive('dashboard')) ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="/modules/incoming/index.php" class="<?php echo isActive('incoming') ? 'active' : ''; ?>">
                            <i class="fas fa-arrow-down"></i>
                            <span>Incoming</span>
                        </a>
                    </li>
                    <li>
                        <a href="/modules/outgoing/index.php" class="<?php echo isActive('outgoing') ? 'active' : ''; ?>">
                            <i class="fas fa-arrow-up"></i>
                            <span>Outgoing</span>
                        </a>
                    </li>
                    <li>
                        <a href="/modules/debt/index.php" class="<?php echo isActive('debt') ? 'active' : ''; ?>">
                            <i class="fas fa-credit-card"></i>
                            <span>Debt</span>
                        </a>
                    </li>
                    <li class="sidebar-divider"></li>
                    <li>
                        <a href="/auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="sidebar-footer">
                <p>&copy; <?php echo date('Y'); ?> Illeris</p>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <div class="header-search">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
                </div>
                
                <div class="header-profile">
                    <span class="profile-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']); ?></span>
                    <div class="profile-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
            </header>
            
            <div class="content">