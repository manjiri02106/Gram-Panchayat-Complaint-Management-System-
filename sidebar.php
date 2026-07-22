<?php
// includes/sidebar.php
// Gram Panchayat Complaint Management System - Sidebar Navigation Template

$current_page = basename($_SERVER['PHP_SELF']);
$roleName = $_SESSION['role_name'] ?? '';
$roleDisplay = $_SESSION['role_display'] ?? '';
$fullName = $_SESSION['full_name'] ?? '';

// Fetch notification count for the badge (role notifications count)
$badgeCount = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $badgeCountStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $badgeCountStmt->execute([$_SESSION['user_id']]);
        $badgeCount = $badgeCountStmt->fetchColumn();
    } catch (PDOException $e) {
        $badgeCount = 0;
    }
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-top">
            <!-- Custom Inline SVG emblem for Panchayat Logo -->
            <svg viewBox="0 0 100 100" width="38" height="38" style="flex-shrink:0;">
                <circle cx="50" cy="50" r="46" fill="#ffffff" stroke="#1e5624" stroke-width="4"/>
                <circle cx="50" cy="50" r="41" fill="none" stroke="#f57c00" stroke-width="2" stroke-dasharray="2,2"/>
                <!-- Tree Trunk -->
                <path d="M47,75 L53,75 L53,55 L47,55 Z" fill="#5d4037"/>
                <!-- Tree Leaves -->
                <circle cx="50" cy="42" r="18" fill="#1e5624"/>
                <circle cx="38" cy="48" r="12" fill="#2e7d32"/>
                <circle cx="62" cy="48" r="12" fill="#2e7d32"/>
                <!-- Ground -->
                <path d="M20,70 Q50,60 80,70 L80,85 L20,85 Z" fill="#81c784"/>
            </svg>
            <h2>GPCMS<span>Gram Panchayat</span></h2>
        </div>
        <div class="sidebar-slogan">Your Voice, Our Responsibility</div>
    </div>
    
    <ul class="sidebar-menu">
        <!-- Dashboard Link (dynamic route) -->
        <li>
            <?php
            $dashLink = 'citizen_dashboard.php';
            if ($roleName == 'super_admin') $dashLink = 'superadmin_dashboard.php';
            elseif ($roleName == 'gp_admin') $dashLink = 'admin_dashboard.php';
            elseif ($roleName == 'field_officer') $dashLink = 'officer_dashboard.php';
            ?>
            <a href="<?= $dashLink ?>" class="sidebar-link <?= ($current_page == 'citizen_dashboard.php' || $current_page == 'admin_dashboard.php' || $current_page == 'officer_dashboard.php' || $current_page == 'superadmin_dashboard.php') ? 'active' : '' ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </li>
        
        <!-- Shared GP Admin & Super Admin Links -->
        <?php if ($roleName == 'gp_admin' || $roleName == 'super_admin'): ?>
            <li>
                <a href="admin_complaints.php" class="sidebar-link <?= ($current_page == 'admin_complaints.php') ? 'active' : '' ?>">
                    <i class="far fa-file-alt"></i> Complaints
                </a>
            </li>
            <li>
                <a href="admin_categories.php" class="sidebar-link <?= ($current_page == 'admin_categories.php') ? 'active' : '' ?>">
                    <i class="fas fa-th-large"></i> Complaint Categories
                </a>
            </li>
        <?php endif; ?>

        <!-- Field Officer Assigned Work Link -->
        <?php if ($roleName == 'field_officer'): ?>
            <li>
                <a href="officer_dashboard.php#assigned-section" class="sidebar-link">
                    <i class="fas fa-user-edit"></i> Assigned Work
                </a>
            </li>
        <?php endif; ?>

        <!-- Citizen Specific Links -->
        <?php if ($roleName == 'citizen'): ?>
            <li>
                <a href="citizen_dashboard.php#register-section" class="sidebar-link">
                    <i class="fas fa-edit"></i> Register Complaint
                </a>
            </li>
            <li>
                <a href="citizen_dashboard.php#history-section" class="sidebar-link">
                    <i class="far fa-list-alt"></i> My Complaints
                </a>
            </li>
        <?php endif; ?>
        
        <!-- Shared GP Admin & Super Admin Reports -->
        <?php if ($roleName == 'gp_admin' || $roleName == 'super_admin'): ?>
            <li>
                <a href="admin_reports.php" class="sidebar-link <?= ($current_page == 'admin_reports.php') ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
        <?php endif; ?>

        <!-- Super Admin specific Links -->
        <?php if ($roleName == 'super_admin'): ?>
            <li>
                <a href="superadmin_users.php" class="sidebar-link <?= ($current_page == 'superadmin_users.php') ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> Manage Users
                </a>
            </li>
            <li>
                <a href="superadmin_settings.php" class="sidebar-link <?= ($current_page == 'superadmin_settings.php') ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i> System Settings
                </a>
            </li>
        <?php endif; ?>
        
        <!-- Common Links (All authenticated users) -->
        <li>
            <a href="notifications.php" class="sidebar-link <?= ($current_page == 'notifications.php') ? 'active' : '' ?>">
                <i class="far fa-bell"></i> Notifications
                <?php if ($badgeCount > 0): ?>
                    <span class="notification-badge" style="position:static; margin-left:auto; width:18px; height:18px; font-size:0.65rem; background-color: var(--secondary);"><?= $badgeCount ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="profile.php" class="sidebar-link <?= ($current_page == 'profile.php') ? 'active' : '' ?>">
                <i class="far fa-user-circle"></i> Profile
            </a>
        </li>
    </ul>

    <!-- Beautiful Sidebar Village Backdrop SVG vector -->
    <div class="sidebar-village-backdrop">
        <svg viewBox="0 0 260 120" width="100%" height="100%" preserveAspectRatio="none">
            <!-- sky background shade -->
            <rect width="260" height="120" fill="#1b5e20" opacity="0.08"/>
            <!-- rolling hills -->
            <path d="M-10,120 Q60,65 130,90 Q200,115 270,75 L270,120 L-10,120 Z" fill="#144d18" opacity="0.4"/>
            <path d="M-10,120 Q50,85 110,100 Q170,115 270,90 L270,120 L-10,120 Z" fill="#0f3c12" opacity="0.6"/>
            <path d="M-10,120 Q80,90 160,105 Q220,95 270,110 L270,120 L-10,120 Z" fill="#0b2e0e"/>
            
            <!-- Cottages -->
            <!-- Cottage 1 -->
            <rect x="25" y="96" width="18" height="12" fill="#d7ccc8"/>
            <polygon points="22,96 34,88 46,96" fill="#d84315"/>
            <rect x="32" y="101" width="4" height="7" fill="#3e2723"/>
            <!-- Cottage 2 -->
            <rect x="52" y="99" width="15" height="10" fill="#efebe9"/>
            <polygon points="49,99 59.5,92 70,99" fill="#c2185b"/>
            <rect x="58" y="103" width="3" height="6" fill="#3e2723"/>
            
            <!-- Vector Trees -->
            <path d="M115,115 L115,96" stroke="#4e342e" stroke-width="2"/>
            <circle cx="115" cy="92" r="7" fill="#2e7d32"/>
            <circle cx="111" cy="94" r="5" fill="#388e3c"/>
            
            <path d="M130,118 L130,100" stroke="#4e342e" stroke-width="2"/>
            <circle cx="130" cy="95" r="6" fill="#1b5e20"/>
        </svg>
    </div>

    <!-- Pinned Sidebar Footer Logout Button at the absolute end -->
    <div class="sidebar-footer" style="padding: 15px 12px; border-top: 1px dashed rgba(255,255,255,0.15); z-index: 10; background-color: var(--primary-dark);">
        <a href="#" class="sidebar-link sidebar-logout" onclick="openModal('logout-confirm-modal'); return false;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>
