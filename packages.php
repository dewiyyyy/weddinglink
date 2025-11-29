<?php
include 'config.php';
require_login();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Handle package actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validate_csrf_token($csrf_token)) {
        $_SESSION['error'] = "Invalid security token.";
        header('Location: packages.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    
    if ($action == 'create' || $action == 'update') {
        $name = sanitize_input($_POST['name']);
        $description = sanitize_input($_POST['description']);
        $price = (float)$_POST['price'];
        $category_id = (int)$_POST['category_id'];
        $duration_hours = (int)$_POST['duration_hours'];
        $features = json_encode(array_filter(array_map('trim', explode("\n", $_POST['features']))));
        
        if ($user_role == 'vendor') {
            $vendor = get_vendor_by_user_id($user_id);
            $vendor_id = $vendor['id'];
        } else {
            $vendor_id = (int)$_POST['vendor_id'];
        }

        if ($action == 'create') {
            $sql = "INSERT INTO packages (name, description, price, duration_hours, category_id, vendor_id, features) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $result = execute($sql, [$name, $description, $price, $duration_hours, $category_id, $vendor_id, $features]);
            
            if ($result) {
                $_SESSION['success'] = "Package created successfully!";
            } else {
                $_SESSION['error'] = "Failed to create package.";
            }
        } else {
            $package_id = (int)$_POST['package_id'];
            
            // Verify ownership for vendors
            if ($user_role == 'vendor') {
                $existing = fetch_one("SELECT * FROM packages WHERE id = ? AND vendor_id = ?", [$package_id, $vendor_id]);
                if (!$existing) {
                    $_SESSION['error'] = "Package not found or access denied.";
                    header('Location: packages.php');
                    exit;
                }
            }
            
            $sql = "UPDATE packages SET name = ?, description = ?, price = ?, duration_hours = ?, category_id = ?, features = ? WHERE id = ?";
            $result = execute($sql, [$name, $description, $price, $duration_hours, $category_id, $features, $package_id]);
            
            if ($result) {
                $_SESSION['success'] = "Package updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update package.";
            }
        }
        
        header('Location: packages.php');
        exit;
    }
}

// Handle package deletion
if (isset($_GET['delete'])) {
    $package_id = (int)$_GET['delete'];
    
    if ($user_role == 'vendor') {
        $vendor = get_vendor_by_user_id($user_id);
        $package = fetch_one("SELECT * FROM packages WHERE id = ? AND vendor_id = ?", [$package_id, $vendor['id']]);
        
        if (!$package) {
            $_SESSION['error'] = "Package not found or access denied.";
            header('Location: packages.php');
            exit;
        }
    }
    
    $result = execute("DELETE FROM packages WHERE id = ?", [$package_id]);
    
    if ($result) {
        $_SESSION['success'] = "Package deleted successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete package.";
    }
    
    header('Location: packages.php');
    exit;
}

// Get packages based on role and filters
$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$my_packages = isset($_GET['my_packages']);

if ($user_role == 'admin') {
    $sql = "SELECT p.*, c.name as category_name, v.company_name as vendor_name 
            FROM packages p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN vendors v ON p.vendor_id = v.id 
            WHERE 1=1";
    
    $params = [];
    
    if ($category_filter) {
        $sql .= " AND p.category_id = ?";
        $params[] = $category_filter;
    }
    
    if ($search) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY p.created_at DESC";
    $packages = fetch_all($sql, $params);
    
} elseif ($user_role == 'vendor') {
    $vendor = get_vendor_by_user_id($user_id);
    $sql = "SELECT p.*, c.name as category_name 
            FROM packages p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.vendor_id = ?";
    
    $params = [$vendor['id']];
    
    if ($category_filter) {
        $sql .= " AND p.category_id = ?";
        $params[] = $category_filter;
    }
    
    if ($search) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY p.created_at DESC";
    $packages = fetch_all($sql, $params);

} else {
    // Customer view - only active packages
    $sql = "SELECT p.*, c.name as category_name, v.company_name as vendor_name, v.rating 
            FROM packages p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN vendors v ON p.vendor_id = v.id 
            WHERE p.status = 'active' AND v.status = 'active'";
    
    $params = [];
   
    if ($category_filter) {
        $sql .= " AND p.category_id = ?";
        $params[] = $category_filter;
    }
    
    if ($search) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR v.company_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY v.rating DESC, p.created_at DESC";
    $packages = fetch_all($sql, $params);
}

$categories = fetch_all("SELECT * FROM categories WHERE status = 'active'");
$vendors = fetch_all("SELECT * FROM vendors WHERE status = 'active'");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packages - WeddingLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navigation.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <?php if ($user_role == 'customer'): ?>
                    Wedding Packages
                <?php else: ?>
                    Manage Packages
                    <?php if ($user_role == 'vendor'): ?>
                        <small class="text-muted">(Your packages)</small>
                    <?php endif; ?>
                <?php endif; ?>
            </h2>
            
            <?php if ($user_role != 'customer'): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#packageModal">
                    <i class="fas fa-plus"></i> Add New Package
                </button>
            <?php endif; ?>
        </div>

        <?php display_message(); ?>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>>
                                    <?= $cat['name'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search packages..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-outline-primary w-100">Search</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Packages Grid -->
        <div class="row">
            <?php if (empty($packages)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle"></i> No packages found.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($packages as $package): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 package-card">
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($package['name']) ?></h5>
                                <p class="card-text text-muted"><?= htmlspecialchars($package['category_name']) ?></p>
                                <p class="card-text"><?= htmlspecialchars(substr($package['description'], 0, 100)) ?>...</p>
                                
                                <?php if ($user_role == 'customer'): ?>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <i class="fas fa-store"></i> <?= htmlspecialchars($package['vendor_name']) ?>
                                            <?php if ($package['rating']): ?>
                                                <span class="text-warning">
                                                    <i class="fas fa-star"></i> <?= number_format($package['rating'], 1) ?>
                                                </span>
                                            <?php endif; ?>
                                        </small>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <?php
                                    $features = json_decode($package['features'] ?? '[]', true);
                                    if ($features):
                                    ?>
                                        <ul class="list-unstyled">
                                            <?php foreach (array_slice($features, 0, 3) as $feature): ?>
                                                <li><i class="fas fa-check text-success"></i> <?= htmlspecialchars(trim($feature)) ?></li>
                                            <?php endforeach; ?>
                                            <?php if (count($features) > 3): ?>
                                                <li><small class="text-muted">+<?= count($features) - 3 ?> more features</small></li>
                                            <?php endif; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="text-primary mb-0"><?= format_currency($package['price']) ?></h5>
                                    <?php if ($package['duration_hours']): ?>
                                        <small class="text-muted"><?= $package['duration_hours'] ?> hours</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-footer">
                                <?php if ($user_role == 'customer'): ?>
                                    <a href="orders.php?create=<?= $package['id'] ?>" class="btn btn-primary w-100">
                                        <i class="fas fa-shopping-cart"></i> Book Now
                                    </a>
                                <?php else: ?>
                                    <div class="btn-group w-100">
                                        <button type="button" class="btn btn-outline-warning btn-sm" 
                                                onclick="editPackage(<?= htmlspecialchars(json_encode($package), ENT_QUOTES, 'UTF-8') ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <a href="packages.php?delete=<?= $package['id'] ?>" class="btn btn-outline-danger btn-sm"
                                           onclick="return confirm('Are you sure you want to delete this package?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Package Modal -->
    <?php if ($user_role != 'customer'): ?>
    <div class="modal fade" id="packageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add New Package</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="create" id="formAction">
                    <input type="hidden" name="package_id" id="packageId">
                    
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Package Name</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Category</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="price" class="form-label">Price (Rp)</label>
                                    <input type="number" class="form-control" id="price" name="price" min="0" step="1000" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="duration_hours" class="form-label">Duration (hours)</label>
                                    <input type="number" class="form-control" id="duration_hours" name="duration_hours" min="1" value="1">
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($user_role == 'admin'): ?>
                            <div class="mb-3">
                                <label for="vendor_id" class="form-label">Vendor</label>
                                <select class="form-select" id="vendor_id" name="vendor_id" required>
                                    <option value="">Select Vendor</option>
                                    <?php foreach ($vendors as $vendor): ?>
                                        <option value="<?= $vendor['id'] ?>"><?= htmlspecialchars($vendor['company_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="features" class="form-label">Features (one per line)</label>
                            <textarea class="form-control" id="features" name="features" rows="4" 
                                      placeholder="Feature 1&#10;Feature 2&#10;Feature 3"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Package</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function editPackage(package) {
        document.getElementById('modalTitle').textContent = 'Edit Package';
        document.getElementById('formAction').value = 'update';
        document.getElementById('packageId').value = package.id;
        document.getElementById('name').value = package.name;
        document.getElementById('description').value = package.description;
        document.getElementById('price').value = package.price;
        document.getElementById('duration_hours').value = package.duration_hours;
        document.getElementById('category_id').value = package.category_id;
        
        <?php if ($user_role == 'admin'): ?>
        document.getElementById('vendor_id').value = package.vendor_id;
        <?php endif; ?>
        
        // Parse features from JSON
        let features = [];
        try {
            features = JSON.parse(package.features || '[]');
        } catch (e) {
            features = [];
        }
        document.getElementById('features').value = features.join('\n');
        
        new bootstrap.Modal(document.getElementById('packageModal')).show();
    }

    // Reset modal when closed
    document.getElementById('packageModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('modalTitle').textContent = 'Add New Package';
        document.getElementById('formAction').value = 'create';
        document.getElementById('packageId').value = '';
        this.querySelector('form').reset();
    });
    </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>