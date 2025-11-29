<?php
include 'config.php';
require_login();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_review'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validate_csrf_token($csrf_token)) {
        $_SESSION['error'] = "Invalid security token.";
        header('Location: reviews.php');
        exit;
    }

    $order_id = (int)$_POST['order_id'];
    $rating = (int)$_POST['rating'];
    $comment = sanitize_input($_POST['comment']);
    
    // Validate inputs
    if (empty($order_id) || empty($rating)) {
        $_SESSION['error'] = "Order and rating are required.";
        header('Location: reviews.php');
        exit;
    }
    
    // Validate rating range
    if ($rating < 1 || $rating > 5) {
        $_SESSION['error'] = "Rating must be between 1 and 5.";
        header('Location: reviews.php');
        exit;
    }
    
    // Validate order belongs to customer and is completed
    $order = fetch_one("
        SELECT o.*, p.vendor_id, p.id as package_id 
        FROM orders o 
        JOIN packages p ON o.package_id = p.id 
        WHERE o.id = ? AND o.customer_id = ? AND o.status = 'completed'
    ", [$order_id, $user_id]);
    
    if (!$order) {
        $_SESSION['error'] = "Order not found, not completed, or access denied.";
        header('Location: reviews.php');
        exit;
    }
    
    // Check if review already exists
    $existing_review = fetch_one("SELECT id FROM reviews WHERE order_id = ?", [$order_id]);
    if ($existing_review) {
        $_SESSION['error'] = "Review already submitted for this order.";
        header('Location: reviews.php');
        exit;
    }
    
    // Insert review
    $sql = "INSERT INTO reviews (order_id, customer_id, vendor_id, package_id, rating, comment) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $result = execute($sql, [
        $order_id, 
        $user_id, 
        $order['vendor_id'], 
        $order['package_id'], 
        $rating, 
        $comment
    ]);
    
    if ($result) {
        // Auto-approve reviews for better user experience, or set to pending for admin approval
        $review_status = 'approved'; // Change to 'pending' if you want admin approval
        
        execute("UPDATE reviews SET status = ? WHERE order_id = ?", [$review_status, $order_id]);
        
        // Update vendor rating
        if ($review_status == 'approved') {
            update_vendor_rating($order['vendor_id']);
        }
        
        $_SESSION['success'] = "Review submitted successfully!";
        
        // Log the review submission
        error_log("Review submitted: Order {$order_id}, Rating: {$rating}, Vendor: {$order['vendor_id']}");
    } else {
        $_SESSION['error'] = "Failed to submit review. Please try again.";
    }
    
    header('Location: reviews.php');
    exit;
}

// Handle review approval/rejection (admin/vendor)
if (isset($_GET['approve']) || isset($_GET['reject'])) {
    if ($user_role != 'admin' && $user_role != 'vendor') {
        $_SESSION['error'] = "Access denied.";
        header('Location: reviews.php');
        exit;
    }
    
    $review_id = isset($_GET['approve']) ? (int)$_GET['approve'] : (int)$_GET['reject'];
    $action = isset($_GET['approve']) ? 'approved' : 'rejected';
    
    // Verify access
    if ($user_role == 'vendor') {
        $vendor = get_vendor_by_user_id($user_id);
        $review = fetch_one("SELECT * FROM reviews WHERE id = ? AND vendor_id = ?", [$review_id, $vendor['id']]);
    } else {
        $review = fetch_one("SELECT * FROM reviews WHERE id = ?", [$review_id]);
    }
    
    if (!$review) {
        $_SESSION['error'] = "Review not found or access denied.";
        header('Location: reviews.php');
        exit;
    }
    
    // Update review status
    $result = execute("UPDATE reviews SET status = ? WHERE id = ?", [$action, $review_id]);
    
    if ($result) {
        if ($action == 'approved') {
            // Update vendor rating if approved
            update_vendor_rating($review['vendor_id']);
            $_SESSION['success'] = "Review approved successfully!";
        } else {
            $_SESSION['success'] = "Review rejected successfully!";
        }
        
        // Log the action
        error_log("Review {$action}: Review ID {$review_id}, By: {$user_id}");
    } else {
        $_SESSION['error'] = "Failed to update review status.";
    }
    
    header('Location: reviews.php');
    exit;
}

