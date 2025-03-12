<?php
/**
 * Login page
 * 
 * This page handles user login authentication.
 */

require_once '../includes/header.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('/index.php');
}

// Handle form submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    // Attempt login if no validation errors
    if (empty($errors)) {
        $user = loginUser($conn, $username, $password);
        
        if ($user) {
            // Redirect based on role
            if ($user['role'] === ROLE_MASTER_ADMIN || $user['role'] === ROLE_ADMIN) {
                redirect('/admin/dashboard.php');
            } else {
                redirect('/student/dashboard.php');
            }
        } else {
            $errors[] = 'Invalid username or password';
        }
    }
}
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm rounded-3 border-0 mt-5">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-circle fa-4x text-primary"></i>
                        <h2 class="mt-3 mb-1">Login to Your Account</h2>
                        <p class="text-muted">Enter your credentials to access your account</p>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($username) ? $username : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p>Don't have an account? <a href="register.php">Register</a></p>
                    </div>
                </div>
            </div>
            
            <!-- Default Account Information for Testing -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-info-circle me-2"></i>Default Admin Account
                </div>
                <div class="card-body">
                    <p class="card-text">For testing purposes, you can use the following account:</p>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>Username:</strong> admin</li>
                        <li class="list-group-item"><strong>Password:</strong> admin123</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
