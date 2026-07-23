<?php
// profile.php
// Gram Panchayat Complaint Management System - Profile Page

require_once 'includes/auth.php';
require_once 'includes/db.php';

require_login();

$userId = $_SESSION['user_id'];
$successMsg = null;
$errorMsg = null;

// Handle Contact Info Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $email = trim(sanitize($_POST['email']));
    $phone = trim(sanitize($_POST['phone']));
    
    if (empty($email) || empty($phone)) {
        $errorMsg = "Email and Phone fields cannot be empty.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = "Please enter a valid email address.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET email = ?, phone = ? WHERE id = ?");
            $stmt->execute([$email, $phone, $userId]);
            $successMsg = "Contact information updated successfully.";
        } catch (PDOException $e) {
            $errorMsg = "Failed to update profile: " . $e->getMessage();
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $errorMsg = "All password fields are required.";
    } elseif ($newPassword !== $confirmPassword) {
        $errorMsg = "New passwords do not match.";
    } elseif (strlen($newPassword) < 6) {
        $errorMsg = "New password must be at least 6 characters.";
    } else {
        try {
            // Verify current password first
            $checkStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $checkStmt->execute([$userId]);
            $hash = $checkStmt->fetchColumn();
            
            if (password_verify($currentPassword, $hash)) {
                // Update password
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$newHash, $userId]);
                $successMsg = "Password changed successfully.";
            } else {
                $errorMsg = "Incorrect current password. Please try again.";
            }
        } catch (PDOException $e) {
            $errorMsg = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch Fresh User Details
try {
    $stmt = $pdo->prepare("
        SELECT u.*, r.display_name AS role_display, r.role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $user = [];
}

include 'includes/header.php';
?>

<!-- Page Header Title -->
<div class="page-header">
    <div>
        <h2>My User Profile</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 3px;">Manage your account credentials and contact parameters.</p>
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

<!-- Profile Layout Grid -->
<div class="dashboard-grid-2" style="grid-template-columns: 1fr 2fr;">
    
    <!-- User Credentials Summary -->
    <div class="card" style="text-align: center; align-self: start;">
        <div style="width: 80px; height: 80px; border-radius: 50%; background-color: var(--primary); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:2rem; font-weight:700; margin: 15px auto; border:3px solid var(--border-color); box-shadow:var(--shadow-sm);">
            <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
        </div>
        
        <h3 style="margin-bottom:5px; color: var(--primary-dark);"><?= htmlspecialchars($user['full_name']) ?></h3>
        <span class="badge badge-assigned" style="margin-bottom: 20px;"><?= htmlspecialchars($user['role_display']) ?></span>
        
        <div style="text-align: left; border-top:1px dashed var(--border-color); padding-top:20px; font-size:0.9rem; line-height: 1.8;">
            <div><strong>Username:</strong> <span style="float:right; color:var(--text-muted);"><?= htmlspecialchars($user['username']) ?></span></div>
            <div><strong>Account ID:</strong> <span style="float:right; color:var(--text-muted);">#<?= $user['id'] ?></span></div>
            <div><strong>Member Since:</strong> <span style="float:right; color:var(--text-muted);"><?= date('d M Y', strtotime($user['created_at'])) ?></span></div>
        </div>
    </div>

    <!-- Forms Column -->
    <div style="display:flex; flex-direction:column; gap:25px;">
        
        <!-- Contact Details Form -->
        <div class="card">
            <h3 style="margin-bottom:15px; color: var(--primary-dark); border-bottom: 1px solid var(--border-color); padding-bottom:8px;">Update Contact Details</h3>
            <form action="profile.php" method="POST" class="form-grid-2">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" name="phone" id="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" required pattern="[0-9]{10,12}">
                </div>
                
                <div style="grid-column: 1 / -1; text-align:right;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Save Details</button>
                </div>
            </form>
        </div>

        <!-- Change Password Form -->
        <div class="card">
            <h3 style="margin-bottom:15px; color: var(--primary-dark); border-bottom: 1px solid var(--border-color); padding-bottom:8px;">Securely Change Password</h3>
            <form action="profile.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label for="current_password">Current Password *</label>
                    <input type="password" name="current_password" id="current_password" class="form-control" required>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="new_password">New Password *</label>
                        <input type="password" name="new_password" id="new_password" class="form-control" required minlength="6" placeholder="Min 6 characters">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password *</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                    </div>
                </div>

                <div style="text-align:right; margin-top:15px;">
                    <button type="submit" class="btn btn-secondary"><i class="fas fa-key"></i> Update Password</button>
                </div>
            </form>
        </div>
        
    </div>
</div>

<?php
include 'includes/footer.php';
?>
