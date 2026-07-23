<?php
// admin_complaints.php
// Gram Panchayat Complaint Management System - Admin Complaints List Page

require_once 'includes/auth.php';
require_once 'includes/db.php';

// Enforce GP Admin or Super Admin Role
check_role(['gp_admin', 'super_admin']);

$userId = $_SESSION['user_id'];
$successMsg = null;
$errorMsg = null;

// Handle Complaint Actions (Reuse Same Logic as Dashboard for Consistency)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'assign_complaint') {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $complaintId = intval($_POST['complaint_id']);
        $officerId = intval($_POST['officer_id']);
        $adminRemarks = sanitize($_POST['admin_remarks']);
        
        if (!empty($complaintId) && !empty($officerId)) {
            try {
                $offStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ? AND role_id = 3");
                $offStmt->execute([$officerId]);
                $officer = $offStmt->fetch();
                
                if ($officer) {
                    $stmt = $pdo->prepare("UPDATE complaints SET assigned_officer_id = ?, status_id = 2, admin_remarks = ? WHERE id = ? AND status_id = 1");
                    $stmt->execute([$officerId, $adminRemarks, $complaintId]);
                    
                    $compStmt = $pdo->prepare("SELECT ticket_id, citizen_id, title FROM complaints WHERE id = ?");
                    $compStmt->execute([$complaintId]);
                    $complaint = $compStmt->fetch();
                    
                    if ($complaint) {
                        $ticketId = $complaint['ticket_id'];
                        $citizenId = $complaint['citizen_id'];
                        
                        $notif1 = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                        $notif1->execute([$citizenId, "Your grievance task (Ticket ID: $ticketId) has been assigned to Field Staff " . $officer['full_name'] . " for maintenance."]);
                        
                        $notif2 = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                        $notif2->execute([$officerId, "You have been assigned a new task: " . $complaint['title'] . " (Ticket ID: $ticketId)"]);
                    }
                    $successMsg = "Complaint successfully assigned to " . htmlspecialchars($officer['full_name']);
                }
            } catch (PDOException $e) {
                $errorMsg = "Database error: " . $e->getMessage();
            }
        }
    }
    
    if ($_POST['action'] === 'verify_work') {
        verify_csrf_token($_POST['csrf_token'] ?? '');
        $complaintId = intval($_POST['complaint_id']);
        $decision = sanitize($_POST['decision']);
        $adminRemarks = sanitize($_POST['admin_remarks']);
        
        if (!empty($complaintId) && !empty($decision)) {
            try {
                $compStmt = $pdo->prepare("SELECT ticket_id, citizen_id, assigned_officer_id FROM complaints WHERE id = ?");
                $compStmt->execute([$complaintId]);
                $complaint = $compStmt->fetch();
                
                if ($complaint) {
                    $ticketId = $complaint['ticket_id'];
                    $citizenId = $complaint['citizen_id'];
                    $officerId = $complaint['assigned_officer_id'];
                    
                    if ($decision === 'resolve') {
                        $stmt = $pdo->prepare("UPDATE complaints SET status_id = 4, admin_remarks = ? WHERE id = ?");
                        $stmt->execute([$adminRemarks, $complaintId]);
                        
                        $n1 = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                        $n1->execute([$citizenId, "Grievance solved! Ticket ID $ticketId was verified and marked as Resolved. Please provide your feedback rating."]);
                        
                        if ($officerId) {
                            $n2 = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                            $n2->execute([$officerId, "Good job! The work on Ticket ID $ticketId was verified and marked as Resolved."]);
                        }
                        $successMsg = "Work verified. Ticket ID $ticketId marked as Resolved.";
                    } elseif ($decision === 'reopen') {
                        $stmt = $pdo->prepare("UPDATE complaints SET status_id = 3, admin_remarks = ? WHERE id = ?");
                        $stmt->execute([$adminRemarks, $complaintId]);
                        
                        if ($officerId) {
                            $n2 = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                            $n2->execute([$officerId, "Task reopened: GP Admin requested revisions on Ticket ID $ticketId. Remarks: $adminRemarks"]);
                        }
                        $successMsg = "Ticket reopened. Re-evaluation request sent back to the assigned field officer.";
                    }
                }
            } catch (PDOException $e) {
                $errorMsg = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Filtering Parameters
$statusFilter = isset($_GET['status']) ? intval($_GET['status']) : 0;
$categoryFilter = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Pagination Configuration
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Construct SQL Query based on filters
$whereClauses = [];
$queryParams = [];

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
    // Count Total Row Count for Pagination
    $countQuery = "SELECT COUNT(*) FROM complaints c" . $whereSQL;
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($queryParams);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    // Fetch Complaints List
    $listQuery = "
        SELECT c.*, cs.display_name AS status_name, cc.category_name, u.full_name AS citizen_name, u.phone AS phone, fo.full_name AS officer_name
        FROM complaints c
        JOIN complaint_statuses cs ON c.status_id = cs.id
        JOIN complaint_categories cc ON c.category_id = cc.id
        JOIN users u ON c.citizen_id = u.id
        LEFT JOIN users fo ON c.assigned_officer_id = fo.id
        " . $whereSQL . "
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    // In PDO, LIMIT and OFFSET parameters should be passed as integers when emulation is off.
    // We bind them manually:
    $stmt = $pdo->prepare($listQuery);
    
    $bindIndex = 1;
    foreach ($queryParams as $param) {
        $stmt->bindValue($bindIndex++, $param);
    }
    $stmt->bindValue($bindIndex++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($bindIndex++, $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $complaints = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $complaints = [];
    $totalRecords = 0;
    $totalPages = 1;
    $errorMsg = "Database error: " . $e->getMessage();
}

// Fetch Filter Lists
try {
    $statuses = $pdo->query("SELECT * FROM complaint_statuses ORDER BY id ASC")->fetchAll();
    $categories = $pdo->query("SELECT * FROM complaint_categories ORDER BY category_name ASC")->fetchAll();
    $officers = $pdo->query("SELECT id, full_name FROM users WHERE role_id = 3 ORDER BY full_name ASC")->fetchAll();
} catch (PDOException $e) {
    $statuses = [];
    $categories = [];
    $officers = [];
}

include 'includes/header.php';
?>

<!-- Page Title Block -->
<div class="page-header">
    <div>
        <h2>Manage Grievance Tickets</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 3px;">Total database records found: <strong><?= $totalRecords ?></strong> complaints</p>
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

<!-- Filters Form Card -->
<div class="card" style="margin-bottom: 25px; padding: 15px 25px;">
    <form action="admin_complaints.php" method="GET" style="display:flex; gap:20px; align-items:flex-end; flex-wrap:wrap;">
        <div class="form-group" style="margin-bottom:0; flex-grow:1; min-width:200px;">
            <label for="status" style="font-size:0.8rem; font-weight:600;">FILTER BY STATUS</label>
            <select name="status" id="status" class="form-control" style="height:38px;">
                <option value="0">All Statuses</option>
                <?php foreach ($statuses as $st): ?>
                    <option value="<?= $st['id'] ?>" <?= $statusFilter === $st['id'] ? 'selected' : '' ?>><?= htmlspecialchars($st['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group" style="margin-bottom:0; flex-grow:1; min-width:200px;">
            <label for="category" style="font-size:0.8rem; font-weight:600;">FILTER BY CATEGORY</label>
            <select name="category" id="category" class="form-control" style="height:38px;">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoryFilter === $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn btn-primary" style="height:38px;"><i class="fas fa-filter"></i> Apply Filters</button>
            <a href="admin_complaints.php" class="btn btn-outline" style="height:38px;"><i class="fas fa-undo"></i> Reset</a>
        </div>
    </form>
</div>

<!-- Complaints Log Card -->
<div class="table-card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Ticket ID</th>
                    <th>Citizen</th>
                    <th>Complaint Title</th>
                    <th>Submitted</th>
                    <th>Assigned Staff</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($complaints)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 30px; color: var(--text-muted);">
                            <i class="fas fa-folder-open" style="font-size: 2rem; display:block; margin-bottom:10px;"></i>
                            No complaints match the specified filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($complaints as $comp): ?>
                        <tr>
                            <td style="font-weight: 600; color: var(--accent);"><?= htmlspecialchars($comp['ticket_id']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($comp['citizen_name']) ?></strong><br>
                                <span style="font-size: 0.75rem; color: var(--text-muted);"><i class="fas fa-phone"></i> <?= htmlspecialchars($comp['phone']) ?></span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($comp['title']) ?></strong><br>
                                <span style="font-size: 0.75rem; color: var(--text-muted); background-color: #eee; padding: 2px 6px; border-radius: 4px;"><?= htmlspecialchars($comp['category_name']) ?></span>
                            </td>
                            <td><?= date('d M Y', strtotime($comp['created_at'])) ?></td>
                            <td>
                                <?php if ($comp['officer_name']): ?>
                                    <span style="font-weight: 500;"><i class="fas fa-user"></i> <?= htmlspecialchars($comp['officer_name']) ?></span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-style: italic;">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge 
                                    <?php 
                                        if ($comp['status_name'] == 'Pending') echo 'badge-pending';
                                        elseif ($comp['status_name'] == 'Assigned') echo 'badge-assigned';
                                        elseif ($comp['status_name'] == 'In Progress') echo 'badge-in_progress';
                                        elseif ($comp['status_name'] == 'Resolved') echo 'badge-resolved';
                                        else echo 'badge-rejected';
                                    ?>
                                ">
                                    <?= htmlspecialchars($comp['status_name']) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="viewComplaintDetails(<?= htmlspecialchars(json_encode($comp)) ?>)"><i class="far fa-eye"></i> View</button>
                                
                                <?php if ($comp['status_name'] === 'Pending'): ?>
                                    <button class="btn btn-secondary btn-sm" onclick="openAssignModal(<?= $comp['id'] ?>, '<?= $comp['ticket_id'] ?>')"><i class="fas fa-user-plus"></i> Assign</button>
                                <?php elseif ($comp['status_name'] === 'In Progress' && !empty($comp['after_image_path'])): ?>
                                    <button class="btn btn-accent btn-sm" onclick="openVerifyModal(<?= $comp['id'] ?>, '<?= $comp['ticket_id'] ?>', '<?= $comp['after_image_path'] ?>', '<?= htmlspecialchars($comp['officer_remarks'] ?? '') ?>')"><i class="fas fa-user-check"></i> Verify</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <div class="page-item"><a href="admin_complaints.php?page=<?= $page - 1 ?>&status=<?= $statusFilter ?>&category=<?= $categoryFilter ?>" class="page-link">&laquo;</a></div>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <div class="page-item <?= $page === $i ? 'active' : '' ?>">
                    <a href="admin_complaints.php?page=<?= $i ?>&status=<?= $statusFilter ?>&category=<?= $categoryFilter ?>" class="page-link"><?= $i ?></a>
                </div>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <div class="page-item"><a href="admin_complaints.php?page=<?= $page + 1 ?>&status=<?= $statusFilter ?>&category=<?= $categoryFilter ?>" class="page-link">&raquo;</a></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Assign Complaint -->
<div class="modal-overlay" id="assign-modal">
    <div class="modal-container" style="max-width: 450px;">
        <div class="modal-header">
            <h3>Assign Complaint Task</h3>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <form action="admin_complaints.php?status=<?= $statusFilter ?>&category=<?= $categoryFilter ?>&page=<?= $page ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="action" value="assign_complaint">
            <input type="hidden" name="complaint_id" id="assign-complaint-id">

            <div class="modal-body">
                <p style="margin-bottom: 15px; font-size:0.9rem;">Assign ticket <strong id="assign-ticket-display" style="color: var(--accent);"></strong> to a Field Maintenance Officer.</p>
                
                <div class="form-group">
                    <label for="officer_id">Select Field Staff Officer *</label>
                    <select name="officer_id" id="officer_id" class="form-control" required>
                        <option value="">-- Choose Staff Officer --</option>
                        <?php foreach ($officers as $off): ?>
                            <option value="<?= $off['id'] ?>"><?= htmlspecialchars($off['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="assign_remarks">Instructions / Admin Remarks</label>
                    <textarea name="admin_remarks" id="assign_remarks" class="form-control" rows="3" placeholder="Provide work instructions..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('assign-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Assign Task</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Verify Completed Work -->
<div class="modal-overlay" id="verify-modal">
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Verify Completed Work</h3>
            <button class="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <form action="admin_complaints.php?status=<?= $statusFilter ?>&category=<?= $categoryFilter ?>&page=<?= $page ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="action" value="verify_work">
            <input type="hidden" name="complaint_id" id="verify-complaint-id">

            <div class="modal-body">
                <p style="margin-bottom: 12px; font-size:0.9rem;">Verify maintenance completion for ticket <strong id="verify-ticket-display" style="color: var(--accent);"></strong>.</p>
                
                <div style="margin-bottom: 15px;">
                    <strong>Completion Photo Proof:</strong>
                    <div style="margin-top: 5px;">
                        <img src="" id="verify-after-photo" class="complaint-img-preview" alt="After work proof photo" style="cursor: zoom-in;" onclick="window.open(this.src, '_blank')">
                    </div>
                </div>

                <div style="margin-bottom: 15px; padding: 10px; background-color: var(--bg-main); border-radius: 4px;">
                    <strong>Officer Completion Remarks:</strong>
                    <p id="verify-officer-remarks" style="font-size:0.85rem; font-style:italic; color: var(--text-primary); margin-top:4px;"></p>
                </div>

                <div class="form-group">
                    <label style="font-weight:600; display:block; margin-bottom:8px;">Administrative Decision *</label>
                    <div style="display:flex; gap: 20px;">
                        <label style="cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
                            <input type="radio" name="decision" value="resolve" required checked>
                            <span class="badge badge-resolved" style="padding: 6px 12px;">Approve & Mark Resolved</span>
                        </label>
                        <label style="cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
                            <input type="radio" name="decision" value="reopen">
                            <span class="badge badge-rejected" style="padding: 6px 12px;">Reject & Reopen Ticket</span>
                        </label>
                    </div>
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <label for="verify_remarks">Verification Remarks / Instructions</label>
                    <textarea name="admin_remarks" id="verify_remarks" class="form-control" rows="3" placeholder="Provide verification feedback..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('verify-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check-double"></i> Submit Decision</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: View Complaint Details (Read Only) -->
<div class="modal-overlay" id="view-modal">
    <div class="modal-container" style="max-width: 650px;">
        <div class="modal-header">
            <h3>Complaint Record Overview</h3>
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
function openAssignModal(id, ticket) {
    document.getElementById('assign-complaint-id').value = id;
    document.getElementById('assign-ticket-display').innerText = ticket;
    document.getElementById('assign_remarks').value = '';
    document.getElementById('officer_id').selectedIndex = 0;
    openModal('assign-modal');
}

function openVerifyModal(id, ticket, afterPath, officerRemarks) {
    document.getElementById('verify-complaint-id').value = id;
    document.getElementById('verify-ticket-display').innerText = ticket;
    document.getElementById('verify-after-photo').src = afterPath;
    document.getElementById('verify-officer-remarks').innerText = officerRemarks ? `"${officerRemarks}"` : "None provided.";
    document.getElementById('verify_remarks').value = '';
    openModal('verify-modal');
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
    badge.innerText = comp.status_name;
    badge.className = 'badge'; 
    if (comp.status_name === 'Pending') badge.classList.add('badge-pending');
    else if (comp.status_name === 'Assigned') badge.classList.add('badge-assigned');
    else if (comp.status_name === 'In Progress') badge.classList.add('badge-in_progress');
    else if (comp.status_name === 'Resolved') badge.classList.add('badge-resolved');
    else badge.classList.add('badge-rejected');

    // Before photo
    const beforePhotoDiv = document.getElementById('detail-before-photo');
    if (comp.image_path) {
        beforePhotoDiv.innerHTML = `<img src="${comp.image_path}" class="complaint-img-preview" alt="Submitted Photo" onclick="window.open('${comp.image_path}', '_blank')">`;
    } else {
        beforePhotoDiv.innerHTML = `<div style="padding: 20px; background: #f0f0f0; border-radius: 4px; text-align: center; color:#888; font-size:0.85rem;"><i class="far fa-image" style="font-size:1.5rem; display:block; margin-bottom:5px;"></i> No photo attached</div>`;
    }

    // After photo
    const afterPhotoDiv = document.getElementById('detail-after-photo');
    if (comp.after_image_path) {
        afterPhotoDiv.innerHTML = `<img src="${comp.after_image_path}" class="complaint-img-preview" alt="Completion Proof Photo" onclick="window.open('${comp.after_image_path}', '_blank')">`;
    } else {
        afterPhotoDiv.innerHTML = `<div style="padding: 20px; background: #f0f0f0; border-radius: 4px; text-align: center; color:#888; font-size:0.85rem;"><i class="far fa-image" style="font-size:1.5rem; display:block; margin-bottom:5px;"></i> Pending completion</div>`;
    }

    // Remarks
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
