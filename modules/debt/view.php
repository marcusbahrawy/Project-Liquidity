<?php
/**
 * View Debt Detailss
 */

// Include database connection
require_once '../../config/database.php';

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id = (int)$_GET['id'];

// Get debt
$stmt = $pdo->prepare("
    SELECT d.*, c.name as category_name, c.color as category_color
    FROM debt d
    LEFT JOIN categories c ON d.category_id = c.id
    WHERE d.id = :id
");
$stmt->execute(['id' => $id]);
$debt = $stmt->fetch();

// Redirect if debt not found
if (!$debt) {
    header('Location: index.php');
    exit;
}

// Get debt payments
$stmt = $pdo->prepare("
    SELECT dp.*, o.date, o.description as payment_description, o.notes
    FROM debt_payments dp
    JOIN outgoing o ON dp.outgoing_id = o.id
    WHERE dp.debt_id = :debt_id
    ORDER BY o.date DESC
");
$stmt->execute(['debt_id' => $id]);
$payments = $stmt->fetchAll();

// Calculate totals and statistics
$totalPaid = 0;
$paymentCount = count($payments);
$averagePayment = 0;
$monthsActive = 0;

if ($paymentCount > 0) {
    foreach ($payments as $payment) {
        $totalPaid += $payment['amount'];
    }
    $averagePayment = $totalPaid / $paymentCount;
    
    // Calculate months active
    if (!empty($payments)) {
        $firstPayment = end($payments);
        $lastPayment = reset($payments);
        
        $firstDate = new DateTime($firstPayment['date']);
        $lastDate = new DateTime($lastPayment['date']);
        $diff = $firstDate->diff($lastDate);
        
        $monthsActive = ($diff->y * 12) + $diff->m + 1; // +1 to include the current month
    }
}

// Group payments by year and month for chart
$paymentsByMonth = [];
$currentDate = new DateTime();
$startDate = (new DateTime())->modify('-12 months');

foreach ($payments as $payment) {
    $date = new DateTime($payment['date']);
    
    // Only include payments from the last 12 months
    if ($date >= $startDate && $date <= $currentDate) {
        $yearMonth = $date->format('Y-m');
        
        if (!isset($paymentsByMonth[$yearMonth])) {
            $paymentsByMonth[$yearMonth] = 0;
        }
        $paymentsByMonth[$yearMonth] += $payment['amount'];
    }
}

// Sort by date
ksort($paymentsByMonth);

// Format for chart
$chartLabels = [];
$chartData = [];
foreach ($paymentsByMonth as $yearMonth => $amount) {
    $date = DateTime::createFromFormat('Y-m', $yearMonth);
    $chartLabels[] = $date->format('M Y');
    $chartData[] = $amount;
}

// Include header
require_once '../../includes/header.php';
?>

<div class="module-header">
    <div class="module-title">
        <h1><?php echo htmlspecialchars($debt['description']); ?></h1>
        <p>Loan/Debt Details</p>
    </div>
    <div class="module-actions">
        <a href="index.php" class="btn btn-light">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
        
        <a href="edit.php?id=<?php echo $debt['id']; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Edit Debt
        </a>
        
        <a href="../outgoing/add.php?is_debt=1&debt_id=<?php echo $debt['id']; ?>" class="btn btn-success">
            <i class="fas fa-plus"></i> Add Payment
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <div class="card-title">Debt Overview</div>
            </div>
            <div class="card-body">
                <div class="debt-summary">
                    <div class="summary-header">
                        <div class="debt-title">
                            <h2><?php echo htmlspecialchars($debt['description']); ?></h2>
                            <?php if ($debt['category_name']): ?>
                                <span class="category-badge" style="background-color: <?php echo htmlspecialchars($debt['category_color']); ?>">
                                    <?php echo htmlspecialchars($debt['category_name']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="debt-dates">
                            <div class="date-item">
                                <span class="date-label">Started</span>
                                <span class="date-value"><?php echo date('M d, Y', strtotime($debt['start_date'])); ?></span>
                            </div>
                            
                            <?php if ($debt['end_date']): ?>
                                <div class="date-item">
                                    <span class="date-label">End Date</span>
                                    <span class="date-value"><?php echo date('M d, Y', strtotime($debt['end_date'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="progress-container">
                        <?php 
                        $paidPercent = $debt['total_amount'] > 0 ? (($debt['total_amount'] - $debt['remaining_amount']) / $debt['total_amount']) * 100 : 0;
                        ?>
                        <div class="progress-header">
                            <span>Payment Progress</span>
                            <span><?php echo number_format($paidPercent, 1); ?>% paid</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $paidPercent; ?>%" aria-valuenow="<?php echo $paidPercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="progress-stats">
                            <div class="stat-item">
                                <span class="stat-value"><?php echo number_format($totalPaid, 2); ?> kr</span>
                                <span class="stat-label">Amount Paid</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo number_format($debt['remaining_amount'], 2); ?> kr</span>
                                <span class="stat-label">Remaining</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo number_format($debt['total_amount'], 2); ?> kr</span>
                                <span class="stat-label">Total</span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($debt['notes'])): ?>
                        <div class="debt-notes">
                            <h3>Notes</h3>
                            <div class="notes-content">
                                <?php echo nl2br(htmlspecialchars($debt['notes'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <div class="card-title">Payment History</div>
            </div>
            <div class="card-body">
                <?php if (empty($payments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-money-bill-wave"></i>
                        <p>No payments recorded yet.</p>
                        <a href="../outgoing/add.php?is_debt=1&debt_id=<?php echo $debt['id']; ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-plus"></i> Add First Payment
                        </a>
                    </div>
                <?php else: ?>
                    <canvas id="paymentsChart" height="250" style="margin-bottom: 20px;"></canvas>
                    
                    <div class="payment-list">
                        <h3>Payment Transactions</h3>
                        
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($payment['date'])); ?></td>
                                            <td><?php echo htmlspecialchars($payment['payment_description']); ?></td>
                                            <td class="text-right"><?php echo number_format($payment['amount'], 2); ?> kr</td>
                                            <td><?php echo !empty($payment['notes']) ? htmlspecialchars(truncateText($payment['notes'], 50)) : ''; ?></td>
                                            <td>
                                                <div class="table-actions">
                                                    <a href="../outgoing/edit.php?id=<?php echo $payment['outgoing_id']; ?>" class="btn btn-sm btn-primary" data-tooltip="Edit Payment">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="2">Total</th>
                                        <th class="text-right"><?php echo number_format($totalPaid, 2); ?> kr</th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <div class="card-title">Debt Statistics</div>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-block">
                        <div class="stat-value"><?php echo $debt['interest_rate'] ? number_format($debt['interest_rate'], 2) . '%' : 'N/A'; ?></div>
                        <div class="stat-label">Interest Rate</div>
                    </div>
                    
                    <div class="stat-block">
                        <div class="stat-value"><?php echo $paymentCount; ?></div>
                        <div class="stat-label">Payments Made</div>
                    </div>
                    
                    <div class="stat-block">
                        <div class="stat-value"><?php echo $paymentCount > 0 ? number_format($averagePayment, 2) . ' kr' : 'N/A'; ?></div>
                        <div class="stat-label">Average Payment</div>
                    </div>
                    
                    <div class="stat-block">
                        <div class="stat-value"><?php echo $monthsActive; ?></div>
                        <div class="stat-label">Months Active</div>
                    </div>
                    
                    <?php if ($debt['remaining_amount'] > 0 && $averagePayment > 0): ?>
                        <div class="stat-block">
                            <div class="stat-value">
                                <?php
                                $paymentsLeft = ceil($debt['remaining_amount'] / $averagePayment);
                                echo $paymentsLeft;
                                ?>
                            </div>
                            <div class="stat-label">Estimated Payments Left</div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($debt['remaining_amount'] > 0 && $averagePayment > 0): ?>
                    <div class="projection-info">
                        <h4>Payment Projection</h4>
                        <p>
                            At the current average payment rate of <?php echo number_format($averagePayment, 2); ?> kr,
                            you will complete this debt in approximately 
                            <strong><?php echo $paymentsLeft; ?> payments</strong>.
                        </p>
                        
                        <?php
                        // Estimate completion date
                        if ($paymentCount >= 2) {
                            $firstPayment = end($payments);
                            $lastPayment = reset($payments);
                            
                            $firstDate = new DateTime($firstPayment['date']);
                            $lastDate = new DateTime($lastPayment['date']);
                            
                            $daysDiff = $lastDate->diff($firstDate)->days;
                            $paymentsDiff = $paymentCount - 1;
                            
                            if ($paymentsDiff > 0 && $daysDiff > 0) {
                                $daysPerPayment = $daysDiff / $paymentsDiff;
                                $daysLeft = $daysPerPayment * $paymentsLeft;
                                
                                $estimatedEndDate = new DateTime();
                                $estimatedEndDate->add(new DateInterval('P' . round($daysLeft) . 'D'));
                                
                                echo '<p>Estimated completion date: <strong>' . $estimatedEndDate->format('F Y') . '</strong></p>';
                            }
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="card-title">Quick Actions</div>
            </div>
            <div class="card-body">
                <div class="action-buttons">
                    <a href="../outgoing/add.php?is_debt=1&debt_id=<?php echo $debt['id']; ?>" class="btn btn-success btn-block mb-3">
                        <i class="fas fa-plus"></i> Add Payment
                    </a>
                    
                    <a href="edit.php?id=<?php echo $debt['id']; ?>" class="btn btn-primary btn-block mb-3">
                        <i class="fas fa-edit"></i> Edit Debt
                    </a>
                    
                    <a href="api.php?action=delete&id=<?php echo $debt['id']; ?>" class="btn btn-danger btn-block delete-btn" data-name="<?php echo htmlspecialchars($debt['description']); ?>">
                        <i class="fas fa-trash"></i> Delete Debt
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($chartLabels) && !empty($chartData)): ?>
    // Initialize payment history chart
    const ctx = document.getElementById('paymentsChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'Payment Amount',
                data: <?php echo json_encode($chartData); ?>,
                backgroundColor: 'rgba(46, 204, 113, 0.6)',
                borderColor: 'rgba(46, 204, 113, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString() + ' kr';
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.raw.toLocaleString() + ' kr';
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<style>
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

.mb-4 {
    margin-bottom: 25px;
}

.mt-4 {
    margin-top: 25px;
}

.debt-summary {
    padding: 10px;
}

.summary-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.debt-title h2 {
    font-size: 20px;
    font-weight: 600;
    margin: 0 0 10px 0;
    color: var(--dark);
}

.debt-dates {
    display: flex;
    gap: 15px;
}

.date-item {
    display: flex;
    flex-direction: column;
    text-align: right;
}

.date-label {
    font-size: 12px;
    color: var(--gray);
}

.date-value {
    font-weight: 500;
}

.progress-container {
    background-color: var(--gray-light);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-weight: 500;
}

.progress {
    height: 8px;
    background-color: white;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 15px;
}

.progress-bar {
    background-color: var(--secondary);
}

.progress-stats {
    display: flex;
    justify-content: space-between;
}

.stat-item {
    display: flex;
    flex-direction: column;
    text-align: center;
}

.stat-value {
    font-weight: 600;
    font-size: 16px;
    color: var(--dark);
}

.stat-label {
    font-size: 12px;
    color: var(--gray);
}

.debt-notes {
    margin-top: 20px;
}

.debt-notes h3 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 10px;
    color: var(--dark);
}

.notes-content {
    background-color: var(--gray-light);
    border-radius: 8px;
    padding: 15px;
    white-space: pre-line;
    line-height: 1.5;
}

.payment-list h3 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 15px;
    color: var(--dark);
    margin-top: 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.stat-block {
    background-color: var(--gray-light);
    border-radius: 8px;
    padding: 15px;
    text-align: center;
}

.projection-info {
    margin-top: 25px;
    background-color: rgba(52, 152, 219, 0.1);
    border-left: 4px solid var(--primary);
    padding: 15px;
    border-radius: 4px;
}

.projection-info h4 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 10px;
    color: var(--dark);
}

.projection-info p {
    margin-bottom: 10px;
    line-height: 1.5;
}

.action-buttons .btn-block {
    display: block;
    width: 100%;
}

.category-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    color: white;
}

.empty-state {
    padding: 40px 25px;
    text-align: center;
}

.empty-state i {
    font-size: 36px;
    color: var(--gray);
    margin-bottom: 10px;
}

.empty-state p {
    color: var(--gray);
    margin-bottom: 15px;
}

.text-right {
    text-align: right;
}
</style>

<?php
// Include footer
require_once '../../includes/footer.php';