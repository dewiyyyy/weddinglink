<?php
// File Upload Helper Functions

function ensure_directory_exists($path) {
    if (!is_dir($path)) {
        return mkdir($path, 0755, true);
    }
    return true;
}

function validate_file_upload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'gif'], $max_size = 2097152) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error: ' . $file['error']];
    }
    
    if ($file["size"] > $max_size) {
        return ['success' => false, 'error' => 'File too large. Maximum size: ' . ($max_size / 1024 / 1024) . 'MB'];
    }
    
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_types)];
    }
    
    return ['success' => true, 'extension' => $file_extension];
}

function upload_payment_proof($file) {
    // Validate file
    $validation = validate_file_upload($file);
    if (!$validation['success']) {
        $_SESSION['error'] = $validation['error'];
        return false;
    }
    
    $target_dir = UPLOAD_PATH . "payments/";
    
    // Create directory if not exists
    if (!ensure_directory_exists($target_dir)) {
        $_SESSION['error'] = "Failed to create upload directory.";
        return false;
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $validation['extension'];
    $target_file = $target_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        // Set proper permissions
        chmod($target_file, 0644);
        return "payments/" . $filename;
    } else {
        error_log("Failed to move uploaded file: " . $file["tmp_name"] . " to " . $target_file);
        $_SESSION['error'] = "Failed to upload file.";
        return false;
    }
}

function get_payment_proof_path($proof_image) {
    if (empty($proof_image)) return null;
    return UPLOAD_PATH . $proof_image;
}

function payment_proof_exists($proof_image) {
    $path = get_payment_proof_path($proof_image);
    return $path && file_exists($path) && is_readable($path);
}

function get_payment_proof_url($proof_image) {
    if (!payment_proof_exists($proof_image)) return null;
    return BASE_URL . '/uploads/' . $proof_image;
}
?>