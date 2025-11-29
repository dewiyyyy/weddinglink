<?php
include 'config.php';
require_login();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Debug: Check user info
error_log("Orders Access - User ID: $user_id, Role: $user_role");

// Handle order creation
if (isset($_GET['create'])) {
    $package_id = (int)$_GET['create'];
    
    // Verify package exists and is active
    $package = fetch_one("
        SELECT p.*, v.company_name as vendor_name, c.name as category_name 
        FROM packages p 
        LEFT JOIN vendors v ON p.vendor_id = v.id 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.id = ? AND p.status = 'active' AND v.status = 'active'
    ", [$package_id]);
    
    if (!$package) {
        $_SESSION['error'] = "Package not found or unavailable.";
        header('Location: packages.php');
        exit;
    }
    
    // Handle order submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        
        if (!validate_csrf_token($csrf_token)) {
            $_SESSION['error'] = "Invalid security token.";
            header('Location: orders.php?create=' . $package_id);
            exit;
        }
        
        $event_date = sanitize_input($_POST['event_date']);
        $event_location = sanitize_input($_POST['event_location']);
        $guest_count = (int)$_POST['guest_count'];
        $notes = sanitize_input($_POST['notes']);
        
        // Validate event date
        if (strtotime($event_date) < strtotime('today')) {
            $_SESSION['error'] = "Event date cannot be in the past.";
            header('Location: orders.php?create=' . $package_id);
            exit;
        }
        
        // Generate invoice number and create order
        $invoice_number = generate_invoice_number();
        
        $sql = "INSERT INTO orders (invoice_number, customer_id, package_id, event_date, event_location, guest_count, notes, total_price) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $result = execute($sql, [
            $invoice_number, 
            $user_id, 
            $package_id, 
            $event_date, 
            $event_location, 
            $guest_count, 
            $notes, 
            $package['price']
        ]);
        
        if ($result) {
            $_SESSION['success'] = "Order created successfully! Please proceed with payment.";
            header('Location: orders.php');
            exit;
        } else {
            $_SESSION['error'] = "Failed to create order. Please try again.";
            header('Location: orders.php?create=' . $package_id);
            exit;
        }
    }
    
    // Show order creation form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Create Order - Event Management</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body>
        <?php include 'navigation.php'; ?>
        
        <div class="container mt-4">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">Create Order</h4>
                        </div>
                        <div class="card-body">
                            <!-- Package Summary -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5>Package Details</h5>
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h6><?= htmlspecialchars($package['name']) ?></h6>
                                            <p class="text-muted mb-1"><?= htmlspecialchars($package['category_name']) ?> • <?= htmlspecialchars($package['vendor_name']) ?></p>
                                            <p class="mb-2"><?= htmlspecialchars($package['description']) ?></p>
                                            
                                            <?php
                                            $features = json_decode($package['features'] ?? '[]', true);
                                            if ($features):
                                            ?>
                                                <ul class="list-unstyled">
                                                    <?php foreach (array_slice($features, 0, 5) as $feature): ?>
                                                        <li><i class="fas fa-check text-success small"></i> <?= htmlspecialchars(trim($feature)) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <h4 class="text-primary"><?= format_currency($package['price']) ?></h4>
                                            <?php if ($package['duration_hours']): ?>
                                                <p class="text-muted"><?= $package['duration_hours'] ?> hours</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Order Form -->
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="event_date" class="form-label">Event Date *</label>
                                            <input type="date" class="form-control" id="event_date" name="event_date" 
                                                   min="<?= date('Y-m-d') ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="guest_count" class="form-label">Guest Count</label>
                                            <input type="number" class="form-control" id="guest_count" name="guest_count" 
                                                   min="1" value="50">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="event_location" class="form-label">Event Location *</label>
                                    <input type="text" class="form-control" id="event_location" name="event_location" 
                                           placeholder="Venue address" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Special Requests / Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                                              placeholder="Any special requirements or notes for the vendor..."></textarea>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-check-circle"></i> Confirm Order
                                    </button>
                                    <a href="packages.php" class="btn btn-outline-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validate_csrf_token($csrf_token)) {
        $_SESSION['error'] = "Invalid security token.";
        header('Location: orders.php');
        exit;
    }
    
    $order_id = (int)$_POST['order_id'];
    $new_status = sanitize_input($_POST['status']);
    
    // Verify permission
    if ($user_role == 'vendor') {
        $vendor = get_vendor_by_user_id($user_id);
        if (!$vendor) {
            $_SESSION['error'] = "Vendor profile not found.";
            header('Location: orders.php');
            exit;
        }
        
        $order = fetch_one("
            SELECT o.* FROM orders o 
            JOIN packages p ON o.package_id = p.id 
            WHERE o.id = ? AND p.vendor_id = ?
        ", [$order_id, $vendor['id']]);
        
        if (!$order) {
            $_SESSION['error'] = "Order not found or access denied.";
            header('Location: orders.php');
            exit;
        }
    }
    
    $result = execute("UPDATE orders SET status = ? WHERE id = ?", [$new_status, $order_id]);
    
    if ($result) {
        $_SESSION['success'] = "Order status updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update order status.";
    }
    
    header('Location: orders.php');
    exit;
}

