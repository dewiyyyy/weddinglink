<?php
include 'config.php';
require_login();

$user_id = $_SESSION['user_id'];
$user = get_user_by_id($user_id);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validate_csrf_token($csrf_token)) {
        $error = "Invalid security token.";
    } else {
        $name = sanitize_input($_POST['name']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone']);
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Check if email is already taken by another user
        $existing_user = fetch_one("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user_id]);
        if ($existing_user) {
            $error = "Email already taken by another user.";
        } else {
            // Update basic info
            $sql = "UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?";
            $params = [$name, $email, $phone, $user_id];
            
            // Handle password change
            if (!empty($new_password)) {
                if (empty($current_password)) {
                    $error = "Current password is required to change password.";
                } elseif (!password_verify($current_password, $user['password'])) {
                    $error = "Current password is incorrect.";
                } elseif ($new_password !== $confirm_password) {
                    $error = "New password and confirmation do not match.";
                } else {
                    $sql = "UPDATE users SET name = ?, email = ?, phone = ?, password = ? WHERE id = ?";
                    $params = [$name, $email, $phone, password_hash($new_password, PASSWORD_DEFAULT), $user_id];
                }
            }
            
            if (empty($error)) {
                $result = execute($sql, $params);
                if ($result) {
                    // Update session
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $success = "Profile updated successfully!";
                    // Refresh user data
                    $user = get_user_by_id($user_id);
                } else {
                    $error = "Failed to update profile.";
                }
            }
        }
    }
}

// Get vendor profile if user is a vendor
$vendor_profile = null;
if ($_SESSION['user_role'] == 'vendor') {
    $vendor_profile = get_vendor_by_user_id($user_id);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - WeddingLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navigation.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h2>Profile Settings</h2>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control" value="<?= ucfirst($user['role']) ?>" readonly>
                            </div>

                            <hr>

                            <h5>Change Password</h5>
                            <p class="text-muted">Leave blank to keep current password</p>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </form>
                    </div>
                </div>

                <!-- Vendor Profile Section -->
                <?php if ($_SESSION['user_role'] == 'vendor' && $vendor_profile): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Vendor Profile</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Company Name:</strong> <?= htmlspecialchars($vendor_profile['company_name']) ?></p>
                                    <p><strong>Service Type:</strong> <?= ucfirst($vendor_profile['service_type']) ?></p>
                                    <p><strong>Price Range:</strong> <?= ucfirst($vendor_profile['price_range']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>City:</strong> <?= htmlspecialchars($vendor_profile['city']) ?></p>
                                    <p><strong>Address:</strong> <?= htmlspecialchars($vendor_profile['address'] ?? '') ?></p>
                                    <p><strong>Status:</strong> 
                                        <span class="badge bg-<?= $vendor_profile['status'] == 'active' ? 'success' : 'danger' ?>">
                                            <?= ucfirst($vendor_profile['status']) ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <?php if ($vendor_profile['description']): ?>
                                <div class="mt-3">
                                    <strong>Description:</strong>
                                    <p class="text-muted"><?= nl2br(htmlspecialchars($vendor_profile['description'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Statistics Section -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Account Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <?php if ($_SESSION['user_role'] == 'customer'): ?>
                                <?php
                                $order_stats = fetch_one("
                                    SELECT 
                                        COUNT(*) as total_orders,
                                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                                        SUM(CASE WHEN payment_status = 'paid' THEN total_price ELSE 0 END) as total_spent
                                    FROM orders 
                                    WHERE customer_id = ?
                                ", [$user_id]);
                                ?>
                                <div class="col-md-4">
                                    <h4><?= $order_stats['total_orders'] ?? 0 ?></h4>
                                    <p class="text-muted">Total Orders</p>
                                </div>
                                <div class="col-md-4">
                                    <h4><?= $order_stats['completed_orders'] ?? 0 ?></h4>
                                    <p class="text-muted">Completed</p>
                                </div>
                                <div class="col-md-4">
                                    <h4><?= format_currency($order_stats['total_spent'] ?? 0) ?></h4>
                                    <p class="text-muted">Total Spent</p>
                                </div>
                                
                            <?php elseif ($_SESSION['user_role'] == 'vendor' && $vendor_profile): ?>
                                <?php
                                $vendor_stats = fetch_one("
                                    SELECT 
                                        COUNT(p.id) as total_packages,
                                        COUNT(o.id) as total_orders,
                                        SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_price ELSE 0 END) as total_earnings
                                    FROM vendors v
                                    LEFT JOIN packages p ON v.id = p.vendor_id
                                    LEFT JOIN orders o ON p.id = o.package_id
                                    WHERE v.id = ?
                                ", [$vendor_profile['id']]);
                                ?>
                                <div class="col-md-4">
                                    <h4><?= $vendor_stats['total_packages'] ?? 0 ?></h4>
                                    <p class="text-muted">Packages</p>
                                </div>
                                <div class="col-md-4">
                                    <h4><?= $vendor_stats['total_orders'] ?? 0 ?></h4>
                                    <p class="text-muted">Total Orders</p>
                                </div>
                                <div class="col-md-4">
                                    <h4><?= format_currency($vendor_stats['total_earnings'] ?? 0) ?></h4>
                                    <p class="text-muted">Total Earnings</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>