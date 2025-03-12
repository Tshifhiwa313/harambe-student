<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/authentication.php';

// Check if a specific accommodation is requested
$accommodationId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($accommodationId) {
    // Get specific accommodation details
    $accommodation = getAccommodationById($accommodationId);
    
    if (!$accommodation) {
        // Accommodation not found, redirect to listings
        header('Location: accommodations.php');
        exit;
    }
} else {
    // Get all accommodations
    $accommodations = fetchAll("SELECT * FROM accommodations ORDER BY name ASC");
}

include 'includes/header.php';
?>

<?php if ($accommodationId && $accommodation): ?>
    <!-- Single Accommodation View -->
    <div class="container">
        <div class="row mb-4">
            <div class="col-md-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="accommodations.php">Accommodations</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?= $accommodation['name'] ?></li>
                    </ol>
                </nav>
                <h1><?= $accommodation['name'] ?></h1>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8 mb-4">
                <div class="card">
                    <?php if (!empty($accommodation['image_path'])): ?>
                        <img src="uploads/accommodations/<?= $accommodation['image_path'] ?>" class="img-fluid" alt="<?= $accommodation['name'] ?>">
                    <?php else: ?>
                        <div class="bg-light p-5 text-center">
                            <i class="fas fa-building fa-5x text-secondary"></i>
                            <p class="mt-3 text-muted">No image available</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <h5 class="card-title">Description</h5>
                        <p class="card-text"><?= nl2br($accommodation['description']) ?></p>
                        
                        <h5 class="mt-4">Location</h5>
                        <p><i class="fas fa-map-marker-alt"></i> <?= $accommodation['location'] ?></p>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h5>Price</h5>
                                <p class="accommodation-price"><?= formatCurrency($accommodation['price_per_month']) ?> / month</p>
                            </div>
                            <div class="col-md-6">
                                <h5>Availability</h5>
                                <p><?= $accommodation['rooms_available'] ?> rooms available</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Apply Now</h5>
                    </div>
                    <div class="card-body">
                        <p>Interested in this accommodation? Submit an application now.</p>
                        
                        <?php if (!isLoggedIn()): ?>
                            <p>You need to be logged in to apply.</p>
                            <div class="d-grid gap-2">
                                <a href="login.php" class="btn btn-primary">Login</a>
                                <a href="register.php" class="btn btn-outline-primary">Register</a>
                            </div>
                        <?php elseif (hasRole(ROLE_STUDENT)): ?>
                            <div class="d-grid">
                                <a href="applications.php?accommodation_id=<?= $accommodation['id'] ?>" class="btn btn-primary btn-lg">Apply Now</a>
                            </div>
                        <?php else: ?>
                            <p class="alert alert-info">Admin users cannot apply for accommodation.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Contact Information</h5>
                    </div>
                    <div class="card-body">
                        <p><i class="fas fa-envelope"></i> info@harambee.co.za</p>
                        <p><i class="fas fa-phone"></i> +27 12 345 6789</p>
                        <p><i class="fas fa-clock"></i> Monday to Friday, 8am to 5pm</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Accommodations Listing -->
    <div class="container">
        <div class="row mb-4">
            <div class="col-md-8">
                <h1>Student Accommodations</h1>
                <p class="lead">Browse our available accommodations for students.</p>
            </div>
        </div>
        
        <?php if (empty($accommodations)): ?>
            <div class="alert alert-info">
                <p class="text-center">No accommodations are currently available. Please check back later.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($accommodations as $accommodation): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 accommodation-card">
                            <?php if (!empty($accommodation['image_path'])): ?>
                                <img src="uploads/accommodations/<?= $accommodation['image_path'] ?>" class="card-img-top" alt="<?= $accommodation['name'] ?>">
                            <?php else: ?>
                                <div class="card-img-top d-flex align-items-center justify-content-center bg-light">
                                    <i class="fas fa-building fa-5x text-secondary"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?= $accommodation['name'] ?></h5>
                                <p class="card-text"><?= substr($accommodation['description'], 0, 100) . '...' ?></p>
                                <p class="accommodation-price"><?= formatCurrency($accommodation['price_per_month']) ?> / month</p>
                                <p><i class="fas fa-map-marker-alt"></i> <?= $accommodation['location'] ?></p>
                                <p><i class="fas fa-door-open"></i> <?= $accommodation['rooms_available'] ?> rooms available</p>
                            </div>
                            <div class="card-footer">
                                <a href="accommodations.php?id=<?= $accommodation['id'] ?>" class="btn btn-primary">View Details</a>
                                <?php if (isLoggedIn() && hasRole(ROLE_STUDENT)): ?>
                                    <a href="applications.php?accommodation_id=<?= $accommodation['id'] ?>" class="btn btn-apply">Apply Now</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
