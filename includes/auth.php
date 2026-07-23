<?php
// includes/auth.php
// Gram Panchayat Complaint Management System - Session & Auth helpers

if (session_status() === PHP_SESSION_NONE) {
    // Set secure session cookie parameters if possible
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

/**
 * Redirect user if they are not logged in.
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Enforce that the user must possess one of the allowed roles.
 * If not, redirect them to their respective dashboard.
 * @param array|string $allowed_roles
 */
function check_role($allowed_roles) {
    require_login();
    $roles = (array)$allowed_roles;
    if (!in_array($_SESSION['role_name'], $roles)) {
        redirect_to_dashboard($_SESSION['role_name']);
        exit();
    }
}

/**
 * Redirect a user to their designated dashboard according to role.
 */
function redirect_to_dashboard($role_name) {
    switch ($role_name) {
        case 'super_admin':
            header("Location: superadmin_dashboard.php");
            break;
        case 'gp_admin':
            header("Location: admin_dashboard.php");
            break;
        case 'field_officer':
            header("Location: officer_dashboard.php");
            break;
        case 'citizen':
            header("Location: citizen_dashboard.php");
            break;
        default:
            header("Location: index.php");
    }
    exit();
}

/**
 * Generate CSRF token.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token.
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die("Security error: CSRF token verification failed.");
    }
}

/**
 * Sanitize input data to prevent XSS.
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
?>
