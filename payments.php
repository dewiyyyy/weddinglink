<?php
include 'config.php';
require_login();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Handle payment proof upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['proof_image'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validate_csrf_token($csrf_token)) {
        $_SESSION['error'] = "Invalid security token.";
        header('Location: payments.php');
        exit;
    }

    $order_id = (int)$_POST['order_id'];
    $bank_name = sanitize_input($_POST['bank_name']);
    $account_number = sanitize_input($_POST['account_number']);
    $account_holder = sanitize_input($_POST['account_holder']);
    
    // Validate inputs
    if (empty($bank_name) || empty($account_number) || empty($account_holder)) {
        $_SESSION['error'] = "All bank details are required.";
        header('Location: payments.php');
        exit;
    }
    
    // Verify order belongs to customer and is unpaid
    if ($user_role == 'customer') {
        $order = fetch_one("SELECT * FROM orders WHERE id = ? AND customer_id = ? AND payment_status = 'unpaid'", [$order_id, $user_id]);
        if (!$order) {
            $_SESSION['error'] = "Order not found, already paid, or access denied.";
            header('Location: payments.php');
            exit;
        }
    }
    
    // Upload proof image
    $proof_image = upload_payment_proof($_FILES['proof_image']);
    
    if ($proof_image) {
        // Check if payment already exists for this order
        $existing_payment = fetch_one("SELECT id FROM payments WHERE order_id = ?", [$order_id]);
        if ($existing_payment) {
            $_SESSION['error'] = "Payment already submitted for this order.";
            header('Location: payments.php');
            exit;
        }
        
        // Create payment record
        $order = fetch_one("SELECT total_price FROM orders WHERE id = ?", [$order_id]);
        
        if ($order) {
            $sql = "INSERT INTO payments (order_id, amount, proof_image, bank_name, account_number, account_holder) 
                   VALUES (?, ?, ?, ?, ?, ?)";
            $result = execute($sql, [
                $order_id, 
                $order['total_price'], 
                $proof_image, 
                $bank_name, 
                $account_number, 
                $account_holder
            ]);
            
            if ($result) {
                // Update order payment status
                execute("UPDATE orders SET payment_status = 'pending' WHERE id = ?", [$order_id]);
                $_SESSION['success'] = "Bukti pembayaran berhasil diupload. Menunggu verifikasi admin.";
                
                // Log the payment submission
                error_log("Payment submitted: Order {$order_id}, Amount: {$order['total_price']}, User: {$user_id}");
            } else {
                $_SESSION['error'] = "Failed to save payment information.";
            }
        }
    }
    
    header('Location: payments.php');
    exit;
}

// Handle payment verification (admin only)
if (isset($_GET['verify']) || isset($_GET['reject'])) {
    if ($user_role != 'admin') {
        $_SESSION['error'] = "Access denied.";
        header('Location: payments.php');
        exit;
    }
    
    $payment_id = isset($_GET['verify']) ? (int)$_GET['verify'] : (int)$_GET['reject'];
    $action = isset($_GET['verify']) ? 'verified' : 'failed';
    
    // Get payment details
    $payment = fetch_one("SELECT * FROM payments WHERE id = ? AND status = 'pending'", [$payment_id]);
    if (!$payment) {
        $_SESSION['error'] = "Payment not found or already processed.";
        header('Location: payments.php');
        exit;
    }
    
    // Update payment status
    $result = execute("UPDATE payments SET status = ?, verified_by = ?, verified_at = NOW() WHERE id = ?", 
          [$action, $user_id, $payment_id]);
    
    if ($result) {
        // Update order payment status and status
        $order_id = $payment['order_id'];
        $new_order_status = $action == 'verified' ? 'confirmed' : 'pending';
        $new_payment_status = $action == 'verified' ? 'paid' : 'failed';
        
        execute("UPDATE orders SET payment_status = ?, status = ? WHERE id = ?", 
              [$new_payment_status, $new_order_status, $order_id]);
        
        $_SESSION['success'] = "Pembayaran berhasil di" . ($action == 'verified' ? 'verifikasi' : 'tolak');
        
        // Log the verification
        error_log("Payment {$action}: Payment ID {$payment_id}, Order {$order_id}, Admin: {$user_id}");
    } else {
        $_SESSION['error'] = "Failed to update payment status.";
    }
    
    header('Location: payments.php');
    exit;
}

