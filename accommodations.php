<?php
$page_title = 'Accommodations';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
require_once 'include/auth.php';

// Check if viewing a specific accommodation
$accommodation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$accommodation = null;

if ($accommodation_id > 0) {
    $accommodation = get_accommodation($accommodation_id);
    if ($accommodation) {
        $page_title = $accommodation['name'];
    }
}

// Handle applying for accommodation
$application_submitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply']) && is_student()) {
    $student_id = get_current_user_id();
    $accommodation_id = isset($_POST['accommodation_id']) ? (int)$_POST['accommodation_id'] : 0;
    
    // Check if already applied
    if (has_student_already_applied($student_id, $accommodation_id)) {
        set_flash_message('warning', 'You have already applied for this accommodation.');
        redirect("accommodations.php?id=$accommodation_id");
    }
    
    // Create application
    $query = "INSERT INTO applications (student_id, accommodation_id, status, created_at) 
              VALUES (?, ?, 'pending', datetime('now'))";
    db_query($query, [$student_id, $accommodation_id]);
    
    // Create notification for student
    create_notification(
        $student_id,
        'Application Submitted',
        'Your application has been submitted and is pending review.',
        'info'
    );
    
    // Create notification for admin
    $accommodation = get_accommodation($accommodation_id);
    if ($accommodation && $accommodation['admin_id']) {
        create_notification(
            $accommodation['admin_id'],
            'New Application',
            'A new application has been submitted for ' . $accommodation['name'],
            'info'
        );
    }
    
    set_flash_message('success', 'Application submitted successfully!');
    redirect("accommodations.php?id=$accommodation_id");
}

// Handle adding new accommodation (admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_accommodation']) && is_admin()) {
    $name = isset($_POST['name']) ? sanitize_input($_POST['name']) : '';
    $description = isset($_POST['description']) ? sanitize_input($_POST['description']) : '';
    $location = isset($_POST['location']) ? sanitize_input($_POST['location']) : '';
    $capacity = isset($_POST['capacity']) ? (int)$_POST['capacity'] : 0;
    $price_per_month = isset($_POST['price_per_month']) ? (float)$_POST['price_per_month'] : 0;
    $available_units = isset($_POST['available_units']) ? (int)$_POST['available_units'] : 0;
    $admin_id = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : 0;
    
    // Process image upload
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image_path = upload_image($_FILES['image']);
    }
    
    // Insert accommodation
    $query = "INSERT INTO accommodations (name, description, location, capacity, price_per_month, 
              available_units, admin_id, image_path, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))";
    db_query($query, [
        $name, $description, $location, $capacity, $price_per_month, 
        $available_units, $admin_id ?: null, $image_path
    ]);
    
    set_flash_message('success', 'Accommodation added successfully!');
    redirect('accommodations.php');
}

// Handle editing accommodation (admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_accommodation']) && is_admin()) {
    $accommodation_id = isset($_POST['accommodation_id']) ? (int)$_POST['accommodation_id'] : 0;
    $name = isset($_POST['name']) ? sanitize_input($_POST['name']) : '';
    $description = isset($_POST['description']) ? sanitize_input($_POST['description']) : '';
    $location = isset($_POST['location']) ? sanitize_input($_POST['location']) : '';
    $capacity = isset($_POST['capacity']) ? (int)$_POST['capacity'] : 0;
    $price_per_month = isset($_POST['price_per_month']) ? (float)$_POST['price_per_month'] : 0;
    $available_units = isset($_POST['available_units']) ? (int)$_POST['available_units'] : 0;
    $admin_id = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : 0;
    
    // Get current accommodation data
    $current_accommodation = get_accommodation($accommodation_id);
    
    // Process image upload
    $image_path = $current_accommodation['image_path'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $new_image_path = upload_image($_FILES['image']);
        if ($new_image_path) {
            $image_path = $new_image_path;
        }
    }
    
    // Update accommodation
    $query = "UPDATE accommodations 
              SET name = ?, description = ?, location = ?, capacity = ?, 
              price_per_month = ?, available_units = ?, admin_id = ?, image_path = ?, 
              updated_at = datetime('now')
              WHERE id = ?";
    db_query($query, [
        $name, $description, $location, $capacity, $price_per_month, 
        $available_units, $admin_id ?: null, $image_path, $accommodation_id
    ]);
    
    set_flash_message('success', 'Accommodation updated successfully!');
    redirect("accommodations.php?id=$accommodation_id");
}