// Get orders based on role
if ($user_role == 'admin') {
    $orders = fetch_all("
        SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone,
               p.name as package_name, v.company_name as vendor_name, c.name as category_name
        FROM orders o
        JOIN users u ON o.customer_id = u.id
        JOIN packages p ON o.package_id = p.id
        JOIN vendors v ON p.vendor_id = v.id
        JOIN categories c ON p.category_id = c.id
        ORDER BY o.created_at DESC
    ");
} elseif ($user_role == 'vendor') {
    $vendor = get_vendor_by_user_id($user_id);
    
    if ($vendor) {
        $orders = fetch_all("
            SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone,
                   p.name as package_name, c.name as category_name
            FROM orders o
            JOIN users u ON o.customer_id = u.id
            JOIN packages p ON o.package_id = p.id
            JOIN categories c ON p.category_id = c.id
            WHERE p.vendor_id = ?
            ORDER BY o.created_at DESC
        ", [$vendor['id']]);
    } else {
        $orders = [];
        $_SESSION['error'] = "Vendor profile not found. Please contact administrator.";
    }
} else {
    // Customer view
    $orders = fetch_all("
        SELECT o.*, p.name as package_name, v.company_name as vendor_name, c.name as category_name
        FROM orders o
        JOIN packages p ON o.package_id = p.id
        JOIN vendors v ON p.vendor_id = v.id
        JOIN categories c ON p.category_id = c.id
        WHERE o.customer_id = ?
        ORDER BY o.created_at DESC
    ", [$user_id]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - Event Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table th {
            border-top: none;
            font-weight: 600;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        .badge {
            font-size: 0.75em;
        }
    </style>
</head>
<body>
    <?php include 'navigation.php'; ?>
    
    <div class="container mt-4">
        <h2 class="mb-4">
            <?php if ($user_role == 'customer'): ?>
                <i class="fas fa-shopping-bag me-2"></i>My Orders
            <?php else: ?>
                <i class="fas fa-clipboard-list me-2"></i>Order Management
                <?php if ($user_role == 'vendor'): ?>
                    <small class="text-muted">(Your packages' orders)</small>
                <?php endif; ?>
            <?php endif; ?>
        </h2>

        <?php 
        // Display success/error messages
        if (isset($_SESSION['success'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>' . $_SESSION['success'] . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
            unset($_SESSION['success']);
        }
        if (isset($_SESSION['error'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>' . $_SESSION['error'] . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
            unset($_SESSION['error']);
        }
        ?>

        <!-- Orders Table -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($orders)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                        <h5>No orders found</h5>
                        <p class="text-muted mb-4">
                            <?php if ($user_role == 'customer'): ?>
                                You haven't placed any orders yet.
                            <?php elseif ($user_role == 'vendor'): ?>
                                No orders have been placed for your packages yet.
                            <?php else: ?>
                                No orders have been placed yet.
                            <?php endif; ?>
                        </p>
                        <?php if ($user_role == 'customer'): ?>
                            <a href="packages.php" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Browse Packages
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Invoice #</th>
                                    <?php if ($user_role != 'customer'): ?>
                                        <th>Customer</th>
                                    <?php endif; ?>
                                    <?php if ($user_role == 'admin'): ?>
                                        <th>Vendor</th>
                                    <?php endif; ?>
                                    <th>Package</th>
                                    <th>Event Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <strong class="d-block"><?= $order['invoice_number'] ?></strong>
                                            <small class="text-muted"><?= date('M j, Y', strtotime($order['created_at'])) ?></small>
                                        </td>
                                        
                                        <?php if ($user_role != 'customer'): ?>
                                            <td>
                                                <div>
                                                    <strong><?= htmlspecialchars($order['customer_name']) ?></strong>
                                                    <?php if ($user_role == 'admin'): ?>
                                                        <br>
                                                        <small class="text-muted"><?= htmlspecialchars($order['customer_email']) ?></small>
                                                        <br>
                                                        <small class="text-muted"><?= htmlspecialchars($order['customer_phone'] ?? 'No phone') ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                        
                                        <?php if ($user_role == 'admin'): ?>
                                            <td><?= htmlspecialchars($order['vendor_name']) ?></td>
                                        <?php endif; ?>
                                        
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($order['package_name']) ?></strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($order['category_name']) ?></small>
                                            </div>
                                        </td>
                                        
                                        <td>
                                            <?= date('M j, Y', strtotime($order['event_date'])) ?>
                                            <?php 
                                            $event_date = strtotime($order['event_date']);
                                            $today = strtotime('today');
                                            if ($event_date < $today): ?>
                                                <br>
                                                <span class="badge bg-secondary">Past Event</span>
                                            <?php elseif ($event_date == $today): ?>
                                                <br>
                                                <span class="badge bg-warning">Today</span>
                                            <?php elseif ($event_date <= strtotime('+7 days')): ?>
                                                <br>
                                                <span class="badge bg-info">Upcoming</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td>
                                            <strong class="text-primary"><?= format_currency($order['total_price']) ?></strong>
                                        </td>
                                        
                                        <td>
                                            <?= get_status_badge($order['status']) ?>
                                        </td>
                                        
                                        <td>
                                            <?= get_status_badge($order['payment_status']) ?>
                                        </td>
                                        
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <!-- View Details Button -->
                                                <button type="button" class="btn btn-outline-primary" 
                                                        data-bs-toggle="modal" data-bs-target="#orderModal<?= $order['id'] ?>"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <!-- Status Update Button (Vendor/Admin only) -->
                                                <?php if ($user_role != 'customer' && $order['status'] != 'completed' && $order['status'] != 'cancelled'): ?>
                                                    <button type="button" class="btn btn-outline-warning"
                                                            data-bs-toggle="modal" data-bs-target="#statusModal<?= $order['id'] ?>"
                                                            title="Update Status">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <!-- Payment Button (Customer only) -->
                                                <?php if ($user_role == 'customer' && $order['payment_status'] == 'unpaid'): ?>
                                                    <a href="payments.php?order_id=<?= $order['id'] ?>" class="btn btn-success" title="Make Payment">
                                                        <i class="fas fa-credit-card"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Order Details Modal -->
                                    <div class="modal fade" id="orderModal<?= $order['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Order Details - <?= $order['invoice_number'] ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h6>Customer Information</h6>
                                                            <p>
                                                                <strong>Name:</strong> 
                                                                <?= $user_role != 'customer' ? htmlspecialchars($order['customer_name']) : $_SESSION['user_name'] ?><br>
                                                                <?php if ($user_role != 'customer'): ?>
                                                                    <strong>Email:</strong> <?= htmlspecialchars($order['customer_email']) ?><br>
                                                                    <strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone'] ?? 'No phone') ?><br>
                                                                <?php endif; ?>
                                                            </p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6>Event Details</h6>
                                                            <p>
                                                                <strong>Date:</strong> <?= date('F j, Y', strtotime($order['event_date'])) ?><br>
                                                                <strong>Location:</strong> <?= htmlspecialchars($order['event_location']) ?><br>
                                                                <strong>Guests:</strong> <?= $order['guest_count'] ?> people<br>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if (!empty($order['notes'])): ?>
                                                        <div class="mb-3">
                                                            <h6>Special Notes</h6>
                                                            <p class="text-muted"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h6>Package Information</h6>
                                                            <p>
                                                                <strong>Package:</strong> <?= htmlspecialchars($order['package_name']) ?><br>
                                                                <strong>Category:</strong> <?= htmlspecialchars($order['category_name']) ?><br>
                                                                <?php if ($user_role == 'customer'): ?>
                                                                    <strong>Vendor:</strong> <?= htmlspecialchars($order['vendor_name']) ?><br>
                                                                <?php endif; ?>
                                                            </p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6>Order Status</h6>
                                                            <p>
                                                                <strong>Status:</strong> 
                                                                <?= get_status_badge($order['status']) ?><br>
                                                                
                                                                <strong>Payment:</strong> 
                                                                <?= get_status_badge($order['payment_status']) ?><br>
                                                                
                                                                <strong>Total Amount:</strong> <?= format_currency($order['total_price']) ?><br>
                                                                <strong>Order Date:</strong> <?= date('F j, Y H:i', strtotime($order['created_at'])) ?><br>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <?php if ($user_role == 'customer' && $order['payment_status'] == 'unpaid'): ?>
                                                        <a href="payments.php?order_id=<?= $order['id'] ?>" class="btn btn-primary">
                                                            <i class="fas fa-credit-card me-2"></i>Make Payment
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Status Update Modal -->
                                    <?php if ($user_role != 'customer' && $order['status'] != 'completed' && $order['status'] != 'cancelled'): ?>
                                        <div class="modal fade" id="statusModal<?= $order['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Update Order Status</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                        <input type="hidden" name="update_status" value="1">
                                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                        
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label for="status<?= $order['id'] ?>" class="form-label">New Status</label>
                                                                <select class="form-select" id="status<?= $order['id'] ?>" name="status" required>
                                                                    <option value="pending" <?= $order['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                                                    <option value="confirmed" <?= $order['status'] == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                                                    <option value="in_progress" <?= $order['status'] == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                                                    <option value="completed" <?= $order['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                                                    <option value="cancelled" <?= $order['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                                </select>
                                                            </div>
                                                            <div class="alert alert-info">
                                                                <small>
                                                                    <i class="fas fa-info-circle me-2"></i>
                                                                    Updating status will notify the customer.
                                                                </small>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Update Status</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>