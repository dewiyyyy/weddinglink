<?php
include 'config.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$form_data = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'role' => 'customer'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = array_map('sanitize_input', $_POST);
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validate_csrf_token($csrf_token)) {
        $error = "Invalid security token. Please try again.";
    } else {
        // Basic validation
        if (empty($form_data['name']) || empty($form_data['email']) || empty($form_data['password'])) {
            $error = "Semua field wajib diisi.";
        } elseif ($form_data['password'] !== $form_data['confirm_password']) {
            $error = "Password dan konfirmasi password tidak cocok.";
        } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $error = "Format email tidak valid.";
        } else {
            // Check if email already exists
            $existing_user = fetch_one("SELECT id FROM users WHERE email = ?", [$form_data['email']]);
            
            if ($existing_user) {
                $error = "Email sudah terdaftar. Silakan gunakan email lain.";
            } else {
                // Hash password
                $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);
                
                // Insert user
                $sql = "INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)";
                $result = execute($sql, [
                    $form_data['name'],
                    $form_data['email'],
                    $form_data['phone'],
                    $hashed_password,
                    $form_data['role']
                ]);
                
                if ($result) {
                    $success = "Registrasi berhasil! Silakan login.";
                    $form_data = ['name' => '', 'email' => '', 'phone' => '', 'role' => 'customer'];
                } else {
                    $error = "Terjadi kesalahan saat registrasi. Silakan coba lagi.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - WeddingLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .register-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 2rem;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php $no_nav = true; ?>
    
    <div class="container">
        <div class="register-container">
            <h2 class="text-center mb-4">
                <i class="fas fa-heart text-primary"></i><br>
                Daftar WeddingLink
            </h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= $form_data['name'] ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= $form_data['email'] ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="phone" class="form-label">No. Telepon</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= $form_data['phone'] ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="role" class="form-label">Daftar sebagai</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="customer" <?= $form_data['role'] == 'customer' ? 'selected' : '' ?>>Customer</option>
                                <option value="vendor" <?= $form_data['role'] == 'vendor' ? 'selected' : '' ?>>Vendor</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Konfirmasi Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">Daftar</button>
            </form>
            
            <div class="text-center mt-3">
                <p>Sudah punya akun? <a href="login.php">Login di sini</a></p>
                <p><a href="index.php">Kembali ke beranda</a></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>