<?php
// Utility Functions

function generate_invoice_number() {
    $prefix = "INV";
    $date = date("Ymd");
    $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return $prefix . $date . $random;
}

function format_currency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function format_date($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}

function format_datetime($datetime, $format = 'd/m/Y H:i') {
    return date($format, strtotime($datetime));
}

function redirect($url) {
    header("Location: $url");
    exit;
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
        'pending' => ['warning', 'Pending'],
        'confirmed' => ['primary', 'Confirmed'],
        'in_progress' => ['info', 'In Progress'],
        'completed' => ['success', 'Completed'],
        'cancelled' => ['danger', 'Cancelled'],
        'verified' => ['success', 'Verified'],
        'failed' => ['danger', 'Failed'],
        'paid' => ['success', 'Paid'],
        'unpaid' => ['danger', 'Unpaid']
    ];
    
    $config = $badges[$status] ?? ['secondary', ucfirst($status)];
    return "<span class='badge bg-{$config[0]}'>{$config[1]}</span>";
}

function get_featured_categories() {
    return fetch_all("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
}

function get_active_vendors() {
    return fetch_all("SELECT * FROM vendors WHERE status = 'active' ORDER BY company_name");
}
?>