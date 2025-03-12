<?php
$page_title = 'Register';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
require_once 'include/auth.php';
require_once 'include/email_functions.php';
require_once 'include/sms_functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$success = false;
$form_data = [
    'username' => '',
    'email' => '',
    'full_name' => '',
    'phone_number' => ''
];

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $form_data['username'] = isset($_POST['username']) ? sanitize_input($_POST['username']) : '';
    $form_data['email'] = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
    $form_data['full_name'] = isset($_POST['full_name']) ? sanitize_input($_POST['full_name']) : '';
    $form_data['phone_number'] = isset($_POST['phone_number']) ? sanitize_input($_POST['phone_number']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Validate inputs
    if (empty($form_data['username'])) {
        $errors[] = 'Username is required';
    } elseif (strlen($form_data['username']) < 3 || strlen($form_data['username']) > 20) {
        $errors[] = 'Username must be between 3 and 20 characters';
    } elseif (get_user_by_username($form_data['username'])) {
        $errors[] = 'Username already exists';
    }
    
    if (empty($form_data['email'])) {
        $errors[] = 'Email is required';
    } elseif (!is_valid_email($form_data['email'])) {
        $errors[] = 'Invalid email format';
    } elseif (get_user_by_email($form_data['email'])) {
        $errors[] = 'Email already exists';
    }
    
    if (empty($form_data['full_name'])) {
        $errors[] = 'Full name is required';
    }
    
    if (!empty($form_data['phone_number']) && !is_valid_phone($form_data['phone_number'])) {
        $errors[] = 'Invalid phone number format';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
    
    // Register user if no validation errors
    if (empty($errors)) {
        $user_id = register_user(
            $form_data['username'],
            $password,
            $form_data['email'],
            $form_data['full_name'],
            $form_data['phone_number'],
            ROLE_STUDENT
        );
        
        if ($user_id) {
            // Send welcome email
            send_welcome_email($user_id);
            
            // Send welcome SMS if phone number provided and Twilio enabled
            if (TWILIO_ENABLED && !empty($form_data['phone_number'])) {
                send_welcome_sms($user_id);
            }
            
            // Set success message and redirect to login
            set_flash_message('success', 'Registration successful! You can now log in.');
            redirect('login.php');
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

// Include header
include 'include/header.php';

// Include navbar
include 'include/navbar.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-primary text-white">
                <h4 class="card-title mb-0">
                    <i class="fas fa-user-plus me-2"></i>Register
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
                
                <form method="post" action="register.php">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo $form_data['username']; ?>" required>
                            </div>
                            <div class="form-text">Choose a unique username (3-20 characters)</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo $form_data['email']; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo $form_data['full_name']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                       value="<?php echo $form_data['phone_number']; ?>">
                            </div>
                            <div class="form-text">Include country code (e.g., +27...)</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i>Register
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-light text-center py-3">
                Already have an account? <a href="login.php">Login here</a>
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
                <h5>1. Acceptance of Terms</h5>
                <p>By registering an account with Harambee Student Living, you agree to comply with and be bound by these Terms and Conditions.</p>
                
                <h5>2. User Account</h5>
                <p>You are responsible for maintaining the confidentiality of your account information and for all activities that occur under your account.</p>
                
                <h5>3. Personal Information</h5>
                <p>You agree to provide accurate, current, and complete information during the registration process and to update such information to keep it accurate, current, and complete.</p>
                
                <h5>4. Communication</h5>
                <p>By registering, you consent to receive communications from Harambee Student Living, including notifications about your application, lease, invoices, and maintenance requests.</p>
                
                <h5>5. Privacy Policy</h5>
                <p>Your use of the Harambee Student Living service is also governed by our Privacy Policy, which outlines how we collect, use, and protect your personal information.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'include/footer.php';
?>
