<?php
/**
 * Admin Users Management
 * 
 * This page allows master administrators to manage admin users.
 */

require_once '../includes/header.php';

// Require master admin authentication
requireAuth(ROLE_MASTER_ADMIN);

// Define action based on GET parameter
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new admin
    if (isset($_POST['add_admin'])) {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $accommodationIds = isset($_POST['accommodation_ids']) ? $_POST['accommodation_ids'] : [];
        
        // Validation
        $errors = [];
        
        if (empty($username)) {
            $errors[] = 'Username is required';
        } elseif (strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters';
        }
        
        if (empty($password)) {
            $errors[] = 'Password is required';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        }
        
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match';
        }
        
        if (empty($email)) {
            $errors[] = 'Email is required';
        } elseif (!isValidEmail($email)) {
            $errors[] = 'Invalid email format';
        }
        
        if (!empty($phone) && !isValidPhone($phone)) {
            $errors[] = 'Invalid phone number format';
        }
        
        if (empty($firstName)) {
            $errors[] = 'First name is required';
        }
        
        if (empty($lastName)) {
            $errors[] = 'Last name is required';
        }
        
        // Check if username already exists
        $checkUsername = fetchRow($conn, "SELECT id FROM users WHERE username = :username", ['username' => $username]);
        if ($checkUsername) {
            $errors[] = 'Username already exists';
        }
        
        // Check if email already exists
        $checkEmail = fetchRow($conn, "SELECT id FROM users WHERE email = :email", ['email' => $email]);
        if ($checkEmail) {
            $errors[] = 'Email already exists';
        }
        
        // Create admin if no errors
        if (empty($errors)) {
            $userData = [
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'email' => $email,
                'phone' => $phone,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'role' => ROLE_ADMIN
            ];
            
            $newAdminId = insertRow($conn, 'users', $userData);
            
            if ($newAdminId) {
                // Assign accommodations if any were selected
                if (!empty($accommodationIds)) {
                    foreach ($accommodationIds as $accommodationId) {
                        $assignData = [
                            'user_id' => $newAdminId,
                            'accommodation_id' => (int)$accommodationId
                        ];
                        
                        insertRow($conn, 'accommodation_admins', $assignData);
                    }
                }
                
                setFlashMessage('Admin user created successfully!', 'success');
                redirect('users.php');
            } else {
                $errors[] = 'Failed to create admin user. Please try again.';
            }
        }
    }
    
    // Update existing admin
    if (isset($_POST['update_admin'])) {
        $userId = (int)$_POST['user_id'];
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $accommodationIds = isset($_POST['accommodation_ids']) ? $_POST['accommodation_ids'] : [];
        
        // Validation
        $errors = [];
        
        if (empty($email)) {
            $errors[] = 'Email is required';
        } elseif (!isValidEmail($email)) {
            $errors[] = 'Invalid email format';
        }
        
        if (!empty($phone) && !isValidPhone($phone)) {
            $errors[] = 'Invalid phone number format';
        }
        
        if (empty($firstName)) {
            $errors[] = 'First name is required';
        }
        
        if (empty($lastName)) {
            $errors[] = 'Last name is required';
        }
        
        // Get current admin data
        $admin = getUser($conn, $userId);
        
        if (!$admin) {
            setFlashMessage('Admin user not found', 'danger');
            redirect('users.php');
        }
        
        // Check if email already exists (if changing email)
        if ($email !== $admin['email']) {
            $checkEmail = fetchRow($conn, "SELECT id FROM users WHERE email = :email AND id != :id", [
                'email' => $email,
                'id' => $userId
            ]);
            
            if ($checkEmail) {
                $errors[] = 'Email already exists';
            }
        }
        
        // Update admin if no errors
        if (empty($errors)) {
            $userData = [
                'email' => $email,
                'phone' => $phone,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (updateRow($conn, 'users', $userData, 'id', $userId)) {
                // Update accommodation assignments
                
                // First, remove all current assignments
                executeQuery($conn, "DELETE FROM accommodation_admins WHERE user_id = :userId", ['userId' => $userId]);
                
                // Then add new assignments
                if (!empty($accommodationIds)) {
                    foreach ($accommodationIds as $accommodationId) {
                        $assignData = [
                            'user_id' => $userId,
                            'accommodation_id' => (int)$accommodationId
                        ];
                        
                        insertRow($conn, 'accommodation_admins', $assignData);
                    }
                }
                
                setFlashMessage('Admin user updated successfully!', 'success');
                redirect('users.php');
            } else {
                $errors[] = 'Failed to update admin user. Please try again.';
            }
        }
    }
    
    // Reset admin password
    if (isset($_POST['reset_password'])) {
        $userId = (int)$_POST['user_id'];
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validation
        $errors = [];
        
        if (empty($newPassword)) {
            $errors[] = 'New password is required';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'New password must be at least 6 characters';
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Passwords do not match';
        }
        
        // Get current admin data
        $admin = getUser($conn, $userId);
        
        if (!$admin) {
            setFlashMessage('Admin user not found', 'danger');
            redirect('users.php');
        }
        
        // Reset password if no errors
        if (empty($errors)) {
            $userData = [
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (updateRow($conn, 'users', $userData, 'id', $userId)) {
                setFlashMessage('Admin password reset successfully!', 'success');
                redirect('users.php');
            } else {
                $errors[] = 'Failed to reset password. Please try again.';
            }
        }
    }
    
    // Delete admin
    if (isset($_POST['delete_admin'])) {
        $userId = (int)$_POST['user_id'];
        
        // Get current admin data
        $admin = getUser($conn, $userId);
        
        if (!$admin) {
            setFlashMessage('Admin user not found', 'danger');
            redirect('users.php');
        }
        
        // Delete admin's accommodation assignments first
        executeQuery($conn, "DELETE FROM accommodation_admins WHERE user_id = :userId", ['userId' => $userId]);
        
        // Delete admin user
        if (deleteRow($conn, 'users', 'id', $userId)) {
            setFlashMessage('Admin user deleted successfully!', 'success');
            redirect('users.php');
        } else {
            setFlashMessage('Failed to delete admin user. Please try again.', 'danger');
            redirect('users.php');
        }
    }
}

// Get admin users
$admins = fetchAll($conn, "SELECT * FROM users WHERE role = :role ORDER BY first_name, last_name", ['role' => ROLE_ADMIN]);

// Get all accommodations
$accommodations = fetchAll($conn, "SELECT * FROM accommodations ORDER BY name");

// Get assigned accommodations for each admin
$adminAccommodations = [];
foreach ($admins as $admin) {
    $assigned = fetchAll($conn, 
        "SELECT a.* FROM accommodations a 
        JOIN accommodation_admins aa ON a.id = aa.accommodation_id 
        WHERE aa.user_id = :userId", 
        ['userId' => $admin['id']]
    );
    
    $adminAccommodations[$admin['id']] = $assigned;
}

// Get specific admin for editing
$admin = null;
if (($action === 'edit' || $action === 'reset_password' || $action === 'delete') && $userId > 0) {
    $admin = getUser($conn, $userId);
    
    if (!$admin || $admin['role'] !== ROLE_ADMIN) {
        setFlashMessage('Admin user not found', 'danger');
        redirect('users.php');
    }
    
    // Get assigned accommodations for this admin
    $assignedAccommodations = fetchAll($conn, 
        "SELECT accommodation_id FROM accommodation_admins WHERE user_id = :userId", 
        ['userId' => $userId]
    );
    
    $assignedAccommodationIds = array_map(function($item) {
        return $item['accommodation_id'];
    }, $assignedAccommodations);
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Manage Admin Users</h2>
        <div>
            <?php if ($action === 'list'): ?>
                <a href="?action=add" class="btn btn-primary">
                    <i class="fas fa-user-plus me-2"></i>Add Admin
                </a>
                <a href="dashboard.php" class="btn btn-outline-primary ms-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            <?php else: ?>
                <a href="users.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Admin Users
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
        <!-- Admin Users List -->
        <?php if (empty($admins)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>No admin users found.
                <a href="?action=add" class="alert-link">Add your first admin user</a>.
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Assigned Accommodations</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins as $admin): ?>
                                    <tr>
                                        <td><?php echo $admin['id']; ?></td>
                                        <td><?php echo $admin['first_name'] . ' ' . $admin['last_name']; ?></td>
                                        <td><?php echo $admin['username']; ?></td>
                                        <td><?php echo $admin['email']; ?></td>
                                        <td>
                                            <?php 
                                            $assigned = $adminAccommodations[$admin['id']] ?? [];
                                            if (empty($assigned)) {
                                                echo '<span class="text-muted">None</span>';
                                            } else {
                                                echo '<span class="badge bg-primary">' . count($assigned) . '</span> ';
                                                echo implode(', ', array_map(function($acc) {
                                                    return $acc['name'];
                                                }, array_slice($assigned, 0, 3)));
                                                
                                                if (count($assigned) > 3) {
                                                    echo ' <span class="text-muted">and ' . (count($assigned) - 3) . ' more</span>';
                                                }
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="?action=edit&id=<?php echo $admin['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?action=reset_password&id=<?php echo $admin['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-key"></i>
                                                </a>
                                                <a href="?action=delete&id=<?php echo $admin['id']; ?>" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
    <?php elseif ($action === 'add'): ?>
        <!-- Add Admin Form -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i>Add New Admin</h4>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                            <div class="form-text">Must be at least 3 characters long. Used for login.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Must be at least 6 characters long.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#confirm_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number (Optional)</label>
                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="e.g., +27123456789">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Assign Accommodations</label>
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($accommodations as $accommodation): ?>
                                        <div class="col-md-4 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="accommodation_ids[]" value="<?php echo $accommodation['id']; ?>" id="acc_<?php echo $accommodation['id']; ?>">
                                                <label class="form-check-label" for="acc_<?php echo $accommodation['id']; ?>">
                                                    <?php echo $accommodation['name']; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">Select the accommodations this admin will manage.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" name="add_admin" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Admin User
                        </button>
                        <a href="users.php" class="btn btn-secondary ms-2">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
    <?php elseif ($action === 'edit' && $admin): ?>
        <!-- Edit Admin Form -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h4 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Admin User: <?php echo $admin['first_name'] . ' ' . $admin['last_name']; ?></h4>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo $admin['first_name']; ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo $admin['last_name']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" value="<?php echo $admin['username']; ?>" readonly disabled>
                            <div class="form-text">Username cannot be changed.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $admin['email']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number (Optional)</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $admin['phone']; ?>" placeholder="e.g., +27123456789">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Assign Accommodations</label>
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($accommodations as $accommodation): ?>
                                        <div class="col-md-4 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="accommodation_ids[]" value="<?php echo $accommodation['id']; ?>" id="acc_<?php echo $accommodation['id']; ?>"
                                                    <?php echo in_array($accommodation['id'], $assignedAccommodationIds) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="acc_<?php echo $accommodation['id']; ?>">
                                                    <?php echo $accommodation['name']; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">Select the accommodations this admin will manage.</div>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="user_id" value="<?php echo $admin['id']; ?>">
                    
                    <div class="mt-4">
                        <button type="submit" name="update_admin" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Admin User
                        </button>
                        <a href="users.php" class="btn btn-secondary ms-2">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
    <?php elseif ($action === 'reset_password' && $admin): ?>
        <!-- Reset Password Form -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h4 class="mb-0"><i class="fas fa-key me-2"></i>Reset Password: <?php echo $admin['first_name'] . ' ' . $admin['last_name']; ?></h4>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    You are resetting the password for <strong><?php echo $admin['first_name'] . ' ' . $admin['last_name']; ?></strong>.
                    This action will immediately change the user's password.
                </div>
                
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#new_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Must be at least 6 characters long.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#confirm_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <input type="hidden" name="user_id" value="<?php echo $admin['id']; ?>">
                    
                    <div class="mt-4">
                        <button type="submit" name="reset_password" class="btn btn-warning">
                            <i class="fas fa-key me-2"></i>Reset Password
                        </button>
                        <a href="users.php" class="btn btn-secondary ms-2">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
    <?php elseif ($action === 'delete' && $admin): ?>
        <!-- Delete Admin Confirmation -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-danger text-white py-3">
                <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Delete Admin User</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. The admin user will be permanently removed from the system.
                </div>
                
                <p>Are you sure you want to delete the following admin user?</p>
                
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $admin['first_name'] . ' ' . $admin['last_name']; ?></h5>
                        <p class="card-text"><strong>Username:</strong> <?php echo $admin['username']; ?></p>
                        <p class="card-text"><strong>Email:</strong> <?php echo $admin['email']; ?></p>
                        <p class="card-text">
                            <strong>Assigned Accommodations:</strong>
                            <?php 
                            if (empty($assignedAccommodationIds)) {
                                echo '<span class="text-muted">None</span>';
                            } else {
                                $assignedNames = [];
                                foreach ($accommodations as $accommodation) {
                                    if (in_array($accommodation['id'], $assignedAccommodationIds)) {
                                        $assignedNames[] = $accommodation['name'];
                                    }
                                }
                                echo implode(', ', $assignedNames);
                            }
                            ?>
                        </p>
                    </div>
                </div>
                
                <form method="post" action="">
                    <input type="hidden" name="user_id" value="<?php echo $admin['id']; ?>">
                    
                    <div class="d-flex justify-content-between">
                        <a href="users.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Cancel
                        </a>
                        <button type="submit" name="delete_admin" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-2"></i>Yes, Delete Admin User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
