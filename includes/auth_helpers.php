<?php
// Authentication & Security Functions

function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }
}

function require_role($allowed_roles) {
    require_login();
    
    if (!in_array($_SESSION['user_role'], (array)$allowed_roles)) {
        $_SESSION['error'] = "Access denied. Insufficient permissions.";
        header('Location: dashboard.php');
        exit;
    }
}

function has_role($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == $role;
}

// Get current user info
function current_user() {
    if (!is_logged_in()) return null;
    
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role']
    ];
}

// Initialize CSRF token
if (empty($_SESSION['csrf_token'])) {
    generate_csrf_token();
}
?>