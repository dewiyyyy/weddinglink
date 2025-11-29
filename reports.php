<?php
include 'config.php';
require_role('admin');

// Date range filter
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Overall statistics
$total_revenue = fetch_one("
    SELECT COALESCE(SUM(total_price), 0) as total 
    FROM orders 
    WHERE payment_status = 'paid'
    AND created_at BETWEEN ? AND ?
", [$start_date, $end_date])['total'];

$total_orders = fetch_one("
    SELECT COUNT(*) as count 
    FROM orders 
    WHERE created_at BETWEEN ? AND ?
", [$start_date, $end_date])['count'];

$total_customers = fetch_one("
    SELECT COUNT(DISTINCT customer_id) as count 
    FROM orders 
    WHERE created_at BETWEEN ? AND ?
", [$start_date, $end_date])['count'];

$total_vendors = fetch_one("SELECT COUNT(*) as count FROM vendors WHERE status = 'active'")['count'];

// Monthly revenue trend
$monthly_revenue = fetch_all("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(total_price) as revenue,
        COUNT(*) as orders
    FROM orders 
    WHERE payment_status = 'paid'
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");

// Popular packages
$popular_packages = fetch_all("
    SELECT 
        p.name,
        p.category_id,
        c.name as category_name,
        COUNT(o.id) as order_count,
        SUM(o.total_price) as revenue
    FROM packages p
    LEFT JOIN orders o ON p.id = o.package_id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY p.id
    ORDER BY order_count DESC
    LIMIT 10
", [$start_date, $end_date]);

// Vendor performance
$vendor_performance = fetch_all("
    SELECT 
        v.company_name,
        v.service_type,
        COUNT(o.id) as order_count,
        SUM(o.total_price) as revenue,
        AVG(o.total_price) as avg_order_value
    FROM vendors v
    LEFT JOIN packages p ON v.id = p.vendor_id
    LEFT JOIN orders o ON p.id = o.package_id
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY v.id
    ORDER BY revenue DESC
    LIMIT 10
", [$start_date, $end_date]);

// Category performance
$category_performance = fetch_all("
    SELECT 
        c.name,
        c.icon,
        COUNT(o.id) as order_count,
        SUM(o.total_price) as revenue
    FROM categories c
    LEFT JOIN packages p ON c.id = p.category_id
    LEFT JOIN orders o ON p.id = o.package_id
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY c.id
    ORDER BY revenue DESC
", [$start_date, $end_date]);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - WeddingLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navigation.php'; ?>

    <div class="container-fluid mt-4">
        <h2>Reports & Analytics</h2>

        <?php display_message(); ?>

        <!-- Date Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <a href="reports.php" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?= format_currency($total_revenue) ?></h4>
                                <p>Total Revenue</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-money-bill-wave fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?= $total_orders ?></h4>
                                <p>Total Orders</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-shopping-cart fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?= $total_customers ?></h4>
                                <p>Active Customers</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?= $total_vendors ?></h4>
                                <p>Active Vendors</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-store fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Monthly Revenue -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Monthly Revenue Trend</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($monthly_revenue)): ?>
                            <p class="text-muted">No revenue data available.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Orders</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($monthly_revenue as $revenue): ?>
                                            <tr>
                                                <td><?= date('F Y', strtotime($revenue['month'] . '-01')) ?></td>
                                                <td><?= $revenue['orders'] ?></td>
                                                <td><?= format_currency($revenue['revenue']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Category Performance -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Category Performance</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($category_performance)): ?>
                            <p class="text-muted">No category data available.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Orders</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($category_performance as $category): ?>
                                            <tr>
                                                <td>
                                                    <i class="fas fa-<?= $category['icon'] ?> text-primary"></i>
                                                    <?= htmlspecialchars($category['name']) ?>
                                                </td>
                                                <td><?= $category['order_count'] ?></td>
                                                <td><?= format_currency($category['revenue']) ?></td>
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

        <div class="row">
            <!-- Popular Packages -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Most Popular Packages</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($popular_packages)): ?>
                            <p class="text-muted">No package data available.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Package</th>
                                            <th>Category</th>
                                            <th>Orders</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($popular_packages as $package): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($package['name']) ?></td>
                                                <td>
                                                    <span class="badge bg-info"><?= htmlspecialchars($package['category_name']) ?></span>
                                                </td>
                                                <td><?= $package['order_count'] ?></td>
                                                <td><?= format_currency($package['revenue']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Vendor Performance -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Top Performing Vendors</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($vendor_performance)): ?>
                            <p class="text-muted">No vendor data available.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Vendor</th>
                                            <th>Service</th>
                                            <th>Orders</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($vendor_performance as $vendor): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($vendor['company_name']) ?></td>
                                                <td>
                                                    <span class="badge bg-secondary"><?= ucfirst($vendor['service_type']) ?></span>
                                                </td>
                                                <td><?= $vendor['order_count'] ?></td>
                                                <td><?= format_currency($vendor['revenue']) ?></td>
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