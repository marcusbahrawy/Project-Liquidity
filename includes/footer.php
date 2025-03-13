<?php
/**
 * Updated Footer Template with Delete Fix
 */
?>
</div><!-- .content -->
        </main><!-- .main-content -->
    </div><!-- .app-container -->
    
    <!-- Main JS -->
    <script src="/assets/js/main.js"></script>
    
    <!-- Delete functionality fix -->
    <script src="/assets/js/delete-fix.js"></script>
    
    <?php if ($current_dir === 'dashboard' || $current_page === 'index.php'): ?>
    <script>
    /**
 * Dashboard JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Charts
    initLiquidityChart();
    initCategoriesChart();
    
    // Event Listeners
    const timelineRange = document.getElementById('timelineRange');
    if (timelineRange) {
        timelineRange.addEventListener('change', function() {
            fetchTimelineData(this.value);
        });
    }
    
    // Transaction filters
    const transactionFilters = document.querySelectorAll('.transactions-nav a');
    const transactionItems = document.querySelectorAll('.transaction-item');
    
    transactionFilters.forEach(filter => {
        filter.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all filters
            transactionFilters.forEach(f => f.classList.remove('active'));
            
            // Add active class to current filter
            this.classList.add('active');
            
            const filterType = this.getAttribute('data-filter');
            
            // Show/hide transactions based on filter
            transactionItems.forEach(item => {
                if (filterType === 'all' || item.getAttribute('data-type') === filterType) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
});

// Variable to store Chart instance
let liquidityChart = null;

/**
 * Initialize Liquidity Timeline Chart
 */
function initLiquidityChart() {
    const ctx = document.getElementById('liquidityChart');
    
    // Check if canvas exists
    if (!ctx) return;
    
    const ctxContext = ctx.getContext('2d');
    
    // Chart configuration
    liquidityChart = new Chart(ctxContext, {
        type: 'line',
        data: {
            labels: [], // Will be populated via fetchTimelineData
            datasets: [
                {
                    label: 'Balance',
                    data: [], // Will be populated via fetchTimelineData
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(52, 152, 219, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.2
                },
                {
                    label: 'Income',
                    data: [], // Will be populated via fetchTimelineData
                    backgroundColor: 'rgba(46, 204, 113, 0)',
                    borderColor: 'rgba(46, 204, 113, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(46, 204, 113, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.2
                },
                {
                    label: 'Expenses',
                    data: [], // Will be populated via fetchTimelineData
                    backgroundColor: 'rgba(231, 76, 60, 0)',
                    borderColor: 'rgba(231, 76, 60, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(231, 76, 60, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(44, 62, 80, 0.8)',
                    padding: 12,
                    titleColor: '#fff',
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    },
                    bodySpacing: 8,
                    usePointStyle: true,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('no-NO', { 
                                    style: 'currency', 
                                    currency: 'NOK',
                                    minimumFractionDigits: 2
                                }).format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                },
                legend: {
                    position: 'top',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat('no-NO', { 
                                style: 'currency', 
                                currency: 'NOK',
                                notation: 'compact',
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            }).format(value);
                        }
                    }
                }
            }
        }
    });
    
    // Fetch initial data (last 30 days)
    fetchTimelineData(30);
}

/**
 * Fetch Timeline Data for Chart
 */
function fetchTimelineData(days) {
    if (!liquidityChart) return;
    
    // In a real application, this would be an AJAX call to the server
    // For demo purposes, generate random data
    
    const labels = [];
    const incomeData = [];
    const expenseData = [];
    const balanceData = [];
    
    let balance = Math.random() * 10000 + 5000; // Starting balance
    const today = new Date();
    
    for (let i = parseInt(days); i >= 0; i--) {
        const date = new Date();
        date.setDate(today.getDate() - i);
        
        // Format date label
        const label = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        labels.push(label);
        
        // Generate random income and expense
        let income = 0;
        let expense = 0;
        
        // Add income on some days (higher probability on 1st and 15th of month)
        const dayOfMonth = date.getDate();
        const incomeProb = (dayOfMonth === 1 || dayOfMonth === 15) ? 0.8 : 0.2;
        
        if (Math.random() < incomeProb) {
            income = Math.random() * 5000 + 1000;
        }
        
        // Daily expenses (higher probability on weekends)
        const dayOfWeek = date.getDay();
        const expenseProb = (dayOfWeek === 0 || dayOfWeek === 6) ? 0.7 : 0.5;
        
        if (Math.random() < expenseProb) {
            expense = Math.random() * 1000 + 200;
        }
        
        // Update balance
        balance += income - expense;
        
        incomeData.push(income);
        expenseData.push(expense);
        balanceData.push(balance);
    }
    
    // Update chart (safely)
    if (liquidityChart) {
        liquidityChart.data.labels = labels;
        liquidityChart.data.datasets[0].data = balanceData;
        liquidityChart.data.datasets[1].data = incomeData;
        liquidityChart.data.datasets[2].data = expenseData;
        liquidityChart.update();
    }
}

/**
 * Initialize Categories Donut Chart
 */
function initCategoriesChart() {
    const ctx = document.getElementById('categoriesChart');
    
    // Check if canvas exists
    if (!ctx) return;
    
    // Get category data from DOM
    const legendItems = document.querySelectorAll('.legend-item');
    const labels = [];
    const data = [];
    const colors = [];
    
    legendItems.forEach(item => {
        const nameEl = item.querySelector('.legend-name');
        const valueEl = item.querySelector('.legend-value');
        const colorEl = item.querySelector('.legend-color');
        
        if (!nameEl || !valueEl || !colorEl) return;
        
        const name = nameEl.textContent;
        const value = parseFloat(valueEl.textContent.replace(/[^\d.-]/g, '')) || 0;
        const color = colorEl.style.backgroundColor;
        
        labels.push(name);
        data.push(value);
        colors.push(color);
    });
    
    // If no data, show placeholder
    if (data.length === 0) {
        labels.push('No Data');
        data.push(1);
        colors.push('#cccccc');
    }
    
    // Chart configuration
    const categoriesChart = new Chart(ctx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(44, 62, 80, 0.8)',
                    padding: 12,
                    titleColor: '#fff',
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    },
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            
                            if (label === 'No Data') return 'No category data available';
                            
                            return `${label}: ${new Intl.NumberFormat('no-NO', { 
                                style: 'currency', 
                                currency: 'NOK',
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            }).format(value)} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}
    </script>
    <?php elseif ($current_dir === 'incoming'): ?>
    <script src="/assets/js/incoming.js"></script>
    <?php elseif ($current_dir === 'outgoing'): ?>
    <script src="/assets/js/outgoing.js"></script>
    <?php elseif ($current_dir === 'debt'): ?>
    <script src="/assets/js/debt.js"></script>
    <?php endif; ?>
</body>
</html>