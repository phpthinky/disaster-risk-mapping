<?php
// core.php - Main core configuration file

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define base paths and URLs
define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR); // Parent directory of core
define('CORE_PATH', __DIR__ . DIRECTORY_SEPARATOR);
define('INCLUDES_PATH', BASE_PATH . 'includes' . DIRECTORY_SEPARATOR);
define('ASSETS_PATH', BASE_PATH . 'assets' . DIRECTORY_SEPARATOR);

// Define URLs (adjust based on your server setup)
define('BASE_URL', 'http://localhost/sablayan-risk/'); // Change this to your actual base URL
define('SITE_URL', BASE_URL);
define('ASSETS_URL', BASE_URL . 'assets/');
define('CSS_URL', ASSETS_URL . 'css/');
define('JS_URL', ASSETS_URL . 'js/');
define('IMAGES_URL', ASSETS_URL . 'images/');

// Load configuration
require_once CORE_PATH . 'config.php';

// Global database connection (from config.php)
global $pdo;

// Define common functions
function base_path($path = '') {
    return BASE_PATH . ltrim($path, '/\\');
}

function core_path($path = '') {
    return CORE_PATH . ltrim($path, '/\\');
}

function base_url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

function site_url($path = '') {
    return SITE_URL . ltrim($path, '/');
}

function assets_url($path = '') {
    return ASSETS_URL . ltrim($path, '/');
}

function redirect($url) {
    header('Location: ' . base_url($url));
    exit;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        redirect('login.php');
    }
}
/*
function get_current_user() {
    global $pdo;
    if (is_logged_in()) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    return null;
}
*/
// Error handling function
function show_error($message, $redirect = null) {
    $_SESSION['error_message'] = $message;
    if ($redirect) {
        redirect($redirect);
    }
}

// Success message function
function show_success($message, $redirect = null) {
    $_SESSION['success_message'] = $message;
    if ($redirect) {
        redirect($redirect);
    }
}

// Display messages (call this in your header)
function display_messages() {
    $output = '';
    if (isset($_SESSION['error_message'])) {
        $output .= '<div class="alert alert-danger alert-dismissible fade show">';
        $output .= '<i class="fas fa-exclamation-circle me-2"></i>' . $_SESSION['error_message'];
        $output .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        $output .= '</div>';
        unset($_SESSION['error_message']);
    }
    if (isset($_SESSION['success_message'])) {
        $output .= '<div class="alert alert-success alert-dismissible fade show">';
        $output .= '<i class="fas fa-check-circle me-2"></i>' . $_SESSION['success_message'];
        $output .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        $output .= '</div>';
        unset($_SESSION['success_message']);
    }
    return $output;
}

// Debug function
function debug_log($data, $title = 'Debug') {
    error_log($title . ': ' . print_r($data, true));
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Generate CSRF token
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>