<?php
// officer_dashboard.php
// Gram Panchayat Complaint Management System - Field Officer Panel

require_once 'includes/auth.php';
require_once 'includes/db.php';

// Enforce Field Officer Role
check_role('field_officer');

$userId = $_SESSION['user_id'];
$successMsg = null;
$errorMsg = null;

// Handle Start Work Action
if (isset($_GET['start_work'])) {
    $compId = intval($_GET['start_work']);
    try {
        $checkStmt = $pdo->prepare("SELECT ticket_id, citizen_id FROM complaints WHERE id = ? AND assigned_officer_id = ? AND status_id = 2");
        $checkStmt->execute([$compId, $userId]);
        $complaint = $checkStmt->fetch();
        
        if ($complaint) {
            $ticketId = $complaint['ticket_id'];
            $citizenId = $complaint['citizen_id'];
            
            $updateStmt = $pdo->prepare("UPDATE complaints SET status_id = 3 WHERE id = ?");
            $updateStmt->execute([$compId]);
            
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $notif->execute([$citizenId, "Field officer has started repair work on your grievance (Ticket ID: $ticketId). Status is now In Progress."]);
            
            $successMsg = "Task status updated to In Progress. Citizen has been notified.";
        } else {
            $errorMsg = "Unauthorized action or invalid ticket status.";
        }
    } catch (PDOException $e) {
        $errorMsg = "Database error: " . $e->getMessage();
    }
}

// Handle Mark Complete Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_work') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $compId = intval($_POST['complaint_id']);
    $remarks = sanitize($_POST['officer_remarks']);
    
    if (empty($compId) || empty($remarks)) {
        $errorMsg = "Please fill in completion remarks.";
    } else {
        $uploadedFilePath = null;
        
        // Handle Photo Upload
        if (isset($_FILES['after_image']) && $_FILES['after_image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['after_image']['tmp_name'];
            $fileName = $_FILES['after_image']['name'];
            $fileSize = $_FILES['after_image']['size'];
            
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($fileExtension, $allowedExtensions)) {
                if ($fileSize < 5242880) { // Max size: 5MB
                    $uploadFileDir = __DIR__ . '/assets/uploads/';
                    if (!file_exists($uploadFileDir)) {
                        mkdir($uploadFileDir, 0777, true);
                    }
                    
                    $newFileName = 'after_' . md5(time() . $fileName) . '.' . $fileExtension;
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
        } else {
            $errorMsg = "An After Photo upload is required to verify completed work.";
        }
        
        if ($errorMsg === null) {
            try {
                $checkStmt = $pdo->prepare("SELECT ticket_id, citizen_id FROM complaints WHERE id = ? AND assigned_officer_id = ? AND status_id = 3");
                $checkStmt->execute([$compId, $userId]);
                $complaint = $checkStmt->fetch();
                
                if ($complaint) {
                    $ticketId = $complaint['ticket_id'];
                    $citizenId = $complaint['citizen_id'];
                    
                    $updateStmt = $pdo->prepare("
                        UPDATE complaints 
                        SET after_image_path = ?, officer_remarks = ? 
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$uploadedFilePath, $remarks, $compId]);
                    
                    $n1 = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    $n1->execute([$citizenId, "Field officer has marked your grievance (Ticket ID: $ticketId) as Completed. Awaiting verification."]);
                    
                    $admins = $pdo->query("SELECT id FROM users WHERE role_id = 2")->fetchAll();
                    $n2 = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    foreach ($admins as $admin) {
                        $n2->execute([$admin['id'], "Field staff " . $_SESSION['full_name'] . " submitted completion proof for Ticket ID: $ticketId."]);
                    }
                    
                    $successMsg = "Completion proof submitted successfully. Grievance sent to GP Admin for review.";
                } else {
                    $errorMsg = "Unauthorized action or invalid ticket status.";
                }
            } catch (PDOException $e) {
                $errorMsg = "Database error: " . $e->getMessage();
            }
        }
    }
}

