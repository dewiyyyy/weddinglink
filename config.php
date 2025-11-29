<?php
session_start();
ob_start();

// Security headers
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Application configuration
define('APP_NAME', 'WeddingLink');
define('BASE_URL', 'https://weddinglink.ct.ws');
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 2097152); // 2MB

// Database configuration
define('DB_HOST', 'sql204.infinityfree.com');
define('DB_NAME', 'if0_40499625_weddinglink');
define('DB_USER', 'if0_40499625');
define('DB_PASS', 'garamalaska123');
define('DB_CHARSET', 'utf8mb4');

// Create database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER, 
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    die("Database connection failed. Please try again later.");
}

// Security functions
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

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_phone($phone) {
    return preg_match('/^[0-9+\-\s()]{10,20}$/', $phone);
}

// Authentication functions
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

// Database helper functions
function query($sql, $params = []) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query failed: " . $e->getMessage() . " - SQL: " . $sql);
        return false;
    }
}

function fetch_all($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt ? $stmt->fetchAll() : [];
}

function fetch_one($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt ? $stmt->fetch() : false;
}

function execute($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt ? $stmt->rowCount() : 0;
}

// File upload function with enhanced security
function upload_payment_proof($file) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Upload error: " . $file['error'];
        return false;
    }
    
    $target_dir = UPLOAD_PATH . "payments/";
    
    // Create directory if not exists
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0755, true)) {
            error_log("Failed to create directory: " . $target_dir);
            $_SESSION['error'] = "Failed to create upload directory.";
            return false;
        }
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        $_SESSION['error'] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
        return false;
    }
    
    // Validate file extension
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed_extensions = ["jpg", "jpeg", "png", "gif"];
    if (!in_array($file_extension, $allowed_extensions)) {
        $_SESSION['error'] = "Invalid file extension.";
        return false;
    }
    
    // Check file size
    if ($file["size"] > MAX_FILE_SIZE) {
        $_SESSION['error'] = "File too large. Maximum size: " . (MAX_FILE_SIZE / 1024 / 1024) . "MB";
        return false;
    }
    
    // Generate secure filename
    $filename = bin2hex(random_bytes(16)) . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        // Set proper permissions
        chmod($target_file, 0644);
        return "payments/" . $filename;
    } else {
        error_log("Failed to move uploaded file: " . $file["tmp_name"] . " to " . $target_file);
        $_SESSION['error'] = "Failed to upload file. Please try again.";
        return false;
    }
}

// Utility functions
function generate_invoice_number() {
    $prefix = "INV";
    $date = date("Ymd");
    $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return $prefix . $date . $random;
}

function format_currency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function get_user_by_id($user_id) {
    return fetch_one("SELECT * FROM users WHERE id = ?", [$user_id]);
}

function get_vendor_by_user_id($user_id) {
    return fetch_one("SELECT * FROM vendors WHERE user_id = ?", [$user_id]);
}

function display_message() {
    if (isset($_SESSION['success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                ' . $_SESSION['success'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
        unset($_SESSION['success']);
    }
    
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                ' . $_SESSION['error'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
        unset($_SESSION['error']);
    }
    
    if (isset($_SESSION['warning'])) {
        echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                ' . $_SESSION['warning'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
        unset($_SESSION['warning']);
    }
}

function get_status_badge($status) {
    $badges = [
        'pending' => 'warning',
        'confirmed' => 'primary', 
        'in_progress' => 'info',
        'completed' => 'success',
        'cancelled' => 'danger',
        'verified' => 'success',
        'failed' => 'danger',
        'paid' => 'success',
        'unpaid' => 'secondary'
    ];
    
    $color = $badges[$status] ?? 'secondary';
    $display_status = ucfirst(str_replace('_', ' ', $status));
    
    return "<span class='badge bg-$color'>$display_status</span>";
}

function validate_date($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function get_web_path($file_path) {
    if (empty($file_path)) return '';
    $full_path = UPLOAD_PATH . $file_path;
    return file_exists($full_path) ? 'uploads/' . $file_path : '';
}

// Initialize CSRF token
if (empty($_SESSION['csrf_token'])) {
    generate_csrf_token();
}

// Session security
if (is_logged_in()) {
    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}
// Add this function to config.php in the utility functions section
function update_vendor_rating($vendor_id) {
    $rating_data = fetch_one("
        SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
        FROM reviews 
        WHERE vendor_id = ? AND status = 'approved'
    ", [$vendor_id]);
    
    if ($rating_data && $rating_data['avg_rating']) {
        $result = execute("UPDATE vendors SET rating = ?, total_reviews = ? WHERE id = ?", [
            round($rating_data['avg_rating'], 2),
            $rating_data['total_reviews'],
            $vendor_id
        ]);
        return $result;
    }
    return false;
}
?>