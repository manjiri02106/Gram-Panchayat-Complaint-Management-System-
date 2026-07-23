<?php
// citizen_dashboard.php
// Gram Panchayat Complaint Management System - Citizen Panel

require_once 'includes/auth.php';
require_once 'includes/db.php';

// Enforce Citizen Role
check_role('citizen');

$userId = $_SESSION['user_id'];
$successMsg = null;
$errorMsg = null;

// Handle Complaint Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_complaint') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $title = sanitize($_POST['title']);
    $categoryId = intval($_POST['category_id']);
    $location = sanitize($_POST['location']);
    $description = sanitize($_POST['description']);
    
    if (empty($title) || empty($categoryId) || empty($location) || empty($description)) {
        $errorMsg = "Please fill in all mandatory text fields.";
    } else {
        $uploadedFilePath = null;
        
        // Handle File Upload
        if (isset($_FILES['complaint_image']) && $_FILES['complaint_image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['complaint_image']['tmp_name'];
            $fileName = $_FILES['complaint_image']['name'];
            $fileSize = $_FILES['complaint_image']['size'];
            
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($fileExtension, $allowedExtensions)) {
                if ($fileSize < 5242880) { // Max size: 5MB
                    $uploadFileDir = __DIR__ . '/assets/uploads/';
                    if (!file_exists($uploadFileDir)) {
                        mkdir($uploadFileDir, 0777, true);
                    }
                    
                    $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                    $dest_path = $uploadFileDir . $newFileName;
                    
                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        $uploadedFilePath = 'assets/uploads/' . $newFileName;
                    } else {
                        $errorMsg = "There was an error moving the uploaded image to the server folder.";
                    }
                } else {
                    $errorMsg = "Image upload failed. Maximum size is 5MB.";
                }
            } else {
                $errorMsg = "Invalid file type. Allowed formats: JPG, JPEG, PNG, GIF.";
            }
        }
        
        if ($errorMsg === null) {
            try {
                $ticketId = 'GP-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
                
                $stmt = $pdo->prepare("
                    INSERT INTO complaints (ticket_id, citizen_id, category_id, title, description, location, image_path, status_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$ticketId, $userId, $categoryId, $title, $description, $location, $uploadedFilePath]);
                
                $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $notifStmt->execute([$userId, "Your complaint has been successfully registered! Ticket ID: $ticketId."]);
                
                $admins = $pdo->query("SELECT id FROM users WHERE role_id = 2")->fetchAll();
                $adminNotifStmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                foreach ($admins as $admin) {
                    $adminNotifStmt->execute([$admin['id'], "New complaint submitted: " . $title . ". Ticket ID: $ticketId"]);
                }
                
                $successMsg = "Complaint registered successfully! Your Ticket ID is: " . $ticketId;
            } catch (PDOException $e) {
                $errorMsg = "Failed to submit complaint: " . $e->getMessage();
            }
        }
    }
}

// Handle Feedback Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_feedback') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $complaintId = intval($_POST['complaint_id']);
    $rating = intval($_POST['rating']);
    $comments = sanitize($_POST['comments']);
    
    if ($rating < 1 || $rating > 5 || empty($complaintId)) {
        $errorMsg = "Invalid rating. Rating must be between 1 and 5 stars.";
    } else {
        try {
            $checkStmt = $pdo->prepare("SELECT id FROM complaints WHERE id = ? AND citizen_id = ? AND status_id = 4");
            $checkStmt->execute([$complaintId, $userId]);
            if ($checkStmt->fetch()) {
                $stmt = $pdo->prepare("
                    INSERT INTO feedback (complaint_id, rating, comments) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE rating = VALUES(rating), comments = VALUES(comments)
                ");
                $stmt->execute([$complaintId, $rating, $comments]);
                $successMsg = "Thank you! Your feedback rating was submitted successfully.";
            } else {
                $errorMsg = "Feedback submission denied. Grievance must be marked as Resolved.";
            }
        } catch (PDOException $e) {
            $errorMsg = "Failed to submit feedback: " . $e->getMessage();
        }
    }
}

