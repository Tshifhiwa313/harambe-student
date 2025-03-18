<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/authentication.php';

// Require admin or master admin role
requireRole([ROLE_MASTER_ADMIN, ROLE_ADMIN], 'index.php');

$error = '';
$success = '';

// Get current user
$user = getCurrentUser();
$isMasterAdmin = hasRole(ROLE_MASTER_ADMIN);

// Process accommodation management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new accommodation
    if (isset($_POST['add_accommodation'])) {
        $name = $_POST['name'] ?? '';
        $location = $_POST['location'] ?? '';
        $description = $_POST['description'] ?? '';
        $rooms = intval($_POST['rooms_available'] ?? 0);
        $price = floatval($_POST['price_per_month'] ?? 0);
        $adminId = $isMasterAdmin ? intval($_POST['admin_id'] ?? 0) : $_SESSION['user_id'];
        
        if (empty($name) || empty($location) || $rooms <= 0 || $price <= 0) {
            $error = 'Please fill all required fields with valid values.';
        } else {
            // Handle image upload
            $imagePath = '';
            if (isset($_FILES['accommodation_image']) && $_FILES['accommodation_image']['error'] == 0) {
                $imagePath = uploadFile($_FILES['accommodation_image'], UPLOADS_DIR);
                
                if (!$imagePath) {
                    $error = 'Failed to upload image. Please try again.';
                }
            }
            
            if (empty($error)) {
                // Insert accommodation
                $accommodationId = insert('accommodations', [
                    'name' => $name,
                    'location' => $location,
                    'description' => $description,
                    'rooms_available' => $rooms,
                    'price_per_month' => $price,
                    'admin_id' => $adminId,
                    'image_path' => $imagePath,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                if ($accommodationId) {
                    $success = 'Accommodation added successfully.';
                } else {
                    $error = 'Failed to add accommodation. Please try again.';
                }
            }
        }
    }
    
    // Update accommodation
    elseif (isset($_POST['update_accommodation'])) {
        $id = intval($_POST['accommodation_id'] ?? 0);
        $name = $_POST['name'] ?? '';
        $location = $_POST['location'] ?? '';
        $description = $_POST['description'] ?? '';
        $rooms = intval($_POST['rooms_available'] ?? 0);
        $price = floatval($_POST['price_per_month'] ?? 0);
        $adminId = $isMasterAdmin ? intval($_POST['admin_id'] ?? 0) : $_SESSION['user_id'];
        
        if ($id <= 0 || empty($name) || empty($location) || $rooms <= 0 || $price <= 0) {
            $error = 'Please fill all required fields with valid values.';
        } else {
            // Get current accommodation data
            $accommodation = getAccommodationById($id);
            
            if (!$accommodation) {
                $error = 'Accommodation not found.';
            } else {
                // Check if admin is authorized to update this accommodation
                $canUpdate = $isMasterAdmin || isAdminAssignedToAccommodation($_SESSION['user_id'], $id);
                
                if (!$canUpdate) {
                    $error = 'You are not authorized to update this accommodation.';
                } else {
                    // Handle image upload if new image provided
                    $imagePath = $accommodation['image_path']; // Keep existing image by default
                    
                    if (isset($_FILES['accommodation_image']) && $_FILES['accommodation_image']['error'] == 0) {
                        $newImagePath = uploadFile($_FILES['accommodation_image'], UPLOADS_DIR);
                        
                        if (!$newImagePath) {
                            $error = 'Failed to upload image. Other changes will still be saved.';
                        } else {
                            $imagePath = $newImagePath;
                        }
                    }
                    
                    // Update accommodation
                    update('accommodations', [
                        'name' => $name,
                        'location' => $location,
                        'description' => $description,
                        'rooms_available' => $rooms,
                        'price_per_month' => $price,
                        'admin_id' => $adminId,
                        'image_path' => $imagePath
                    ], 'id', $id);
                    
                    $success = 'Accommodation updated successfully.';
                }
            }
        }
    }
    
    // Add new admin (Master Admin only)
    elseif (isset($_POST['add_admin']) && $isMasterAdmin) {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $firstName = $_POST['first_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        
        if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
            $error = 'Please fill all required fields.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $userData = [
                'phone' => $phone,
                'first_name' => $firstName,
                'last_name' => $lastName
            ];
            
            // Register the admin
            $adminId = registerUser($username, $email, $password, ROLE_ADMIN, $userData);
            
            if ($adminId) {
                $success = 'Admin user created successfully.';
                
                // Send welcome email
                sendWelcomeEmail($email, $username, $password, ROLE_ADMIN);
            } else {
                $error = 'Username or email already exists.';
            }
        }
    }
}

