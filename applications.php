<?php
include_once __DIR__ . '/config/constants.php';
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/authentication.php';
require_once 'includes/email.php';
require_once 'includes/sms.php';

// Require login
requireLogin();

$error = '';
$success = '';

// Get requested action and ID
$action = $_GET['action'] ?? '';
$applicationId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$accommodationId = isset($_GET['accommodation_id']) ? intval($_GET['accommodation_id']) : 0;

// Process application approval/rejection
if (hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN]) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $appId = intval($_POST['application_id'] ?? 0);
    $status = intval($_POST['status'] ?? 0);
    $notes = $_POST['notes'] ?? '';
    
    if ($appId && $status) {
        // Check if admin is authorized to manage this application
        $application = getApplicationById($appId);
        
        if ($application) {
            $canManage = hasRole(ROLE_MASTER_ADMIN) || 
                         isAdminAssignedToAccommodation($_SESSION['user_id'], $application['accommodation_id']);
            
            if ($canManage) {
                // Update application status
                update('applications', [
                    'status' => $status,
                    'notes' => $notes,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id', $appId);
                
                // Send notification email to student
                $user = fetchOne("SELECT * FROM users WHERE id = ?", [$application['user_id']]);
                if ($user) {
                    sendApplicationStatusEmail(
                        $user['email'],
                        $user['username'],
                        $application['accommodation_name'],
                        $status
                    );
                    
                    // Send SMS if phone number is available and status is updated
                    if (!empty($user['phone'])) {
                        sendApplicationStatusSMS(
                            $user['phone'],
                            $user['username'],
                            $application['accommodation_name'],
                            $status
                        );
                    }
                }
                
                // Create lease if application is approved
                if ($status == STATUS_APPROVED) {
                    // Get accommodation details
                    $accommodation = getAccommodationById($application['accommodation_id']);
                    
                    if ($accommodation) {
                        // Create the lease
                        $leaseId = insert('leases', [
                            'user_id' => $application['user_id'],
                            'accommodation_id' => $application['accommodation_id'],
                            'start_date' => date('Y-m-d', strtotime('+7 days')),
                            'end_date' => date('Y-m-d', strtotime('+12 months')),
                            'monthly_rent' => $accommodation['price_per_month'],
                            'security_deposit' => $accommodation['price_per_month'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        // Notify student about lease
                        if ($leaseId && $user) {
                            $lease = getLeaseById($leaseId);
                            sendLeaseEmail($user['email'], $user['username'], $lease);
                            
                            if (!empty($user['phone'])) {
                                sendLeaseSMS($user['phone'], $user['username'], $lease);
                            }
                        }
                    }
                }
                
                $success = 'Application status updated successfully.';
            } else {
                $error = 'You are not authorized to manage this application.';
            }
        } else {
            $error = 'Application not found.';
        }
    } else {
        $error = 'Invalid application data.';
    }
}

// Process new application submission
if (hasRole(ROLE_STUDENT) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    $accId = intval($_POST['accommodation_id'] ?? 0);
    
    if ($accId) {
        // Check if student already has a pending application for this accommodation
        $existingApplication = fetchOne(
            "SELECT id FROM applications WHERE user_id = ? AND accommodation_id = ? AND status = ?",
            [$_SESSION['user_id'], $accId, STATUS_PENDING]
        );
        
        if ($existingApplication) {
            $error = 'You already have a pending application for this accommodation.';
        } else {
            // Create new application
            $applicationId = insert('applications', [
                'user_id' => $_SESSION['user_id'],
                'accommodation_id' => $accId,
                'status' => STATUS_PENDING,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($applicationId) {
                $success = 'Your application has been submitted successfully.';
                
                // Update student profile if additional data is provided
                if (!empty($_POST['first_name']) && !empty($_POST['last_name'])) {
                    update('users', [
                        'first_name' => $_POST['first_name'],
                        'last_name' => $_POST['last_name'],
                        'phone' => $_POST['phone'] ?? ''
                    ], 'id', $_SESSION['user_id']);
                    
                    // Create or update student profile
                    $studentProfile = fetchOne("SELECT id FROM student_profiles WHERE user_id = ?", [$_SESSION['user_id']]);
                    
                    $profileData = [
                        'student_number' => $_POST['student_number'] ?? '',
                        'date_of_birth' => $_POST['date_of_birth'] ?? null,
                        'gender' => $_POST['gender'] ?? '',
                        'id_number' => $_POST['id_number'] ?? '',
                        'address' => $_POST['address'] ?? '',
                        'emergency_contact_name' => $_POST['emergency_contact_name'] ?? '',
                        'emergency_contact_phone' => $_POST['emergency_contact_phone'] ?? ''
                    ];
                    
                    if ($studentProfile) {
                        update('student_profiles', $profileData, 'user_id', $_SESSION['user_id']);
                    } else {
                        $profileData['user_id'] = $_SESSION['user_id'];
                        $profileData['created_at'] = date('Y-m-d H:i:s');
                        insert('student_profiles', $profileData);
                    }
                }
                
                // Redirect to view the application
                header('Location: applications.php?id=' . $applicationId);
                exit;
            } else {
                $error = 'Failed to submit application. Please try again.';
            }
        }
    } else {
        $error = 'Invalid accommodation selected.';
    }
}

// Get application data based on role and context
if ($applicationId) {
    // View specific application
    $application = getApplicationById($applicationId);
    
    if (!$application) {
        header('Location: applications.php');
        exit;
    }
    
    // Check permission to view this application
    $canView = false;
    
    if (hasRole(ROLE_STUDENT)) {
        $canView = $application['user_id'] == $_SESSION['user_id'];
    } elseif (hasRole(ROLE_ADMIN)) {
        $canView = isAdminAssignedToAccommodation($_SESSION['user_id'], $application['accommodation_id']);
    } elseif (hasRole(ROLE_MASTER_ADMIN)) {
        $canView = true;
    }
    
    if (!$canView) {
        header('Location: applications.php');
        exit;
    }
} elseif ($accommodationId) {
    // New application form
    $accommodation = getAccommodationById($accommodationId);
    
    if (!$accommodation) {
        header('Location: accommodations.php');
        exit;
    }
    
    // If user is admin, redirect to applications list
    if (hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])) {
        header('Location: applications.php');
        exit;
    }
    
    // Get user profile data
    $user = getCurrentUser();
    $studentProfile = fetchOne("SELECT * FROM student_profiles WHERE user_id = ?", [$_SESSION['user_id']]);
} else {
    // Applications list
    if (hasRole(ROLE_MASTER_ADMIN)) {
        $applications = getApplications();
    } elseif (hasRole(ROLE_ADMIN)) {
        $adminId = $_SESSION['user_id'];
        $applications = getApplications(null, $adminId);
    } else {
        $applications = getStudentApplications($_SESSION['user_id']);
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
    
    <?php if ($applicationId && $application): ?>
        <!-- View Single Application -->
        <div class="row mb-4">
            <div class="col-md-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?= hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN]) ? 'admin.php' : 'dashboard.php' ?>">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="applications.php">Applications</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Application #<?= $application['id'] ?></li>
                    </ol>
                </nav>
                <h1>Application Details</h1>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Application #<?= $application['id'] ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6>Status</h6>
                                <?php if ($application['status'] == STATUS_PENDING): ?>
                                    <span class="badge bg-warning">Pending</span>
                                <?php elseif ($application['status'] == STATUS_APPROVED): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php elseif ($application['status'] == STATUS_REJECTED): ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6>Applied On</h6>
                                <p><?= formatDate($application['created_at']) ?></p>
                            </div>
                        </div>
                        
                        <h6>Accommodation</h6>
                        <p><?= $application['accommodation_name'] ?></p>
                        
                        <?php if (hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])): ?>
                            <h6>Student</h6>
                            <p><?= $application['username'] ?> (<?= $application['email'] ?>)</p>
                        <?php endif; ?>
                        
                        <?php if (!empty($application['notes'])): ?>
                            <h6>Notes</h6>
                            <p><?= nl2br($application['notes']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN]) && $application['status'] == STATUS_PENDING): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Update Application Status</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="applications.php">
                                <input type="hidden" name="application_id" value="<?= $application['id'] ?>">
                                <input type="hidden" name="action" value="update_status">
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="<?= STATUS_APPROVED ?>">Approve</option>
                                        <option value="<?= STATUS_REJECTED ?>">Reject</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"><?= $application['notes'] ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Update Status</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Accommodation Details</h5>
                    </div>
                    <div class="card-body">
                        <h6><?= $application['accommodation_name'] ?></h6>
                        <p><i class="fas fa-map-marker-alt"></i> <?= fetchOne("SELECT location FROM accommodations WHERE id = ?", [$application['accommodation_id']])['location'] ?></p>
                        <p><i class="fas fa-money-bill-wave"></i> <?= formatCurrency(fetchOne("SELECT price_per_month FROM accommodations WHERE id = ?", [$application['accommodation_id']])['price_per_month']) ?> / month</p>
                        
                        <a href="accommodations.php?id=<?= $application['accommodation_id'] ?>" class="btn btn-outline-primary">View Accommodation</a>
                    </div>
                </div>
                
                <?php if ($application['status'] == STATUS_APPROVED): ?>
                    <?php 
                    $lease = fetchOne("SELECT id FROM leases WHERE user_id = ? AND accommodation_id = ?", 
                                     [$application['user_id'], $application['accommodation_id']]);
                    if ($lease): 
                    ?>
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">Lease Created</h5>
                            </div>
                            <div class="card-body">
                                <p>A lease has been created for this application.</p>
                                <a href="leases.php?id=<?= $lease['id'] ?>" class="btn btn-outline-success">View Lease</a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
    <?php elseif ($accommodationId && $accommodation && hasRole(ROLE_STUDENT)): ?>
        <!-- New Application Form -->
        <div class="row mb-4">
            <div class="col-md-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="accommodations.php">Accommodations</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Apply</li>
                    </ol>
                </nav>
                <h1>Apply for Accommodation</h1>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Application Form</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="applications.php" id="application_form">
                            <input type="hidden" name="accommodation_id" value="<?= $accommodation['id'] ?>">
                            <input type="hidden" name="submit_application" value="1">
                            
                            <h5 class="mb-3">Personal Information</h5>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?= $user['first_name'] ?? '' ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?= $user['last_name'] ?? '' ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" value="<?= $user['email'] ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= $user['phone'] ?? '' ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control datepicker" id="date_of_birth" name="date_of_birth" value="<?= $studentProfile['date_of_birth'] ?? '' ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?= isset($studentProfile['gender']) && $studentProfile['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= isset($studentProfile['gender']) && $studentProfile['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                                        <option value="Other" <?= isset($studentProfile['gender']) && $studentProfile['gender'] == 'Other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="student_number" class="form-label">Student Number</label>
                                <input type="text" class="form-control" id="student_number" name="student_number" value="<?= $studentProfile['student_number'] ?? '' ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="id_number" class="form-label">ID Number / Passport</label>
                                <input type="text" class="form-control" id="id_number" name="id_number" value="<?= $studentProfile['id_number'] ?? '' ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Current Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?= $studentProfile['address'] ?? '' ?></textarea>
                            </div>
                            
                            <h5 class="mb-3 mt-4">Emergency Contact</h5>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="emergency_contact_name" class="form-label">Contact Name</label>
                                    <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" value="<?= $studentProfile['emergency_contact_name'] ?? '' ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="emergency_contact_phone" class="form-label">Contact Phone</label>
                                    <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" value="<?= $studentProfile['emergency_contact_phone'] ?? '' ?>">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">Submit Application</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Accommodation Details</h5>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title"><?= $accommodation['name'] ?></h5>
                        <?php if (!empty($accommodation['image_path'])): ?>
                            <img src="uploads/accommodations/<?= $accommodation['image_path'] ?>" class="img-fluid mb-3" alt="<?= $accommodation['name'] ?>">
                        <?php endif; ?>
                        <p><i class="fas fa-map-marker-alt"></i> <?= $accommodation['location'] ?></p>
                        <p><i class="fas fa-money-bill-wave"></i> <?= formatCurrency($accommodation['price_per_month']) ?> / month</p>
                        <p><i class="fas fa-door-open"></i> <?= $accommodation['rooms_available'] ?> rooms available</p>
                        
                        <a href="accommodations.php?id=<?= $accommodation['id'] ?>" class="btn btn-outline-primary">View Details</a>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Applications List -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h1><?= hasRole(ROLE_STUDENT) ? 'My Applications' : 'Student Applications' ?></h1>
                <p class="lead">
                    <?= hasRole(ROLE_STUDENT) ? 'Manage your accommodation applications.' : 'View and manage student applications.' ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <?php if (hasRole(ROLE_STUDENT)): ?>
                    <a href="accommodations.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Application
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (empty($applications)): ?>
            <div class="alert alert-info">
                <?php if (hasRole(ROLE_STUDENT)): ?>
                    <p class="text-center">You haven't made any applications yet. <a href="accommodations.php">Browse accommodations</a> to apply.</p>
                <?php else: ?>
                    <p class="text-center">No applications found.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?= hasRole(ROLE_STUDENT) ? 'My Applications' : 'Applications List' ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <?php if (hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])): ?>
                                        <th>Student</th>
                                    <?php endif; ?>
                                    <th>Accommodation</th>
                                    <th>Status</th>
                                    <th>Applied On</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $app): ?>
                                    <tr>
                                        <td><?= $app['id'] ?></td>
                                        <?php if (hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])): ?>
                                            <td><?= $app['username'] ?></td>
                                        <?php endif; ?>
                                        <td><?= $app['accommodation_name'] ?></td>
                                        <td>
                                            <?php if ($app['status'] == STATUS_PENDING): ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php elseif ($app['status'] == STATUS_APPROVED): ?>
                                                <span class="badge bg-success">Approved</span>
                                            <?php elseif ($app['status'] == STATUS_REJECTED): ?>
                                                <span class="badge bg-danger">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= formatDate($app['created_at']) ?></td>
                                        <td>
                                            <a href="applications.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
