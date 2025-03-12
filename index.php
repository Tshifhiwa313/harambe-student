<?php
$page_title = 'Home';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
require_once 'include/auth.php';

// Get available accommodations
$accommodations = get_available_accommodations();

// Include header
include 'include/header.php';

// Include navbar
include 'include/navbar.php';
?>

<div class="container mt-4">
    <!-- Hero Section -->
    <div class="row mb-5">
        <div class="col-lg-6">
            <div class="card border-0 bg-light">
                <div class="card-body p-4">
                    <h1 class="display-4 fw-bold text-primary mb-4">Harambee Student Living</h1>
                    <p class="lead mb-4">Find your perfect student accommodation with us. Comfortable, affordable, and convenient options for all students.</p>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                        <?php if (!is_logged_in()): ?>
                            <a href="register.php" class="btn btn-primary btn-lg px-4 me-md-2">
                                <i class="fas fa-user-plus me-2"></i>Sign Up
                            </a>
                            <a href="accommodations.php" class="btn btn-outline-primary btn-lg px-4">
                                <i class="fas fa-building me-2"></i>View Accommodations
                            </a>
                        <?php elseif (is_student()): ?>
                            <a href="accommodations.php" class="btn btn-primary btn-lg px-4 me-md-2">
                                <i class="fas fa-building me-2"></i>Browse Accommodations
                            </a>
                            <a href="applications.php" class="btn btn-outline-primary btn-lg px-4">
                                <i class="fas fa-file-alt me-2"></i>My Applications
                            </a>
                        <?php else: ?>
                            <a href="admin.php" class="btn btn-primary btn-lg px-4 me-md-2">
                                <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
                            </a>
                            <a href="accommodations.php" class="btn btn-outline-primary btn-lg px-4">
                                <i class="fas fa-building me-2"></i>Manage Accommodations
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 d-flex align-items-center">
            <div class="bg-primary text-white p-5 rounded-3 w-100">
                <h2 class="h3 mb-4">Why Choose Harambee?</h2>
                <ul class="list-unstyled mb-0">
                    <li class="mb-3"><i class="fas fa-check-circle me-2"></i> Quality accommodations near campuses</li>
                    <li class="mb-3"><i class="fas fa-check-circle me-2"></i> Secure and safe living environments</li>
                    <li class="mb-3"><i class="fas fa-check-circle me-2"></i> Fast maintenance response</li>
                    <li class="mb-3"><i class="fas fa-check-circle me-2"></i> Inclusive utilities in many properties</li>
                    <li><i class="fas fa-check-circle me-2"></i> Support from dedicated property managers</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Featured Accommodations -->
    <h2 class="text-center mb-4">Featured Accommodations</h2>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-5">
        <?php if (empty($accommodations)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No accommodations available at the moment. Please check back later.
                </div>
            </div>
        <?php else: ?>
            <?php foreach (array_slice($accommodations, 0, 6) as $accommodation): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm">
                        <?php if (!empty($accommodation['image_path']) && file_exists($accommodation['image_path'])): ?>
                            <img src="<?php echo $accommodation['image_path']; ?>" class="card-img-top" alt="<?php echo $accommodation['name']; ?>">
                        <?php else: ?>
                            <div class="card-img-top bg-light text-center py-5">
                                <i class="fas fa-building fa-5x text-secondary"></i>
                            </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $accommodation['name']; ?></h5>
                            <p class="card-text text-muted mb-1">
                                <i class="fas fa-map-marker-alt me-2"></i><?php echo $accommodation['location']; ?>
                            </p>
                            <p class="card-text mb-2">
                                <span class="badge bg-primary"><?php echo format_currency($accommodation['price_per_month']); ?> /month</span>
                                <span class="badge bg-secondary ms-1"><?php echo $accommodation['available_units']; ?> units available</span>
                            </p>
                            <p class="card-text mb-3"><?php echo substr($accommodation['description'], 0, 100); ?>...</p>
                            <div class="d-grid">
                                <a href="accommodations.php?id=<?php echo $accommodation['id']; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-info-circle me-2"></i>View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- How It Works -->
    <div class="row mb-5">
        <div class="col-12 text-center mb-4">
            <h2>How It Works</h2>
            <p class="lead">Four simple steps to secure your student accommodation</p>
        </div>
        <div class="col-md-3">
            <div class="text-center">
                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                    <i class="fas fa-search fa-2x"></i>
                </div>
                <h3 class="h5">1. Browse</h3>
                <p>Explore our available accommodations to find your perfect match.</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center">
                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                    <i class="fas fa-file-alt fa-2x"></i>
                </div>
                <h3 class="h5">2. Apply</h3>
                <p>Submit your application for your chosen accommodation.</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center">
                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                    <i class="fas fa-file-signature fa-2x"></i>
                </div>
                <h3 class="h5">3. Sign</h3>
                <p>Review and digitally sign your lease agreement.</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="text-center">
                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                    <i class="fas fa-home fa-2x"></i>
                </div>
                <h3 class="h5">4. Move In</h3>
                <p>Get your keys and enjoy your new student accommodation!</p>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <div class="row">
        <div class="col-12">
            <div class="bg-light p-5 rounded-3 text-center">
                <h2>Ready to Find Your Student Accommodation?</h2>
                <p class="lead mb-4">Join thousands of students who have found their ideal accommodation with Harambee Student Living.</p>
                <div class="d-flex justify-content-center gap-3">
                    <?php if (!is_logged_in()): ?>
                        <a href="register.php" class="btn btn-primary btn-lg px-4">
                            <i class="fas fa-user-plus me-2"></i>Sign Up Now
                        </a>
                    <?php else: ?>
                        <a href="accommodations.php" class="btn btn-primary btn-lg px-4">
                            <i class="fas fa-building me-2"></i>Browse Accommodations
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'include/footer.php';
?>
