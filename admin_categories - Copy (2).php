<?php
// admin_categories.php
// Gram Panchayat Complaint Management System - Complaint Categories Page

require_once 'includes/auth.php';
require_once 'includes/db.php';

// Enforce GP Admin or Super Admin Role
check_role(['gp_admin', 'super_admin']);

$successMsg = null;
$errorMsg = null;

// Handle Add Category Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $name = sanitize($_POST['category_name']);
    $desc = sanitize($_POST['description']);
    
    if (empty($name)) {
        $errorMsg = "Category name is required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO complaint_categories (category_name, description) VALUES (?, ?)");
            $stmt->execute([$name, $desc]);
            $successMsg = "Category '$name' created successfully.";
        } catch (PDOException $e) {
            $errorMsg = "Failed to create category: Name might already exist.";
        }
    }
}

// Handle Update Category Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_category') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $catId = intval($_POST['category_id']);
    $name = sanitize($_POST['category_name']);
    $desc = sanitize($_POST['description']);
    
    if (empty($name) || empty($catId)) {
        $errorMsg = "Category name and ID are required.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE complaint_categories SET category_name = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $desc, $catId]);
            $successMsg = "Category updated successfully.";
        } catch (PDOException $e) {
            $errorMsg = "Failed to update category: Name might already exist.";
        }
    }
}

// Handle Delete Category Request
if (isset($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    try {
        // First check if there are any complaints associated with this category
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE category_id = ?");
        $checkStmt->execute([$deleteId]);
        $associatedComplaints = $checkStmt->fetchColumn();
        
        if ($associatedComplaints > 0) {
            $errorMsg = "Cannot delete category: $associatedComplaints complaints are currently linked to it. Please reassign them first.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM complaint_categories WHERE id = ?");
            $stmt->execute([$deleteId]);
            $successMsg = "Category deleted successfully.";
        }
    } catch (PDOException $e) {
        $errorMsg = "Failed to delete category: Database restriction.";
    }
}

// Fetch All Categories
try {
    $categories = $pdo->query("
        SELECT cc.*, COUNT(c.id) AS complaints_count 
        FROM complaint_categories cc 
        LEFT JOIN complaints c ON cc.id = c.category_id 
        GROUP BY cc.id 
        ORDER BY cc.category_name ASC
    ")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

include 'includes/header.php';
?>

<!-- Page Title Block -->
<div class="page-header">
    <div>
        <h2>Complaint Categories Management</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 3px;">Configure the registration categories available to citizens.</p>
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

<!-- Double Column Layout -->
<div class="dashboard-grid-2" style="grid-template-columns: 1fr 2fr;">
    
    <!-- Add New Category Card -->
    <div class="card" style="align-self: start;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid var(--secondary); padding-bottom: 10px;">
            <i class="fas fa-plus-circle" style="color: var(--secondary); font-size: 1.3rem;"></i>
            <h3 style="margin: 0; color: var(--primary-dark);">Add Category</h3>
        </div>
        
        <form action="admin_categories.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="action" value="add_category">
            
            <div class="form-group">
                <label for="category_name">Category Name *</label>
                <input type="text" name="category_name" id="category_name" class="form-control" placeholder="e.g. Electricity" required>
            </div>

            <div class="form-group">
                <label for="description">Brief Description</label>
                <textarea name="description" id="description" class="form-control" rows="4" placeholder="Brief summary of the category scope..."></textarea>
            </div>

            <button type="submit" class="btn btn-secondary" style="width: 100%; margin-top: 10px;">
                <i class="fas fa-plus"></i> Save Category
            </button>
        </form>
    </div>

    <!-- Categories List Table -->
    <div class="table-card">
        <div class="table-card-header">
            <h3>Registered Categories</h3>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th style="text-align: center;">Active Tickets</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                No categories registered in the database.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><?= $cat['id'] ?></td>
                                <td style="font-weight: 600; color: var(--primary-dark);"><?= htmlspecialchars($cat['category_name']) ?></td>
                                <td style="font-size: 0.85rem; color: var(--text-muted); max-width: 300px;"><?= htmlspecialchars($cat['description']) ?></td>
                                <td style="text-align: center;">
                                    <span class="badge badge-assigned" style="min-width: 30px; text-align: center;"><?= $cat['complaints_count'] ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <button class="btn btn-primary btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($cat)) ?>)"><i class="far fa-edit"></i> Edit</button>
                                    
                                    <?php if ($cat['complaints_count'] == 0): ?>
                                        <a href="admin_categories.php?delete=<?= $cat['id'] ?>" class="btn btn-outline btn-sm" style="border-color: var(--error); color: var(--error);" onclick="return confirm('Are you sure you want to delete the category \'<?= htmlspecialchars($cat['category_name']) ?>\'?')"><i class="far fa-trash-alt"></i> Delete</a>
                                    <?php else: ?>
                                        <button class="btn btn-outline btn-sm" style="border-color: #ccc; color: #ccc;" disabled title="Cannot delete category with active tickets"><i class="far fa-trash-alt"></i> Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Edit Category Details -->
<div class="modal-overlay" id="edit-modal">
    <div class="modal-container" style="max-width: 450px;">
        <div class="modal-header">
            <h3>Modify Category Details</h3>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <form action="admin_categories.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="action" value="update_category">
            <input type="hidden" name="category_id" id="edit-category-id">

            <div class="modal-body">
                <div class="form-group">
                    <label for="edit-category-name">Category Name *</label>
                    <input type="text" name="category_name" id="edit-category-name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="edit-description">Description</label>
                    <textarea name="description" id="edit-description" class="form-control" rows="4"></textarea>
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
function openEditModal(cat) {
    document.getElementById('edit-category-id').value = cat.id;
    document.getElementById('edit-category-name').value = cat.category_name;
    document.getElementById('edit-description').value = cat.description || '';
    openModal('edit-modal');
}
</script>

<?php
include 'includes/footer.php';
?>
