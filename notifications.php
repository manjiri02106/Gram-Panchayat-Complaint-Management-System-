<?php
// notifications.php
// Gram Panchayat Complaint Management System - Notifications Hub

require_once 'includes/auth.php';
require_once 'includes/db.php';

require_login();

$userId = $_SESSION['user_id'];
$successMsg = null;

// Handle Mark All as Read Request
if (isset($_GET['mark_all'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
        $successMsg = "All notifications marked as read.";
    } catch (PDOException $e) {
        // fail silently
    }
}

// Fetch All Notifications for the User
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    $notifications = [];
}

include 'includes/header.php';
?>

<!-- Page Header Title -->
<div class="page-header">
    <div>
        <h2>Notifications Hub</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 3px;">Audited timeline of system events, assignments, and resolution progress.</p>
    </div>
    
    <?php if (!empty($notifications)): ?>
        <a href="notifications.php?mark_all=1" class="btn btn-outline btn-sm"><i class="fas fa-check-double"></i> Mark All as Read</a>
    <?php endif; ?>
</div>

<!-- Alert Notifications -->
<?php if ($successMsg): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMsg) ?>
    </div>
<?php endif; ?>

<!-- Notifications List -->
<div class="card" style="padding: 0;">
    <div style="padding: 20px; border-bottom: 1px solid var(--border-color);">
        <h3 style="font-size: 1rem; color: var(--primary-dark); margin:0;">Inbox Updates</h3>
    </div>
    
    <div>
        <?php if (empty($notifications)): ?>
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                <i class="far fa-bell-slash" style="font-size: 3rem; display:block; margin-bottom: 15px;"></i>
                You do not have any notifications in your inbox.
            </div>
        <?php else: ?>
            <ul style="list-style: none;">
                <?php foreach ($notifications as $notif): ?>
                    <li style="padding: 20px; border-bottom: 1px solid var(--border-color); display:flex; gap:20px; align-items:flex-start; <?= $notif['is_read'] == 0 ? 'background-color: rgba(46, 125, 50, 0.03);' : '' ?>">
                        <div style="width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; background-color: <?= $notif['is_read'] == 0 ? 'rgba(245, 124, 0, 0.1)' : '#eee' ?>; color: <?= $notif['is_read'] == 0 ? 'var(--secondary)' : '#666' ?>;">
                            <?php if ($notif['is_read'] == 0): ?>
                                <i class="fas fa-envelope-open-text"></i>
                            <?php else: ?>
                                <i class="far fa-envelope"></i>
                            <?php endif; ?>
                        </div>
                        <div style="flex-grow:1;">
                            <p style="font-size: 0.95rem; margin-bottom: 5px; color: var(--text-primary); <?= $notif['is_read'] == 0 ? 'font-weight: 500;' : '' ?>">
                                <?= htmlspecialchars($notif['message']) ?>
                            </p>
                            <span style="font-size:0.8rem; color: var(--text-muted);">
                                <i class="far fa-clock"></i> <?= date('d M Y, h:i A', strtotime($notif['created_at'])) ?>
                            </span>
                        </div>
                        <?php if ($notif['is_read'] == 0): ?>
                            <span class="badge badge-pending">New</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<?php
include 'includes/footer.php';
?>
