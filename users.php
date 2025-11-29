<?php
include 'config.php';
require_role('admin');

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validate_csrf_token($csrf_token)) {
        $_SESSION['error'] = "Invalid security token.";
        header('Location: users.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    
    if ($action == 'update') {
        $user_id = (int)$_POST['user_id'];
        $name = sanitize_input($_POST['name']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone']);
        $role = sanitize_input($_POST['role']);
        $status = sanitize_input($_POST['status']);
        
        // Check if email is already taken by another user
        $existing_user = fetch_one("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user_id]);
        if ($existing_user) {
            $_SESSION['error'] = "Email already taken by another user.";
        } else {
            $sql = "UPDATE users SET name = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?";
            $result = execute($sql, [$name, $email, $phone, $role, $status, $user_id]);
            
            if ($result) {
                $_SESSION['success'] = "User updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update user.";
            }
        }
    }
    
    header('Location: users.php');
    exit;
}

// Handle user status toggle
if (isset($_GET['toggle_status'])) {
    $user_id = (int)$_GET['toggle_status'];
    
    $user = fetch_one("SELECT * FROM users WHERE id = ?", [$user_id]);
    if ($user && $user['id'] != $_SESSION['user_id']) { // Prevent self-deactivation
        $new_status = $user['status'] == 'active' ? 'inactive' : 'active';
        execute("UPDATE users SET status = ? WHERE id = ?", [$new_status, $user_id]);
        $_SESSION['success'] = "User status updated!";
    } else {
        $_SESSION['error'] = "Cannot deactivate your own account.";
    }
    
    header('Location: users.php');
    exit;
}

// Get all users
$users = fetch_all("
    SELECT u.*, 
           (SELECT COUNT(*) FROM orders o WHERE o.customer_id = u.id) as order_count,
           (SELECT COUNT(*) FROM vendors v WHERE v.user_id = u.id) as vendor_profile
    FROM users u
    ORDER BY u.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - WeddingLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navigation.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>User Management</h2>
        </div>

        <?php display_message(); ?>

        <!-- Users List -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($users)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5>No users found</h5>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Contact</th>
                                    <th>Role</th>
                                    <th>Orders</th>
                                    <th>Vendor Profile</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($user['name']) ?></strong>
                                            <br>
                                            <small class="text-muted">ID: <?= $user['id'] ?></small>
                                        </td>
                                        <td>
                                            <div>
                                                <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($user['phone'] ?: 'No phone') ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $user['role'] == 'admin' ? 'danger' : 
                                                ($user['role'] == 'vendor' ? 'warning' : 'info')
                                            ?>">
                                                <?= ucfirst($user['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['role'] == 'customer'): ?>
                                                <span class="badge bg-secondary"><?= $user['order_count'] ?> orders</span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['role'] == 'vendor'): ?>
                                                <span class="badge bg-<?= $user['vendor_profile'] ? 'success' : 'warning' ?>">
                                                    <?= $user['vendor_profile'] ? 'Has Profile' : 'No Profile' ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $user['status'] == 'active' ? 'success' : 'danger' ?>">
                                                <?= ucfirst($user['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-warning"
                                                        onclick="editUser(<?= htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8') ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <a href="users.php?toggle_status=<?= $user['id'] ?>" 
                                                       class="btn btn-sm btn-<?= $user['status'] == 'active' ? 'danger' : 'success' ?>"
                                                       onclick="return confirm('Are you sure you want to <?= $user['status'] == 'active' ? 'deactivate' : 'activate' ?> this user?')">
                                                        <i class="fas fa-<?= $user['status'] == 'active' ? 'times' : 'check' ?>"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-outline-secondary" disabled>
                                                        <i class="fas fa-user"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="user_id" id="userId">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="customer">Customer</option>
                                        <option value="vendor">Vendor</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function editUser(user) {
        document.getElementById('userId').value = user.id;
        document.getElementById('name').value = user.name;
        document.getElementById('email').value = user.email;
        document.getElementById('phone').value = user.phone || '';
        document.getElementById('role').value = user.role;
        document.getElementById('status').value = user.status;
        
        new bootstrap.Modal(document.getElementById('userModal')).show();
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>