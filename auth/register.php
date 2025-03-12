<?php
/**
 * Registration page
 * 
 * This page handles new user registration.
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
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName = sanitize($_POST['last_name'] ?? '');
    
    // Basic validation
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
    
    // Register user if no validation errors
    if (empty($errors)) {
        $userData = [
            'username' => $username,
            'password' => $password,  // Will be hashed in registerUser function
            'email' => $email,
            'phone' => $phone,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role' => ROLE_STUDENT  // All registrations are for students
        ];
        
        $userId = registerUser($conn, $userData);
        
        if ($userId) {
            setFlashMessage('Registration successful! You can now login.', 'success');
            redirect('/auth/login.php');
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm rounded-3 border-0 mt-5">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-plus fa-4x text-primary"></i>
                        <h2 class="mt-3 mb-1">Create an Account</h2>
                        <p class="text-muted">Register as a student to apply for accommodation</p>
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
                    
                    <form method="post" action="" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo isset($firstName) ? $firstName : ''; ?>" required>
                                <div class="invalid-feedback">
                                    Please enter your first name.
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo isset($lastName) ? $lastName : ''; ?>" required>
                                <div class="invalid-feedback">
                                    Please enter your last name.
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($email) ? $email : ''; ?>" required>
                            <div class="invalid-feedback">
                                Please enter a valid email address.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number (Optional)</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo isset($phone) ? $phone : ''; ?>" placeholder="e.g., +27123456789">
                            <div class="form-text">Enter phone number with country code for SMS notifications.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($username) ? $username : ''; ?>" required>
                            <div class="invalid-feedback">
                                Username must be at least 3 characters.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">
                                Password must be at least 6 characters.
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#confirm_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">
                                Passwords must match.
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="terms" required>
                            <label class="form-check-label" for="terms">I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a></label>
                            <div class="invalid-feedback">
                                You must agree to the terms and conditions.
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus me-2"></i>Register
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p>Already have an account? <a href="login.php">Login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Terms and Conditions Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Terms and Conditions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6>1. Account Registration</h6>
                <p>By registering an account with Harambee Student Living, you agree to provide accurate, current, and complete information. You are responsible for maintaining the confidentiality of your account credentials.</p>
                
                <h6>2. Application Process</h6>
                <p>Submitting an application does not guarantee accommodation. All applications are subject to review and approval based on availability and eligibility criteria.</p>
                
                <h6>3. Lease Agreements</h6>
                <p>If your application is approved, you will be required to sign a legally binding lease agreement. By signing the lease, you agree to abide by all terms and conditions specified in the agreement.</p>
                
                <h6>4. Payments</h6>
                <p>You are responsible for making all payments on time as specified in your lease agreement. Late payments may result in penalties or termination of your lease.</p>
                
                <h6>5. Privacy Policy</h6>
                <p>Your personal information will be handled in accordance with our privacy policy. We collect and use your information primarily to provide and improve our services.</p>
                
                <h6>6. Communications</h6>
                <p>By registering, you consent to receive communications from Harambee Student Living regarding your account, application, lease, invoices, and maintenance requests.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
