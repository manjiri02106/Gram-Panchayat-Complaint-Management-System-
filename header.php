<?php
// includes/header.php
// Gram Panchayat Complaint Management System - Shared Dashboard Header Template

require_once 'auth.php';
require_once 'db.php';

// Enforce login for dashboard pages
require_login();

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$fullName = $_SESSION['full_name'];
$roleName = $_SESSION['role_name'];
$roleDisplay = $_SESSION['role_display'];

// Fetch unread notifications count
$notifCountStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$notifCountStmt->execute([$userId]);
$unreadCount = $notifCountStmt->fetchColumn();

// Fetch latest 5 notifications
$notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$notifStmt->execute([$userId]);
$recentNotifications = $notifStmt->fetchAll();

// Get initials of user for avatar
$nameParts = explode(' ', $fullName);
$initials = '';
if (count($nameParts) > 0) {
    $initials .= strtoupper(substr($nameParts[0], 0, 1));
}
if (count($nameParts) > 1) {
    $initials .= strtoupper(substr($nameParts[count($nameParts) - 1], 0, 1));
}
if (empty($initials)) {
    $initials = 'GP';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gram Panchayat Complaint Management System</title>
    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/style.css?v=1.0.2">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=1.0.2">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar Navigation -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content Area -->
        <main class="main-content">
            <!-- Header Nav matching the template -->
            <header class="main-header">
                <div class="header-left">
                    <span id="sidebar-toggle" class="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </span>
                </div>

                <!-- Global search bar in center -->
                <div class="header-search">
                    <input type="text" id="table-search" class="form-control" placeholder="Search here...">
                    <i class="fas fa-search"></i>
                </div>

                <div class="header-right">
                    <!-- Datetime holder with Calendar Icon -->
                    <div id="live-time" class="header-datetime">
                        <!-- Loaded dynamically via javascript -->
                    </div>

                    <!-- Notifications Dropdown -->
                    <div class="header-notifications" id="notif-trigger">
                        <span class="notification-icon">
                            <i class="far fa-bell"></i>
                            <?php if ($unreadCount > 0): ?>
                                <span class="notification-badge"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </span>
                        
                        <div class="notification-dropdown" id="notif-dropdown">
                            <div class="notif-header">
                                <h4>Notifications</h4>
                                <?php if ($unreadCount > 0): ?>
                                    <span class="badge badge-pending"><?= $unreadCount ?> New</span>
                                <?php endif; ?>
                            </div>
                            <div class="notif-body">
                                <?php if (empty($recentNotifications)): ?>
                                    <div class="notif-empty">No notifications yet.</div>
                                <?php else: ?>
                                    <?php foreach ($recentNotifications as $notif): ?>
                                        <a href="#" class="notif-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>">
                                            <p><?= htmlspecialchars($notif['message']) ?></p>
                                            <span class="notif-time">
                                                <i class="far fa-clock"></i> 
                                                <?= date('d M Y, h:i A', strtotime($notif['created_at'])) ?>
                                            </span>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="notif-header" style="border-top: 1px solid var(--border-color); border-bottom: none; justify-content: center; padding: 8px;">
                                <a href="notifications.php" style="font-size: 0.8rem; color: var(--accent); font-weight: 500;">View All Notifications</a>
                            </div>
                        </div>
                    </div>

                    <!-- User Profile Info -->
                    <div class="header-user-menu" id="profile-trigger">
                        <div class="user-profile-trigger">
                            <div class="user-avatar"><?= $initials ?></div>
                            <div class="user-info" style="display: block; line-height: 1.2; text-align: left;">
                                <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-primary); max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($fullName) ?></div>
                                <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;"><?= htmlspecialchars($roleDisplay) ?></span>
                            </div>
                            <i class="fas fa-chevron-down" style="font-size: 0.75rem; color: var(--text-muted)"></i>
                        </div>
                        
                        <div class="profile-dropdown" id="profile-dropdown">
                            <div style="padding: 12px 16px; border-bottom: 1px solid var(--border-color);">
                                <strong style="font-size: 0.85rem; color: var(--text-primary); display:block;"><?= htmlspecialchars($fullName) ?></strong>
                                <span style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($username) ?></span>
                            </div>
                            <a href="profile.php" class="profile-dropdown-link">
                                <i class="far fa-user"></i> My Profile
                            </a>
                            <a href="logout.php" class="profile-dropdown-link border-top" style="color: var(--error)">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>

                    <!-- Separate Logout Icon Button on Far Right -->
                    <a href="logout.php" class="header-logout-btn" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </header>
            
            <!-- Dashboard Content Main Body Container -->
            <div class="content-body">
