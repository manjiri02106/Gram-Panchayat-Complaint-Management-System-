<?php
// superadmin_settings.php
// Gram Panchayat Complaint Management System - Super Admin Portal Settings Page

require_once 'includes/auth.php';
require_once 'includes/db.php';

// Enforce Super Admin Role
check_role('super_admin');

$successMsg = null;
$errorMsg = null;

// Handle Actions (add / delete for Announcements, Schemes, Contacts)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $action = $_POST['action'];
    
    // 1. Announcements
    if ($action === 'add_announcement') {
        $title = sanitize($_POST['title']);
        $content = sanitize($_POST['content']);
        if (!empty($title) && !empty($content)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO announcements (title, content) VALUES (?, ?)");
                $stmt->execute([$title, $content]);
                $successMsg = "Announcement posted successfully.";
            } catch (PDOException $e) {
                $errorMsg = "Failed to post announcement.";
            }
        }
    }
    
    // 2. Government Schemes
    if ($action === 'add_scheme') {
        $title = sanitize($_POST['title']);
        $desc = sanitize($_POST['description']);
        $link = sanitize($_POST['link']);
        if (!empty($title) && !empty($desc)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO government_schemes (title, description, link) VALUES (?, ?, ?)");
                $stmt->execute([$title, $desc, $link]);
                $successMsg = "Government Scheme posted successfully.";
            } catch (PDOException $e) {
                $errorMsg = "Failed to post scheme.";
            }
        }
    }
    
    // 3. Emergency Contacts
    if ($action === 'add_contact') {
        $name = sanitize($_POST['name']);
        $desg = sanitize($_POST['designation']);
        $phone = sanitize($_POST['phone_number']);
        if (!empty($name) && !empty($desg) && !empty($phone)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO emergency_contacts (name, designation, phone_number) VALUES (?, ?, ?)");
                $stmt->execute([$name, $desg, $phone]);
                $successMsg = "Emergency Contact added successfully.";
            } catch (PDOException $e) {
                $errorMsg = "Failed to add contact.";
            }
        }
    }
}

// Handle Delete parameters
if (isset($_GET['delete_ann'])) {
    $id = intval($_GET['delete_ann']);
    try {
        $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$id]);
        $successMsg = "Announcement deleted successfully.";
    } catch (PDOException $e) {
        $errorMsg = "Delete failed.";
    }
}
if (isset($_GET['delete_scheme'])) {
    $id = intval($_GET['delete_scheme']);
    try {
        $pdo->prepare("DELETE FROM government_schemes WHERE id = ?")->execute([$id]);
        $successMsg = "Government scheme deleted successfully.";
    } catch (PDOException $e) {
        $errorMsg = "Delete failed.";
    }
}
if (isset($_GET['delete_contact'])) {
    $id = intval($_GET['delete_contact']);
    try {
        $pdo->prepare("DELETE FROM emergency_contacts WHERE id = ?")->execute([$id]);
        $successMsg = "Emergency contact deleted successfully.";
    } catch (PDOException $e) {
        $errorMsg = "Delete failed.";
    }
}

// Fetch lists for display
try {
    $announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll();
    $schemes = $pdo->query("SELECT * FROM government_schemes ORDER BY created_at DESC")->fetchAll();
    $contacts = $pdo->query("SELECT * FROM emergency_contacts ORDER BY id ASC")->fetchAll();
} catch (PDOException $e) {
    $announcements = [];
    $schemes = [];
    $contacts = [];
}

include 'includes/header.php';
?>

<!-- Page Header Title -->
<div class="page-header">
    <div>
        <h2>Portal Content & Settings</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 3px;">Manage announcements, local government schemes, and emergency dial directories displayed on the citizen homepage.</p>
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

<!-- Tabs Navigation -->
<div style="display:flex; gap:10px; margin-bottom: 25px; border-bottom:1px solid var(--border-color); padding-bottom: 10px;" class="no-print">
    <button class="btn btn-primary" onclick="switchTab('tab-announcements')"><i class="fas fa-bullhorn"></i> Announcements</button>
    <button class="btn btn-outline" id="btn-schemes" onclick="switchTab('tab-schemes')"><i class="fas fa-scroll"></i> Schemes</button>
    <button class="btn btn-outline" id="btn-contacts" onclick="switchTab('tab-contacts')"><i class="fas fa-phone-alt"></i> Contacts</button>
</div>

<!-- 1. Announcements Tab -->
<div id="tab-announcements" class="tab-content">
    <div class="dashboard-grid-2" style="grid-template-columns: 1fr 2fr;">
        <!-- Add Card -->
        <div class="card" style="align-self: start;">
            <h3 style="margin-bottom:15px; color: var(--primary-dark);">Post Announcement</h3>
            <form action="superadmin_settings.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="add_announcement">
                
                <div class="form-group">
                    <label for="ann_title">Title *</label>
                    <input type="text" name="title" id="ann_title" class="form-control" required placeholder="e.g. Ward 2 Electrification">
                </div>
                <div class="form-group">
                    <label for="ann_content">Announcements Text *</label>
                    <textarea name="content" id="ann_content" class="form-control" rows="4" required placeholder="Details..."></textarea>
                </div>
                <button type="submit" class="btn btn-secondary" style="width: 100%;"><i class="fas fa-plus"></i> Post Announcement</button>
            </form>
        </div>
        
        <!-- Table List -->
        <div class="table-card">
            <div class="table-card-header"><h3>Active Announcements</h3></div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Content</th>
                            <th>Date Posted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($announcements)): ?>
                            <tr><td colspan="4" style="text-align:center;">No announcements.</td></tr>
                        <?php else: ?>
                            <?php foreach ($announcements as $ann): ?>
                                <tr>
                                    <td style="font-weight:600;"><?= htmlspecialchars($ann['title']) ?></td>
                                    <td style="font-size:0.85rem; max-width:250px;"><?= htmlspecialchars($ann['content']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($ann['created_at'])) ?></td>
                                    <td><a href="superadmin_settings.php?delete_ann=<?= $ann['id'] ?>" class="btn btn-outline btn-sm" style="border-color:var(--error); color:var(--error);" onclick="return confirm('Delete announcement?')"><i class="far fa-trash-alt"></i> Delete</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 2. Schemes Tab -->
