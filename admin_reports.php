<?php
// admin_reports.php
// Gram Panchayat Complaint Management System - Reports Panel

require_once 'includes/auth.php';
require_once 'includes/db.php';

// Enforce GP Admin or Super Admin Role
check_role(['gp_admin', 'super_admin']);

// Filtering parameters
$startDate = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : '';
$statusFilter = isset($_GET['status']) ? intval($_GET['status']) : 0;
$categoryFilter = isset($_GET['category']) ? intval($_GET['category']) : 0;

$whereClauses = [];
$queryParams = [];

if (!empty($startDate)) {
    $whereClauses[] = "DATE(c.created_at) >= ?";
    $queryParams[] = $startDate;
}
if (!empty($endDate)) {
    $whereClauses[] = "DATE(c.created_at) <= ?";
    $queryParams[] = $endDate;
}
if ($statusFilter > 0) {
    $whereClauses[] = "c.status_id = ?";
    $queryParams[] = $statusFilter;
}
if ($categoryFilter > 0) {
    $whereClauses[] = "c.category_id = ?";
    $queryParams[] = $categoryFilter;
}

$whereSQL = "";
if (count($whereClauses) > 0) {
    $whereSQL = " WHERE " . implode(" AND ", $whereClauses);
}

try {
    // 1. Fetch matching complaints
    $reportQuery = "
        SELECT c.*, cs.display_name AS status_name, cc.category_name, u.full_name AS citizen_name, fo.full_name AS officer_name
        FROM complaints c
        JOIN complaint_statuses cs ON c.status_id = cs.id
        JOIN complaint_categories cc ON c.category_id = cc.id
        JOIN users u ON c.citizen_id = u.id
        LEFT JOIN users fo ON c.assigned_officer_id = fo.id
        " . $whereSQL . "
        ORDER BY c.created_at DESC
    ";
    $stmt = $pdo->prepare($reportQuery);
    $stmt->execute($queryParams);
    $records = $stmt->fetchAll();
    
    // 2. Compute statistics for the matching set
    $stats = [
        'total' => count($records),
        'pending' => 0,
        'assigned' => 0,
        'inprogress' => 0,
        'resolved' => 0,
        'rejected' => 0
    ];
    
    foreach ($records as $r) {
        if ($r['status_name'] === 'Pending') $stats['pending']++;
        elseif ($r['status_name'] === 'Assigned') $stats['assigned']++;
        elseif ($r['status_name'] === 'In Progress') $stats['inprogress']++;
        elseif ($r['status_name'] === 'Resolved') $stats['resolved']++;
        elseif ($r['status_name'] === 'Rejected') $stats['rejected']++;
    }
    
} catch (PDOException $e) {
    $records = [];
    $stats = ['total' => 0, 'pending' => 0, 'assigned' => 0, 'inprogress' => 0, 'resolved' => 0, 'rejected' => 0];
    $errorMsg = "Database error: " . $e->getMessage();
}

// Fetch Filter dropdown data
try {
    $statuses = $pdo->query("SELECT * FROM complaint_statuses ORDER BY id ASC")->fetchAll();
    $categories = $pdo->query("SELECT * FROM complaint_categories ORDER BY category_name ASC")->fetchAll();
} catch (PDOException $e) {
    $statuses = [];
    $categories = [];
}

include 'includes/header.php';
?>