// Get accommodations list
$accommodations = [];
if (is_master_admin()) {
    $accommodations = get_all_accommodations();
} elseif (is_admin()) {
    $accommodations = get_admin_accommodations(get_current_user_id());
} else {
    $accommodations = get_available_accommodations();
}

// Get admin users (for dropdown)
$admin_users = [];
if (is_master_admin()) {
    $admin_users = get_admin_users();
}

// Include header
include 'include/header.php';

// Include navbar
include 'include/navbar.php';
?>

<div class="container mt-4">
    <?php if ($accommodation): ?>
        <!-- Single Accommodation View -->
        <div class="row mb-4">
            <div class="col-md-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="accommodations.php">Accommodations</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo $accommodation['name']; ?></li>
                    </ol>
                </nav>
            </div>
            <?php if (is_admin() && (is_master_admin() || $accommodation['admin_id'] == get_current_user_id())): ?>
                <div class="col-md-4 text-end">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editAccommodationModal">
                        <i class="fas fa-edit me-2"></i>Edit Accommodation
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <?php if (!empty($accommodation['image_path']) && file_exists($accommodation['image_path'])): ?>
                        <img src="<?php echo $accommodation['image_path']; ?>" class="card-img-top img-fluid" alt="<?php echo $accommodation['name']; ?>">
                    <?php else: ?>
                        <div class="card-img-top bg-light text-center py-5">
                            <i class="fas fa-building fa-5x text-secondary"></i>
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <h1 class="card-title"><?php echo $accommodation['name']; ?></h1>
                        <p class="card-text text-muted mb-3">
                            <i class="fas fa-map-marker-alt me-2"></i><?php echo $accommodation['location']; ?>
                        </p>
                        <div class="mb-4">
                            <span class="badge bg-primary fs-5"><?php echo format_currency($accommodation['price_per_month']); ?> /month</span>
                            <?php if ($accommodation['available_units'] > 0): ?>
                                <span class="badge bg-success fs-5 ms-2"><?php echo $accommodation['available_units']; ?> units available</span>
                            <?php else: ?>
                                <span class="badge bg-danger fs-5 ms-2">No units available</span>
                            <?php endif; ?>
                        </div>
                        <h5>Description</h5>
                        <p class="card-text"><?php echo nl2br($accommodation['description']); ?></p>
                        <div class="row mt-4">
                            <div class="col-6">
                                <h5>Details</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item"><i class="fas fa-users me-2"></i>Capacity: <?php echo $accommodation['capacity']; ?> students</li>
                                    <li class="list-group-item"><i class="fas fa-building me-2"></i>Available Units: <?php echo $accommodation['available_units']; ?></li>
                                </ul>
                            </div>
                            <div class="col-6">
                                <h5>Amenities</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item"><i class="fas fa-wifi me-2"></i>Wi-Fi</li>
                                    <li class="list-group-item"><i class="fas fa-bolt me-2"></i>Electricity included</li>
                                    <li class="list-group-item"><i class="fas fa-water me-2"></i>Water included</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Availability</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($accommodation['available_units'] > 0): ?>
                            <p class="card-text">This accommodation currently has <strong><?php echo $accommodation['available_units']; ?> units available</strong> for students.</p>
                            <?php if (is_student() && $accommodation['available_units'] > 0): ?>
                                <?php if (has_student_already_applied(get_current_user_id(), $accommodation['id'])): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>You have already applied for this accommodation.
                                    </div>
                                    <a href="applications.php" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-file-alt me-2"></i>View My Applications
                                    </a>
                                <?php else: ?>
                                    <form method="post" action="accommodations.php">
                                        <input type="hidden" name="accommodation_id" value="<?php echo $accommodation['id']; ?>">
                                        <button type="submit" name="apply" class="btn btn-success w-100 mb-3">
                                            <i class="fas fa-check-circle me-2"></i>Apply Now
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php elseif (!is_logged_in()): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>You must be logged in to apply.
                                </div>
                                <a href="login.php" class="btn btn-primary w-100 mb-2">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login
                                </a>
                                <a href="register.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-user-plus me-2"></i>Register
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>No units are currently available.
                            </div>
                            <p>Check back later or explore other accommodations.</p>
                            <a href="accommodations.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-building me-2"></i>Browse Other Accommodations
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Contact Information</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">For inquiries about this accommodation, please contact:</p>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item"><i class="fas fa-envelope me-2"></i>Email: <?php echo ADMIN_EMAIL; ?></li>
                            <li class="list-group-item"><i class="fas fa-phone me-2"></i>Phone: +27 12 345 6789</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (is_admin() && (is_master_admin() || $accommodation['admin_id'] == get_current_user_id())): ?>
            <!-- Edit Accommodation Modal -->
            <div class="modal fade" id="editAccommodationModal" tabindex="-1" aria-labelledby="editAccommodationModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="editAccommodationModalLabel">Edit Accommodation</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post" action="accommodations.php" enctype="multipart/form-data">
                            <div class="modal-body">
                                <input type="hidden" name="accommodation_id" value="<?php echo $accommodation['id']; ?>">
                                
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <label for="edit_name" class="form-label">Name</label>
                                        <input type="text" class="form-control" id="edit_name" name="name" value="<?php echo $accommodation['name']; ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="edit_price_per_month" class="form-label">Price per Month (R)</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="edit_price_per_month" name="price_per_month" value="<?php echo $accommodation['price_per_month']; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="edit_location" class="form-label">Location</label>
                                    <input type="text" class="form-control" id="edit_location" name="location" value="<?php echo $accommodation['location']; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="edit_description" class="form-label">Description</label>
                                    <textarea class="form-control" id="edit_description" name="description" rows="4" required><?php echo $accommodation['description']; ?></textarea>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="edit_capacity" class="form-label">Total Capacity</label>
                                        <input type="number" min="1" class="form-control" id="edit_capacity" name="capacity" value="<?php echo $accommodation['capacity']; ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="edit_available_units" class="form-label">Available Units</label>
                                        <input type="number" min="0" class="form-control" id="edit_available_units" name="available_units" value="<?php echo $accommodation['available_units']; ?>" required>
                                    </div>
                                    <?php if (is_master_admin()): ?>
                                        <div class="col-md-4">
                                            <label for="edit_admin_id" class="form-label">Assigned Admin</label>
                                            <select class="form-select" id="edit_admin_id" name="admin_id">
                                                <option value="">None</option>
                                                <?php foreach ($admin_users as $admin): ?>
                                                    <option value="<?php echo $admin['id']; ?>" <?php echo ($admin['id'] == $accommodation['admin_id']) ? 'selected' : ''; ?>>
                                                        <?php echo $admin['full_name'] . ' (' . $admin['username'] . ')'; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="edit_image" class="form-label">Accommodation Image</label>
                                    <?php if (!empty($accommodation['image_path']) && file_exists($accommodation['image_path'])): ?>
                                        <div class="mb-2">
                                            <img src="<?php echo $accommodation['image_path']; ?>" class="img-thumbnail" alt="Current Image" style="max-height: 150px;">
                                            <span class="ms-2">Current Image</span>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" id="edit_image" name="image" accept="image/*">
                                    <div class="form-text">Upload a new image to replace the current one. Leave empty to keep the current image.</div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="edit_accommodation" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- Accommodations List View -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="mb-3"><?php echo $page_title; ?></h1>
                <p class="lead">
                    <?php if (is_admin()): ?>
                        Manage your student accommodations, view applications, and handle leases.
                    <?php else: ?>
                        Find your perfect student accommodation. Browse our available options and apply online.
                    <?php endif; ?>
                </p>
            </div>
            <?php if (is_admin()): ?>
                <div class="col-md-4 text-end">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccommodationModal">
                        <i class="fas fa-plus-circle me-2"></i>Add Accommodation
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (empty($accommodations)): ?>
            <div class="alert alert-info">
                <?php if (is_admin()): ?>
                    <p><i class="fas fa-info-circle me-2"></i>No accommodations found. Click the "Add Accommodation" button to create one.</p>
                <?php else: ?>
                    <p><i class="fas fa-info-circle me-2"></i>No accommodations available at the moment. Please check back later.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($accommodations as $acc): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm">
                            <?php if (!empty($acc['image_path']) && file_exists($acc['image_path'])): ?>
                                <img src="<?php echo $acc['image_path']; ?>" class="card-img-top" alt="<?php echo $acc['name']; ?>">
                            <?php else: ?>
                                <div class="card-img-top bg-light text-center py-5">
                                    <i class="fas fa-building fa-5x text-secondary"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $acc['name']; ?></h5>
                                <p class="card-text text-muted mb-1">
                                    <i class="fas fa-map-marker-alt me-2"></i><?php echo $acc['location']; ?>
                                </p>
                                <p class="card-text mb-2">
                                    <span class="badge bg-primary"><?php echo format_currency($acc['price_per_month']); ?> /month</span>
                                    <?php if ($acc['available_units'] > 0): ?>
                                        <span class="badge bg-success ms-1"><?php echo $acc['available_units']; ?> units available</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger ms-1">No units available</span>
                                    <?php endif; ?>
                                </p>
                                <p class="card-text mb-3"><?php echo substr($acc['description'], 0, 100); ?>...</p>
                                
                                <?php if (is_admin() && isset($acc['admin_name'])): ?>
                                    <p class="card-text text-muted small">
                                        <i class="fas fa-user-tie me-1"></i>Managed by: <?php echo $acc['admin_name'] ?: 'Not assigned'; ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="d-grid">
                                    <a href="accommodations.php?id=<?php echo $acc['id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-info-circle me-2"></i>View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (is_admin()): ?>
            <!-- Add Accommodation Modal -->
            <div class="modal fade" id="addAccommodationModal" tabindex="-1" aria-labelledby="addAccommodationModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="addAccommodationModalLabel">Add New Accommodation</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post" action="accommodations.php" enctype="multipart/form-data">
                            <div class="modal-body">
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <label for="name" class="form-label">Name</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="price_per_month" class="form-label">Price per Month (R)</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="price_per_month" name="price_per_month" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" class="form-control" id="location" name="location" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label for="capacity" class="form-label">Total Capacity</label>
                                        <input type="number" min="1" class="form-control" id="capacity" name="capacity" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="available_units" class="form-label">Available Units</label>
                                        <input type="number" min="0" class="form-control" id="available_units" name="available_units" required>
                                    </div>
                                    <?php if (is_master_admin()): ?>
                                        <div class="col-md-4">
                                            <label for="admin_id" class="form-label">Assigned Admin</label>
                                            <select class="form-select" id="admin_id" name="admin_id">
                                                <option value="">None</option>
                                                <?php foreach ($admin_users as $admin): ?>
                                                    <option value="<?php echo $admin['id']; ?>">
                                                        <?php echo $admin['full_name'] . ' (' . $admin['username'] . ')'; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="image" class="form-label">Accommodation Image</label>
                                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="add_accommodation" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-2"></i>Add Accommodation
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// Include footer
include 'include/footer.php';
?>