<div id="tab-schemes" class="tab-content" style="display:none;">
    <div class="dashboard-grid-2" style="grid-template-columns: 1fr 2fr;">
        <!-- Add Card -->
        <div class="card" style="align-self: start;">
            <h3 style="margin-bottom:15px; color: var(--primary-dark);">Add Government Scheme</h3>
            <form action="superadmin_settings.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="add_scheme">
                
                <div class="form-group">
                    <label for="sch_title">Scheme Name *</label>
                    <input type="text" name="title" id="sch_title" class="form-control" required placeholder="e.g. PM-Kisan Yojana">
                </div>
                <div class="form-group">
                    <label for="sch_desc">Description *</label>
                    <textarea name="description" id="sch_desc" class="form-control" rows="3" required placeholder="Eligibility or benefits details..."></textarea>
                </div>
                <div class="form-group">
                    <label for="sch_link">External Link</label>
                    <input type="url" name="link" id="sch_link" class="form-control" placeholder="https://scheme-details.nic.in">
                </div>
                <button type="submit" class="btn btn-secondary" style="width: 100%;"><i class="fas fa-plus"></i> Add Scheme</button>
            </form>
        </div>
        
        <!-- Table List -->
        <div class="table-card">
            <div class="table-card-header"><h3>Registered Schemes</h3></div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Scheme Name</th>
                            <th>Description</th>
                            <th>Link</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($schemes)): ?>
                            <tr><td colspan="4" style="text-align:center;">No schemes registered.</td></tr>
                        <?php else: ?>
                            <?php foreach ($schemes as $sch): ?>
                                <tr>
                                    <td style="font-weight:600; color:var(--primary);"><?= htmlspecialchars($sch['title']) ?></td>
                                    <td style="font-size:0.85rem; max-width:250px;"><?= htmlspecialchars($sch['description']) ?></td>
                                    <td><a href="<?= htmlspecialchars($sch['link']) ?>" target="_blank"><?= htmlspecialchars($sch['link']) ?></a></td>
                                    <td><a href="superadmin_settings.php?delete_scheme=<?= $sch['id'] ?>" class="btn btn-outline btn-sm" style="border-color:var(--error); color:var(--error);" onclick="return confirm('Delete scheme?')"><i class="far fa-trash-alt"></i> Delete</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 3. Contacts Tab -->
<div id="tab-contacts" class="tab-content" style="display:none;">
    <div class="dashboard-grid-2" style="grid-template-columns: 1fr 2fr;">
        <!-- Add Card -->
        <div class="card" style="align-self: start;">
            <h3 style="margin-bottom:15px; color: var(--primary-dark);">Add Emergency Contact</h3>
            <form action="superadmin_settings.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="add_contact">
                
                <div class="form-group">
                    <label for="cnt_name">Contact Name / Department *</label>
                    <input type="text" name="name" id="cnt_name" class="form-control" required placeholder="e.g. Village Water Board">
                </div>
                <div class="form-group">
                    <label for="cnt_desg">Designation / Role *</label>
                    <input type="text" name="designation" id="cnt_desg" class="form-control" required placeholder="e.g. Junior Engineer">
                </div>
                <div class="form-group">
                    <label for="cnt_phone">Phone Number *</label>
                    <input type="tel" name="phone_number" id="cnt_phone" class="form-control" required placeholder="e.g. +91 99999 88888">
                </div>
                <button type="submit" class="btn btn-secondary" style="width: 100%;"><i class="fas fa-plus"></i> Save Contact</button>
            </form>
        </div>
        
        <!-- Table List -->
        <div class="table-card">
            <div class="table-card-header"><h3>Local Contacts Directory</h3></div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Contact Name</th>
                            <th>Designation</th>
                            <th>Phone Number</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contacts)): ?>
                            <tr><td colspan="4" style="text-align:center;">No contacts registered.</td></tr>
                        <?php else: ?>
                            <?php foreach ($contacts as $cnt): ?>
                                <tr>
                                    <td style="font-weight:600;"><?= htmlspecialchars($cnt['name']) ?></td>
                                    <td><?= htmlspecialchars($cnt['designation']) ?></td>
                                    <td style="font-weight:600; color:var(--accent);"><?= htmlspecialchars($cnt['phone_number']) ?></td>
                                    <td><a href="superadmin_settings.php?delete_contact=<?= $cnt['id'] ?>" class="btn btn-outline btn-sm" style="border-color:var(--error); color:var(--error);" onclick="return confirm('Delete contact?')"><i class="far fa-trash-alt"></i> Delete</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tabId) {
    // Hide all tab contents
    const contents = document.querySelectorAll('.tab-content');
    contents.forEach(content => {
        content.style.display = 'none';
    });
    
    // Show current tab content
    document.getElementById(tabId).style.display = 'block';
    
    // Update button styling (simple toggling)
    const buttons = document.querySelectorAll('.no-print button');
    buttons.forEach(btn => {
        btn.className = 'btn btn-outline';
    });
    
    // Set clicked button to primary style (dynamic depending on trigger event)
    const activeBtn = window.event.target.closest('button');
    if (activeBtn) {
        activeBtn.className = 'btn btn-primary';
    }
}
</script>

<?php
include 'includes/footer.php';
?>
