/**
 * Dashboard JavaScript - Updated for real data and future projections
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
    
    // Fetch dashboard stats
    fetchDashboardStats();
});

// Variable to store Chart instance
let liquidityChart = null;

/**
 * Fetch dashboard stats
 */
function fetchDashboardStats() {
    fetch('/api_dashboard.php?action=stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateDashboardStats(data.data);
            } else {
                console.error('Error fetching dashboard stats:', data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching dashboard stats:', error);
        });
}

/**
 * Update dashboard stats with real data
 */
function updateDashboardStats(stats) {
    // Update balance card
    const balanceValue = document.querySelector('.stat-card:nth-child(1) .stat-value');
    if (balanceValue) {
        balanceValue.textContent = formatCurrency(stats.currentBalance);
    }
    
    // Update income card
    const incomeValue = document.querySelector('.stat-card:nth-child(2) .stat-value');
    if (incomeValue) {
        incomeValue.textContent = formatCurrency(stats.upcomingIncome);
    }
    
    // Update expense card
    const expenseValue = document.querySelector('.stat-card:nth-child(3) .stat-value');
    if (expenseValue) {
        expenseValue.textContent = formatCurrency(stats.upcomingExpenses);
    }
    
    // Update debt card
    const debtValue = document.querySelector('.stat-card:nth-child(4) .stat-value');
    if (debtValue) {
        debtValue.textContent = formatCurrency(stats.totalDebt);
    }
    
    // Update trends
    const balanceTrend = document.querySelector('.stat-card:nth-child(1) .stat-trend');
    if (balanceTrend) {
        const projectedChange = stats.projectedBalance - stats.currentBalance;
        const percentChange = stats.currentBalance !== 0 ? (projectedChange / Math.abs(stats.currentBalance) * 100).toFixed(1) : 0;
        
        balanceTrend.className = 'stat-trend ' + (projectedChange >= 0 ? 'trend-up' : 'trend-down');
        balanceTrend.innerHTML = `
            <i class="fas ${projectedChange >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'}"></i>
            ${Math.abs(percentChange)}% projected change
        `;
    }
}

/**
 * Format currency value
 */
function formatCurrency(value) {
    return new Intl.NumberFormat('no-NO', { 
        style: 'currency', 
        currency: 'NOK',
        minimumFractionDigits: 2
    }).format(value);
}

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
                    label: 'Projected Balance',
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
                    label: 'Upcoming Income',
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
                    label: 'Upcoming Expenses',
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
    
    // Fetch initial data (next 30 days)
    fetchTimelineData(30);
}

/**
 * Fetch Timeline Data for Chart
 */
function fetchTimelineData(days) {
    if (!liquidityChart) return;
    
    // Show loading indicator
    const ctx = document.getElementById('liquidityChart');
    if (ctx) {
        ctx.style.opacity = 0.5;
    }
    
    // Fetch data from API
    fetch(`/api_dashboard.php?action=timeline&days=${days}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateChartData(data.data);
            } else {
                console.error('Error fetching timeline data:', data.message);
            }
            
            // Hide loading indicator
            if (ctx) {
                ctx.style.opacity = 1;
            }
        })
        .catch(error => {
            console.error('Error fetching timeline data:', error);
            
            // Hide loading indicator
            if (ctx) {
                ctx.style.opacity = 1;
            }
        });
}

/**
 * Update chart with real data
 */
function updateChartData(data) {
    if (!liquidityChart) return;
    
    liquidityChart.data.labels = data.labels;
    liquidityChart.data.datasets[0].data = data.balanceData;
    liquidityChart.data.datasets[1].data = data.incomeData;
    liquidityChart.data.datasets[2].data = data.expenseData;
    liquidityChart.update();
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