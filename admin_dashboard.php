<?php
// admin_dashboard.php
// Gram Panchayat Complaint Management System - Admin Dashboard

require_once 'includes/auth.php';
require_once 'includes/db.php';

// Enforce GP Admin or Super Admin Role
check_role(['gp_admin', 'super_admin']);

$userId = $_SESSION['user_id'];
$successMsg = null;
$errorMsg = null;

// Handle Complaint Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_complaint') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $complaintId = intval($_POST['complaint_id']);
    $officerId = intval($_POST['officer_id']);
    $adminRemarks = sanitize($_POST['admin_remarks']);
    
    if (empty($complaintId) || empty($officerId)) {
        $errorMsg = "Please select a field officer to assign.";
    } else {
        try {
            $offStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ? AND role_id = 3");
            $offStmt->execute([$officerId]);
            $officer = $offStmt->fetch();
            
            if ($officer) {
                $stmt = $pdo->prepare("UPDATE complaints SET assigned_officer_id = ?, status_id = 2, admin_remarks = ? WHERE id = ? AND status_id = 1");
                $stmt->execute([$officerId, $adminRemarks, $complaintId]);
                
                $compStmt = $pdo->prepare("SELECT ticket_id, citizen_id, title FROM complaints WHERE id = ?");
                $compStmt->execute([$complaintId]);
                $complaint = $compStmt->fetch();
                
                if ($complaint) {
                    $ticketId = $complaint['ticket_id'];
                    $citizenId = $complaint['citizen_id'];
                    
                    $notif1 = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    $notif1->execute([$citizenId, "Your grievance task (Ticket ID: $ticketId) has been assigned to Field Staff " . $officer['full_name'] . " for maintenance."]);
                    
                    $notif2 = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    $notif2->execute([$officerId, "You have been assigned a new task: " . $complaint['title'] . " (Ticket ID: $ticketId)"]);
                }
                
                $successMsg = "Complaint successfully assigned to " . htmlspecialchars($officer['full_name']);
            } else {
                $errorMsg = "Invalid Field Officer selected.";
            }
        } catch (PDOException $e) {
            $errorMsg = "Database error: " . $e->getMessage();
        }
    }
}

// Handle Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_work') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $complaintId = intval($_POST['complaint_id']);
    $decision = sanitize($_POST['decision']);
    $adminRemarks = sanitize($_POST['admin_remarks']);
    
    if (empty($complaintId) || empty($decision)) {
        $errorMsg = "Missing verification parameter.";
    } else {
        try {
            $compStmt = $pdo->prepare("SELECT ticket_id, citizen_id, assigned_officer_id FROM complaints WHERE id = ?");
            $compStmt->execute([$complaintId]);
            $complaint = $compStmt->fetch();
            
            if ($complaint) {
                $ticketId = $complaint['ticket_id'];
                $citizenId = $complaint['citizen_id'];
                $officerId = $complaint['assigned_officer_id'];
                
                if ($decision === 'resolve') {
                    $stmt = $pdo->prepare("UPDATE complaints SET status_id = 4, admin_remarks = ? WHERE id = ?");
                    $stmt->execute([$adminRemarks, $complaintId]);
                    
                    $n1 = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    $n1->execute([$citizenId, "Grievance solved! Ticket ID $ticketId was verified and marked as Resolved. Please provide your feedback rating."]);
                    
                    if ($officerId) {
                        $n2 = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                        $n2->execute([$officerId, "Good job! The work on Ticket ID $ticketId was verified and marked as Resolved by the GP Admin."]);
                    }
                    $successMsg = "Work verified. Ticket ID $ticketId marked as Resolved.";
                } elseif ($decision === 'reopen') {
                    $stmt = $pdo->prepare("UPDATE complaints SET status_id = 3, admin_remarks = ? WHERE id = ?");
                    $stmt->execute([$adminRemarks, $complaintId]);
                    
                    if ($officerId) {
                        $n2 = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                        $n2->execute([$officerId, "Task reopened: GP Admin requested revisions on Ticket ID $ticketId. Remarks: $adminRemarks"]);
                    }
                    $successMsg = "Ticket reopened. Re-evaluation request sent back to the assigned field officer.";
                }
            }
        } catch (PDOException $e) {
            $errorMsg = "Database error: " . $e->getMessage();
        }
    }
}

// 1. Fetch Summary Statistics
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

// 2. Fetch Recent Complaints (Last 5)
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