// 1. Fetch Summary Statistics
try {
    $statsStmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN status_id = 2 THEN 1 ELSE 0 END) as assigned_jobs,
            SUM(CASE WHEN status_id = 3 AND after_image_path IS NULL THEN 1 ELSE 0 END) as active_jobs,
            SUM(CASE WHEN status_id = 3 AND after_image_path IS NOT NULL THEN 1 ELSE 0 END) as waiting_verification,
            SUM(CASE WHEN status_id = 4 THEN 1 ELSE 0 END) as completed_jobs
        FROM complaints 
        WHERE assigned_officer_id = ?
    ");
    $statsStmt->execute([$userId]);
    $stats = $statsStmt->fetch();
} catch (PDOException $e) {
    $stats = ['assigned_jobs' => 0, 'active_jobs' => 0, 'waiting_verification' => 0, 'completed_jobs' => 0];
}

// 2. Fetch Assigned Tasks
try {
    $listStmt = $pdo->prepare("
        SELECT c.*, cs.display_name AS status_name, cc.category_name, u.full_name AS citizen_name, u.phone AS phone
        FROM complaints c
        JOIN complaint_statuses cs ON c.status_id = cs.id
        JOIN complaint_categories cc ON c.category_id = cc.id
        JOIN users u ON c.citizen_id = u.id
        WHERE c.assigned_officer_id = ? AND c.status_id IN (2, 3, 4)
        ORDER BY c.updated_at DESC
    ");
    $listStmt->execute([$userId]);
    $myTasks = $listStmt->fetchAll();
} catch (PDOException $e) {
    $myTasks = [];
}

// 3. Fetch Notification alerts
try {
    $logsStmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 4
    ");
    $logsStmt->execute([$userId]);
    $logs = $logsStmt->fetchAll();
} catch (PDOException $e) {
    $logs = [];
}

include 'includes/header.php';
?>

<!-- Page Title Block -->
<div class="page-header">
    <div>
        <h2>Dashboard</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 3px;">Welcome back, Maintenance Staff!</p>
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
    <!-- Card 1: Assigned Work -->
    <div class="stat-card stat-total">
        <div class="stat-card-body">
            <div class="stat-icon"><i class="fas fa-file-signature"></i></div>
            <div class="stat-info">
                <h3>Assigned Work</h3>
                <div class="stat-value"><?= intval($stats['assigned_jobs'] ?? 0) ?></div>
            </div>
        </div>
        <div class="stat-card-footer">
            <a href="#assigned-section">View tasks <i class="fas fa-chevron-right"></i></a>
        </div>
    </div>
    
    <!-- Card 2: Active Progress -->
    <div class="stat-card stat-progress">
        <div class="stat-card-body">
            <div class="stat-icon"><i class="fas fa-spinner fa-spin" style="animation-duration: 3s;"></i></div>
            <div class="stat-info">
                <h3>Active Progress</h3>
                <div class="stat-value"><?= intval($stats['active_jobs'] ?? 0) ?></div>
            </div>
        </div>
        <div class="stat-card-footer">
            <a href="#assigned-section">View details <i class="fas fa-chevron-right"></i></a>
        </div>
    </div>

    <!-- Card 3: Awaiting Verification -->
    <div class="stat-card stat-pending">
        <div class="stat-card-body">
            <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
            <div class="stat-info">
                <h3>Awaiting Approval</h3>
                <div class="stat-value"><?= intval($stats['waiting_verification'] ?? 0) ?></div>
            </div>
        </div>
        <div class="stat-card-footer">
            <a href="#assigned-section">View details <i class="fas fa-chevron-right"></i></a>
        </div>
    </div>

    <!-- Card 4: Resolved Tasks -->
    <div class="stat-card stat-resolved">
        <div class="stat-card-body">
            <div class="stat-icon"><i class="fas fa-check-double"></i></div>
            <div class="stat-info">
                <h3>Resolved Tasks</h3>
                <div class="stat-value"><?= intval($stats['completed_jobs'] ?? 0) ?></div>
            </div>
        </div>
        <div class="stat-card-footer">
            <a href="#assigned-section">View details <i class="fas fa-chevron-right"></i></a>
        </div>
    </div>
</div>

<!-- Main Layout Grid (2 Columns: Tasks Left, Notifications Right) -->
<div class="dashboard-layout-grid" style="grid-template-columns: 2fr 1.2fr; gap: 20px;">
    
    <!-- Left Column: Tasks Log Table -->
    <div class="table-card" id="assigned-section">
        <div class="table-card-header">
            <h3>My Assigned Grievance Tasks</h3>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Citizen Details</th>
                        <th>Complaint Title</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($myTasks)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <i class="fas fa-check-circle" style="font-size: 2rem; display:block; margin-bottom:10px; color:var(--success);"></i>
                                You do not have any pending tasks assigned.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($myTasks as $task): ?>
                            <tr>
                                <td style="font-weight: 600; color: var(--accent);"><?= htmlspecialchars($task['ticket_id']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($task['citizen_name']) ?></strong><br>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);"><i class="fas fa-phone-alt"></i> <?= htmlspecialchars($task['phone']) ?></span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($task['title']) ?></strong><br>
                                    <span style="font-size: 0.75rem; color: var(--text-muted); background-color: #f7fafc; border:1px solid #edf2f7; padding: 1px 6px; border-radius: 4px;"><?= htmlspecialchars($task['category_name']) ?></span>
                                </td>
                                <td><i class="fas fa-map-marker-alt" style="color:var(--error);"></i> <?= htmlspecialchars($task['location']) ?></td>
                                <td>
                                    <span class="badge 
                                        <?php 
                                            if ($task['status_name'] == 'Pending') echo 'badge-pending';
                                            elseif ($task['status_name'] == 'Assigned') echo 'badge-assigned';
                                            elseif ($task['status_name'] == 'In Progress') {
                                                if ($task['after_image_path']) echo 'badge-pending';
                                                else echo 'badge-in_progress';
                                            }
                                            elseif ($task['status_name'] == 'Resolved') echo 'badge-resolved';
                                            else echo 'badge-rejected';
                                        ?>
                                    ">
                                        <?php 
                                            if ($task['status_name'] === 'In Progress' && $task['after_image_path']) {
                                                echo 'Awaiting Approval';
                                            } else {
                                                echo htmlspecialchars($task['status_name']);
                                            }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="viewComplaintDetails(<?= htmlspecialchars(json_encode($task)) ?>)"><i class="far fa-eye"></i> View</button>
                                    
                                    <?php if ($task['status_name'] === 'Assigned'): ?>
                                        <a href="officer_dashboard.php?start_work=<?= $task['id'] ?>#assigned-section" class="btn btn-info btn-sm" style="background-color:var(--accent);"><i class="fas fa-play"></i> Start Work</a>
                                    <?php elseif ($task['status_name'] === 'In Progress' && empty($task['after_image_path'])): ?>
                                        <button class="btn btn-secondary btn-sm" onclick="openCompleteModal(<?= $task['id'] ?>, '<?= $task['ticket_id'] ?>')"><i class="fas fa-check"></i> Mark Complete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Column: Notifications list -->
    <div class="card" style="display:flex; flex-direction:column; justify-content:space-between;">
        <div class="card-header-flex">
            <h3>Recent Updates</h3>
            <a href="notifications.php">View All</a>
        </div>
        
        <div class="bullet-list" style="overflow-y:auto; max-height: 280px;">
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

<!-- Modal: Mark Task Complete -->
<div class="modal-overlay" id="complete-modal">
    <div class="modal-container" style="max-width: 440px;">
        <div class="modal-header">
            <h3>Submit Work Completion</h3>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <form action="officer_dashboard.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="action" value="complete_work">
            <input type="hidden" name="complaint_id" id="complete-complaint-id">

            <div class="modal-body">
                <p style="margin-bottom: 15px; font-size:0.9rem;">Submit repair proof photos for ticket <strong id="complete-ticket-display" style="color: var(--accent);"></strong>.</p>
                
                <div class="form-group">
                    <label for="after_image">Upload After Maintenance Photo *</label>
                    <input type="file" name="after_image" id="after_image" class="form-control" accept="image/*" required>
                    <span class="form-text">Upload a picture of the resolved area. Max size: 5MB.</span>
                </div>

                <div class="form-group">
                    <label for="officer_remarks">Completion Remarks *</label>
                    <textarea name="officer_remarks" id="officer_remarks" class="form-control" rows="3" placeholder="Explain the resolution work performed..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('complete-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Submit Proof</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: View Details (Read Only) -->
<div class="modal-overlay" id="view-modal">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Task Details Overview</h3>
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
                    <strong>Submitting Citizen:</strong>
                    <span id="detail-citizen" style="display:block; font-size:0.9rem;"></span>
                </div>
                <div>
                    <strong>Location / Ward:</strong>
                    <span id="detail-location" style="display:block; font-size:0.9rem;"></span>
                </div>
                <div>
                    <strong>Category:</strong>
                    <span id="detail-category" style="display:block; font-size:0.9rem;"></span>
                </div>
                <div>
                    <strong>Submitted Date:</strong>
                    <span id="detail-date" style="display:block; font-size:0.9rem;"></span>
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

<script>
function openCompleteModal(id, ticket) {
    document.getElementById('complete-complaint-id').value = id;
    document.getElementById('complete-ticket-display').innerText = ticket;
    document.getElementById('officer_remarks').value = '';
    document.getElementById('after_image').value = '';
    openModal('complete-modal');
}

function viewComplaintDetails(comp) {
    document.getElementById('detail-ticket-id').innerText = comp.ticket_id;
    document.getElementById('detail-title').innerText = comp.title;
    document.getElementById('detail-category').innerText = comp.category_name;
    document.getElementById('detail-location').innerText = comp.location;
    document.getElementById('detail-description').innerText = comp.description;
    document.getElementById('detail-citizen').innerText = comp.citizen_name;
    
    const dateSubmitted = new Date(comp.created_at);
    document.getElementById('detail-date').innerText = dateSubmitted.toLocaleDateString('en-IN', {day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true});

    const badge = document.getElementById('detail-status');
    if (comp.status_name === 'In Progress' && comp.after_image_path) {
        badge.innerText = 'Awaiting Approval';
        badge.className = 'badge badge-pending';
    } else {
        badge.innerText = comp.status_name;
        badge.className = 'badge'; 
        if (comp.status_name === 'Pending') badge.classList.add('badge-pending');
        else if (comp.status_name === 'Assigned') badge.classList.add('badge-assigned');
        else if (comp.status_name === 'In Progress') badge.classList.add('badge-in_progress');
        else if (comp.status_name === 'Resolved') badge.classList.add('badge-resolved');
        else badge.classList.add('badge-rejected');
    }

    const beforePhotoDiv = document.getElementById('detail-before-photo');
    if (comp.image_path) {
        beforePhotoDiv.innerHTML = `<img src="${comp.image_path}" class="complaint-img-preview" alt="Submitted Photo" onclick="window.open('${comp.image_path}', '_blank')">`;
    } else {
        beforePhotoDiv.innerHTML = `<div style="padding: 15px; background: #f7fafc; border-radius: 4px; text-align: center; color:#888; font-size:0.85rem;">No photo attached</div>`;
    }

    const afterPhotoDiv = document.getElementById('detail-after-photo');
    if (comp.after_image_path) {
        afterPhotoDiv.innerHTML = `<img src="${comp.after_image_path}" class="complaint-img-preview" alt="Completion Proof Photo" onclick="window.open('${comp.after_image_path}', '_blank')">`;
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
</script>

<?php
include 'includes/footer.php';
?>