// Get payments based on role
if ($user_role == 'admin') {
    $payments = fetch_all("
        SELECT p.*, o.invoice_number, u.name as customer_name, u.email as customer_email,
               pk.name as package_name, v.company_name as vendor_name
        FROM payments p 
        JOIN orders o ON p.order_id = o.id 
        JOIN users u ON o.customer_id = u.id 
        JOIN packages pk ON o.package_id = pk.id
        JOIN vendors v ON pk.vendor_id = v.id
        ORDER BY p.created_at DESC
    ");
} elseif ($user_role == 'vendor') {
    $vendor = get_vendor_by_user_id($user_id);
    if ($vendor) {
        $payments = fetch_all("
            SELECT p.*, o.invoice_number, u.name as customer_name, pk.name as package_name
            FROM payments p 
            JOIN orders o ON p.order_id = o.id 
            JOIN packages pk ON o.package_id = pk.id
            JOIN users u ON o.customer_id = u.id 
            WHERE pk.vendor_id = ? 
            ORDER BY p.created_at DESC
        ", [$vendor['id']]);
    } else {
        $payments = [];
    }
} else {
    $payments = fetch_all("
        SELECT p.*, o.invoice_number, pk.name as package_name, v.company_name as vendor_name
        FROM payments p 
        JOIN orders o ON p.order_id = o.id 
        JOIN packages pk ON o.package_id = pk.id
        JOIN vendors v ON pk.vendor_id = v.id
        WHERE o.customer_id = ? 
        ORDER BY p.created_at DESC
    ", [$user_id]);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - WeddingLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navigation.php'; ?>

    <div class="container mt-4">
        <h2>Manajemen Pembayaran</h2>
        
        <?php display_message(); ?>
        
        <?php if ($user_role == 'customer'): ?>
        <!-- Upload Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Upload Bukti Transfer</h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data" id="paymentForm">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Pilih Order *</label>
                                <select name="order_id" class="form-select" required id="orderSelect">
                                    <option value="">-- Pilih Order --</option>
                                    <?php 
                                    $orders = fetch_all("
                                        SELECT o.*, p.name as package_name, v.company_name as vendor_name
                                        FROM orders o 
                                        JOIN packages p ON o.package_id = p.id 
                                        JOIN vendors v ON p.vendor_id = v.id
                                        WHERE o.customer_id = ? AND o.payment_status = 'unpaid'
                                    ", [$user_id]);
                                    foreach ($orders as $order): ?>
                                    <option value="<?= $order['id'] ?>" data-amount="<?= $order['total_price'] ?>">
                                        <?= $order['invoice_number'] ?> - <?= $order['package_name'] ?> 
                                        (<?= format_currency($order['total_price']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nama Bank *</label>
                                <input type="text" name="bank_name" class="form-control" required 
                                       placeholder="Contoh: BCA, Mandiri, BNI">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nomor Rekening *</label>
                                <input type="text" name="account_number" class="form-control" required
                                       placeholder="Nomor rekening pengirim">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Nama Pemilik Rekening *</label>
                                <input type="text" name="account_holder" class="form-control" required
                                       placeholder="Nama sesuai rekening">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Bukti Transfer *</label>
                                <input type="file" name="proof_image" class="form-control" 
                                       accept="image/jpeg,image/png,image/gif" required
                                       onchange="previewImage(this, 'imagePreview')">
                                <div class="form-text">
                                    Format: JPG, PNG, GIF (Maksimal <?= (MAX_FILE_SIZE / 1024 / 1024) ?>MB)
                                </div>
                                
                                <!-- Image Preview -->
                                <div class="mt-2 text-center">
                                    <img id="imagePreview" src="" alt="Preview" class="img-thumbnail d-none" 
                                         style="max-height: 200px;">
                                </div>
                            </div>
                            
                            <!-- Order Summary -->
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6>Order Summary</h6>
                                    <div id="orderSummary" class="text-muted">
                                        Pilih order untuk melihat detail
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            Pastikan bukti transfer jelas terbaca dan sesuai dengan jumlah pembayaran.
                            Setelah upload, tunggu verifikasi dari admin.
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-upload"></i> Upload Bukti Pembayaran
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Payments Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Daftar Pembayaran</h5>
            </div>
            <div class="card-body">
                <?php if (empty($payments)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                        <h5>No payments found</h5>
                        <p class="text-muted">
                            <?php if ($user_role == 'customer'): ?>
                                You haven't made any payments yet.
                            <?php else: ?>
                                No payment records available.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Invoice</th>
                                    <?php if ($user_role != 'customer'): ?>
                                    <th>Customer</th>
                                    <?php endif; ?>
                                    <?php if ($user_role == 'admin'): ?>
                                    <th>Vendor</th>
                                    <?php endif; ?>
                                    <th>Package</th>
                                    <th>Amount</th>
                                    <th>Bank</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>
                                        <strong><?= $payment['invoice_number'] ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?= date('d/m/Y', strtotime($payment['created_at'])) ?>
                                        </small>
                                    </td>
                                    
                                    <?php if ($user_role != 'customer'): ?>
                                    <td>
                                        <strong><?= $payment['customer_name'] ?></strong>
                                        <?php if ($user_role == 'admin'): ?>
                                        <br>
                                        <small class="text-muted"><?= $payment['customer_email'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    
                                    <?php if ($user_role == 'admin'): ?>
                                    <td><?= $payment['vendor_name'] ?></td>
                                    <?php endif; ?>
                                    
                                    <td><?= $payment['package_name'] ?></td>
                                    <td>
                                        <strong><?= format_currency($payment['amount']) ?></strong>
                                    </td>
                                    <td>
                                        <?= $payment['bank_name'] ?>
                                        <?php if ($payment['account_number']): ?>
                                            <br>
                                            <small class="text-muted"><?= $payment['account_number'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= get_status_badge($payment['status']) ?>
                                        <?php if ($payment['verified_at']): ?>
                                            <br>
                                            <small class="text-muted">
                                                <?= date('d/m/Y H:i', strtotime($payment['verified_at'])) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y H:i', strtotime($payment['created_at'])) ?>
                                    </td>
                                    
                                    <td>
                                        <div class="btn-group-vertical btn-group-sm">
                                            <?php if ($user_role == 'admin' && $payment['status'] == 'pending'): ?>
                                                <a href="payments.php?verify=<?= $payment['id'] ?>" 
                                                   class="btn btn-success mb-1"
                                                   onclick="return confirmAction('Verify this payment?')">
                                                    <i class="fas fa-check"></i> Verify
                                                </a>
                                                <a href="payments.php?reject=<?= $payment['id'] ?>" 
                                                   class="btn btn-danger mb-1"
                                                   onclick="return confirmAction('Reject this payment?')">
                                                    <i class="fas fa-times"></i> Reject
                                                </a>
                                            <?php elseif ($user_role == 'admin'): ?>
                                                <span class="text-muted small">
                                                    <?= ucfirst($payment['status']) ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if (($user_role == 'admin' || $user_role == 'vendor') && $payment['proof_image']): ?>
                                                <?php
                                                $proof_path = get_web_path($payment['proof_image']);
                                                if ($proof_path):
                                                ?>
                                                    <a href="<?= $proof_path ?>" 
                                                       target="_blank" 
                                                       class="btn btn-outline-info mt-1"
                                                       onclick="return openProofModal('<?= $proof_path ?>', '<?= $payment['invoice_number'] ?>')">
                                                        <i class="fas fa-eye"></i> View Proof
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted small">File not found</span>
                                                <?php endif; ?>
                                            <?php elseif (($user_role == 'admin' || $user_role == 'vendor') && !$payment['proof_image']): ?>
                                                <span class="text-muted small">No proof</span>
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

    <!-- Proof Modal -->
    <div class="modal fade" id="proofModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Proof - <span id="proofInvoice"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="proofImage" src="" alt="Payment Proof" class="img-fluid rounded" style="max-height: 70vh;">
                    <div class="mt-3" id="proofInfo"></div>
                </div>
                <div class="modal-footer">
                    <a href="#" id="downloadProof" class="btn btn-primary" download>
                        <i class="fas fa-download"></i> Download
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Image preview function
    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        const file = input.files[0];
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.remove('d-none');
            }
            reader.readAsDataURL(file);
        } else {
            preview.src = '';
            preview.classList.add('d-none');
        }
    }
    
    // Order selection handler
    document.getElementById('orderSelect').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const amount = selectedOption.getAttribute('data-amount');
        const orderSummary = document.getElementById('orderSummary');
        
        if (amount) {
            orderSummary.innerHTML = `
                <strong>Amount to Pay:</strong> Rp ${parseInt(amount).toLocaleString('id-ID')}<br>
                <strong>Order:</strong> ${selectedOption.textContent.split(' - ')[1]}
            `;
        } else {
            orderSummary.innerHTML = 'Pilih order untuk melihat detail';
        }
    });
    
    // Proof modal function
    function openProofModal(imagePath, invoiceNumber) {
        document.getElementById('proofInvoice').textContent = invoiceNumber;
        document.getElementById('proofImage').src = imagePath;
        document.getElementById('downloadProof').href = imagePath;
        
        const proofModal = new bootstrap.Modal(document.getElementById('proofModal'));
        proofModal.show();
        
        return false;
    }
    
    // Form submission handler
    document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
    });
    
    // Confirm action helper
    function confirmAction(message) {
        return confirm(message);
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>