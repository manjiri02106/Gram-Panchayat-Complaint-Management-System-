<?php
// login.php
// Gram Panchayat Complaint Management System - Login Page

require_once 'includes/auth.php';
require_once 'includes/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    redirect_to_dashboard($_SESSION['role_name']);
}

$error = null;
$success = null;

if (isset($_GET['registered'])) {
    $success = "Citizen registration successful! Please login with your username.";
}
if (isset($_GET['loggedout'])) {
    $success = "You have been successfully logged out.";
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    $posted_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    verify_csrf_token($posted_token);
    
    $username = trim(sanitize($_POST['username']));
    $password = trim($_POST['password']); // Don't sanitize password to avoid modifying characters
    
    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT u.*, r.role_name, r.display_name AS role_display 
                FROM users u 
                JOIN roles r ON u.role_id = r.id 
                WHERE u.username = ?
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['role_name'] = $user['role_name'];
                $_SESSION['role_display'] = $user['role_display'];
                
                // Add a notification when logging in
                $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $notifStmt->execute([$user['id'], "Welcome back, " . $user['full_name'] . "! You logged in successfully at " . date('h:i A')]);
                
                // Redirect based on role
                redirect_to_dashboard($user['role_name']);
            } else {
                $error = "Invalid username or password. Please try again.";
            }
        } catch (PDOException $e) {
            $error = "A system error occurred. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Login - GPCMS</title>
    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background-color: var(--bg-main);">

    <!-- Simple Navbar -->
    <nav class="navbar" style="position: static; margin-bottom: 20px;">
        <div class="container" style="justify-content: center;">
            <div class="nav-logo">
                <i class="fas fa-landmark logo-icon"></i>
                <div class="logo-text">
                    <h1>GPCMS Portal</h1>
                    <span>Government of India - Rural Development</span>
                </div>
            </div>
        </div>
    </nav>

    <!-- Auth Wrapper -->
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <div style="width: 60px; height: 60px; border-radius: 50%; background-color: rgba(46, 125, 50, 0.1); display: inline-flex; align-items: center; justify-content: center;">
                    <i class="fas fa-user-lock" style="font-size: 1.8rem; color: var(--primary);"></i>
                </div>
                <h2>Secure Login</h2>
                <p>Access your GP dashboard panel</p>
            </div>

            <!-- Messages -->
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="form-group">
                    <label for="username"><i class="fas fa-user" style="color: var(--primary);"></i> Username</label>
                    <input type="text" name="username" id="username" class="form-control" placeholder="Enter username" required autofocus autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="password"><i class="fas fa-key" style="color: var(--primary);"></i> Password</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px; height: 42px;">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>

            <div class="form-footer">
                <p>Are you a village citizen? <a href="register.php" style="font-weight: 600; color: var(--secondary);">Register Here</a></p>
                <div style="margin-top: 15px; font-size: 0.8rem; border-top: 1px dashed var(--border-color); padding-top: 15px;">
                    <a href="index.php" style="color: var(--accent);"><i class="fas fa-arrow-left"></i> Back to Homepage</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Small Footer -->
    <footer style="text-align: center; font-size: 0.8rem; color: var(--text-muted); padding: 20px 0;">
        <p>&copy; <?= date('Y') ?> Gram Panchayat Grievance Redressal System | Version 1.0.0</p>
    </footer>

</body>
</html>
