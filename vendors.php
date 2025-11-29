<?php
include 'config.php';
require_role('admin');

// Handle vendor actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validate_csrf_token($csrf_token)) {
        $_SESSION['error'] = "Invalid security token.";
        header('Location: vendors.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    
    if ($action == 'create') {
        $user_id = (int)$_POST['user_id'];
        $company_name = sanitize_input($_POST['company_name']);
        $description = sanitize_input($_POST['description']);
        $service_type = sanitize_input($_POST['service_type']);
        $address = sanitize_input($_POST['address']);
        $city = sanitize_input($_POST['city']);
        $price_range = sanitize_input($_POST['price_range']);
        
        // Check if user exists and is a vendor
        $user = get_user_by_id($user_id);
        if (!$user || $user['role'] != 'vendor') {
            $_SESSION['error'] = "Selected user is not a vendor.";
            header('Location: vendors.php');
            exit;
        }
        
        // Check if vendor already exists for this user
        $existing_vendor = fetch_one("SELECT * FROM vendors WHERE user_id = ?", [$user_id]);
        if ($existing_vendor) {
            $_SESSION['error'] = "Vendor profile already exists for this user.";
            header('Location: vendors.php');
            exit;
        }
        
        $sql = "INSERT INTO vendors (user_id, company_name, description, service_type, address, city, price_range) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $result = execute($sql, [$user_id, $company_name, $description, $service_type, $address, $city, $price_range]);
        
        if ($result) {
            $_SESSION['success'] = "Vendor created successfully!";
        } else {
            $_SESSION['error'] = "Failed to create vendor.";
        }
        
    } elseif ($action == 'update') {
        $vendor_id = (int)$_POST['vendor_id'];
        $company_name = sanitize_input($_POST['company_name']);
        $description = sanitize_input($_POST['description']);
        $service_type = sanitize_input($_POST['service_type']);
        $address = sanitize_input($_POST['address']);
        $city = sanitize_input($_POST['city']);
        $price_range = sanitize_input($_POST['price_range']);
        $status = sanitize_input($_POST['status']);
        
        $sql = "UPDATE vendors SET company_name = ?, description = ?, service_type = ?, address = ?, city = ?, price_range = ?, status = ? WHERE id = ?";
        $result = execute($sql, [$company_name, $description, $service_type, $address, $city, $price_range, $status, $vendor_id]);
        
        if ($result) {
            $_SESSION['success'] = "Vendor updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update vendor.";
        }
    }
    
    header('Location: vendors.php');
    exit;
}

// Handle vendor status toggle
if (isset($_GET['toggle_status'])) {
    $vendor_id = (int)$_GET['toggle_status'];
    
    $vendor = fetch_one("SELECT * FROM vendors WHERE id = ?", [$vendor_id]);
    if ($vendor) {
        $new_status = $vendor['status'] == 'active' ? 'inactive' : 'active';
        execute("UPDATE vendors SET status = ? WHERE id = ?", [$new_status, $vendor_id]);
        $_SESSION['success'] = "Vendor status updated!";
    }
    
    header('Location: vendors.php');
    exit;
}

