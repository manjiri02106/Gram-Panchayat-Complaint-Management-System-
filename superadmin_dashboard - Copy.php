<?php
// superadmin_dashboard.php
// Gram Panchayat Complaint Management System - Super Admin Panel Dashboard

require_once 'includes/auth.php';
require_once 'includes/db.php';

// Enforce Super Admin Role
check_role('super_admin');

$userId = $_SESSION['user_id'];

// 1. Fetch Overall Complaints Summary
try {
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status_id = 1 THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status_id IN (2, 3) THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status_id = 4 THEN 1 ELSE 0 END) as resolved
        FROM complaints
    ")->fetch();
} catch (PDOException $e) {
    $stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'resolved' => 0];
}

// 2. Fetch User Counts by Role
try {
    $userStats = $pdo->query("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role_id = 2 THEN 1 ELSE 0 END) as gp_admins,
            SUM(CASE WHEN role_id = 3 THEN 1 ELSE 0 END) as field_officers,
            SUM(CASE WHEN role_id = 4 THEN 1 ELSE 0 END) as citizens
        FROM users
    ")->fetch();
} catch (PDOException $e) {
    $userStats = ['total_users' => 0, 'gp_admins' => 0, 'field_officers' => 0, 'citizens' => 0];
}

// 3. Fetch Recent Citizen Feedback (Last 5)
try {
    $feedbackLogs = $pdo->query("
        SELECT f.*, c.ticket_id, c.title as complaint_title, u.full_name as citizen_name
        FROM feedback f
        JOIN complaints c ON f.complaint_id = c.id
        JOIN users u ON c.citizen_id = u.id
        ORDER BY f.created_at DESC
        LIMIT 4
    ")->fetchAll();
} catch (PDOException $e) {
    $feedbackLogs = [];
}

// 4. Fetch Recent Complaints (Last 5)
try {
    $recentComplaints = $pdo->query("
        SELECT c.*, cs.display_name AS status_name, cc.category_name, u.full_name AS citizen_name
        FROM complaints c
        JOIN complaint_statuses cs ON c.status_id = cs.id
        JOIN complaint_categories cc ON c.category_id = cc.id
        JOIN users u ON c.citizen_id = u.id
        ORDER BY c.created_at DESC
        LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    $recentComplaints = [];
}

// 5. Fetch Assigned Work stats
$assignedCount = 0;
$progressCount = 0;
$completedCount = 0;
$overdueCount = 0;
try {
    $assignedCount = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status_id = 2")->fetchColumn();
    $progressCount = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status_id = 3")->fetchColumn();
    $completedCount = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status_id = 4")->fetchColumn();
    $overdueCount = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status_id IN (1,2,3) AND created_at < DATE_SUB(NOW(), INTERVAL 5 DAY)")->fetchColumn();
} catch (PDOException $e) {
    // Fail silent
}

include 'includes/header.php';
?>

<!-- Page Title Block -->
<div class="page-header">
    <div>
        <h2>Dashboard</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 3px;">Welcome back, Super Administrator!</p>
    </div>
</div>

<!-- Summary Cards (Complaints) -->
<div class="stats-grid">
    <!-- Card 1: Total Complaints -->
    <div class="stat-card stat-total">
        <div class="stat-card-body">
            <div class="stat-icon"><i class="far fa-clipboard"></i></div>
            <div class="stat-info">
                <h3>Total Complaints</h3>
                <div class="stat-value"><?= intval($stats['total']) ?></div>
            </div>
        </div>
        <div class="stat-card-footer">
            <a href="admin_complaints.php">View all complaints <i class="fas fa-chevron-right"></i></a>
        </div>
    </div>
    
    <!-- Card 2: Pending -->
    <div class="stat-card stat-pending">
        <div class="stat-card-body">
            <div class="stat-icon"><i class="far fa-clock"></i></div>
            <div class="stat-info">
                <h3>Pending Complaints</h3>
                <div class="stat-value"><?= intval($stats['pending']) ?></div>
            </div>
        </div>
        <div class="stat-card-footer">
            <a href="admin_complaints.php?status=1">View details <i class="fas fa-chevron-right"></i></a>
        </div>
    </div>

    <!-- Card 3: In Progress -->
    <div class="stat-card stat-progress">
        <div class="stat-card-body">
            <div class="stat-icon"><i class="fas fa-sync-alt"></i></div>
            <div class="stat-info">
                <h3>In Progress</h3>
                <div class="stat-value"><?= intval($stats['in_progress']) ?></div>
            </div>
        </div>
        <div class="stat-card-footer">
            <a href="admin_complaints.php?status=3">View details <i class="fas fa-chevron-right"></i></a>
        </div>
    </div>

    <!-- Card 4: Resolved -->
    <div class="stat-card stat-resolved">
        <div class="stat-card-body">
            <div class="stat-icon"><i class="far fa-check-circle"></i></div>
            <div class="stat-info">
                <h3>Resolved</h3>
                <div class="stat-value"><?= intval($stats['resolved']) ?></div>
            </div>
        </div>
        <div class="stat-card-footer">
            <a href="admin_complaints.php?status=4">View details <i class="fas fa-chevron-right"></i></a>
        </div>
    </div>
</div>

<!-- Super User Directory Cards -->
<h3 style="font-size:0.95rem; font-weight:700; color:var(--primary-dark); margin-bottom:12px; text-transform:uppercase; letter-spacing:0.5px;">User Directory Statistics</h3>
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom:24px;">
    <div class="stat-card" style="border-left: 4px solid var(--primary-dark);">
        <div class="stat-card-body" style="padding:15px 20px;">
            <div class="stat-icon" style="background-color:#eee; color:#333;"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <h3 style="font-size:0.75rem;">Total Users</h3>
                <div class="stat-value" style="font-size:1.5rem;"><?= intval($userStats['total_users']) ?></div>
            </div>
        </div>
    </div>
    
    <div class="stat-card" style="border-left: 4px solid var(--accent);">
        <div class="stat-card-body" style="padding:15px 20px;">
            <div class="stat-icon" style="background-color:#e3f2fd; color:var(--accent);"><i class="fas fa-user-shield"></i></div>
            <div class="stat-info">
                <h3 style="font-size:0.75rem;">GP Admins</h3>
                <div class="stat-value" style="font-size:1.5rem;"><?= intval($userStats['gp_admins']) ?></div>
            </div>
        </div>
    </div>

    <div class="stat-card" style="border-left: 4px solid var(--secondary);">
        <div class="stat-card-body" style="padding:15px 20px;">
            <div class="stat-icon" style="background-color:#fff3e0; color:var(--secondary);"><i class="fas fa-user-edit"></i></div>
            <div class="stat-info">
                <h3 style="font-size:0.75rem;">Field Officers</h3>
                <div class="stat-value" style="font-size:1.5rem;"><?= intval($userStats['field_officers']) ?></div>
            </div>
        </div>
    </div>

    <div class="stat-card" style="border-left: 4px solid var(--success);">
        <div class="stat-card-body" style="padding:15px 20px;">
            <div class="stat-icon" style="background-color:#e8f5e9; color:var(--success);"><i class="fas fa-user-friends"></i></div>
            <div class="stat-info">
                <h3 style="font-size:0.75rem;">Citizens</h3>
                <div class="stat-value" style="font-size:1.5rem;"><?= intval($userStats['citizens']) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Main Grid Layout (3 Columns) -->
<div class="dashboard-layout-grid">
    
    <!-- Column 1: Complaints Overview Line Chart -->
    <div class="card" style="display:flex; flex-direction:column; justify-content:space-between;">
        <div class="card-header-flex" style="border-bottom:none; margin-bottom:5px;">
            <h3>Complaints Overview</h3>
            <select class="form-control" style="width:110px; height:30px; font-size:0.75rem; padding:4px 8px; border-radius:4px;">
                <option>This Month</option>
            </select>
        </div>
        
        <div class="chart-container" style="height:190px; margin-top:10px;">
            <canvas id="complaintsOverviewChart"></canvas>
        </div>

        <div class="chart-stats-row">
            <div class="chart-stat-item">
                <span>Received</span>
                <strong style="color:var(--primary);"><?= intval($stats['total']) ?></strong>
            </div>
            <div class="chart-stat-item">
                <span>Pending</span>
                <strong style="color:var(--secondary);"><?= intval($stats['pending']) ?></strong>
            </div>
            <div class="chart-stat-item">
                <span>In Progress</span>
                <strong style="color:var(--accent);"><?= intval($stats['in_progress']) ?></strong>
            </div>
            <div class="chart-stat-item">
                <span>Resolved</span>
                <strong style="color:var(--success);"><?= intval($stats['resolved']) ?></strong>
            </div>
        </div>
    </div>

    <!-- Column 2: Recent Complaints -->
    <div class="card" style="display:flex; flex-direction:column; justify-content:space-between;">
        <div class="card-header-flex">
            <h3>Recent Complaints</h3>
            <a href="admin_complaints.php">View All</a>
        </div>
        
        <div class="recent-list" style="overflow-y:auto; max-height: 250px;">
            <?php if (empty($recentComplaints)): ?>
                <p style="color:var(--text-muted); text-align:center; padding:30px 0;">No active complaints.</p>
            <?php else: ?>
                <?php 
                $catIcons = [
                    'Water Supply' => 'fa-tint',
                    'Street Lights' => 'fa-lightbulb',
                    'Sanitation & Garbage' => 'fa-trash-alt',
                    'Roads & Drainage' => 'fa-road'
                ];
                $catColors = [
                    'Water Supply' => '#e3f2fd',
                    'Street Lights' => '#fff9c4',
                    'Sanitation & Garbage' => '#ffe0b2',
                    'Roads & Drainage' => '#f5f5f5'
                ];
                $iconColors = [
                    'Water Supply' => '#1976d2',
                    'Street Lights' => '#fbc02d',
                    'Sanitation & Garbage' => '#f57c00',
                    'Roads & Drainage' => '#616161'
                ];
                
                foreach ($recentComplaints as $comp): 
                    $catName = $comp['category_name'];
                    $icon = $catIcons[$catName] ?? 'fa-exclamation-triangle';
                    $bg = $catColors[$catName] ?? '#efebe9';
                    $icColor = $iconColors[$catName] ?? '#5d4037';
                ?>
                    <div class="recent-list-item">
                        <div class="recent-item-left">
                            <div class="recent-item-icon" style="background-color: <?= $bg ?>; color: <?= $icColor ?>;">
                                <i class="fas <?= $icon ?>"></i>
                            </div>
                            <div class="recent-item-info">
                                <h4 style="max-width:140px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($comp['title']) ?></h4>
                                <span>ID: <?= htmlspecialchars($comp['ticket_id']) ?> &bull; <?= date('d M Y', strtotime($comp['created_at'])) ?></span>
                            </div>
                        </div>
                        <div>
                            <span class="badge 
                                <?php 
                                    if ($comp['status_name'] == 'Pending') echo 'badge-pending';
                                    elseif ($comp['status_name'] == 'Assigned') echo 'badge-assigned';
                                    elseif ($comp['status_name'] == 'In Progress') echo 'badge-in_progress';
                                    elseif ($comp['status_name'] == 'Resolved') echo 'badge-resolved';
                                    else echo 'badge-rejected';
                                ?>
                            " style="font-size:0.7rem; padding:2px 8px;"><?= htmlspecialchars($comp['status_name']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Column 3: Citizen Feedback Ratings -->
    <div class="card" style="display:flex; flex-direction:column; justify-content:space-between;">
        <div class="card-header-flex">
            <h3>Citizen Feedback</h3>
        </div>
        
        <div class="bullet-list" style="overflow-y:auto; max-height: 250px;">
            <?php if (empty($feedbackLogs)): ?>
                <p style="color:var(--text-muted); text-align:center; padding:40px 0;">No ratings logged.</p>
            <?php else: ?>
                <?php foreach ($feedbackLogs as $feed): ?>
                    <div class="bullet-list-item" style="gap:10px;">
                        <div style="flex-grow:1;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                                <strong style="font-weight:600; color:var(--text-primary);"><?= htmlspecialchars($feed['citizen_name']) ?></strong>
                                <span style="color:var(--secondary); font-size:0.75rem;">
                                    <?php for ($i=1; $i<=5; $i++): ?>
                                        <i class="<?= $i <= $feed['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                    <?php endfor; ?>
                                </span>
                            </div>
                            <span style="font-size:0.75rem; color:var(--accent); font-weight:600;">ID: <?= htmlspecialchars($feed['ticket_id']) ?></span>
                            <p style="font-style:italic; margin-top:2px; font-size:0.8rem; color:var(--text-muted);">"<?= htmlspecialchars($feed['comments']) ?: 'No comments.' ?>"</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Lower Row: Quick Actions Left, Assigned Work Summary Right -->
<div class="dashboard-layout-grid" style="grid-template-columns: 1.5fr 1.5fr; gap: 20px;">
    
    <!-- Quick Actions Card -->
    <div class="card">
        <h3 style="font-size:0.95rem; font-weight:700; color:var(--primary-dark); margin-bottom:15px; border-bottom:1px solid #edf2f7; padding-bottom:8px;">Quick Actions</h3>
        <div class="quick-actions-grid">
            <div class="quick-action-box" onclick="window.location.href='superadmin_users.php'">
                <div class="icon-circle" style="background-color:#e8f5e9; color:var(--primary);"><i class="fas fa-users-cog"></i></div>
                <h4>Manage Users</h4>
            </div>
            <div class="quick-action-box" onclick="window.location.href='superadmin_settings.php'">
                <div class="icon-circle" style="background-color:#e3f2fd; color:var(--accent);"><i class="fas fa-sliders-h"></i></div>
                <h4>Portal Settings</h4>
            </div>
            <div class="quick-action-box" onclick="window.location.href='admin_complaints.php'">
                <div class="icon-circle" style="background-color:#fff3e0; color:var(--secondary);"><i class="fas fa-file-invoice"></i></div>
                <h4>Complaints Log</h4>
            </div>
            <div class="quick-action-box" onclick="window.location.href='admin_reports.php'">
                <div class="icon-circle" style="background-color:#f3e5f5; color:#7b1fa2;"><i class="fas fa-print"></i></div>
                <h4>View Reports</h4>
            </div>
        </div>
    </div>

    <!-- Assigned Work Summary Card -->
    <div class="card">
        <div class="card-header-flex" style="margin-bottom:15px; padding-bottom:8px;">
            <h3>Assigned Work Summary</h3>
            <a href="admin_complaints.php">View All</a>
        </div>
        
        <div class="assigned-work-grid">
            <!-- Box 1 -->
            <div class="assigned-work-box">
                <i class="far fa-file-alt" style="color:var(--accent);"></i>
                <div>
                    <h4>Total Assigned</h4>
                    <span class="value"><?= intval($assignedCount) ?></span>
                </div>
            </div>
            <!-- Box 2 -->
            <div class="assigned-work-box">
                <i class="fas fa-spinner fa-spin" style="color:var(--secondary); animation-duration:4s;"></i>
                <div>
                    <h4>In Progress</h4>
                    <span class="value"><?= intval($progressCount) ?></span>
                </div>
            </div>
            <!-- Box 3 -->
            <div class="assigned-work-box">
                <i class="far fa-check-circle" style="color:var(--success);"></i>
                <div>
                    <h4>Completed</h4>
                    <span class="value"><?= intval($completedCount) ?></span>
                </div>
            </div>
            <!-- Box 4 -->
            <div class="assigned-work-box">
                <i class="fas fa-exclamation-triangle" style="color:var(--error);"></i>
                <div>
                    <h4>Overdue</h4>
                    <span class="value"><?= intval($overdueCount) ?></span>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const total = <?= intval($stats['total']) ?>;
    const pending = <?= intval($stats['pending']) ?>;
    const progress = <?= intval($stats['in_progress']) ?>;
    const resolved = <?= intval($stats['resolved']) ?>;
    initDashboardCharts(total, pending, progress, resolved);
});
</script>
<script src="assets/js/chart-config.js"></script>

<?php
include 'includes/footer.php';
?>