// 3. Fetch Notification alerts for Admin
try {
    $adminNotifications = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 4
    ");
    $adminNotifications->execute([$userId]);
    $logs = $adminNotifications->fetchAll();
} catch (PDOException $e) {
    $logs = [];
}

// 4. Fetch Assigned Work Summary stats
$assignedCount = 0;
$progressCount = 0;
$completedCount = 0;
$overdueCount = 0;
try {
    $assignedCount = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status_id = 2")->fetchColumn();
    $progressCount = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status_id = 3")->fetchColumn();
    $completedCount = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status_id = 4")->fetchColumn();
    // Overdue is mock or complaints older than 5 days
    $overdueCount = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status_id IN (1,2,3) AND created_at < DATE_SUB(NOW(), INTERVAL 5 DAY)")->fetchColumn();
} catch (PDOException $e) {
    // Fail silent
}

// 5. Fetch Field Officers for assignment dropdown
try {
    $officers = $pdo->query("SELECT id, full_name FROM users WHERE role_id = 3 ORDER BY full_name ASC")->fetchAll();
} catch (PDOException $e) {
    $officers = [];
}

include 'includes/header.php';
?>

<!-- Page Header Title -->
<div class="page-header">
    <div>
        <h2>Dashboard</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 3px;">Welcome back, Gram Sevak Office!</p>
    </div>
</div>

<!-- Alert Notifications -->
<?php if ($successMsg): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMsg) ?>
    </div>
<?php endif; ?>

<?php if ($errorMsg): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMsg) ?>
    </div>
<?php endif; ?>

<!-- Summary Cards Grid matching template -->
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
    
    <!-- Card 2: Pending Complaints -->
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

<!-- Main Dashboard Layout Grid (3 Columns) -->
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

    <!-- Column 2: Recent Complaints List -->
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

    <!-- Column 3: Notifications Feed -->
    <div class="card" style="display:flex; flex-direction:column; justify-content:space-between;">
        <div class="card-header-flex">
            <h3>Notifications</h3>
            <a href="notifications.php">View All</a>
        </div>
        
        <div class="bullet-list" style="overflow-y:auto; max-height:250px;">
            <?php if (empty($logs)): ?>
                <p style="color:var(--text-muted); text-align:center; padding:30px 0;">No updates.</p>
            <?php else: ?>
                <?php 
                $bulletColors = ['#f57c00', '#2e7d32', '#d32f2f', '#1976d2'];
                foreach ($logs as $idx => $log): 
                    $bulletColor = $bulletColors[$idx % count($bulletColors)];
                ?>
                    <div class="bullet-list-item">
                        <div class="bullet-dot" style="background-color: <?= $bulletColor ?>;"></div>
                        <div class="bullet-content">
                            <h4><?= htmlspecialchars($log['message']) ?></h4>
                            <span><?= date('d M Y, h:i A', strtotime($log['created_at'])) ?></span>
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
            <div class="quick-action-box" onclick="window.location.href='admin_complaints.php'">
                <div class="icon-circle" style="background-color:#e8f5e9; color:var(--primary);"><i class="fas fa-edit"></i></div>
                <h4>Complaints Log</h4>
            </div>
            <div class="quick-action-box" onclick="window.location.href='admin_categories.php'">
                <div class="icon-circle" style="background-color:#e3f2fd; color:var(--accent);"><i class="fas fa-tags"></i></div>
                <h4>Categories</h4>
            </div>
            <div class="quick-action-box" onclick="window.location.href='admin_reports.php'">
                <div class="icon-circle" style="background-color:#fff3e0; color:var(--secondary);"><i class="fas fa-folder"></i></div>
                <h4>View Reports</h4>
            </div>
            <div class="quick-action-box" onclick="window.location.href='notifications.php'">
                <div class="icon-circle" style="background-color:#f3e5f5; color:#7b1fa2;"><i class="fas fa-bullhorn"></i></div>
                <h4>System Logs</h4>
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
    // Render the Dashboard Line Chart
    const total = <?= intval($stats['total']) ?>;
    const pending = <?= intval($stats['pending']) ?>;
    const progress = <?= intval($stats['in_progress']) ?>;
    const resolved = <?= intval($stats['resolved']) ?>;
    initDashboardCharts(total, pending, progress, resolved);
});
</script>
<script src="assets/js/chart-config.js"></script>

<?php
// Include Footer Layout
include 'includes/footer.php';
?>
