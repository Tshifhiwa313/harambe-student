<?php
$page_title = 'Login';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
require_once 'include/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$username = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? sanitize_input($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validate inputs
    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    // Attempt login if no validation errors
    if (empty($errors)) {
        $user = login_user($username, $password);
        
        if ($user) {
            // Redirect based on user role
            if ($user['role'] === ROLE_MASTER_ADMIN) {
                redirect('master_admin.php');
            } elseif ($user['role'] === ROLE_ADMIN) {
                redirect('admin.php');
            } else {
                redirect('student_dashboard.php');
            }
        } else {
            $errors[] = 'Invalid username or password';
        }
    }
}

// Include header
include 'include/header.php';

// Include navbar
include 'include/navbar.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm mt-5">
            <div class="card-header bg-primary text-white">
                <h4 class="card-title mb-0">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </h4>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="login.php">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo $username; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-light text-center py-3">
                Don't have an account? <a href="register.php">Register here</a>
            </div>
        </div>
        
        <!-- Demo Account Information -->
        <div class="alert alert-info mt-4">
            <h5><i class="fas fa-info-circle me-2"></i>Demo Accounts</h5>
            <p class="mb-2">You can use the following demo accounts to test the system:</p>
            <ul class="list-unstyled">
                <li><strong>Master Admin:</strong> username: masteradmin, password: admin123</li>
            </ul>
        </div>
    </div>
</div>

<?php
// Include footer
include 'include/footer.php';
?>
