<?php
/**
 * User profile page
 * 
 * This page allows users to view and update their profile information.
 */

require_once '../includes/header.php';

// Require authentication
requireAuth();

$userId = getCurrentUserId();
$user = getUser($conn, $userId);

// Handle form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check which form was submitted
    if (isset($_POST['update_profile'])) {
        // Handle profile update
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        
        // Validate input
        if (empty($firstName)) {
            $errors[] = 'First name is required';
        }
        
        if (empty($lastName)) {
            $errors[] = 'Last name is required';
        }
        
        if (empty($email)) {
            $errors[] = 'Email is required';
        } elseif (!isValidEmail($email)) {
            $errors[] = 'Invalid email format';
        }
        
        if (!empty($phone) && !isValidPhone($phone)) {
            $errors[] = 'Invalid phone number format';
        }
        
        // Check if email already exists (excluding current user)
        if ($email !== $user['email']) {
            $checkEmail = fetchRow($conn, "SELECT id FROM users WHERE email = :email AND id != :id", [
                'email' => $email,
                'id' => $userId
            ]);
            
            if ($checkEmail) {
                $errors[] = 'Email already exists';
            }
        }
        
        // Update profile if no validation errors
        if (empty($errors)) {
            $userData = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone
            ];
            
            if (updateUserProfile($conn, $userId, $userData)) {
                $success = true;
                $user = getUser($conn, $userId); // Refresh user data
                setFlashMessage('Profile updated successfully!', 'success');
            } else {
                $errors[] = 'Failed to update profile. Please try again.';
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Handle password change
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate input
        if (empty($currentPassword)) {
            $errors[] = 'Current password is required';
        }
        
        if (empty($newPassword)) {
            $errors[] = 'New password is required';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'New password must be at least 6 characters';
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Passwords do not match';
        }
        
        // Change password if no validation errors
        if (empty($errors)) {
            if (changeUserPassword($conn, $userId, $currentPassword, $newPassword)) {
                $success = true;
                setFlashMessage('Password changed successfully!', 'success');
            } else {
                $errors[] = 'Failed to change password. Please check your current password and try again.';
            }
        }
    }
}
?>

<div class="container">
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="profile-avatar mb-3">
                        <i class="fas fa-user"></i>
                    </div>
                    <h5><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h5>
                    <p class="text-muted mb-1">
                        <?php 
                            switch ($user['role']) {
                                case ROLE_MASTER_ADMIN:
                                    echo '<span class="badge bg-danger">Master Admin</span>';
                                    break;
                                case ROLE_ADMIN:
                                    echo '<span class="badge bg-primary">Admin</span>';
                                    break;
                                case ROLE_STUDENT:
                                    echo '<span class="badge bg-success">Student</span>';
                                    break;
                                default:
                                    echo '<span class="badge bg-secondary">User</span>';
                            }
                        ?>
                    </p>
                    <p class="text-muted mb-0">
                        <i class="fas fa-user me-1"></i> <?php echo $user['username']; ?>
                    </p>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item">
                        <i class="fas fa-envelope me-2"></i> <?php echo $user['email']; ?>
                    </li>
                    <?php if (!empty($user['phone'])): ?>
                        <li class="list-group-item">
                            <i class="fas fa-phone me-2"></i> <?php echo $user['phone']; ?>
                        </li>
                    <?php endif; ?>
                    <li class="list-group-item">
                        <i class="fas fa-calendar-alt me-2"></i> Joined: <?php echo formatDate($user['created_at']); ?>
                    </li>
                </ul>
                <div class="card-body">
                    <a href="../index.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-arrow-left me-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Update Profile</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo $user['first_name']; ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo $user['last_name']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $user['email']; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $user['phone']; ?>" placeholder="e.g., +27123456789">
                            <div class="form-text">Enter phone number with country code for SMS notifications.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" value="<?php echo $user['username']; ?>" readonly disabled>
                            <div class="form-text">Username cannot be changed.</div>
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#current_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#new_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Password must be at least 6 characters.</div>
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
                        
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
