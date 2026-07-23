<?php
// logout.php
// Gram Panchayat Complaint Management System - Logout Process

require_once 'includes/auth.php';

// Empty the session variables
$_SESSION = array();

// Destroy session cookie if present
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect back to landing page
header("Location: login.php?loggedout=1");
exit();
?>
