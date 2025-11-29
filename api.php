<?php
include 'config.php';
header('Content-Type: application/json');

// Simple API authentication (enhance this for production)
function authenticate_api() {
    $headers = getallheaders();
    $api_key = $headers['X-API-Key'] ?? '';
    
    // In production, validate against database
    $valid_keys = ['weddinglink_api_key_2024'];
    if (!in_array($api_key, $valid_keys)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key']);
        exit;
    }
}

// Get packages API
if ($_GET['action'] == 'get_packages' && $_SERVER['REQUEST_METHOD'] == 'GET') {
    authenticate_api();
    
    $category_id = $_GET['category_id'] ?? '';
    $vendor_id = $_GET['vendor_id'] ?? '';
    $limit = min($_GET['limit'] ?? 10, 50);
    $offset = $_GET['offset'] ?? 0;
    
    $sql = "SELECT p.*, c.name as category_name, v.company_name as vendor_name, v.rating
            FROM packages p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN vendors v ON p.vendor_id = v.id 
            WHERE p.status = 'active' AND v.status = 'active'";
    
    $params = [];
    
    if ($category_id) {
        $sql .= " AND p.category_id = ?";
        $params[] = $category_id;
    }
    
    if ($vendor_id) {
        $sql .= " AND p.vendor_id = ?";
        $params[] = $vendor_id;
    }
    
    $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $packages = fetch_all($sql, $params);
    
    // Format response
    $response = [
        'success' => true,
        'data' => $packages,
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'total' => count($packages)
        ]
    ];
    
    echo json_encode($response);
    exit;
}

// Get vendor details API
if ($_GET['action'] == 'get_vendor' && $_SERVER['REQUEST_METHOD'] == 'GET') {
    authenticate_api();
    
    $vendor_id = $_GET['vendor_id'] ?? '';
    
    if (!$vendor_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Vendor ID required']);
        exit;
    }
    
    $vendor = fetch_one("
        SELECT v.*, u.name as contact_name, u.email, u.phone
        FROM vendors v
        JOIN users u ON v.user_id = u.id
        WHERE v.id = ? AND v.status = 'active'
    ", [$vendor_id]);
    
    if (!$vendor) {
        http_response_code(404);
        echo json_encode(['error' => 'Vendor not found']);
        exit;
    }
    
    // Get vendor packages
    $packages = fetch_all("
        SELECT * FROM packages 
        WHERE vendor_id = ? AND status = 'active'
        ORDER BY created_at DESC
    ", [$vendor_id]);
    
    // Get vendor reviews
    $reviews = fetch_all("
        SELECT r.*, u.name as customer_name
        FROM reviews r
        JOIN users u ON r.customer_id = u.id
        WHERE r.vendor_id = ? AND r.status = 'approved'
        ORDER BY r.created_at DESC
        LIMIT 10
    ", [$vendor_id]);
    
    $response = [
        'success' => true,
        'data' => [
            'vendor' => $vendor,
            'packages' => $packages,
            'reviews' => $reviews
        ]
    ];
    
    echo json_encode($response);
    exit;
}

// Create order API
if ($_GET['action'] == 'create_order' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    authenticate_api();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['customer_id', 'package_id', 'event_date', 'event_location'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            exit;
        }
    }
    
    $customer_id = (int)$input['customer_id'];
    $package_id = (int)$input['package_id'];
    $event_date = sanitize_input($input['event_date']);
    $event_location = sanitize_input($input['event_location']);
    $guest_count = (int)($input['guest_count'] ?? 0);
    $notes = sanitize_input($input['notes'] ?? '');
    
    // Validate package exists and is active
    $package = fetch_one("
        SELECT p.*, v.status as vendor_status 
        FROM packages p 
        JOIN vendors v ON p.vendor_id = v.id 
        WHERE p.id = ? AND p.status = 'active' AND v.status = 'active'
    ", [$package_id]);
    
    if (!$package) {
        http_response_code(404);
        echo json_encode(['error' => 'Package not found or unavailable']);
        exit;
    }
    
    // Validate customer exists
    $customer = get_user_by_id($customer_id);
    if (!$customer || $customer['role'] != 'customer') {
        http_response_code(404);
        echo json_encode(['error' => 'Customer not found']);
        exit;
    }
    
    // Validate event date
    if (!validate_date($event_date) || strtotime($event_date) < strtotime('today')) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid event date']);
        exit;
    }
    
    // Generate invoice and create order
    $invoice_number = generate_invoice_number();
    
    $sql = "INSERT INTO orders (invoice_number, customer_id, package_id, event_date, event_location, guest_count, notes, total_price) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $result = execute($sql, [
        $invoice_number, 
        $customer_id, 
        $package_id, 
        $event_date, 
        $event_location, 
        $guest_count, 
        $notes, 
        $package['price']
    ]);
    
    if ($result) {
        $order_id = $pdo->lastInsertId();
        $order = fetch_one("SELECT * FROM orders WHERE id = ?", [$order_id]);
        
        $response = [
            'success' => true,
            'message' => 'Order created successfully',
            'data' => $order
        ];
        
        echo json_encode($response);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create order']);
    }
    
    exit;
}

// Default response for invalid endpoints
http_response_code(404);
echo json_encode(['error' => 'Endpoint not found']);
?>