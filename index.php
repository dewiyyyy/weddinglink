<?php include 'config.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeddingLink - Platform Jasa Pernikahan Terpercaya</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'navigation.php'; ?>
    
    <main>
        <!-- Hero Section -->
        <section class="hero-section text-center">
            <div class="container">
                <h1 class="display-4 fw-bold mb-4">Mewujudkan Pernikahan Impian Anda</h1>
                <p class="lead mb-4">Temukan vendor pernikahan terpercaya dengan harga transparan dan kualitas terjamin</p>
                <a href="packages.php" class="btn btn-light btn-lg px-5">Jelajahi Layanan</a>
            </div>
        </section>

        <!-- Categories Section -->
        <section class="py-5">
            <div class="container">
                <h2 class="text-center mb-5">Kategori Layanan</h2>
                <div class="row g-4">
                    <?php
                    $categories = fetch_all("SELECT * FROM categories WHERE status = 'active'");
                    foreach ($categories as $category):
                    ?>
                    <div class="col-md-4 col-lg-2">
                        <a href="packages.php?category=<?= $category['id'] ?>" class="text-decoration-none">
                            <div class="card category-card text-center p-3">
                                <i class="fas fa-<?= $category['icon'] ?> feature-icon"></i>
                                <h6 class="fw-bold"><?= $category['name'] ?></h6>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="py-5 bg-light">
            <div class="container">
                <div class="row text-center">
                    <div class="col-md-4">
                        <i class="fas fa-shield-alt fa-3x text-primary mb-3"></i>
                        <h5>Terpercaya</h5>
                        <p>Semua vendor melalui proses verifikasi ketat</p>
                    </div>
                    <div class="col-md-4">
                        <i class="fas fa-tag fa-3x text-primary mb-3"></i>
                        <h5>Harga Transparan</h5>
                        <p>Tidak ada biaya tersembunyi, semua harga jelas</p>
                    </div>
                    <div class="col-md-4">
                        <i class="fas fa-headset fa-3x text-primary mb-3"></i>
                        <h5>Support 24/7</h5>
                        <p>Tim support siap membantu kapan saja</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-dark text-white py-4">
        <div class="container text-center">
            <p>&copy; 2024 WeddingLink. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>