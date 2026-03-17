<?php
// includes/auth.php
// System-wide Authentication and Security Functions

session_start();

/**
 * Require user to be logged in, otherwise redirect to login page.
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Check if current user has the specified role
 */
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Require a specific role to access the page (e.g. 'admin')
 */
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        // Option to redirect to an access denied page or dashboard
        header("Location: index.php?error=access_denied");
        exit();
    }
}

/**
 * Generate CSRF Token and store in session
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF Token
 */
function validateCSRFToken($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return false;
}

/**
 * Sanitize Output (Prevent XSS)
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>
