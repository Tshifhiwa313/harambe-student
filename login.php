<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/authentication.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Attempt to log in
        if (loginUser($username, $password)) {
            // Redirect based on user role
            if (hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])) {
                header('Location: admin.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-container">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Login</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success" role="alert">
                    <?= $success ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="login.php">
                <div class="mb-3">
                    <label for="username" class="form-label">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username or email" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                        <span class="input-group-text">
                            <i class="fas fa-eye" id="togglePassword" style="cursor: pointer;"></i>
                        </span>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">Login</button>
                </div>
            </form>
            
            <div class="text-center mt-3">
                <p>Don't have an account? <a href="register.php">Register</a></p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
