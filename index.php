<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/authentication.php';

// Create database tables if they don't exist
if (!tableExists('users')) {
    header('Location: schema.php');
    exit;
}

// Get featured accommodations
$featuredAccommodations = fetchAll("SELECT * FROM accommodations ORDER BY id DESC LIMIT 3");

include 'includes/header.php';
?>

<!-- Hero Section -->
<div class="bg-primary text-white py-5 mb-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold">Find Your Perfect Student Accommodation</h1>
                <p class="lead">Harambee Student Living offers quality, affordable accommodation for students across South Africa.</p>
                <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                    <a href="accommodations.php" class="btn btn-light btn-lg px-4 me-md-2">Browse Accommodations</a>
                    <?php if (!isLoggedIn()): ?>
                        <a href="register.php" class="btn btn-outline-light btn-lg px-4">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block">
                <div class="text-center">
                    <i class="fas fa-building fa-10x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Featured Accommodations -->
<div class="container mb-5">
    <h2 class="text-center mb-4">Featured Accommodations</h2>
    
    <?php if (empty($featuredAccommodations)): ?>
        <div class="alert alert-info">
            <p class="text-center">No accommodations available at the moment. Please check back later.</p>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($featuredAccommodations as $accommodation): ?>
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
        
        <div class="text-center mt-4">
            <a href="accommodations.php" class="btn btn-outline-primary btn-lg">View All Accommodations</a>
        </div>
    <?php endif; ?>
</div>

<!-- Features Section -->
<div class="bg-light py-5">
    <div class="container">
        <h2 class="text-center mb-5">Why Choose Harambee Student Living?</h2>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card border-0 h-100 dashboard-card">
                    <div class="card-body">
                        <div class="icon">
                            <i class="fas fa-bed"></i>
                        </div>
                        <h3 class="card-title h4">Quality Accommodations</h3>
                        <p class="card-text">Our accommodations are designed to meet the needs of students, providing comfortable living spaces for study and relaxation.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 h-100 dashboard-card">
                    <div class="card-body">
                        <div class="icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h3 class="card-title h4">Safe & Secure</h3>
                        <p class="card-text">Security is our priority. All our accommodations have security measures to ensure students can focus on their studies.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 h-100 dashboard-card">
                    <div class="card-body">
                        <div class="icon">
                            <i class="fas fa-tools"></i>
                        </div>
                        <h3 class="card-title h4">Maintenance Support</h3>
                        <p class="card-text">Our responsive maintenance team ensures any issues are quickly addressed, providing you with a hassle-free living experience.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- How It Works Section -->
<div class="container py-5">
    <h2 class="text-center mb-5">How It Works</h2>
    
    <div class="row">
        <div class="col-md-3 text-center mb-4">
            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                <i class="fas fa-search fa-2x"></i>
            </div>
            <h3 class="h5">1. Browse</h3>
            <p>Explore our available accommodations and find the perfect match for your needs.</p>
        </div>
        
        <div class="col-md-3 text-center mb-4">
            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                <i class="fas fa-file-alt fa-2x"></i>
            </div>
            <h3 class="h5">2. Apply</h3>
            <p>Submit your application online with just a few clicks.</p>
        </div>
        
        <div class="col-md-3 text-center mb-4">
            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                <i class="fas fa-file-signature fa-2x"></i>
            </div>
            <h3 class="h5">3. Sign Lease</h3>
            <p>Once approved, digitally sign your lease agreement.</p>
        </div>
        
        <div class="col-md-3 text-center mb-4">
            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                <i class="fas fa-home fa-2x"></i>
            </div>
            <h3 class="h5">4. Move In</h3>
            <p>Get your keys and move into your new accommodation.</p>
        </div>
    </div>
    
    <div class="text-center mt-4">
        <?php if (!isLoggedIn()): ?>
            <a href="register.php" class="btn btn-primary btn-lg">Get Started Today</a>
        <?php else: ?>
            <a href="accommodations.php" class="btn btn-primary btn-lg">Find Accommodation</a>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
