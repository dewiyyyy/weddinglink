<?php
// Database Helper Functions

function query($sql, $params = []) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query failed: " . $e->getMessage() . " - SQL: " . $sql);
        return false;
    }
}

function fetch_all($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt ? $stmt->fetchAll() : [];
}

function fetch_one($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt ? $stmt->fetch() : false;
}

function execute($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt ? $stmt->rowCount() : 0;
}

// User functions
function get_user_by_id($user_id) {
    return fetch_one("SELECT * FROM users WHERE id = ?", [$user_id]);
}

function get_user_by_email($email) {
    return fetch_one("SELECT * FROM users WHERE email = ?", [$email]);
}

// Vendor functions
function get_vendor_by_user_id($user_id) {
    return fetch_one("SELECT * FROM vendors WHERE user_id = ?", [$user_id]);
}

function get_vendor_by_id($vendor_id) {
    return fetch_one("SELECT * FROM vendors WHERE id = ?", [$vendor_id]);
}

// Package functions
function get_package_by_id($package_id) {
    return fetch_one("
        SELECT p.*, c.name as category_name, v.company_name as vendor_name 
        FROM packages p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN vendors v ON p.vendor_id = v.id 
        WHERE p.id = ?
    ", [$package_id]);
}

// Order functions
function get_order_by_id($order_id) {
    return fetch_one("
        SELECT o.*, u.name as customer_name, p.name as package_name 
        FROM orders o 
        JOIN users u ON o.customer_id = u.id 
        JOIN packages p ON o.package_id = p.id 
        WHERE o.id = ?
    ", [$order_id]);
}

// Payment functions
function get_payment_by_id($payment_id) {
    return fetch_one("
        SELECT p.*, o.invoice_number, u.name as customer_name 
        FROM payments p 
        JOIN orders o ON p.order_id = o.id 
        JOIN users u ON o.customer_id = u.id 
        WHERE p.id = ?
    ", [$payment_id]);
}

function get_payment_with_details($payment_id, $user_role, $user_id) {
    if ($user_role == 'admin') {
        return fetch_one("
            SELECT p.*, o.invoice_number, u.name as customer_name, u.email as customer_email,
                   pk.name as package_name, v.company_name as vendor_name
            FROM payments p 
            JOIN orders o ON p.order_id = o.id 
            JOIN users u ON o.customer_id = u.id 
            JOIN packages pk ON o.package_id = pk.id
            JOIN vendors v ON pk.vendor_id = v.id
            WHERE p.id = ?
        ", [$payment_id]);
    } elseif ($user_role == 'vendor') {
        $vendor = get_vendor_by_user_id($user_id);
        return fetch_one("
            SELECT p.*, o.invoice_number, u.name as customer_name, pk.name as package_name
            FROM payments p 
            JOIN orders o ON p.order_id = o.id 
            JOIN packages pk ON o.package_id = pk.id
            JOIN users u ON o.customer_id = u.id 
            WHERE p.id = ? AND pk.vendor_id = ?
        ", [$payment_id, $vendor['id']]);
    } else {
        return fetch_one("
            SELECT p.*, o.invoice_number, pk.name as package_name, v.company_name as vendor_name
            FROM payments p 
            JOIN orders o ON p.order_id = o.id 
            JOIN packages pk ON o.package_id = pk.id
            JOIN vendors v ON pk.vendor_id = v.id
            WHERE p.id = ? AND o.customer_id = ?
        ", [$payment_id, $user_id]);
    }
}
?>