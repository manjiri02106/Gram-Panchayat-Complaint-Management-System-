/* assets/js/chart-config.js */
/* Gram Panchayat Complaint Management System - Analytics Chart Setup */

function initDashboardCharts(receivedData, pendingData, progressData, resolvedData) {
    // 1. Complaints Overview Line Chart
    const overviewCtx = document.getElementById('complaintsOverviewChart');
    if (overviewCtx) {
        // Mock timeline labels for the month
        const labels = ['01 May', '05 May', '09 May', '13 May', '17 May', '21 May', '25 May', '30 May'];
        
        new Chart(overviewCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Received Complaints',
                    data: [15, 38, 30, 48, 38, 70, 42, 78], // Smooth line matching mockup curve
                    borderColor: '#2E7D32',
                    backgroundColor: 'rgba(46, 125, 50, 0.05)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointBackgroundColor: '#2E7D32',
                    pointRadius: 4
                }, {
                    label: 'Resolved Complaints',
                    data: [8, 22, 28, 35, 30, 52, 38, 62],
                    borderColor: '#1976D2',
                    backgroundColor: 'rgba(25, 118, 210, 0.03)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointBackgroundColor: '#1976D2',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false // Hide default legend; custom boxes are rendered in HTML
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#edf2f7' },
                        ticks: { font: { family: 'Inter', size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter', size: 10 } }
                    }
                }
            }
        });
    }

    // 2. Status Distribution Doughnut Chart (Fallback/Audit page)
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        const stats = {
            'Pending': pendingData || 0,
            'In Progress': progressData || 0,
            'Resolved': resolvedData || 0
        };
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(stats),
                datasets: [{
                    data: Object.values(stats),
                    backgroundColor: ['#f57c00', '#1976d2', '#2e7d32'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } }
                }
            }
        });
    }
}
