<?php
// superadmin_users.php
// Gram Panchayat Complaint Management System - Super Admin User Management Page

require_once 'includes/auth.php';
require_once 'includes/db.php';

// Enforce Super Admin Role
check_role('super_admin');

$successMsg = null;
$errorMsg = null;

// Handle Add User Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $fullName = trim(sanitize($_POST['full_name']));
    $username = trim(sanitize($_POST['username']));
    $email = trim(sanitize($_POST['email']));
    $phone = trim(sanitize($_POST['phone']));
    $roleId = intval($_POST['role_id']);
    $password = $_POST['password'];
    
    if (empty($fullName) || empty($username) || empty($email) || empty($phone) || empty($roleId) || empty($password)) {
        $errorMsg = "All fields are required.";
    } elseif (strlen($password) < 6) {
        $errorMsg = "Password must be at least 6 characters.";
    } else {
        try {
            // Check duplicate username
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $checkStmt->execute([$username]);
            if ($checkStmt->fetch()) {
                $errorMsg = "Username is already taken.";
            } else {
                $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password, full_name, email, phone, role_id) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$username, $hashedPass, $fullName, $email, $phone, $roleId]);
                $successMsg = "User account created successfully.";
            }
        } catch (PDOException $e) {
            $errorMsg = "Database error: " . $e->getMessage();
        }
    }
}

// Handle Update User Role Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user_role') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $targetUserId = intval($_POST['user_id']);
    $roleId = intval($_POST['role_id']);
    
    if (empty($targetUserId) || empty($roleId)) {
        $errorMsg = "User ID and Role are required.";
    } else {
        // Prevent modifying own superadmin role
        if ($targetUserId === $_SESSION['user_id']) {
            $errorMsg = "You cannot modify your own administrative role.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ?");
                $stmt->execute([$roleId, $targetUserId]);
                $successMsg = "User role updated successfully.";
            } catch (PDOException $e) {
                $errorMsg = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Handle Delete User Request
if (isset($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    
    if ($deleteId === $_SESSION['user_id']) {
        $errorMsg = "You cannot delete your own administrative account.";
    } else {
        try {
            // Check if user has complaints (as citizen or officer)
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE citizen_id = ? OR assigned_officer_id = ?");
            $checkStmt->execute([$deleteId, $deleteId]);
            $linkedRecords = $checkStmt->fetchColumn();
            
            if ($linkedRecords > 0) {
                $errorMsg = "Cannot delete user: They have $linkedRecords associated grievance tickets in the system. Consider changing their role instead.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$deleteId]);
                $successMsg = "User deleted successfully.";
            }
        } catch (PDOException $e) {
            $errorMsg = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch All Users
try {
    $users = $pdo->query("
        SELECT u.*, r.display_name AS role_display, r.role_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        ORDER BY u.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $users = [];
}

// Fetch All Roles for Form Options
try {
    $roles = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    $roles = [];
}

include 'includes/header.php';
?>

<!-- Page Header Title -->
<div class="page-header">
    <div>
        <h2>User Accounts Management</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 3px;">Register official staff members, allocate privileges, and manage village citizen records.</p>
    </div>
    
    <button class="btn btn-secondary" onclick="openModal('add-modal')"><i class="fas fa-user-plus"></i> Create User Account</button>
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

<!-- Users List Card -->
<div class="table-card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Contact Info</th>
                    <th>System Role</th>
                    <th>Date Registered</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td style="font-weight: 600;"><?= htmlspecialchars($u['full_name']) ?></td>
                        <td style="color: var(--accent);"><?= htmlspecialchars($u['username']) ?></td>
                        <td>
                            <i class="far fa-envelope"></i> <?= htmlspecialchars($u['email']) ?><br>
                            <i class="fas fa-phone-alt"></i> <?= htmlspecialchars($u['phone']) ?>
                        </td>
                        <td>
                            <span class="badge 
                                <?php
                                    if ($u['role_name'] === 'super_admin') echo 'badge-rejected'; // Red
                                    elseif ($u['role_name'] === 'gp_admin') echo 'badge-assigned'; // Blue
                                    elseif ($u['role_name'] === 'field_officer') echo 'badge-in_progress'; // Orange/Cyan
                                    else echo 'badge-resolved'; // Green
                                ?>
                            ">
                                <?= htmlspecialchars($u['role_display']) ?>
                            </span>
                        </td>
                        <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                        <td style="text-align: center;">
                            <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                <button class="btn btn-primary btn-sm" onclick="openEditModal(<?= $u['id'] ?>, <?= $u['role_id'] ?>, '<?= htmlspecialchars($u['full_name']) ?>')"><i class="fas fa-edit"></i> Edit Role</button>
                                <a href="superadmin_users.php?delete=<?= $u['id'] ?>" class="btn btn-outline btn-sm" style="border-color: var(--error); color: var(--error);" onclick="return confirm('Are you sure you want to delete user \'<?= htmlspecialchars($u['username']) ?>\'?')"><i class="far fa-trash-alt"></i> Delete</a>
                            <?php else: ?>
                                <span style="font-size:0.85rem; color:var(--text-muted); font-style:italic;">Active Account</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add New User -->
<div class="modal-overlay" id="add-modal">
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Create User Account</h3>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <form action="superadmin_users.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="action" value="add_user">

            <div class="modal-body">
                <div class="form-group">
                    <label for="add_full_name">Full Name *</label>
                    <input type="text" name="full_name" id="add_full_name" class="form-control" placeholder="Enter full name" required>
                </div>
                
                <div class="form-group">
                    <label for="add_username">Username *</label>
                    <input type="text" name="username" id="add_username" class="form-control" placeholder="Create username" required>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="add_email">Email Address *</label>
                        <input type="email" name="email" id="add_email" class="form-control" placeholder="e.g. email@gp.gov.in" required>
                    </div>
                    <div class="form-group">
                        <label for="add_phone">Phone Number *</label>
                        <input type="tel" name="phone" id="add_phone" class="form-control" placeholder="e.g. 9876543210" required pattern="[0-9]{10,12}">
                    </div>
                </div>

                <div class="form-group">
                    <label for="add_role_id">System Role *</label>
                    <select name="role_id" id="add_role_id" class="form-control" required>
                        <option value="">-- Choose Role Option --</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['display_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="add_password">Temporary Password *</label>
                    <input type="password" name="password" id="add_password" class="form-control" placeholder="Min. 6 characters" required minlength="6">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('add-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Register User</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit User Role -->
<div class="modal-overlay" id="edit-modal">
    <div class="modal-container" style="max-width: 450px;">
        <div class="modal-header">
            <h3>Modify User Privilege</h3>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <form action="superadmin_users.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="action" value="update_user_role">
            <input type="hidden" name="user_id" id="edit-user-id">

            <div class="modal-body">
                <p style="margin-bottom:15px; font-size:0.9rem;">Modify system role access level for user <strong id="edit-user-display" style="color: var(--accent);"></strong>.</p>
                
                <div class="form-group">
                    <label for="edit_role_id">Assign New Role *</label>
                    <select name="role_id" id="edit_role_id" class="form-control" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['display_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('edit-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(userId, roleId, fullName) {
    document.getElementById('edit-user-id').value = userId;
    document.getElementById('edit-user-display').innerText = fullName;
    document.getElementById('edit_role_id').value = roleId;
    openModal('edit-modal');
}
</script>

<?php
include 'includes/footer.php';
?>
