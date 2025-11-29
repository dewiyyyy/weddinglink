<?php
include 'config.php';
require_login();

$user = current_user();
$user_id = $user['id'];
$user_role = $user['role'];

// Only admin and vendor can access
if ($user_role != 'admin' && $user_role != 'vendor') {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied. Only admin and vendor can view payment proofs.');
}

$payment_id = $_GET['id'] ?? 0;
$download = isset($_GET['download']);

if (empty($payment_id)) {
    header('HTTP/1.0 400 Bad Request');
    die('Payment ID is required.');
}

// Get payment data with security checks
$payment = get_payment_with_details($payment_id, $user_role, $user_id);

if (!$payment) {
    header('HTTP/1.0 404 Not Found');
    die('Payment not found or access denied.');
}

if (empty($payment['proof_image'])) {
    header('HTTP/1.0 404 Not Found');
    die('No proof image found for this payment.');
}

$file_path = get_payment_proof_path($payment['proof_image']);

// Security checks
if (!payment_proof_exists($payment['proof_image'])) {
    header('HTTP/1.0 404 Not Found');
    die('Proof image file not found on server.');
}

// Get file info
$file_info = pathinfo($file_path);
$file_extension = strtolower($file_info['extension']);
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

if (!in_array($file_extension, $allowed_extensions)) {
    header('HTTP/1.0 400 Bad Request');
    die('Invalid file type.');
}

// Get MIME type
$mime_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif'
];

$mime_type = $mime_types[$file_extension] ?? 'application/octet-stream';

// Set headers
if ($download) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="payment_proof_' . $payment['invoice_number'] . '.' . $file_extension . '"');
} else {
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: inline; filename="payment_proof_' . $payment['invoice_number'] . '.' . $file_extension . '"');
}

header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, max-age=3600');

// Output file
readfile($file_path);
exit;
?>