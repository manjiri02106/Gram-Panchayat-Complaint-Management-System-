<?php
// index.php
// Gram Panchayat Complaint Management System - Public Landing Page

require_once 'includes/auth.php';
require_once 'includes/db.php';

// Handle Complaint Status Tracking
$trackedComplaint = null;
$trackingError = null;
$ticketIdInput = '';

if (isset($_GET['ticket_id'])) {
    $ticketIdInput = sanitize($_GET['ticket_id']);
    if (!empty($ticketIdInput)) {
        try {
            $trackStmt = $pdo->prepare("
                SELECT c.*, cs.display_name AS status_name, cc.category_name, u.full_name AS citizen_name, fo.full_name AS officer_name
                FROM complaints c
                JOIN complaint_statuses cs ON c.status_id = cs.id
                JOIN complaint_categories cc ON c.category_id = cc.id
                JOIN users u ON c.citizen_id = u.id
                LEFT JOIN users fo ON c.assigned_officer_id = fo.id
                WHERE c.ticket_id = ?
            ");
            $trackStmt->execute([$ticketIdInput]);
            $trackedComplaint = $trackStmt->fetch();
            
            if (!$trackedComplaint) {
                $trackingError = "No complaint found with Ticket ID: " . htmlspecialchars($ticketIdInput);
            }
        } catch (PDOException $e) {
            $trackingError = "Database search error. Please try again.";
        }
    }
}

// Fetch Public Announcements
try {
    $announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 4")->fetchAll();
} catch (PDOException $e) {
    $announcements = [];
}

// Fetch Government Schemes
try {
    $schemes = $pdo->query("SELECT * FROM government_schemes ORDER BY created_at DESC LIMIT 4")->fetchAll();
} catch (PDOException $e) {
    $schemes = [];
}

// Fetch Emergency Contacts
try {
    $contacts = $pdo->query("SELECT * FROM emergency_contacts ORDER BY id ASC LIMIT 6")->fetchAll();
} catch (PDOException $e) {
    $contacts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gram Panchayat Complaint Management System (GPCMS)</title>
    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <!-- Navigation Header matching mockup template -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-logo">
                <!-- Custom SVG Panchayat Emblem -->
                <svg viewBox="0 0 100 100" width="46" height="46" style="flex-shrink:0;">
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
                <div class="logo-text">
                    <h1>Gram Panchayat</h1>
                    <span style="font-weight:700; color:var(--primary); font-size:0.85rem;">Complaint Management System</span>
                    <span style="font-size: 0.65rem; color: var(--text-muted); font-style:italic;">Building a Better Village Together</span>
                </div>
            </div>
            <div class="nav-links">
                <a href="index.php" class="nav-link active">Home</a>
                <a href="#about-section" class="nav-link">About Us</a>
                <a href="#announcements-section" class="nav-link">Public Announcements</a>
                <a href="#schemes-section" class="nav-link">Schemes</a>
                <a href="#footer-contact-section" class="nav-link">Contact Us</a>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?php 
                        if ($_SESSION['role_name'] == 'super_admin') echo 'superadmin_dashboard.php';
                        elseif ($_SESSION['role_name'] == 'gp_admin') echo 'admin_dashboard.php';
                        elseif ($_SESSION['role_name'] == 'field_officer') echo 'officer_dashboard.php';
                        else echo 'citizen_dashboard.php';
                    ?>" class="btn btn-primary btn-sm"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary btn-sm" style="background-color:var(--primary-dark);"><i class="fas fa-user"></i> Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Banner with 2 Column Grid (Slogans Left, Track Card Right) -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h2>Welcome to<br><span>Gram Panchayat</span><br>Complaint Management System</h2>
                <p>Your voice, Our responsibility. Together we build a better village.</p>
                <div class="hero-actions">
                    <a href="citizen_dashboard.php" class="btn btn-secondary"><i class="fas fa-user-plus"></i> Register Complaint</a>
                    <a href="#tracking-section" class="btn btn-outline" style="border-color:#ffffff; color:#ffffff;"><i class="fas fa-search"></i> Track Complaint</a>
                </div>
            </div>

            <!-- Floating Track Your Complaint Card -->
            <div class="track-card" id="tracking-section">
                <div class="track-card-header">
                    <h3>Track Your Complaint</h3>
                </div>
                <div class="track-card-body">
                    <form action="index.php#tracking-section" method="GET">
                        <div class="track-input-group">
                            <input type="text" name="ticket_id" class="form-control" placeholder="Enter Complaint ID" required value="<?= htmlspecialchars($ticketIdInput) ?>" style="text-transform: uppercase;">
                            <button type="submit" class="btn btn-primary">Track Status</button>
                        </div>
                    </form>

                    <h4>Know your complaint status</h4>
                    
                    <!-- Progress Timeline representation -->
                    <div class="timeline-row">
                        <div class="timeline-line"></div>
                        
                        <?php
                            $step = 1;
                            if ($trackedComplaint) {
                                if ($trackedComplaint['status_name'] == 'Assigned') $step = 2;
                                elseif ($trackedComplaint['status_name'] == 'In Progress') $step = 3;
                                elseif ($trackedComplaint['status_name'] == 'Resolved') $step = 4;
                            }
                        ?>

                        <!-- Node 1: Registered -->
                        <div class="timeline-node <?= $step >= 1 ? 'active' : '' ?>">
                            <div class="timeline-circle"><i class="fas fa-edit"></i></div>
                            <span class="timeline-label">Registered</span>
                        </div>

                        <!-- Node 2: In Progress -->
                        <div class="timeline-node <?= $step >= 2 ? ($step == 2 ? 'warning' : 'active') : '' ?>">
                            <div class="timeline-circle"><i class="fas fa-cog fa-spin" style="animation-duration: 4s;"></i></div>
                            <span class="timeline-label">In Progress</span>
                        </div>

                        <!-- Node 3: Under Review -->
                        <div class="timeline-node <?= $step >= 3 ? ($step == 3 ? 'warning' : 'active') : '' ?>">
                            <div class="timeline-circle"><i class="far fa-eye"></i></div>
                            <span class="timeline-label">Under Review</span>
                        </div>

                        <!-- Node 4: Resolved -->
                        <div class="timeline-node <?= $step == 4 ? 'resolved' : '' ?>">
                            <div class="timeline-circle"><i class="fas fa-check"></i></div>
                            <span class="timeline-label">Resolved</span>
                        </div>
                    </div>

                    <!-- Tracking Result Details (If tracked) -->
                    <?php if ($trackedComplaint): ?>
                        <div style="margin-top: 25px; padding: 15px; border-top: 1px dashed var(--border-color); font-size: 0.85rem;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                <strong style="color:var(--primary); font-size:0.95rem;"><?= htmlspecialchars($trackedComplaint['ticket_id']) ?></strong>
                                <span class="badge 
                                    <?php 
                                        if ($trackedComplaint['status_name'] == 'Pending') echo 'badge-pending';
                                        elseif ($trackedComplaint['status_name'] == 'Assigned') echo 'badge-assigned';
                                        elseif ($trackedComplaint['status_name'] == 'In Progress') echo 'badge-in_progress';
                                        elseif ($trackedComplaint['status_name'] == 'Resolved') echo 'badge-resolved';
                                        else echo 'badge-rejected';
                                    ?>
                                "><?= htmlspecialchars($trackedComplaint['status_name']) ?></span>
                            </div>
                            <p style="margin-bottom:5px;"><strong>Subject:</strong> <?= htmlspecialchars($trackedComplaint['title']) ?></p>
                            <p style="margin-bottom:5px;"><strong>Location:</strong> <?= htmlspecialchars($trackedComplaint['location']) ?></p>
                            <p style="margin-bottom:5px;"><strong>Submitted On:</strong> <?= date('d M Y', strtotime($trackedComplaint['created_at'])) ?></p>
                            <?php if ($trackedComplaint['admin_remarks']): ?>
                                <p style="margin-top:8px; padding:8px; background-color:#f7fafc; border-left:3px solid var(--primary); font-style:italic;">
                                    <strong>Admin Remarks:</strong> "<?= htmlspecialchars($trackedComplaint['admin_remarks']) ?>"
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($trackingError): ?>
                        <div class="alert alert-danger" style="margin-top: 20px;">
                            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($trackingError) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Grid Cards Section (4 cards matching template) -->
    <section class="section" style="background-color: var(--bg-main);">
        <div class="container grid-4">
            
            <!-- Card 1: About Gram Panchayat -->
            <div class="card about-card" id="about-section">
                <div class="section-title">
                    <h2><i class="far fa-user-circle"></i> About Gram Panchayat</h2>
                </div>
                <div style="width:100%; height:130px; background-color:#edf2f7; border-radius:6px; display:flex; align-items:center; justify-content:center; margin-bottom:15px;">
                    <i class="fas fa-landmark" style="font-size:3rem; color:var(--primary); opacity:0.3;"></i>
                </div>
                <p>Gram Panchayat is the cornerstone of rural development and self-governance. We work for the welfare of every citizen and the overall development of our village.</p>
                <a href="#about-section" class="btn btn-primary btn-sm" style="width:100%; justify-content:center;">Read More</a>
            </div>

            <!-- Card 2: Public Announcements -->
            <div class="card" id="announcements-section">
                <div class="section-title">
                    <h2><i class="fas fa-bullhorn"></i> Public Announcements</h2>
                    <a href="#announcements-section">View All</a>
                </div>
                <div class="announcements-table-list">
                    <?php if (empty($announcements)): ?>
                        <p style="color:var(--text-muted); font-size:0.85rem; text-align:center; padding:30px 0;">No active announcements.</p>
                    <?php else: ?>
                        <?php 
                        // Set nice icon circle backgrounds
                        $colors = ['#fce8e6', '#e3f2fd', '#fff3e0', '#e8f5e9'];
                        $icons = ['fa-bullhorn', 'fa-tint', 'fa-road', 'fa-briefcase-medical'];
                        $textColors = ['#c62828', '#1565c0', '#ef6c00', '#2e7d32'];
                        
                        foreach ($announcements as $idx => $ann): 
                            $color = $colors[$idx % count($colors)];
                            $icon = $icons[$idx % count($icons)];
                            $tColor = $textColors[$idx % count($textColors)];
                        ?>
                            <div class="announcement-item-small">
                                <div class="ann-icon-circle" style="background-color: <?= $color ?>; color: <?= $tColor ?>;">
                                    <i class="fas <?= $icon ?>"></i>
                                </div>
                                <div class="ann-content-small">
                                    <h4><?= htmlspecialchars($ann['title']) ?></h4>
                                    <span><?= date('M d, Y', strtotime($ann['created_at'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card 3: Government Schemes -->
            <div class="card" id="schemes-section">
                <div class="section-title">
                    <h2><i class="fas fa-gift"></i> Government Schemes</h2>
                    <a href="#schemes-section">View All</a>
                </div>
                <div class="schemes-small-grid">
                    <div class="scheme-small-box" onclick="window.open('https://pmay-g.nic.in', '_blank')">
                        <i class="fas fa-home"></i>
                        <h4>PM Awas Yojana</h4>
                    </div>
                    <div class="scheme-small-box" onclick="window.open('https://pmkisan.gov.in', '_blank')">
                        <i class="fas fa-wheat-awn" style="color:#f57c00;"></i>
                        <h4>PM Kisan Samman</h4>
                    </div>
                    <div class="scheme-small-box" onclick="window.open('https://jaljeevanmission.gov.in', '_blank')">
                        <i class="fas fa-tint" style="color:#1976d2;"></i>
                        <h4>Jal Jeevan Mission</h4>
                    </div>
                    <div class="scheme-small-box" onclick="window.open('https://ayushmanbharat.mp.gov.in', '_blank')">
                        <i class="fas fa-users-medical" style="color:#2e7d32;"></i>
                        <h4>Ayushman Bharat</h4>
                    </div>
                </div>
            </div>

            <!-- Card 4: Emergency Contacts -->
            <div class="card" id="contacts-section">
                <div class="section-title">
                    <h2><i class="fas fa-phone-alt"></i> Emergency Contacts</h2>
                </div>
                <div class="contacts-table-list">
                    <div class="contact-row-small">
                        <span class="name">Police</span>
                        <span class="number">100</span>
                    </div>
                    <div class="contact-row-small">
                        <span class="name">Ambulance</span>
                        <span class="number">108</span>
                    </div>
                    <div class="contact-row-small">
                        <span class="name">Fire Brigade</span>
                        <span class="number">101</span>
                    </div>
                    <div class="contact-row-small">
                        <span class="name">Electricity Department</span>
                        <span class="number">1912</span>
                    </div>
                    <div class="contact-row-small">
                        <span class="name">Water Supply Department</span>
                        <span class="number">1800-123-4567</span>
                    </div>
                    <div class="contact-row-small">
                        <span class="name">Gram Panchayat Office</span>
                        <span class="number">1234567890</span>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- Call to Action Cream-Colored Banner matching template -->
    <section class="cta-banner">
        <div class="container">
            <div class="cta-left">
                <!-- Custom Inline SVG Minimal Family Outline Drawing -->
                <svg viewBox="0 0 100 60" width="80" height="50">
                    <!-- Father -->
                    <circle cx="30" cy="20" r="7" fill="#F57C00"/>
                    <path d="M20,50 C20,32 40,32 40,50 Z" fill="#2E7D32"/>
                    <!-- Mother -->
                    <circle cx="70" cy="22" r="6" fill="#F57C00"/>
                    <path d="M60,50 C60,34 80,34 80,50 Z" fill="#1976D2"/>
                    <!-- Child -->
                    <circle cx="50" cy="28" r="5" fill="#F57C00"/>
                    <path d="M43,50 C43,40 57,40 57,50 Z" fill="#FFB74D"/>
                </svg>
                <div class="cta-text">
                    <h3>Register your complaint and help us serve you better.</h3>
                    <p>Your feedback is important for the development of our village.</p>
                </div>
            </div>
            <div>
                <a href="citizen_dashboard.php" class="btn btn-primary" style="background-color:#1e5624;"><i class="fas fa-edit"></i> Register Complaint Now</a>
            </div>
        </div>
    </section>

    <!-- Public Footer matching the 5 Column Layout mockup -->
    <footer class="footer" id="footer-contact-section">
        <div class="container footer-grid">
            <!-- Col 1: Gram Panchayat Name -->
            <div class="footer-col">
                <h3 style="color:#ffffff;">Gram Panchayat</h3>
                <p>Working for a clean, green, and developed village.</p>
                <div class="footer-socials">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
            
            <!-- Col 2: Quick Links -->
            <div class="footer-col">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="#about-section">About Us</a></li>
                    <li><a href="#announcements-section">Public Announcements</a></li>
                    <li><a href="#schemes-section">Government Schemes</a></li>
                    <li><a href="#footer-contact-section">Contact Us</a></li>
                </ul>
            </div>

            <!-- Col 3: Other Services -->
            <div class="footer-col">
                <h3>Other Services</h3>
                <ul>
                    <li><a href="citizen_dashboard.php">Register Complaint</a></li>
                    <li><a href="#tracking-section">Track Complaint</a></li>
                    <li><a href="#">Download Forms</a></li>
                    <li><a href="#">Gallery</a></li>
                </ul>
            </div>

            <!-- Col 4: Contact details -->
            <div class="footer-col">
                <h3>Contact Us</h3>
                <p><i class="fas fa-map-marker-alt" style="color:var(--secondary); margin-right:5px;"></i> Gram Panchayat Office, Village Name, Taluka, District</p>
                <p><i class="fas fa-phone-alt" style="color:var(--secondary); margin-right:5px;"></i> 1234567890</p>
                <p><i class="far fa-envelope" style="color:var(--secondary); margin-right:5px;"></i> gpanchayat@gmail.com</p>
                <p><i class="far fa-clock" style="color:var(--secondary); margin-right:5px;"></i> Mon - Sat (10:00 AM - 5:00 PM)</p>
            </div>

            <!-- Col 5: Important Links -->
            <div class="footer-col">
                <h3>Important Links</h3>
                <ul>
                    <li><a href="https://india.gov.in" target="_blank">Government of India</a></li>
                    <li><a href="https://maharashtra.gov.in" target="_blank">Maharashtra Government</a></li>
                    <li><a href="https://panchayat.gov.in" target="_blank">Zilla Parishad</a></li>
                    <li><a href="https://panchayat.gov.in" target="_blank">Grampanchayat Portal</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="container">
                <p>&copy; <?= date('Y') ?> Gram Panchayat. All Rights Reserved.</p>
                <div class="footer-bottom-links">
                    <a href="#">Privacy Policy</a> | 
                    <a href="#">Terms & Conditions</a>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
