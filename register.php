<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/authentication.php';
require_once 'includes/email.php';
include 'functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $first_name = $_POST['first_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';
        $student_number = $_POST['student_number'] ?? '';
        $college = $_POST['college'] ?? '';
        $date_of_birth = $_POST['date_of_birth'] ?? '';
        
        // Validate input
        if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($student_number) || empty($college) || empty($date_of_birth)) {
            $error = 'Please fill all required fields';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } else {
            // Create user data
            $userData = [
                'phone' => $phone,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'student_number' => $student_number,
                'college' => $college,
                'date_of_birth' => $date_of_birth
            ];
            
            // Register the user as a student
            $userId = registerUser($username, $email, $password, ROLE_STUDENT, $userData);
            
            if ($userId) {
                $success = 'Registration successful! You can now login.';
                
                // Try to send welcome email but don't halt registration if it fails
                try {
                    sendWelcomeEmail($email, $username, $password, ROLE_STUDENT);
                } catch (Exception $e) {
                    error_log("Failed to send welcome email: " . $e->getMessage());
                    // Continue with registration even if email fails
                }
                
                // Redirect to login page after short delay
                header('refresh:2;url=login.php');
            } else {
                $error = 'Username or email already exists';
            }
        }
    } catch (Exception $e) {
        error_log("Error during registration: " . $e->getMessage());
        $error = 'An unexpected error occurred. Please try again later.';
    }
}

include 'includes/header.php';
?>

<div class="auth-container">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Register</h3>
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
            
            <form method="post" action="register.php">
                <div class="mb-3">
                    <label for="username" class="form-label">Username *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Create a username" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email address" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" placeholder="Enter your first name">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Enter your last name">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="Enter your phone number">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="student_number" class="form-label">Student Number *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                        <input type="text" class="form-control" id="student_number" name="student_number" placeholder="Enter your student number" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="college" class="form-label">College/Institution *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-university"></i></span>
                        <select class="form-select" id="college" name="college" required>
                            <option value="" selected disabled>Select your college/institution</option>
                            <option value="University of Johannesburg">University of Johannesburg</option>
                            <option value="University of the Witwatersrand">University of the Witwatersrand</option>
                            <option value="University of Pretoria">University of Pretoria</option>
                            <option value="Tshwane University of Technology">Tshwane University of Technology</option>
                            <option value="UNISA">UNISA</option>
                            <option value="Cape Peninsula University of Technology">Cape Peninsula University of Technology</option>
                            <option value="University of Cape Town">University of Cape Town</option>
                            <option value="Stellenbosch University">Stellenbosch University</option>
                            <option value="University of KwaZulu-Natal">University of KwaZulu-Natal</option>
                            <option value="Durban University of Technology">Durban University of Technology</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="date_of_birth" class="form-label">Date of Birth *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Create a password" required>
                        <span class="input-group-text">
                            <i class="fas fa-eye" id="togglePassword" style="cursor: pointer;"></i>
                        </span>
                    </div>
                    <div class="form-text">Password must be at least 6 characters long</div>
                </div>
                
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">Register</button>
                </div>
            </form>
            
            <div class="text-center mt-3">
                <p>Already have an account? <a href="login.php">Login</a></p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
