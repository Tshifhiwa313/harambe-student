<?php
/**
 * Admin Accommodations Management
 * 
 * This page allows administrators to view, add, edit, and delete accommodations.
 */

require_once '../includes/header.php';

// Require admin authentication
requireAuth([ROLE_MASTER_ADMIN, ROLE_ADMIN]);

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

// Define action based on GET parameter
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$accommodationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new accommodation
    if (isset($_POST['add_accommodation'])) {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $address = sanitize($_POST['address']);
        $price = (float)$_POST['price'];
        $roomsAvailable = (int)$_POST['rooms_available'];
        
        // Validate input
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Accommodation name is required';
        }
        
        if (empty($address)) {
            $errors[] = 'Address is required';
        }
        
        if ($price <= 0) {
            $errors[] = 'Price must be greater than zero';
        }
        
        if ($roomsAvailable < 0) {
            $errors[] = 'Rooms available must be zero or greater';
        }
        
        // Process image upload if provided
        $imagePath = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $imagePath = uploadFile($_FILES['image'], ACCOMMODATION_UPLOADS);
            
            if ($imagePath === false) {
                $errors[] = 'Failed to upload image. Please ensure it is a valid image file (JPG, PNG, GIF) and less than 5MB.';
            }
        }
        
        // Insert accommodation if no errors
        if (empty($errors)) {
            $accommodationData = [
                'name' => $name,
                'description' => $description,
                'address' => $address,
                'price' => $price,
                'rooms_available' => $roomsAvailable,
                'image_path' => $imagePath
            ];
            
            $newAccommodationId = insertRow($conn, 'accommodations', $accommodationData);
            
            if ($newAccommodationId) {
                // If master admin and admin_id is provided, assign admin to this accommodation
                if ($userRole === ROLE_MASTER_ADMIN && isset($_POST['admin_id']) && !empty($_POST['admin_id'])) {
                    $adminId = (int)$_POST['admin_id'];
                    
                    // Check if user exists and is an admin
                    $admin = fetchRow($conn, "SELECT id FROM users WHERE id = :id AND role = :role", [
                        'id' => $adminId,
                        'role' => ROLE_ADMIN
                    ]);
                    
                    if ($admin) {
                        $assignData = [
                            'user_id' => $adminId,
                            'accommodation_id' => $newAccommodationId
                        ];
                        
                        insertRow($conn, 'accommodation_admins', $assignData);
                    }
                } elseif ($userRole === ROLE_ADMIN) {
                    // If current user is an admin, assign them to this accommodation
                    $assignData = [
                        'user_id' => $userId,
                        'accommodation_id' => $newAccommodationId
                    ];
                    
                    insertRow($conn, 'accommodation_admins', $assignData);
                }
                
                setFlashMessage('Accommodation added successfully!', 'success');
                redirect('accommodations.php');
            } else {
                $errors[] = 'Failed to add accommodation. Please try again.';
            }
        }
    }
    
    // Update existing accommodation
    if (isset($_POST['update_accommodation'])) {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $address = sanitize($_POST['address']);
        $price = (float)$_POST['price'];
        $roomsAvailable = (int)$_POST['rooms_available'];
        
        // Validate input
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Accommodation name is required';
        }
        
        if (empty($address)) {
            $errors[] = 'Address is required';
        }
        
        if ($price <= 0) {
            $errors[] = 'Price must be greater than zero';
        }
        
        if ($roomsAvailable < 0) {
            $errors[] = 'Rooms available must be zero or greater';
        }
        
        // Get current accommodation data
        $accommodation = getAccommodation($conn, $accommodationId);
        
        if (!$accommodation) {
            setFlashMessage('Accommodation not found', 'danger');
            redirect('accommodations.php');
        }
        
        // Check permission to edit
        if ($userRole !== ROLE_MASTER_ADMIN && !isAdminAssignedToAccommodation($conn, $userId, $accommodationId)) {
            setFlashMessage('You do not have permission to edit this accommodation', 'danger');
            redirect('accommodations.php');
        }
        
        // Process image upload if provided
        $imagePath = $accommodation['image_path']; // Keep existing image by default
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $newImagePath = uploadFile($_FILES['image'], ACCOMMODATION_UPLOADS);
            
            if ($newImagePath === false) {
                $errors[] = 'Failed to upload image. Please ensure it is a valid image file (JPG, PNG, GIF) and less than 5MB.';
            } else {
                // Delete old image if it exists
                if (!empty($imagePath)) {
                    $oldImagePath = ACCOMMODATION_UPLOADS . '/' . $imagePath;
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
                
                $imagePath = $newImagePath;
            }
        }
        
        // Update accommodation if no errors
        if (empty($errors)) {
            $accommodationData = [
                'name' => $name,
                'description' => $description,
                'address' => $address,
                'price' => $price,
                'rooms_available' => $roomsAvailable,
                'image_path' => $imagePath,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (updateRow($conn, 'accommodations', $accommodationData, 'id', $accommodationId)) {
                // If master admin and admin_id is provided, update admin assignment
                if ($userRole === ROLE_MASTER_ADMIN && isset($_POST['admin_id']) && !empty($_POST['admin_id'])) {
                    $adminId = (int)$_POST['admin_id'];
                    
                    // Check if user exists and is an admin
                    $admin = fetchRow($conn, "SELECT id FROM users WHERE id = :id AND role = :role", [
                        'id' => $adminId,
                        'role' => ROLE_ADMIN
                    ]);
                    
                    if ($admin) {
                        // Remove existing assignments
                        executeQuery($conn, "DELETE FROM accommodation_admins WHERE accommodation_id = :accommodationId", [
                            'accommodationId' => $accommodationId
                        ]);
                        
                        // Add new assignment
                        $assignData = [
                            'user_id' => $adminId,
                            'accommodation_id' => $accommodationId
                        ];
                        
                        insertRow($conn, 'accommodation_admins', $assignData);
                    }
                }
                
                setFlashMessage('Accommodation updated successfully!', 'success');
                redirect('accommodations.php');
            } else {
                $errors[] = 'Failed to update accommodation. Please try again.';
            }
        }
    }
    
    // Delete accommodation
    if (isset($_POST['delete_accommodation'])) {
        // Check permission to delete
        if ($userRole !== ROLE_MASTER_ADMIN) {
            setFlashMessage('You do not have permission to delete accommodations', 'danger');
            redirect('accommodations.php');
        }
        
        // Get accommodation details
        $accommodation = getAccommodation($conn, $accommodationId);
        
        if (!$accommodation) {
            setFlashMessage('Accommodation not found', 'danger');
            redirect('accommodations.php');
        }
        
        // Check if there are active leases for this accommodation
        $activeLeases = fetchRow($conn, "SELECT COUNT(*) as count FROM leases WHERE accommodation_id = :id AND end_date >= CURRENT_DATE", ['id' => $accommodationId]);
        
        if ($activeLeases && $activeLeases['count'] > 0) {
            setFlashMessage('Cannot delete accommodation with active leases. End all leases first.', 'danger');
            redirect('accommodations.php');
        }
        
        // Delete accommodation
        if (deleteRow($conn, 'accommodations', 'id', $accommodationId)) {
            // Delete image if it exists
            if (!empty($accommodation['image_path'])) {
                $imagePath = ACCOMMODATION_UPLOADS . '/' . $accommodation['image_path'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            
            // Delete related records
            executeQuery($conn, "DELETE FROM accommodation_admins WHERE accommodation_id = :id", ['id' => $accommodationId]);
            
            setFlashMessage('Accommodation deleted successfully!', 'success');
        } else {
            setFlashMessage('Failed to delete accommodation', 'danger');
        }
        
        redirect('accommodations.php');
    }
}

// Get accommodations based on user role
if ($userRole === ROLE_MASTER_ADMIN) {
    // Master admin can see all accommodations
    $accommodations = fetchAll($conn, "SELECT a.*, COUNT(aa.id) as admin_count FROM accommodations a LEFT JOIN accommodation_admins aa ON a.id = aa.accommodation_id GROUP BY a.id ORDER BY a.name");
} else {
    // Regular admin can only see assigned accommodations
    $accommodations = getAdminAccommodations($conn, $userId);
}

// Get admin users for assignment (master admin only)
$adminUsers = [];
if ($userRole === ROLE_MASTER_ADMIN) {
    $adminUsers = fetchAll($conn, "SELECT id, username, first_name, last_name FROM users WHERE role = :role ORDER BY first_name, last_name", ['role' => ROLE_ADMIN]);
}

// Get assigned admin for each accommodation (master admin only)
$accommodationAdmins = [];
if ($userRole === ROLE_MASTER_ADMIN) {
    $adminsResult = fetchAll($conn, "SELECT aa.accommodation_id, aa.user_id, u.username, u.first_name, u.last_name FROM accommodation_admins aa JOIN users u ON aa.user_id = u.id");
    
    foreach ($adminsResult as $admin) {
        $accommodationAdmins[$admin['accommodation_id']] = $admin;
    }
}

// Get specific accommodation for editing
$accommodation = null;
if (($action === 'edit' || $action === 'delete') && $accommodationId > 0) {
    $accommodation = getAccommodation($conn, $accommodationId);
    
    // Check if accommodation exists
    if (!$accommodation) {
        setFlashMessage('Accommodation not found', 'danger');
        redirect('accommodations.php');
    }
    
    // Check permission to edit/delete for regular admins
    if ($userRole !== ROLE_MASTER_ADMIN && !isAdminAssignedToAccommodation($conn, $userId, $accommodationId)) {
        setFlashMessage('You do not have permission to edit this accommodation', 'danger');
        redirect('accommodations.php');
    }
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Manage Accommodations</h2>
        <div>
            <?php if ($action === 'list'): ?>
                <a href="?action=add" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i>Add Accommodation
                </a>
            <?php else: ?>
                <a href="accommodations.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to List
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (isset($errors) && !empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if ($action === 'list'): ?>
        <!-- Accommodations List -->
        <?php if (empty($accommodations)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>No accommodations found.
                <?php if ($userRole === ROLE_ADMIN): ?>
                    Please contact the master administrator to assign accommodations to you.
                <?php else: ?>
                    Please add accommodations by clicking the "Add Accommodation" button above.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($accommodations as $acc): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <?php if (!empty($acc['image_path'])): ?>
                                <img src="<?php echo '../uploads/accommodations/' . $acc['image_path']; ?>" class="card-img-top" alt="<?php echo $acc['name']; ?>">
                            <?php else: ?>
                                <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                                    <i class="fas fa-building fa-5x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $acc['name']; ?></h5>
                                <p class="card-text"><?php echo substr($acc['description'], 0, 100); ?>...</p>
                                <p class="accommodation-price"><?php echo formatCurrency($acc['price']); ?> per month</p>
                                <p class="accommodation-address">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <?php echo $acc['address']; ?>
                                </p>
                                <p class="accommodation-rooms">
                                    <i class="fas fa-door-open me-2"></i>
                                    <?php echo $acc['rooms_available']; ?> rooms available
                                </p>
                                <?php if ($userRole === ROLE_MASTER_ADMIN && isset($accommodationAdmins[$acc['id']])): ?>
                                    <p class="accommodation-admin">
                                        <i class="fas fa-user-shield me-2"></i>
                                        Managed by: <?php echo $accommodationAdmins[$acc['id']]['first_name'] . ' ' . $accommodationAdmins[$acc['id']]['last_name']; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex justify-content-between">
                                    <a href="?action=edit&id=<?php echo $acc['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </a>
                                    <?php if ($userRole === ROLE_MASTER_ADMIN): ?>
                                        <a href="?action=delete&id=<?php echo $acc['id']; ?>" class="btn btn-danger">
                                            <i class="fas fa-trash-alt me-1"></i>Delete
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
    <?php elseif ($action === 'add'): ?>
        <!-- Add Accommodation Form -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Accommodation</h4>
            </div>
            <div class="card-body">
                <form method="post" action="" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="name" class="form-label">Accommodation Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2" required></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="price" class="form-label">Monthly Price (R)</label>
                                    <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="rooms_available" class="form-label">Rooms Available</label>
                                    <div class="input-group">
                                        <button type="button" class="btn btn-outline-secondary" id="decrementRoom">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" class="form-control text-center" id="roomsAvailable" name="rooms_available" min="0" value="1" required>
                                        <button type="button" class="btn btn-outline-secondary" id="incrementRoom">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($userRole === ROLE_MASTER_ADMIN && !empty($adminUsers)): ?>
                                <div class="mb-3">
                                    <label for="admin_id" class="form-label">Assign Admin (Optional)</label>
                                    <select class="form-select" id="admin_id" name="admin_id">
                                        <option value="">-- Select Admin --</option>
                                        <?php foreach ($adminUsers as $admin): ?>
                                            <option value="<?php echo $admin['id']; ?>">
                                                <?php echo $admin['first_name'] . ' ' . $admin['last_name'] . ' (' . $admin['username'] . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">If no admin is assigned, you (Master Admin) will manage this accommodation.</div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="image" class="form-label">Accommodation Image</label>
                                <div class="image-preview mb-3" id="imagePreview">
                                    <div class="d-flex align-items-center justify-content-center h-100 bg-light">
                                        <i class="fas fa-upload fa-3x text-muted"></i>
                                    </div>
                                </div>
                                <input type="file" class="form-control" id="imageUpload" name="image" accept="image/*">
                                <div class="form-text">Upload a clear image of the accommodation. Max size: 5MB.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" name="add_accommodation" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Accommodation
                        </button>
                        <a href="accommodations.php" class="btn btn-secondary ms-2">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
    <?php elseif ($action === 'edit' && $accommodation): ?>
        <!-- Edit Accommodation Form -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Accommodation</h4>
            </div>
            <div class="card-body">
                <form method="post" action="" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="name" class="form-label">Accommodation Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo $accommodation['name']; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4"><?php echo $accommodation['description']; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2" required><?php echo $accommodation['address']; ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="price" class="form-label">Monthly Price (R)</label>
                                    <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" value="<?php echo $accommodation['price']; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="rooms_available" class="form-label">Rooms Available</label>
                                    <div class="input-group">
                                        <button type="button" class="btn btn-outline-secondary" id="decrementRoom">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" class="form-control text-center" id="roomsAvailable" name="rooms_available" min="0" value="<?php echo $accommodation['rooms_available']; ?>" required>
                                        <button type="button" class="btn btn-outline-secondary" id="incrementRoom">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($userRole === ROLE_MASTER_ADMIN && !empty($adminUsers)): ?>
                                <div class="mb-3">
                                    <label for="admin_id" class="form-label">Assign Admin</label>
                                    <select class="form-select" id="admin_id" name="admin_id">
                                        <option value="">-- Select Admin --</option>
                                        <?php 
                                        $currentAdminId = isset($accommodationAdmins[$accommodation['id']]) ? $accommodationAdmins[$accommodation['id']]['user_id'] : null;
                                        foreach ($adminUsers as $admin): 
                                        ?>
                                            <option value="<?php echo $admin['id']; ?>" <?php echo ($currentAdminId == $admin['id']) ? 'selected' : ''; ?>>
                                                <?php echo $admin['first_name'] . ' ' . $admin['last_name'] . ' (' . $admin['username'] . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="image" class="form-label">Accommodation Image</label>
                                <div class="image-preview mb-3" id="imagePreview">
                                    <?php if (!empty($accommodation['image_path'])): ?>
                                        <img src="<?php echo '../uploads/accommodations/' . $accommodation['image_path']; ?>" alt="<?php echo $accommodation['name']; ?>">
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center h-100 bg-light">
                                            <i class="fas fa-upload fa-3x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <input type="file" class="form-control" id="imageUpload" name="image" accept="image/*">
                                <div class="form-text">Upload a new image to replace the current one. Leave empty to keep the current image.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" name="update_accommodation" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Accommodation
                        </button>
                        <a href="accommodations.php" class="btn btn-secondary ms-2">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
    <?php elseif ($action === 'delete' && $accommodation): ?>
        <!-- Delete Accommodation Confirmation -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-danger text-white py-3">
                <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Delete Accommodation</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. All data related to this accommodation will be permanently removed.
                </div>
                
                <p>Are you sure you want to delete the following accommodation?</p>
                
                <div class="card mb-4">
                    <div class="row g-0">
                        <div class="col-md-4">
                            <?php if (!empty($accommodation['image_path'])): ?>
                                <img src="<?php echo '../uploads/accommodations/' . $accommodation['image_path']; ?>" class="img-fluid rounded-start" alt="<?php echo $accommodation['name']; ?>">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center h-100 bg-light">
                                    <i class="fas fa-building fa-5x text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $accommodation['name']; ?></h5>
                                <p class="card-text"><?php echo substr($accommodation['description'], 0, 150); ?>...</p>
                                <p class="card-text"><i class="fas fa-map-marker-alt me-2"></i><?php echo $accommodation['address']; ?></p>
                                <p class="card-text"><i class="fas fa-money-bill-wave me-2"></i><?php echo formatCurrency($accommodation['price']); ?> per month</p>
                                <p class="card-text"><i class="fas fa-door-open me-2"></i><?php echo $accommodation['rooms_available']; ?> rooms available</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="post" action="">
                    <div class="d-flex justify-content-between">
                        <a href="accommodations.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Cancel
                        </a>
                        <button type="submit" name="delete_accommodation" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-2"></i>Yes, Delete Accommodation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