// Get data for dashboard
if ($isMasterAdmin) {
    // Get all accommodations
    $accommodations = getAccommodations();
    
    // Get all admins
    $admins = getAdmins();
    
    // Get statistics
    $totalStudents = fetchOne("SELECT COUNT(*) as count FROM users WHERE role = ?", [ROLE_STUDENT])['count'];
    $totalApplications = fetchOne("SELECT COUNT(*) as count FROM applications")['count'];
    $totalLeases = fetchOne("SELECT COUNT(*) as count FROM leases")['count'];
    $totalMaintenance = fetchOne("SELECT COUNT(*) as count FROM maintenance_requests")['count'];
    
    // Get pending applications
    $pendingApplications = fetchAll("SELECT a.*, u.username, u.email, ac.name as accommodation_name 
                                   FROM applications a
                                   JOIN users u ON a.user_id = u.id
                                   JOIN accommodations ac ON a.accommodation_id = ac.id
                                   WHERE a.status = ?
                                   ORDER BY a.created_at DESC
                                   LIMIT 5", [STATUS_PENDING]);
} else {
    // Get admin's accommodations
    $accommodations = getAdminAccommodations($_SESSION['user_id']);
    
    // Get accommodation IDs managed by this admin
    $accommodationIds = array_column($accommodations, 'id');
    
    if (!empty($accommodationIds)) {
        $placeholders = implode(',', array_fill(0, count($accommodationIds), '?'));
        
        // Get statistics for this admin's accommodations
        $totalStudents = fetchOne("SELECT COUNT(DISTINCT user_id) as count FROM leases WHERE accommodation_id IN ($placeholders)", $accommodationIds)['count'];
        $totalApplications = fetchOne("SELECT COUNT(*) as count FROM applications WHERE accommodation_id IN ($placeholders)", $accommodationIds)['count'];
        $totalLeases = fetchOne("SELECT COUNT(*) as count FROM leases WHERE accommodation_id IN ($placeholders)", $accommodationIds)['count'];
        $totalMaintenance = fetchOne("SELECT COUNT(*) as count FROM maintenance_requests WHERE accommodation_id IN ($placeholders)", $accommodationIds)['count'];
        
        // Get pending applications for this admin's accommodations
        $pendingApplications = fetchAll("SELECT a.*, u.username, u.email, ac.name as accommodation_name 
                                       FROM applications a
                                       JOIN users u ON a.user_id = u.id
                                       JOIN accommodations ac ON a.accommodation_id = ac.id
                                       WHERE a.status = ? AND a.accommodation_id IN ($placeholders)
                                       ORDER BY a.created_at DESC
                                       LIMIT 5", array_merge([STATUS_PENDING], $accommodationIds));
    } else {
        // No accommodations assigned yet
        $totalStudents = 0;
        $totalApplications = 0;
        $totalLeases = 0;
        $totalMaintenance = 0;
        $pendingApplications = [];
    }
}

include 'includes/header.php';
?>

<div class="container">
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>Admin Dashboard</h1>
            <p class="lead">Welcome, <?= $user['username'] ?> (<?= getRoleName($user['role']) ?>)</p>
        </div>
        <div class="col-md-4 text-end">
            <?php if ($isMasterAdmin): ?>
                <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                    <i class="fas fa-user-plus"></i> Add Admin
                </button>
            <?php endif; ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccommodationModal">
                <i class="fas fa-plus"></i> Add Accommodation
            </button>
        </div>
    </div>
    
    <!-- Dashboard Summary -->
    <div class="row mb-5">
        <div class="col-md-3 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-body">
                    <div class="icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <h3 class="h5"><?= count($accommodations) ?></h3>
                    <p class="text-muted">Accommodations</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-body">
                    <div class="icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h3 class="h5"><?= $totalStudents ?></h3>
                    <p class="text-muted">Students</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-body">
                    <div class="icon">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <h3 class="h5"><?= $totalLeases ?></h3>
                    <p class="text-muted">Leases</p>
                    <a href="leases.php" class="btn btn-sm btn-outline-primary mt-2">View All</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-body">
                    <div class="icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h3 class="h5"><?= $totalMaintenance ?></h3>
                    <p class="text-muted">Maintenance</p>
                    <a href="maintenance.php" class="btn btn-sm btn-outline-primary mt-2">View All</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pending Applications -->
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Pending Applications</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pendingApplications)): ?>
                        <p class="text-center text-muted">No pending applications at this time.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Student</th>
                                        <th>Accommodation</th>
                                        <th>Applied On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingApplications as $application): ?>
                                        <tr>
                                            <td><?= $application['id'] ?></td>
                                            <td><?= $application['username'] ?></td>
                                            <td><?= $application['accommodation_name'] ?></td>
                                            <td><?= formatDate($application['created_at']) ?></td>
                                            <td>
                                                <a href="applications.php?id=<?= $application['id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> Review
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="applications.php" class="btn btn-outline-primary">View All Applications</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Managed Accommodations -->
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?= $isMasterAdmin ? 'All Accommodations' : 'Your Managed Accommodations' ?></h5>
                </div>
                <div class="card-body">
                    <?php if (empty($accommodations)): ?>
                        <p class="text-center text-muted">
                            <?= $isMasterAdmin ? 'No accommodations have been added yet.' : 'You have not been assigned any accommodations to manage.' ?>
                        </p>
                        <div class="text-center">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccommodationModal">
                                <i class="fas fa-plus"></i> Add Accommodation
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($accommodations as $accommodation): ?>
                                <div class="col-md-4 mb-4">
                                    <div class="card h-100">
                                        <?php if (!empty($accommodation['image_path'])): ?>
                                            <img src="uploads/accommodations/<?= $accommodation['image_path'] ?>" class="card-img-top" alt="<?= $accommodation['name'] ?>">
                                        <?php else: ?>
                                            <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                                                <i class="fas fa-building fa-5x text-secondary"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h5 class="card-title"><?= $accommodation['name'] ?></h5>
                                            <p><i class="fas fa-map-marker-alt"></i> <?= $accommodation['location'] ?></p>
                                            <p><i class="fas fa-money-bill-wave"></i> <?= formatCurrency($accommodation['price_per_month']) ?> / month</p>
                                            <p><i class="fas fa-door-open"></i> <?= $accommodation['rooms_available'] ?> rooms available</p>
                                            
                                            <?php if ($isMasterAdmin && !empty($accommodation['admin_id'])): ?>
                                                <?php $admin = fetchOne("SELECT username FROM users WHERE id = ?", [$accommodation['admin_id']]); ?>
                                                <p><i class="fas fa-user-shield"></i> Managed by: <?= $admin ? $admin['username'] : 'Unknown' ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer">
                                            <button type="button" class="btn btn-primary edit-accommodation" 
                                                    data-id="<?= $accommodation['id'] ?>"
                                                    data-name="<?= htmlspecialchars($accommodation['name']) ?>"
                                                    data-location="<?= htmlspecialchars($accommodation['location']) ?>"
                                                    data-description="<?= htmlspecialchars($accommodation['description']) ?>"
                                                    data-rooms="<?= $accommodation['rooms_available'] ?>"
                                                    data-price="<?= $accommodation['price_per_month'] ?>"
                                                    data-admin-id="<?= $accommodation['admin_id'] ?>"
                                                    data-bs-toggle="modal" data-bs-target="#editAccommodationModal">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <a href="accommodations.php?id=<?= $accommodation['id'] ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3 mb-3">
                            <a href="applications.php" class="btn btn-outline-primary btn-lg d-block">
                                <i class="fas fa-file-alt mb-2 d-block fa-2x"></i>
                                Manage Applications
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="leases.php" class="btn btn-outline-primary btn-lg d-block">
                                <i class="fas fa-file-contract mb-2 d-block fa-2x"></i>
                                Manage Leases
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="invoices.php" class="btn btn-outline-primary btn-lg d-block">
                                <i class="fas fa-file-invoice-dollar mb-2 d-block fa-2x"></i>
                                Manage Invoices
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="notifications.php" class="btn btn-outline-primary btn-lg d-block">
                                <i class="fas fa-bell mb-2 d-block fa-2x"></i>
                                Send Notifications
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Accommodation Modal -->
    <div class="modal fade" id="addAccommodationModal" tabindex="-1" aria-labelledby="addAccommodationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post" action="admin.php" enctype="multipart/form-data">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="addAccommodationModalLabel">Add New Accommodation</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Accommodation Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="location" class="form-label">Location *</label>
                            <input type="text" class="form-control" id="location" name="location" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="rooms_available" class="form-label">Rooms Available *</label>
                                <input type="number" class="form-control" id="rooms_available" name="rooms_available" min="1" required>
                            </div>
                            <div class="col-md-6">
                                <label for="price_per_month" class="form-label">Price Per Month (R) *</label>
                                <input type="number" step="0.01" class="form-control" id="price_per_month" name="price_per_month" min="0.01" required>
                            </div>
                        </div>
                        
                        <?php if ($isMasterAdmin): ?>
                            <div class="mb-3">
                                <label for="admin_id" class="form-label">Assign Admin</label>
                                <select class="form-select" id="admin_id" name="admin_id">
                                    <option value="">Select Admin (Optional)</option>
                                    <?php foreach ($admins as $admin): ?>
                                        <option value="<?= $admin['id'] ?>"><?= $admin['username'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="accommodation_image" class="form-label">Accommodation Image</label>
                            <input type="file" class="form-control" id="accommodation_image" name="accommodation_image" accept="image/*">
                            <div class="form-text">Upload an image of the accommodation (optional).</div>
                        </div>
                        
                        <div class="mb-3">
                            <img id="image_preview" src="" alt="Preview" style="max-width: 100%; max-height: 200px; display: none;">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_accommodation" class="btn btn-primary">Add Accommodation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Accommodation Modal -->
    <div class="modal fade" id="editAccommodationModal" tabindex="-1" aria-labelledby="editAccommodationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post" action="admin.php" enctype="multipart/form-data">
                    <input type="hidden" name="accommodation_id" id="edit_accommodation_id">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="editAccommodationModalLabel">Edit Accommodation</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Accommodation Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_location" class="form-label">Location *</label>
                            <input type="text" class="form-control" id="edit_location" name="location" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_rooms_available" class="form-label">Rooms Available *</label>
                                <input type="number" class="form-control" id="edit_rooms_available" name="rooms_available" min="1" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_price_per_month" class="form-label">Price Per Month (R) *</label>
                                <input type="number" step="0.01" class="form-control" id="edit_price_per_month" name="price_per_month" min="0.01" required>
                            </div>
                        </div>
                        
                        <?php if ($isMasterAdmin): ?>
                            <div class="mb-3">
                                <label for="edit_admin_id" class="form-label">Assign Admin</label>
                                <select class="form-select" id="edit_admin_id" name="admin_id">
                                    <option value="">Select Admin (Optional)</option>
                                    <?php foreach ($admins as $admin): ?>
                                        <option value="<?= $admin['id'] ?>"><?= $admin['username'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="edit_accommodation_image" class="form-label">Update Accommodation Image</label>
                            <input type="file" class="form-control" id="edit_accommodation_image" name="accommodation_image" accept="image/*">
                            <div class="form-text">Upload a new image only if you want to replace the existing one.</div>
                        </div>
                        
                        <div class="mb-3">
                            <img id="edit_image_preview" src="" alt="Preview" style="max-width: 100%; max-height: 200px; display: none;">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_accommodation" class="btn btn-primary">Update Accommodation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php if ($isMasterAdmin): ?>
    <!-- Add Admin Modal -->
    <div class="modal fade" id="addAdminModal" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="admin.php">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="addAdminModalLabel">Add New Admin</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="admin_username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="admin_username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin_email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="admin_email" name="email" required>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="admin_first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="admin_first_name" name="first_name">
                            </div>
                            <div class="col-md-6">
                                <label for="admin_last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="admin_last_name" name="last_name">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin_phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="admin_phone" name="phone">
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin_password" class="form-label">Password *</label>
                            <input type="password" class="form-control" id="admin_password" name="password" required>
                            <div class="form-text">Password must be at least 6 characters long.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin_confirm_password" class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" id="admin_confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_admin" class="btn btn-primary">Add Admin</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize edit accommodation modal
        document.querySelectorAll('.edit-accommodation').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const location = this.getAttribute('data-location');
                const description = this.getAttribute('data-description');
                const rooms = this.getAttribute('data-rooms');
                const price = this.getAttribute('data-price');
                const adminId = this.getAttribute('data-admin-id');
                
                document.getElementById('edit_accommodation_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_location').value = location;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_rooms_available').value = rooms;
                document.getElementById('edit_price_per_month').value = price;
                
                if (document.getElementById('edit_admin_id')) {
                    document.getElementById('edit_admin_id').value = adminId || '';
                }
            });
        });
        
        // Image preview for add accommodation
        const imageUpload = document.getElementById('accommodation_image');
        const imagePreview = document.getElementById('image_preview');
        
        if (imageUpload && imagePreview) {
            imageUpload.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.addEventListener('load', function() {
                        imagePreview.src = this.result;
                        imagePreview.style.display = 'block';
                    });
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Image preview for edit accommodation
        const editImageUpload = document.getElementById('edit_accommodation_image');
        const editImagePreview = document.getElementById('edit_image_preview');
        
        if (editImageUpload && editImagePreview) {
            editImageUpload.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.addEventListener('load', function() {
                        editImagePreview.src = this.result;
                        editImagePreview.style.display = 'block';
                    });
                    reader.readAsDataURL(file);
                }
            });
        }
    });
</script>

<?php include 'includes/footer.php'; ?>

<?php
include 'functions.php';

function viewApplications() {
    // Implement logic to view applications
}

function approveApplication($applicationId) {
    // Implement logic to approve application
}

function declineApplication($applicationId) {
    // Implement logic to decline application
}

function sendLeaseAgreement($leaseData) {
    // Implement logic to send lease agreement
}

function downloadLeaseAgreement($leaseData) {
    // Implement logic to download lease agreement
}

// Example usage
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['approve'])) {
        approveApplication($_POST['application_id']);
    } elseif (isset($_POST['decline'])) {
        declineApplication($_POST['application_id']);
    } elseif (isset($_POST['send_lease'])) {
        sendLeaseAgreement($_POST['lease_data']);
    } elseif (isset($_POST['download_lease'])) {
        downloadLeaseAgreement($_POST['lease_data']);
    }
}
?>