// Handle review deletion (admin and customer can delete their own reviews)
if (isset($_GET['delete'])) {
    $review_id = (int)$_GET['delete'];
    
    // Get review details
    $review = fetch_one("SELECT * FROM reviews WHERE id = ?", [$review_id]);
    if (!$review) {
        $_SESSION['error'] = "Review not found.";
        header('Location: reviews.php');
        exit;
    }
    
    // Verify ownership or admin access
    if ($user_role != 'admin' && $review['customer_id'] != $user_id) {
        $_SESSION['error'] = "Access denied.";
        header('Location: reviews.php');
        exit;
    }
    
    $result = execute("DELETE FROM reviews WHERE id = ?", [$review_id]);
    
    if ($result) {
        // Update vendor rating after deletion
        if ($review['status'] == 'approved') {
            update_vendor_rating($review['vendor_id']);
        }
        $_SESSION['success'] = "Review deleted successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete review.";
    }
    
    header('Location: reviews.php');
    exit;
}

// Get reviews based on role
if ($user_role == 'admin') {
    $reviews = fetch_all("
        SELECT r.*, u.name as customer_name, v.company_name as vendor_name, 
               p.name as package_name, o.invoice_number
        FROM reviews r
        JOIN users u ON r.customer_id = u.id
        JOIN vendors v ON r.vendor_id = v.id
        JOIN packages p ON r.package_id = p.id
        JOIN orders o ON r.order_id = o.id
        ORDER BY r.created_at DESC
    ");
    
    // Statistics for admin
    $review_stats = fetch_one("
        SELECT 
            COUNT(*) as total_reviews,
            AVG(rating) as avg_rating,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_reviews,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_reviews
        FROM reviews
    ");
    
} elseif ($user_role == 'vendor') {
    $vendor = get_vendor_by_user_id($user_id);
    if ($vendor) {
        $reviews = fetch_all("
            SELECT r.*, u.name as customer_name, p.name as package_name, o.invoice_number
            FROM reviews r
            JOIN users u ON r.customer_id = u.id
            JOIN packages p ON r.package_id = p.id
            JOIN orders o ON r.order_id = o.id
            WHERE r.vendor_id = ?
            ORDER BY r.created_at DESC
        ", [$vendor['id']]);
        
        // Vendor statistics
        $vendor_stats = fetch_one("
            SELECT 
                COUNT(*) as total_reviews,
                AVG(rating) as avg_rating,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_reviews
            FROM reviews 
            WHERE vendor_id = ?
        ", [$vendor['id']]);
    } else {
        $reviews = [];
        $vendor_stats = [];
    }
} else {
    // Customer - get their reviews and completed orders available for review
    $reviews = fetch_all("
        SELECT r.*, v.company_name as vendor_name, p.name as package_name, o.invoice_number
        FROM reviews r
        JOIN vendors v ON r.vendor_id = v.id
        JOIN packages p ON r.package_id = p.id
        JOIN orders o ON r.order_id = o.id
        WHERE r.customer_id = ?
        ORDER BY r.created_at DESC
    ", [$user_id]);
    
    // Get completed orders without reviews
    $reviewable_orders = fetch_all("
        SELECT o.*, p.name as package_name, v.company_name as vendor_name, v.rating as vendor_rating
        FROM orders o
        JOIN packages p ON o.package_id = p.id
        JOIN vendors v ON p.vendor_id = v.id
        WHERE o.customer_id = ? 
        AND o.status = 'completed'
        AND o.id NOT IN (SELECT order_id FROM reviews WHERE customer_id = ?)
        ORDER BY o.created_at DESC
    ", [$user_id, $user_id]);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews - WeddingLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .rating-stars {
            color: #ffc107;
            font-size: 1.2rem;
        }
        .review-card {
            border-left: 4px solid #007bff;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .review-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .rating-input .star {
            cursor: pointer;
            font-size: 1.8rem;
            color: #ddd;
            transition: color 0.2s, transform 0.2s;
            margin: 0 2px;
        }
        .rating-input .star:hover {
            transform: scale(1.2);
        }
        .rating-input .star.active,
        .rating-input .star:hover,
        .rating-input .star.hover {
            color: #ffc107;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        .review-badge {
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <?php include 'navigation.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-star text-warning"></i>
                Reviews & Ratings
            </h2>
            
            <?php if ($user_role == 'admin' && isset($review_stats)): ?>
                <div class="text-end">
                    <small class="text-muted">
                        Total Reviews: <strong><?= $review_stats['total_reviews'] ?></strong> | 
                        Avg Rating: <strong><?= number_format($review_stats['avg_rating'] ?? 0, 1) ?></strong>
                    </small>
                </div>
            <?php elseif ($user_role == 'vendor' && isset($vendor_stats)): ?>
                <div class="text-end">
                    <small class="text-muted">
                        Your Rating: <strong><?= number_format($vendor_stats['avg_rating'] ?? 0, 1) ?></strong> | 
                        Reviews: <strong><?= $vendor_stats['total_reviews'] ?></strong>
                    </small>
                </div>
            <?php endif; ?>
        </div>
        
        <?php display_message(); ?>

        <!-- Statistics Cards for Admin/Vendor -->
        <?php if ($user_role == 'admin' && isset($review_stats)): ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3><?= $review_stats['total_reviews'] ?></h3>
                        <p class="mb-0">Total Reviews</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3><?= number_format($review_stats['avg_rating'] ?? 0, 1) ?></h3>
                        <p class="mb-0">Average Rating</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3><?= $review_stats['pending_reviews'] ?></h3>
                        <p class="mb-0">Pending Reviews</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3><?= $review_stats['approved_reviews'] ?></h3>
                        <p class="mb-0">Approved Reviews</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Review Form for Customers -->
        <?php if ($user_role == 'customer' && !empty($reviewable_orders)): ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-edit me-2"></i>Submit New Review
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="reviewForm">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="submit_review" value="1">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Select Order *</label>
                                <select name="order_id" class="form-select" required id="orderSelect">
                                    <option value="">-- Choose Completed Order --</option>
                                    <?php foreach ($reviewable_orders as $order): ?>
                                    <option value="<?= $order['id'] ?>" 
                                            data-vendor="<?= htmlspecialchars($order['vendor_name']) ?>"
                                            data-vendor-rating="<?= $order['vendor_rating'] ?>">
                                        <?= $order['invoice_number'] ?> - <?= $order['package_name'] ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text" id="vendorInfo">
                                    Select an order to see vendor details
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Your Rating *</label>
                                <div class="rating-input mb-2" id="ratingInput">
                                    <span class="star" data-rating="1" title="Poor">★</span>
                                    <span class="star" data-rating="2" title="Fair">★</span>
                                    <span class="star" data-rating="3" title="Good">★</span>
                                    <span class="star" data-rating="4" title="Very Good">★</span>
                                    <span class="star" data-rating="5" title="Excellent">★</span>
                                    <input type="hidden" name="rating" id="ratingValue" required>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <small class="form-text">Click stars to rate your experience</small>
                                    <small id="ratingText" class="text-muted">Not rated</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Your Review</label>
                                <textarea name="comment" class="form-control" rows="6" 
                                          placeholder="Share your experience with this vendor... 
How was the service quality?
Was the vendor professional?
Would you recommend them to others?"
                                          maxlength="1000"></textarea>
                                <div class="form-text">
                                    <span id="charCount">0</span>/1000 characters
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-2"></i>
                            Your review helps other couples make better decisions. 
                            Be honest and constructive in your feedback.
                        </small>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="reset" class="btn btn-outline-secondary me-md-2">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-paper-plane me-2"></i>Submit Review
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php elseif ($user_role == 'customer' && empty($reviewable_orders)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                You don't have any completed orders available for review. 
                Reviews can be submitted for completed orders only.
            </div>
        <?php endif; ?>

        <!-- Reviews List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    <?php if ($user_role == 'customer'): ?>
                        My Reviews
                    <?php elseif ($user_role == 'vendor'): ?>
                        Customer Reviews
                        <?php if (isset($vendor_stats)): ?>
                            <small class="text-muted">
                                (Average: <?= number_format($vendor_stats['avg_rating'] ?? 0, 1) ?> ★)
                            </small>
                        <?php endif; ?>
                    <?php else: ?>
                        All Reviews
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($reviews)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-comments fa-4x text-muted mb-3"></i>
                        <h4>No reviews found</h4>
                        <p class="text-muted mb-4">
                            <?php if ($user_role == 'customer'): ?>
                                You haven't submitted any reviews yet.
                            <?php elseif ($user_role == 'vendor'): ?>
                                No reviews for your packages yet.
                            <?php else: ?>
                                No reviews available in the system.
                            <?php endif; ?>
                        </p>
                        <?php if ($user_role == 'customer' && !empty($reviewable_orders)): ?>
                            <a href="#reviewForm" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Write Your First Review
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($reviews as $review): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card review-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="flex-grow-1">
                                                <h6 class="card-title mb-1 text-primary">
                                                    <?= htmlspecialchars($review['package_name']) ?>
                                                </h6>
                                                <p class="text-muted small mb-1">
                                                    <i class="fas fa-receipt me-1"></i>
                                                    <?= $review['invoice_number'] ?>
                                                </p>
                                                <?php if ($user_role != 'customer'): ?>
                                                    <p class="text-muted small mb-1">
                                                        <i class="fas fa-user me-1"></i>
                                                        By: <?= htmlspecialchars($review['customer_name']) ?>
                                                    </p>
                                                <?php endif; ?>
                                                <?php if ($user_role == 'admin' || $user_role == 'customer'): ?>
                                                    <p class="text-muted small mb-0">
                                                        <i class="fas fa-store me-1"></i>
                                                        Vendor: <?= htmlspecialchars($review['vendor_name']) ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-end">
                                                <div class="rating-stars mb-2">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star<?= $i <= $review['rating'] ? '' : '-o' ?>"></i>
                                                    <?php endfor; ?>
                                                    <small class="text-muted ms-1">(<?= $review['rating'] ?>.0)</small>
                                                </div>
                                                <span class="badge bg-<?= 
                                                    $review['status'] == 'approved' ? 'success' : 
                                                    ($review['status'] == 'rejected' ? 'danger' : 'warning')
                                                ?> review-badge">
                                                    <?= ucfirst($review['status']) ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($review['comment'])): ?>
                                            <div class="review-comment">
                                                <p class="card-text">
                                                    <i class="fas fa-quote-left text-muted me-2"></i>
                                                    <?= nl2br(htmlspecialchars($review['comment'])) ?>
                                                </p>
                                            </div>
                                        <?php else: ?>
                                            <p class="card-text text-muted fst-italic">
                                                <i class="fas fa-minus me-2"></i>No comment provided
                                            </p>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?= date('M j, Y \a\t H:i', strtotime($review['created_at'])) ?>
                                            </small>
                                            
                                            <div class="btn-group btn-group-sm">
                                                <?php if (($user_role == 'admin' || $user_role == 'vendor') && $review['status'] == 'pending'): ?>
                                                    <a href="reviews.php?approve=<?= $review['id'] ?>" 
                                                       class="btn btn-success"
                                                       onclick="return confirmAction('Approve this review?')">
                                                        <i class="fas fa-check"></i> Approve
                                                    </a>
                                                    <a href="reviews.php?reject=<?= $review['id'] ?>" 
                                                       class="btn btn-danger"
                                                       onclick="return confirmAction('Reject this review?')">
                                                        <i class="fas fa-times"></i> Reject
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($user_role == 'admin' || $review['customer_id'] == $user_id): ?>
                                                    <a href="reviews.php?delete=<?= $review['id'] ?>" 
                                                       class="btn btn-outline-danger"
                                                       onclick="return confirmAction('Delete this review? This action cannot be undone.')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Show reviewable orders count for customers -->
                    <?php if ($user_role == 'customer' && !empty($reviewable_orders)): ?>
                        <div class="text-center mt-4">
                            <div class="alert alert-warning">
                                <i class="fas fa-star me-2"></i>
                                You have <strong><?= count($reviewable_orders) ?></strong> completed orders waiting for your review.
                                <a href="#reviewForm" class="alert-link">Write a review now!</a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Star rating functionality
        const ratingInput = document.getElementById('ratingInput');
        const ratingValue = document.getElementById('ratingValue');
        const ratingText = document.getElementById('ratingText');
        const stars = ratingInput?.querySelectorAll('.star');
        
        const ratingLabels = {
            1: 'Poor',
            2: 'Fair', 
            3: 'Good',
            4: 'Very Good',
            5: 'Excellent'
        };
        
        if (stars) {
            let currentRating = 0;
            let isHovering = false;
            
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    currentRating = parseInt(this.getAttribute('data-rating'));
                    ratingValue.value = currentRating;
                    updateStarDisplay(currentRating);
                    updateRatingText(currentRating);
                });
                
                star.addEventListener('mouseover', function() {
                    if (!currentRating) {
                        const hoverRating = parseInt(this.getAttribute('data-rating'));
                        updateStarDisplay(hoverRating, true);
                        updateRatingText(hoverRating);
                    }
                });
                
                star.addEventListener('mouseout', function() {
                    if (!currentRating) {
                        updateStarDisplay(0);
                        updateRatingText(0);
                    } else {
                        updateStarDisplay(currentRating);
                    }
                });
            });
            
            function updateStarDisplay(rating, isHover = false) {
                stars.forEach(star => {
                    const starRating = parseInt(star.getAttribute('data-rating'));
                    if (starRating <= rating) {
                        star.classList.add('active');
                        if (isHover) star.classList.add('hover');
                    } else {
                        star.classList.remove('active', 'hover');
                    }
                });
            }
            
            function updateRatingText(rating) {
                if (rating > 0) {
                    ratingText.textContent = ratingLabels[rating];
                    ratingText.className = 'text-warning fw-bold';
                } else {
                    ratingText.textContent = 'Not rated';
                    ratingText.className = 'text-muted';
                }
            }
        }
        
        // Order selection handler
        const orderSelect = document.getElementById('orderSelect');
        const vendorInfo = document.getElementById('vendorInfo');
        
        if (orderSelect && vendorInfo) {
            orderSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const vendorName = selectedOption.getAttribute('data-vendor');
                const vendorRating = selectedOption.getAttribute('data-vendor-rating');
                
                if (vendorName) {
                    let vendorHtml = `<strong>Vendor:</strong> ${vendorName}`;
                    if (vendorRating && vendorRating > 0) {
                        vendorHtml += ` | <strong>Rating:</strong> ${vendorRating} ★`;
                    }
                    vendorInfo.innerHTML = vendorHtml;
                } else {
                    vendorInfo.innerHTML = 'Select an order to see vendor details';
                }
            });
        }
        
        // Character count for comment
        const commentTextarea = document.querySelector('textarea[name="comment"]');
        const charCount = document.getElementById('charCount');
        
        if (commentTextarea && charCount) {
            commentTextarea.addEventListener('input', function() {
                charCount.textContent = this.value.length;
                
                if (this.value.length > 900) {
                    charCount.className = 'text-warning';
                } else {
                    charCount.className = 'text-muted';
                }
            });
        }
        
        // Form validation
        const reviewForm = document.getElementById('reviewForm');
        if (reviewForm) {
            reviewForm.addEventListener('submit', function(e) {
                if (!ratingValue.value) {
                    e.preventDefault();
                    showAlert('Please select a rating before submitting.', 'warning');
                    ratingInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return false;
                }
                
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            });
        }
        
        // Helper function to show alerts
        function showAlert(message, type = 'info') {
            // You can implement a custom alert system here
            alert(message); // Simple alert for now
        }
        
        // Confirm action helper
        function confirmAction(message) {
            return confirm(message);
        }
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>