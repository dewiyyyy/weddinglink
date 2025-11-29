<?php
// error.php - Custom error page
$error_code = http_response_code();
$error_messages = [
    400 => 'Bad Request',
    401 => 'Unauthorized', 
    403 => 'Forbidden',
    404 => 'Page Not Found',
    500 => 'Internal Server Error'
];

$error_title = $error_messages[$error_code] ?? 'Error';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error <?= $error_code ?> - WeddingLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid vh-100 d-flex align-items-center justify-content-center">
        <div class="text-center">
            <h1 class="display-1 fw-bold text-primary"><?= $error_code ?></h1>
            <h2 class="mb-4"><?= $error_title ?></h2>
            <p class="lead mb-4 text-muted">
                <?php if ($error_code == 404): ?>
                    Halaman yang Anda cari tidak ditemukan.
                <?php elseif ($error_code == 403): ?>
                    Anda tidak memiliki akses ke halaman ini.
                <?php else: ?>
                    Terjadi kesalahan. Silakan coba lagi nanti.
                <?php endif; ?>
            </p>
            <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                <a href="index.php" class="btn btn-primary btn-lg px-4">
                    <i class="fas fa-home me-2"></i>Kembali ke Beranda
                </a>
                <a href="javascript:history.back()" class="btn btn-outline-secondary btn-lg px-4">
                    <i class="fas fa-arrow-left me-2"></i>Kembali
                </a>
            </div>
        </div>
    </div>
</body>
</html>