<!-- Print-Only Style Overlay -->
<style>
@media print {
    /* Hide dashboard wrappers, sidebar, header, filters, and footer */
    .sidebar, .main-header, .no-print, footer, .page-header button {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    .content-body {
        padding: 0 !important;
    }
    .card, .table-card {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    body {
        background-color: #ffffff !important;
        color: #000000 !important;
        font-size: 12px !important;
    }
    .table th {
        background-color: #eee !important;
        color: #000 !important;
        border-bottom: 2px solid #333 !important;
    }
    .table td, .table th {
        padding: 8px 12px !important;
        border-bottom: 1px solid #ddd !important;
    }
    .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 30px;
        border-bottom: 3px double #333;
        padding-bottom: 10px;
    }
}
.print-header {
    display: none;
}
</style>

<!-- Print Header Signature (Visible only when printing) -->
<div class="print-header">
    <h2 style="margin-bottom:5px; color: var(--primary-dark);">Gram Panchayat Grievance Redressal Portal</h2>
    <h3 style="margin-bottom:10px; color: var(--text-muted);">OFFICIAL GPCMS SYSTEM REPORT</h3>
    <p style="font-size:0.9rem;">
        Generated On: <?= date('d M Y, h:i A') ?> | 
        Report Period: <?= empty($startDate) ? 'Inception' : date('d/m/Y', strtotime($startDate)) ?> to <?= empty($endDate) ? 'Present' : date('d/m/Y', strtotime($endDate)) ?>
    </p>
</div>

<!-- Page Title Block -->
<div class="page-header">
    <div>
        <h2>System Reports & Analytics</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 3px;">Filter complaints, review analytics, and generate printable reports.</p>
    </div>
    
    <button class="btn btn-secondary no-print" onclick="window.print()"><i class="fas fa-print"></i> Print Report</button>
</div>

<!-- Filters Card (Hidden in print) -->
<div class="card no-print" style="margin-bottom: 25px;">
    <h3 style="font-size: 1rem; color: var(--primary-dark); margin-bottom: 15px; border-bottom: 1px dashed var(--border-color); padding-bottom: 8px;">Report Filtering Parameters</h3>
    <form action="admin_reports.php" method="GET" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: flex-end;">
        <div class="form-group" style="margin-bottom:0;">
            <label for="start_date" style="font-size:0.8rem; font-weight:600;">START DATE</label>
            <input type="date" name="start_date" id="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label for="end_date" style="font-size:0.8rem; font-weight:600;">END DATE</label>
            <input type="date" name="end_date" id="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label for="status" style="font-size:0.8rem; font-weight:600;">STATUS</label>
            <select name="status" id="status" class="form-control">
                <option value="0">All Statuses</option>
                <?php foreach ($statuses as $st): ?>
                    <option value="<?= $st['id'] ?>" <?= $statusFilter === $st['id'] ? 'selected' : '' ?>><?= htmlspecialchars($st['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom:0;">
            <label for="category" style="font-size:0.8rem; font-weight:600;">CATEGORY</label>
            <select name="category" id="category" class="form-control">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoryFilter === $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn btn-primary" style="height:38px; flex-grow:1;"><i class="fas fa-search"></i> Generate</button>
            <a href="admin_reports.php" class="btn btn-outline" style="height:38px;"><i class="fas fa-undo"></i> Reset</a>
        </div>
    </form>
</div>

<!-- Matching Statistics Panel -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin-bottom: 25px;">
    <div class="stat-card" style="padding:15px; border-left: 4px solid var(--accent);">
        <div class="stat-info">
            <h3 style="font-size:0.75rem;">Total Matching</h3>
            <div class="stat-value" style="font-size:1.6rem;"><?= $stats['total'] ?></div>
        </div>
    </div>
    
    <div class="stat-card" style="padding:15px; border-left: 4px solid var(--warning);">
        <div class="stat-info">
            <h3 style="font-size:0.75rem;">Pending</h3>
            <div class="stat-value" style="font-size:1.6rem;"><?= $stats['pending'] ?></div>
        </div>
    </div>

    <div class="stat-card" style="padding:15px; border-left: 4px solid var(--info);">
        <div class="stat-info">
            <h3 style="font-size:0.75rem;">In Progress</h3>
            <div class="stat-value" style="font-size:1.6rem;"><?= ($stats['assigned'] + $stats['inprogress']) ?></div>
        </div>
    </div>

    <div class="stat-card" style="padding:15px; border-left: 4px solid var(--success);">
        <div class="stat-info">
            <h3 style="font-size:0.75rem;">Resolved</h3>
            <div class="stat-value" style="font-size:1.6rem;"><?= $stats['resolved'] ?></div>
        </div>
    </div>
</div>

<!-- Results Card -->
<div class="table-card">
    <div class="table-card-header no-print">
        <h3>Report Results Log</h3>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Ticket ID</th>
                    <th>Citizen Name</th>
                    <th>Grievance Title</th>
                    <th>Category</th>
                    <th>Submitted On</th>
                    <th>Assigned Staff</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 30px; color: var(--text-muted);">
                            No complaint records match the specified filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $rec): ?>
                        <tr>
                            <td style="font-weight: 600; color: var(--accent);"><?= htmlspecialchars($rec['ticket_id']) ?></td>
                            <td><?= htmlspecialchars($rec['citizen_name']) ?></td>
                            <td><?= htmlspecialchars($rec['title']) ?></td>
                            <td><?= htmlspecialchars($rec['category_name']) ?></td>
                            <td><?= date('d/m/Y', strtotime($rec['created_at'])) ?></td>
                            <td><?= $rec['officer_name'] ? htmlspecialchars($rec['officer_name']) : 'Unassigned' ?></td>
                            <td>
                                <span class="badge 
                                    <?php 
                                        if ($rec['status_name'] == 'Pending') echo 'badge-pending';
                                        elseif ($rec['status_name'] == 'Assigned') echo 'badge-assigned';
                                        elseif ($rec['status_name'] == 'In Progress') echo 'badge-in_progress';
                                        elseif ($rec['status_name'] == 'Resolved') echo 'badge-resolved';
                                        else echo 'badge-rejected';
                                    ?>
                                ">
                                    <?= htmlspecialchars($rec['status_name']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
include 'includes/footer.php';
?>
