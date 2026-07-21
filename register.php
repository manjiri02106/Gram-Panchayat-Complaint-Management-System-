<?php
// register.php
// Gram Panchayat Complaint Management System - Citizen Registration Page

require_once 'includes/auth.php';
require_once 'includes/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    redirect_to_dashboard($_SESSION['role_name']);
}

$error = null;
$csrf_token = generate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF
    $posted_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    verify_csrf_token($posted_token);
    
    $fullName = trim(sanitize($_POST['full_name']));
    $username = trim(sanitize($_POST['username']));
    $email = trim(sanitize($_POST['email']));
    $phone = trim(sanitize($_POST['phone']));
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validations
    if (empty($fullName) || empty($username) || empty($email) || empty($phone) || empty($password) || empty($confirmPassword)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match. Please re-enter.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            // Check if username is already taken
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $checkStmt->execute([$username]);
            if ($checkStmt->fetch()) {
                $error = "Username is already taken. Please choose another one.";
            } else {
                // Insert new Citizen user (role_id = 4)
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $insertStmt = $pdo->prepare("
                    INSERT INTO users (username, password, full_name, email, phone, role_id) 
                    VALUES (?, ?, ?, ?, ?, 4)
                ");
                $insertStmt->execute([$username, $hashedPassword, $fullName, $email, $phone]);
                
                // Redirect to login page with success code
                header("Location: login.php?registered=1");
                exit();
            }
        } catch (PDOException $e) {
            $error = "A database error occurred: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Registration - GPCMS</title>
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
    <div class="auth-wrapper" style="padding: 20px;">
        <div class="auth-card" style="max-width: 500px; padding: 25px;">
            <div class="auth-header" style="margin-bottom: 20px;">
                <div style="width: 50px; height: 50px; border-radius: 50%; background-color: rgba(245, 124, 0, 0.1); display: inline-flex; align-items: center; justify-content: center;">
                    <i class="fas fa-user-plus" style="font-size: 1.6rem; color: var(--secondary);"></i>
                </div>
                <h2>Citizen Registration</h2>
                <p>Register to submit complaints and track resolutions</p>
            </div>

            <!-- Error message display -->
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="form-group">
                    <label for="full_name"><i class="fas fa-signature" style="color: var(--primary);"></i> Full Name</label>
                    <input type="text" name="full_name" id="full_name" class="form-control" placeholder="Enter your full name" required value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>">
                </div>

                <div class="form-group">
                    <label for="username"><i class="fas fa-user-tag" style="color: var(--primary);"></i> Username</label>
                    <input type="text" name="username" id="username" class="form-control" placeholder="Create a unique username" required value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                </div>

                <div class="grid-2" style="grid-template-columns: 1fr; gap: 0;">
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope" style="color: var(--primary);"></i> Email Address</label>
                        <input type="email" name="email" id="email" class="form-control" placeholder="e.g. citizen@domain.com" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="phone"><i class="fas fa-phone" style="color: var(--primary);"></i> Phone Number</label>
                        <input type="tel" name="phone" id="phone" class="form-control" placeholder="10-digit mobile number" required pattern="[0-9]{10,12}" value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                        <span class="form-text">Format: 10-12 numeric digits</span>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="password"><i class="fas fa-key" style="color: var(--primary);"></i> Password</label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Min. 6 chars" required minlength="6">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password"><i class="fas fa-key" style="color: var(--primary);"></i> Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Re-enter password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-secondary" style="width: 100%; margin-top: 10px; height: 42px;">
                    <i class="fas fa-user-check"></i> Register Account
                </button>
            </form>

            <div class="form-footer">
                <p>Already registered? <a href="login.php" style="font-weight: 600; color: var(--primary);">Login Portal</a></p>
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