// Get all vendors with user information
$vendors = fetch_all("
    SELECT v.*, u.name as user_name, u.email, u.phone, u.status as user_status,
           (SELECT COUNT(*) FROM packages p WHERE p.vendor_id = v.id AND p.status = 'active') as package_count,
           (SELECT COUNT(*) FROM orders o JOIN packages p ON o.package_id = p.id WHERE p.vendor_id = v.id) as order_count
    FROM vendors v
    JOIN users u ON v.user_id = u.id
    ORDER BY v.created_at DESC
");

// Get users with vendor role who don't have vendor profiles yet
$available_vendors = fetch_all("
    SELECT u.* FROM users u 
    WHERE u.role = 'vendor' 
    AND u.id NOT IN (SELECT user_id FROM vendors)
    AND u.status = 'active'
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendors - WeddingLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navigation.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Vendor Management</h2>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#vendorModal">
                <i class="fas fa-plus"></i> Add New Vendor
            </button>
        </div>

        <?php display_message(); ?>

        <!-- Vendors List -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($vendors)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-store fa-3x text-muted mb-3"></i>
                        <h5>No vendors found</h5>
                        <p class="text-muted">No vendor profiles have been created yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Contact</th>
                                    <th>Service Type</th>
                                    <th>Location</th>
                                    <th>Packages</th>
                                    <th>Orders</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendors as $vendor): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($vendor['company_name']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars(substr($vendor['description'] ?? 'No description', 0, 50)) ?>...</small>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($vendor['user_name']) ?></strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($vendor['email']) ?></small>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($vendor['phone'] ?? 'No phone') ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= ucfirst($vendor['service_type']) ?></span>
                                            <br>
                                            <small class="text-muted"><?= ucfirst($vendor['price_range']) ?> price range</small>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($vendor['city']) ?>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars(substr($vendor['address'] ?? 'No address', 0, 30)) ?>...</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?= $vendor['package_count'] ?> packages</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= $vendor['order_count'] ?> orders</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $vendor['status'] == 'active' ? 'success' : 'danger' ?>">
                                                <?= ucfirst($vendor['status']) ?>
                                            </span>
                                            <br>
                                            <small class="text-muted">User: <?= ucfirst($vendor['user_status']) ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-outline-warning"
                                                        onclick="editVendor(<?= htmlspecialchars(json_encode($vendor), ENT_QUOTES, 'UTF-8') ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="vendors.php?toggle_status=<?= $vendor['id'] ?>" 
                                                   class="btn btn-sm btn-<?= $vendor['status'] == 'active' ? 'danger' : 'success' ?>"
                                                   onclick="return confirm('Are you sure you want to <?= $vendor['status'] == 'active' ? 'deactivate' : 'activate' ?> this vendor?')">
                                                    <i class="fas fa-<?= $vendor['status'] == 'active' ? 'times' : 'check' ?>"></i>
                                                </a>
                                                <a href="packages.php?vendor=<?= $vendor['id'] ?>" class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-box"></i>
                                                </a>
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

    <!-- Vendor Modal -->
    <div class="modal fade" id="vendorModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Vendor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="create" id="formAction">
                    <input type="hidden" name="vendor_id" id="vendorId">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="user_id" class="form-label">Select User *</label>
                            <select class="form-select" id="user_id" name="user_id" required>
                                <option value="">Choose a vendor user...</option>
                                <?php foreach ($available_vendors as $user): ?>
                                    <option value="<?= $user['id'] ?>">
                                        <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Only users with vendor role who don't have vendor profiles are shown.</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="company_name" class="form-label">Company Name *</label>
                                    <input type="text" class="form-control" id="company_name" name="company_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="service_type" class="form-label">Service Type *</label>
                                    <select class="form-select" id="service_type" name="service_type" required>
                                        <option value="">Select service type...</option>
                                        <option value="fotografi">Fotografi</option>
                                        <option value="makeup">Makeup</option>
                                        <option value="dekorasi">Dekorasi</option>
                                        <option value="catering">Catering</option>
                                        <option value="venue">Venue</option>
                                        <option value="lainnya">Lainnya</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Company Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="city" class="form-label">City *</label>
                                    <input type="text" class="form-control" id="city" name="city" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="price_range" class="form-label">Price Range *</label>
                                    <select class="form-select" id="price_range" name="price_range" required>
                                        <option value="">Select price range...</option>
                                        <option value="budget">Budget</option>
                                        <option value="medium">Medium</option>
                                        <option value="premium">Premium</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3" id="statusField" style="display: none;">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Vendor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function editVendor(vendor) {
        document.getElementById('modalTitle').textContent = 'Edit Vendor';
        document.getElementById('formAction').value = 'update';
        document.getElementById('vendorId').value = vendor.id;
        
        // Disable user selection for editing
        document.getElementById('user_id').value = vendor.user_id;
        document.getElementById('user_id').disabled = true;
        
        document.getElementById('company_name').value = vendor.company_name;
        document.getElementById('description').value = vendor.description || '';
        document.getElementById('service_type').value = vendor.service_type;
        document.getElementById('city').value = vendor.city;
        document.getElementById('price_range').value = vendor.price_range;
        document.getElementById('address').value = vendor.address || '';
        document.getElementById('status').value = vendor.status;
        
        // Show status field for editing
        document.getElementById('statusField').style.display = 'block';
        
        new bootstrap.Modal(document.getElementById('vendorModal')).show();
    }

    // Reset modal when closed
    document.getElementById('vendorModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('modalTitle').textContent = 'Add New Vendor';
        document.getElementById('formAction').value = 'create';
        document.getElementById('vendorId').value = '';
        document.getElementById('user_id').disabled = false;
        document.getElementById('statusField').style.display = 'none';
        this.querySelector('form').reset();
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>