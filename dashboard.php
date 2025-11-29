<?php
include 'config.php';
require_login();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['user_name'];

// Get statistics based on role
if ($user_role == 'admin') {
    $total_users = fetch_one("SELECT COUNT(*) as count FROM users")['count'];
    $total_vendors = fetch_one("SELECT COUNT(*) as count FROM vendors WHERE status = 'active'")['count'];
    $total_orders = fetch_one("SELECT COUNT(*) as count FROM orders")['count'];
    $total_revenue = fetch_one("SELECT COALESCE(SUM(total_price), 0) as total FROM orders WHERE payment_status = 'paid'")['total'];
    
    $recent_orders = fetch_all("
        SELECT o.*, u.name as customer_name, p.name as package_name 
        FROM orders o 
        JOIN users u ON o.customer_id = u.id 
        JOIN packages p ON o.package_id = p.id 
        ORDER BY o.created_at DESC LIMIT 5
    ");
    
} elseif ($user_role == 'vendor') {
    $vendor = get_vendor_by_user_id($user_id);
    $vendor_id = $vendor['id'];
    
    $total_packages = fetch_one("SELECT COUNT(*) as count FROM packages WHERE vendor_id = ?", [$vendor_id])['count'];
    $total_orders = fetch_one("SELECT COUNT(*) as count FROM orders o JOIN packages p ON o.package_id = p.id WHERE p.vendor_id = ?", [$vendor_id])['count'];
    $pending_orders = fetch_one("SELECT COUNT(*) as count FROM orders o JOIN packages p ON o.package_id = p.id WHERE p.vendor_id = ? AND o.status = 'pending'", [$vendor_id])['count'];
    $total_earnings = fetch_one("SELECT COALESCE(SUM(o.total_price), 0) as total FROM orders o JOIN packages p ON o.package_id = p.id WHERE p.vendor_id = ? AND o.payment_status = 'paid'", [$vendor_id])['total'];
    
    $recent_orders = fetch_all("
        SELECT o.*, u.name as customer_name, p.name as package_name 
        FROM orders o 
        JOIN users u ON o.customer_id = u.id 
        JOIN packages p ON o.package_id = p.id 
        WHERE p.vendor_id = ?
        ORDER BY o.created_at DESC LIMIT 5
    ", [$vendor_id]);
    
} else {
    $total_orders = fetch_one("SELECT COUNT(*) as count FROM orders WHERE customer_id = ?", [$user_id])['count'];
    $pending_orders = fetch_one("SELECT COUNT(*) as count FROM orders WHERE customer_id = ? AND status = 'pending'", [$user_id])['count'];
    $completed_orders = fetch_one("SELECT COUNT(*) as count FROM orders WHERE customer_id = ? AND status = 'completed'", [$user_id])['count'];
    $total_spent = fetch_one("SELECT COALESCE(SUM(total_price), 0) as total FROM orders WHERE customer_id = ? AND payment_status = 'paid'", [$user_id])['total'];
    
    $recent_orders = fetch_all("
        SELECT o.*, p.name as package_name, v.company_name as vendor_name 
        FROM orders o 
        JOIN packages p ON o.package_id = p.id 
        JOIN vendors v ON p.vendor_id = v.id 
        WHERE o.customer_id = ?
        ORDER BY o.created_at DESC LIMIT 5
    ", [$user_id]);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - WeddingLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navigation.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Welcome, <?= $user_name ?></h5>
                        <p class="text-muted"><?= ucfirst($user_role) ?></p>
                        
                        <ul class="nav nav-pills flex-column">
                            <li class="nav-item">
                                <a class="nav-link active" href="dashboard.php">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            </li>
                            <?php if ($user_role == 'admin'): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="users.php">
                                        <i class="fas fa-users"></i> Manage Users
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="vendors.php">
                                        <i class="fas fa-store"></i> Manage Vendors
                                    </a>
                                </li>
                            <?php elseif ($user_role == 'vendor'): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="packages.php?my_packages=1">
                                        <i class="fas fa-box"></i> My Packages
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li class="nav-item">
                                <a class="nav-link" href="orders.php">
                                    <i class="fas fa-shopping-cart"></i> Orders
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="payments.php">
                                    <i class="fas fa-credit-card"></i> Payments
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="profile.php">
                                    <i class="fas fa-user"></i> Profile
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <h2 class="mb-4">Dashboard</h2>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <?php if ($user_role == 'admin'): ?>
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h4><?= $total_users ?></h4>
                                    <p>Total Users</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h4><?= $total_vendors ?></h4>
                                    <p>Active Vendors</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h4><?= $total_orders ?></h4>
                                    <p>Total Orders</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h4><?= format_currency($total_revenue) ?></h4>
                                    <p>Total Revenue</p>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($user_role == 'vendor'): ?>
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h4><?= $total_packages ?></h4>
                                    <p>My Packages</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h4><?= $total_orders ?></h4>
                                    <p>Total Orders</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h4><?= $pending_orders ?></h4>
                                    <p>Pending Orders</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h4><?= format_currency($total_earnings) ?></h4>
                                    <p>Total Earnings</p>
                                </div>
                            </div>
                        </div>
                        
                    <?php else: ?>
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h4><?= $total_orders ?></h4>
                                    <p>Total Orders</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h4><?= $pending_orders ?></h4>
                                    <p>Pending Orders</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h4><?= $completed_orders ?></h4>
                                    <p>Completed Orders</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h4><?= format_currency($total_spent) ?></h4>
                                    <p>Total Spent</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Orders -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Orders</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_orders)): ?>
                            <p class="text-muted">No orders found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Invoice</th>
                                            <?php if ($user_role != 'customer'): ?>
                                                <th>Customer</th>
                                            <?php endif; ?>
                                            <?php if ($user_role == 'customer'): ?>
                                                <th>Vendor</th>
                                            <?php endif; ?>
                                            <th>Package</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td><?= $order['invoice_number'] ?></td>
                                                <?php if ($user_role != 'customer'): ?>
                                                    <td><?= $order['customer_name'] ?></td>
                                                <?php endif; ?>
                                                <?php if ($user_role == 'customer'): ?>
                                                    <td><?= $order['vendor_name'] ?></td>
                                                <?php endif; ?>
                                                <td><?= $order['package_name'] ?></td>
                                                <td><?= format_currency($order['total_price']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $order['status'] == 'completed' ? 'success' : 
                                                        ($order['status'] == 'confirmed' ? 'primary' : 
                                                        ($order['status'] == 'in_progress' ? 'info' : 'warning')) 
                                                    ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>