// 1. Fetch Summary Statistics
try {
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status_id = 1 THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status_id IN (2, 3) THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status_id = 4 THEN 1 ELSE 0 END) as resolved
        FROM complaints 
        WHERE citizen_id = ?
    ");
    $statsStmt->execute([$userId]);
    $stats = $statsStmt->fetch();
} catch (PDOException $e) {
    $stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'resolved' => 0];
}

// 2. Fetch Complaint Categories
try {
    $categories = $pdo->query("SELECT * FROM complaint_categories ORDER BY category_name ASC")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// 3. Fetch My Complaints List
try {
    $complaintsStmt = $pdo->prepare("
        SELECT c.*, cs.display_name AS status_name, cc.category_name, f.rating
        FROM complaints c
        JOIN complaint_statuses cs ON c.status_id = cs.id
        JOIN complaint_categories cc ON c.category_id = cc.id
        LEFT JOIN feedback f ON c.id = f.complaint_id
        WHERE c.citizen_id = ?
        ORDER BY c.created_at DESC
    ");
    $complaintsStmt->execute([$userId]);
    $myComplaints = $complaintsStmt->fetchAll();
} catch (PDOException $e) {
    $myComplaints = [];
}

include 'includes/header.php';
?>

<!-- Page Header Title -->
<div class="page-header">
    <div>
        <h2>Dashboard</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 3px;">Welcome back, <?= htmlspecialchars($_SESSION['full_name']) ?>!</p>
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

<!-- Summary Cards Grid -->
<div class="stats-grid">
    <!-- Card 1: My Total -->
    <div class="stat-card stat-total">
        <div class="stat-card-body">
            <div class="stat-icon"><i class="far fa-clipboard"></i></div>
            <div class="stat-info">
                <h3>My Complaints</h3>
                <div class="stat-value"><?= intval($stats['total']) ?></div>
            </div>
        </div>
        <div class="stat-card-footer">
            <a href="#history-section">View all complaints <i class="fas fa-chevron-right"></i></a>
        </div>
    </div>
    
    <!-- Card 2: Pending -->
    <div class="stat-card stat-pending">
        <div class="stat-card-body">
            <div class="stat-icon"><i class="far fa-clock"></i></div>
            <div class="stat-info">
                <h3>Pending Review</h3>
                <div class="stat-value"><?= intval($stats['pending']) ?></div>
            </div>
        </div>
        <div class="stat-card-footer">
            <a href="#history-section">View details <i class="fas fa-chevron-right"></i></a>
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
            <a href="#history-section">View details <i class="fas fa-chevron-right"></i></a>
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
            <a href="#history-section">View details <i class="fas fa-chevron-right"></i></a>
        </div>
    </div>
</div>

<!-- Grid Layout: Register Left, History Right -->
<div class="dashboard-layout-grid" style="grid-template-columns: 1.2fr 2fr; gap: 20px;">
    
    <!-- Register Complaint Form -->
    <div class="card" id="register-section" style="align-self: start;">
        <div class="card-header-flex" style="margin-bottom:15px; padding-bottom:8px;">
            <h3>Submit Grievance</h3>
        </div>
        
        <form action="citizen_dashboard.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="action" value="register_complaint">
            
            <div class="form-group">
                <label for="title">Complaint Subject *</label>
                <input type="text" name="title" id="title" class="form-control" placeholder="e.g. Broken street light near house" required>
            </div>

            <div class="form-group">
                <label for="category_id">Category *</label>
                <select name="category_id" id="category_id" class="form-control" required>
                    <option value="">-- Choose Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="location">Location / Ward *</label>
                <input type="text" name="location" id="location" class="form-control" placeholder="e.g. Ward 3, Ambedkar Colony" required>
            </div>

            <div class="form-group">
                <label for="description">Detailed Description *</label>
                <textarea name="description" id="description" class="form-control" rows="3" placeholder="Explain the grievance clearly..." required></textarea>
            </div>

            <div class="form-group">
                <label for="complaint-image"><i class="far fa-image"></i> Attachment Photo</label>
                <input type="file" name="complaint_image" id="complaint-image" class="form-control" accept="image/*">
                <span class="form-text">Optional. Max size: 5MB.</span>
            </div>

            <div id="image-preview-container"></div>

            <button type="submit" class="btn btn-secondary" style="width: 100%; margin-top: 10px; height: 38px;">
                <i class="fas fa-paper-plane"></i> Submit Grievance
            </button>
        </form>
    </div>

    <!-- History list table -->
    <div class="table-card" id="history-section">
        <div class="table-card-header">
            <h3>My Complaint History</h3>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Complaint Details</th>
                        <th>Location</th>
                        <th>Submitted On</th>
                        <th>Status</th>
                        <th style="text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($myComplaints)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <i class="fas fa-clipboard-list" style="font-size: 2rem; display:block; margin-bottom:10px;"></i>
                                You have not registered any complaints yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($myComplaints as $comp): ?>
                            <tr>
                                <td style="font-weight: 600; color: var(--accent);"><?= htmlspecialchars($comp['ticket_id']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($comp['title']) ?></strong><br>
                                    <span style="font-size: 0.75rem; color: var(--text-muted); background-color: #f7fafc; border:1px solid #edf2f7; padding: 1px 6px; border-radius: 4px;"><?= htmlspecialchars($comp['category_name']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($comp['location']) ?></td>
                                <td><?= date('d M Y', strtotime($comp['created_at'])) ?></td>
                                <td>
                                    <span class="badge 
                                        <?php 
                                            if ($comp['status_name'] == 'Pending') echo 'badge-pending';
                                            elseif ($comp['status_name'] == 'Assigned') echo 'badge-assigned';
                                            elseif ($comp['status_name'] == 'In Progress') echo 'badge-in_progress';
                                            elseif ($comp['status_name'] == 'Resolved') echo 'badge-resolved';
                                            else echo 'badge-rejected';
                                        ?>
                                    "><?= htmlspecialchars($comp['status_name']) ?></span>
                                </td>
                                <td style="text-align: center; display:flex; gap:5px; justify-content:center;">
                                    <button class="btn btn-primary btn-sm" onclick="viewComplaintDetails(<?= htmlspecialchars(json_encode($comp)) ?>)"><i class="far fa-eye"></i> View</button>
                                    <?php if ($comp['status_name'] == 'Resolved'): ?>
                                        <?php if (empty($comp['rating'])): ?>
                                            <button class="btn btn-secondary btn-sm" onclick="openFeedbackModal(<?= $comp['id'] ?>, '<?= $comp['ticket_id'] ?>')"><i class="far fa-star"></i> Feedback</button>
                                        <?php else: ?>
                                            <span style="font-size:0.8rem; color: var(--success); font-weight:700; align-self:center; margin-left:5px;"><i class="fas fa-star"></i> <?= $comp['rating'] ?></span>
                                        <?php endif; ?>
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

<!-- Citizen Quick Actions Grid -->
<div class="card" style="margin-top: 20px;">
    <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--primary-dark); margin-bottom: 15px; border-bottom: 1px solid #edf2f7; padding-bottom: 8px;">Citizen Services</h3>
    <div class="quick-actions-grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="quick-action-box" onclick="window.location.href='#register-section'">
            <div class="icon-circle" style="background-color:#e8f5e9; color:var(--primary);"><i class="fas fa-plus"></i></div>
            <h4>Register Complaint</h4>
        </div>
        <div class="quick-action-box" onclick="window.location.href='#history-section'">
            <div class="icon-circle" style="background-color:#e3f2fd; color:var(--accent);"><i class="fas fa-search"></i></div>
            <h4>Track Complaint</h4>
        </div>
        <div class="quick-action-box" onclick="window.location.href='notifications.php'">
            <div class="icon-circle" style="background-color:#fff3e0; color:var(--secondary);"><i class="fas fa-bell"></i></div>
            <h4>Alert Notifications</h4>
        </div>
    </div>
</div>

<!-- Modal: View Details -->
<div class="modal-overlay" id="view-modal">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Complaint Tracking Details</h3>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed var(--border-color); padding-bottom: 12px; margin-bottom: 12px;">
                <div>
                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight:600;">TICKET ID</span>
                    <h4 id="detail-ticket-id" style="color: var(--accent); margin:0;"></h4>
                </div>
                <div>
                    <span style="font-size: 0.75rem; color: var(--text-muted); font-weight:600; display:block; text-align:right;">STATUS</span>
                    <span id="detail-status" class="badge"></span>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <strong>Category:</strong>
                    <span id="detail-category" style="display:block; font-size:0.9rem;"></span>
                </div>
                <div>
                    <strong>Location / Ward:</strong>
                    <span id="detail-location" style="display:block; font-size:0.9rem;"></span>
                </div>
                <div>
                    <strong>Date Submitted:</strong>
                    <span id="detail-date" style="display:block; font-size:0.9rem;"></span>
                </div>
                <div>
                    <strong>Last Updated:</strong>
                    <span id="detail-updated" style="display:block; font-size:0.9rem;"></span>
                </div>
            </div>

            <div style="margin-bottom: 15px; padding: 12px; background-color: var(--bg-main); border-radius: 4px;">
                <strong>Grievance Title:</strong>
                <p id="detail-title" style="font-weight:600; margin-bottom: 5px;"></p>
                <strong>Description:</strong>
                <p id="detail-description" style="font-size:0.85rem; line-height: 1.4;"></p>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div>
                    <strong>Before Work (Submitted Photo):</strong>
                    <div id="detail-before-photo" style="margin-top: 5px;"></div>
                </div>
                <div>
                    <strong>After Work (Completion Proof):</strong>
                    <div id="detail-after-photo" style="margin-top: 5px;"></div>
                </div>
            </div>

            <div id="detail-remarks-section" style="border-top: 1px solid var(--border-color); padding-top: 15px; margin-top: 15px;">
                <div id="detail-admin-remarks" style="margin-bottom: 10px; font-size: 0.85rem;"></div>
                <div id="detail-officer-remarks" style="font-size: 0.85rem;"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline btn-sm" onclick="closeModal('view-modal')">Close</button>
        </div>
    </div>
</div>

<!-- Modal: Feedback -->
<div class="modal-overlay" id="feedback-modal">
    <div class="modal-container" style="max-width: 420px;">
        <div class="modal-header">
            <h3>Submit Grievance Feedback</h3>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <form action="citizen_dashboard.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="action" value="submit_feedback">
            <input type="hidden" name="complaint_id" id="feedback-complaint-id">

            <div class="modal-body">
                <p style="margin-bottom: 15px; font-size: 0.9rem;">Your grievance has been resolved! Please rate the quality of the maintenance work.</p>
                
                <div class="form-group" style="text-align: center; margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 10px; font-weight:600;">Work Quality Rating</label>
                    <div style="display: inline-flex; gap: 15px; font-size: 1.8rem; cursor: pointer;">
                        <label style="cursor: pointer;"><input type="radio" name="rating" value="1" required style="display:none;" onclick="updateStarHighlight(1)"><i class="far fa-star star-icon" id="star-1"></i></label>
                        <label style="cursor: pointer;"><input type="radio" name="rating" value="2" style="display:none;" onclick="updateStarHighlight(2)"><i class="far fa-star star-icon" id="star-2"></i></label>
                        <label style="cursor: pointer;"><input type="radio" name="rating" value="3" style="display:none;" onclick="updateStarHighlight(3)"><i class="far fa-star star-icon" id="star-3"></i></label>
                        <label style="cursor: pointer;"><input type="radio" name="rating" value="4" style="display:none;" onclick="updateStarHighlight(4)"><i class="far fa-star star-icon" id="star-4"></i></label>
                        <label style="cursor: pointer;"><input type="radio" name="rating" value="5" style="display:none;" onclick="updateStarHighlight(5)"><i class="far fa-star star-icon" id="star-5"></i></label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="feedback_comments">Add Remarks / Review Comments</label>
                    <textarea name="comments" id="feedback_comments" class="form-control" rows="3" placeholder="Write your feedback..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('feedback-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Submit Rating</button>
            </div>
        </form>
    </div>
</div>

<script>
function viewComplaintDetails(comp) {
    document.getElementById('detail-ticket-id').innerText = comp.ticket_id;
    document.getElementById('detail-title').innerText = comp.title;
    document.getElementById('detail-category').innerText = comp.category_name;
    document.getElementById('detail-location').innerText = comp.location;
    document.getElementById('detail-description').innerText = comp.description;
    
    const dateSubmitted = new Date(comp.created_at);
    document.getElementById('detail-date').innerText = dateSubmitted.toLocaleDateString('en-IN', {day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true});
    
    const dateUpdated = new Date(comp.updated_at);
    document.getElementById('detail-updated').innerText = dateUpdated.toLocaleDateString('en-IN', {day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true});

    const badge = document.getElementById('detail-status');
    badge.innerText = comp.status_name;
    badge.className = 'badge'; 
    if (comp.status_name === 'Pending') badge.classList.add('badge-pending');
    else if (comp.status_name === 'Assigned') badge.classList.add('badge-assigned');
    else if (comp.status_name === 'In Progress') badge.classList.add('badge-in_progress');
    else if (comp.status_name === 'Resolved') badge.classList.add('badge-resolved');
    else badge.classList.add('badge-rejected');

    const beforePhotoDiv = document.getElementById('detail-before-photo');
    if (comp.image_path) {
        beforePhotoDiv.innerHTML = `<img src="${comp.image_path}" class="complaint-img-preview" alt="Before Photo" onclick="window.open('${comp.image_path}', '_blank')">`;
    } else {
        beforePhotoDiv.innerHTML = `<div style="padding: 15px; background: #f7fafc; border-radius: 4px; text-align: center; color:#888; font-size:0.85rem;">No photo attached</div>`;
    }

    const afterPhotoDiv = document.getElementById('detail-after-photo');
    if (comp.after_image_path) {
        afterPhotoDiv.innerHTML = `<img src="${comp.after_image_path}" class="complaint-img-preview" alt="After Photo" onclick="window.open('${comp.after_image_path}', '_blank')">`;
    } else {
        afterPhotoDiv.innerHTML = `<div style="padding: 15px; background: #f7fafc; border-radius: 4px; text-align: center; color:#888; font-size:0.85rem;">Pending completion</div>`;
    }

    const adminRemarksDiv = document.getElementById('detail-admin-remarks');
    if (comp.admin_remarks) {
        adminRemarksDiv.innerHTML = `<strong>GP Admin Remarks:</strong><p style="color:var(--text-muted); font-style:italic;">"${comp.admin_remarks}"</p>`;
    } else {
        adminRemarksDiv.innerHTML = '';
    }

    const officerRemarksDiv = document.getElementById('detail-officer-remarks');
    if (comp.officer_remarks) {
        officerRemarksDiv.innerHTML = `<strong>Field Officer Remarks:</strong><p style="color:var(--text-muted); font-style:italic;">"${comp.officer_remarks}"</p>`;
    } else {
        officerRemarksDiv.innerHTML = '';
    }

    openModal('view-modal');
}

function openFeedbackModal(complaintId, ticketId) {
    document.getElementById('feedback-complaint-id').value = complaintId;
    document.getElementById('feedback_comments').value = '';
    updateStarHighlight(0);
    openModal('feedback-modal');
}

function updateStarHighlight(rating) {
    for (let i = 1; i <= 5; i++) {
        const star = document.getElementById('star-' + i);
        if (i <= rating) {
            star.className = 'fas fa-star star-icon';
            star.style.color = 'var(--secondary)';
        } else {
            star.className = 'far fa-star star-icon';
            star.style.color = 'var(--text-muted)';
        }
    }
}
</script>

<?php
include 'includes/footer.php';
?>
