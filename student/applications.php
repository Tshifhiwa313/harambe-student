<?php
/**
 * Student Applications
 * 
 * This page allows students to view and manage their accommodation applications.
 */

require_once '../includes/header.php';

// Require student authentication
requireAuth(ROLE_STUDENT);

$userId = getCurrentUserId();

// Define action based on GET parameter
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$applicationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$accommodationId = isset($_GET['accommodation_id']) ? (int)$_GET['accommodation_id'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Submit new application
    if (isset($_POST['submit_application'])) {
        $accommodationId = (int)$_POST['accommodation_id'];
        $moveInDate = $_POST['move_in_date'];
        $additionalInfo = sanitize($_POST['additional_info'] ?? '');
        
        // Validation
        $errors = [];
        
        if ($accommodationId <= 0) {
            $errors[] = 'Please select a valid accommodation';
        }
        
        if (empty($moveInDate) || !isValidDate($moveInDate)) {
            $errors[] = 'Please enter a valid move-in date';
        } elseif (strtotime($moveInDate) < strtotime(date('Y-m-d'))) {
            $errors[] = 'Move-in date must be in the future';
        }
        
        // Check if accommodation exists and has available rooms
        $accommodation = getAccommodation($conn, $accommodationId);
        if (!$accommodation) {
            $errors[] = 'Selected accommodation not found';
        } elseif (!hasAvailableRooms($conn, $accommodationId)) {
            $errors[] = 'Selected accommodation has no available rooms';
        }
        
        // Check if student already has an application for this accommodation
        $existingApplication = fetchRow($conn, 
            "SELECT id FROM applications WHERE user_id = :userId AND accommodation_id = :accommodationId AND status = :status", 
            ['userId' => $userId, 'accommodationId' => $accommodationId, 'status' => STATUS_PENDING]
        );
        
        if ($existingApplication) {
            $errors[] = 'You already have a pending application for this accommodation';
        }
        
        // Check if student already has an active lease
        if (hasActiveLease($conn, $userId)) {
            $errors[] = 'You already have an active lease. Contact administration if you wish to change accommodations.';
        }
        
        // Submit application if no errors
        if (empty($errors)) {
            $applicationData = [
                'user_id' => $userId,
                'accommodation_id' => $accommodationId,
                'move_in_date' => $moveInDate,
                'additional_info' => $additionalInfo,
                'status' => STATUS_PENDING,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $newApplicationId = insertRow($conn, 'applications', $applicationData);
            
            if ($newApplicationId) {
                // Create notification for admin(s)
                $student = getUser($conn, $userId);
                
                // Find admins for this accommodation
                $admins = fetchAll($conn, 
                    "SELECT u.id 
                    FROM users u 
                    JOIN accommodation_admins aa ON u.id = aa.user_id 
                    WHERE aa.accommodation_id = :accommodationId", 
                    ['accommodationId' => $accommodationId]
                );
                
                foreach ($admins as $admin) {
                    $notificationData = [
                        'user_id' => $admin['id'],
                        'message' => "New application from {$student['first_name']} {$student['last_name']} for {$accommodation['name']}",
                        'type' => 'application',
                        'is_read' => 0
                    ];
                    
                    insertRow($conn, 'notifications', $notificationData);
                }
                
                // Also notify master admin(s)
                $masterAdmins = fetchAll($conn, "SELECT id FROM users WHERE role = :role", ['role' => ROLE_MASTER_ADMIN]);
                
                foreach ($masterAdmins as $admin) {
                    $notificationData = [
                        'user_id' => $admin['id'],
                        'message' => "New application from {$student['first_name']} {$student['last_name']} for {$accommodation['name']}",
                        'type' => 'application',
                        'is_read' => 0
                    ];
                    
                    insertRow($conn, 'notifications', $notificationData);
                }
                
                setFlashMessage('Application submitted successfully!', 'success');
                redirect("applications.php?action=view&id=$newApplicationId");
            } else {
                $errors[] = 'Failed to submit application. Please try again.';
            }
        }
    }
    
    // Cancel application
    if (isset($_POST['cancel_application'])) {
        $applicationId = (int)$_POST['application_id'];
        
        // Get application details
        $application = getApplication($conn, $applicationId);
        
        if (!$application) {
            setFlashMessage('Application not found', 'danger');
            redirect('applications.php');
        }
        
        // Check if application belongs to the current user
        if ($application['user_id'] != $userId) {
            setFlashMessage('You do not have permission to cancel this application', 'danger');
            redirect('applications.php');
        }
        
        // Check if application can be cancelled (only pending applications can be cancelled)
        if ($application['status'] !== STATUS_PENDING) {
            setFlashMessage('Only pending applications can be cancelled', 'danger');
            redirect('applications.php');
        }
        
        // Cancel application
        if (deleteRow($conn, 'applications', 'id', $applicationId)) {
            setFlashMessage('Application cancelled successfully', 'success');
            redirect('applications.php');
        } else {
            setFlashMessage('Failed to cancel application. Please try again.', 'danger');
            redirect("applications.php?action=view&id=$applicationId");
        }
    }
}

// Get student's applications
$applications = fetchAll($conn, 
    "SELECT a.*, acc.name as accommodation_name, acc.address 
    FROM applications a 
    JOIN accommodations acc ON a.accommodation_id = acc.id 
    WHERE a.user_id = :userId 
    ORDER BY a.created_at DESC", 
    ['userId' => $userId]
);

// Get specific application for viewing
$application = null;
if ($action === 'view' && $applicationId > 0) {
    $application = fetchRow($conn, 
        "SELECT a.*, acc.name as accommodation_name, acc.address, acc.description, acc.price, acc.image_path 
        FROM applications a 
        JOIN accommodations acc ON a.accommodation_id = acc.id 
        WHERE a.id = :id AND a.user_id = :userId", 
        ['id' => $applicationId, 'userId' => $userId]
    );
    
    if (!$application) {
        setFlashMessage('Application not found', 'danger');
        redirect('applications.php');
    }
}

// Get available accommodations for new application
if ($action === 'add') {
    // Check if student already has an active lease
    if (hasActiveLease($conn, $userId)) {
        setFlashMessage('You already have an active lease. Contact administration if you wish to change accommodations.', 'danger');
        redirect('applications.php');
    }
    
    // Get accommodations with available rooms
    $availableAccommodations = fetchAll($conn, "SELECT * FROM accommodations WHERE rooms_available > 0 ORDER BY name");
    
    // If accommodation_id is specified, get its details
    $selectedAccommodation = null;
    if ($accommodationId > 0) {
        $selectedAccommodation = getAccommodation($conn, $accommodationId);
        
        if (!$selectedAccommodation || $selectedAccommodation['rooms_available'] <= 0) {
            setFlashMessage('Selected accommodation not found or has no available rooms', 'danger');
            redirect('applications.php?action=add');
        }
    }
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">My Applications</h2>
        <div>
            <?php if ($action === 'list'): ?>
                <a href="?action=add" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i>New Application
                </a>
                <a href="dashboard.php" class="btn btn-outline-primary ms-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            <?php else: ?>
                <a href="applications.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Applications
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
        <!-- Applications List -->
        <?php if (empty($applications)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>You have not submitted any applications yet.
                <a href="?action=add" class="alert-link">Submit your first application</a>.
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Accommodation</th>
                                    <th>Move-in Date</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $app): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo $app['accommodation_name']; ?></div>
                                            <small class="text-muted"><?php echo $app['address']; ?></small>
                                        </td>
                                        <td><?php echo formatDate($app['move_in_date']); ?></td>
                                        <td><?php echo formatDate($app['created_at']); ?></td>
                                        <td>
                                            <?php
                                            switch ($app['status']) {
                                                case STATUS_PENDING:
                                                    echo '<span class="badge bg-warning text-dark">Pending</span>';
                                                    break;
                                                case STATUS_APPROVED:
                                                    echo '<span class="badge bg-success">Approved</span>';
                                                    break;
                                                case STATUS_REJECTED:
                                                    echo '<span class="badge bg-danger">Rejected</span>';
                                                    break;
                                                default:
                                                    echo '<span class="badge bg-secondary">' . ucfirst($app['status']) . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="?action=view&id=<?php echo $app['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i>
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
        
    <?php elseif ($action === 'add'): ?>
        <!-- New Application Form -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>New Application</h4>
            </div>
            <div class="card-body">
                <?php if (empty($availableAccommodations)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>There are no accommodations with available rooms at the moment.
                        Please check back later or contact administration for assistance.
                    </div>
                <?php else: ?>
                    <form method="post" action="" id="applicationForm" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="accommodation_id" class="form-label">Select Accommodation</label>
                            <select class="form-select" id="accommodation_id" name="accommodation_id" required>
                                <option value="">-- Select Accommodation --</option>
                                <?php foreach ($availableAccommodations as $acc): ?>
                                    <option value="<?php echo $acc['id']; ?>" <?php echo ($selectedAccommodation && $selectedAccommodation['id'] == $acc['id']) ? 'selected' : ''; ?>>
                                        <?php echo $acc['name']; ?> - <?php echo formatCurrency($acc['price']); ?>/month
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">
                                Please select an accommodation.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="move_in_date" class="form-label">Desired Move-in Date</label>
                            <input type="date" class="form-control" id="move_in_date" name="move_in_date" min="<?php echo date('Y-m-d'); ?>" required>
                            <div class="invalid-feedback">
                                Please select a valid future date.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="additional_info" class="form-label">Additional Information (Optional)</label>
                            <textarea class="form-control" id="additional_info" name="additional_info" rows="4" placeholder="Any special requests or information you'd like to provide"></textarea>
                        </div>
                        
                        <!-- Preview of selected accommodation -->
                        <div id="accommodationPreview" class="mb-3" style="display: none;">
                            <label class="form-label">Selected Accommodation</label>
                            <div class="card">
                                <div class="row g-0">
                                    <div class="col-md-4" id="previewImage">
                                        <!-- Image will be inserted here via JavaScript -->
                                    </div>
                                    <div class="col-md-8">
                                        <div class="card-body">
                                            <h5 class="card-title" id="previewName"></h5>
                                            <p class="card-text" id="previewAddress"></p>
                                            <p class="card-text" id="previewPrice"></p>
                                            <p class="card-text" id="previewDescription"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            By submitting this application, you acknowledge that:
                            <ul class="mb-0 mt-2">
                                <li>Your application will be reviewed by the accommodation administrator.</li>
                                <li>Application approval is subject to room availability and admin discretion.</li>
                                <li>If approved, you will be notified and required to sign a lease agreement.</li>
                            </ul>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" name="submit_application" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Submit Application
                            </button>
                            <a href="applications.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
    <?php elseif ($action === 'view' && $application): ?>
        <!-- View Application Details -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h4 class="mb-0">
                    <i class="fas fa-file-alt me-2"></i>Application Details
                </h4>
                <div>
                    <?php if ($application['status'] === STATUS_PENDING): ?>
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cancelApplicationModal">
                            <i class="fas fa-times me-2"></i>Cancel Application
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Application Status</h5>
                        <div class="mb-4">
                            <?php
                            switch ($application['status']) {
                                case STATUS_PENDING:
                                    echo '<div class="alert alert-warning">
                                        <i class="fas fa-clock me-2"></i>
                                        <strong>Pending:</strong> Your application is under review. We\'ll notify you once it\'s processed.
                                    </div>';
                                    break;
                                case STATUS_APPROVED:
                                    echo '<div class="alert alert-success">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <strong>Approved:</strong> Congratulations! Your application has been approved.
                                    </div>';
                                    break;
                                case STATUS_REJECTED:
                                    echo '<div class="alert alert-danger">
                                        <i class="fas fa-times-circle me-2"></i>
                                        <strong>Rejected:</strong> We\'re sorry, but your application was not approved.
                                    </div>';
                                    break;
                                default:
                                    echo '<div class="alert alert-secondary">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>' . ucfirst($application['status']) . ':</strong> Your application status has been updated.
                                    </div>';
                            }
                            ?>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Application Details</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Application ID:</strong> #<?php echo $application['id']; ?></p>
                                <p><strong>Submitted:</strong> <?php echo formatDate($application['created_at']); ?></p>
                                <p><strong>Desired Move-in Date:</strong> <?php echo formatDate($application['move_in_date']); ?></p>
                                <?php if ($application['status'] !== STATUS_PENDING): ?>
                                    <p><strong>Status Updated:</strong> <?php echo formatDate($application['updated_at']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($application['additional_info'])): ?>
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Additional Information</h6>
                                </div>
                                <div class="card-body">
                                    <p><?php echo nl2br($application['additional_info']); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($application['status'] === STATUS_APPROVED): ?>
                            <div class="card mb-4 border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">Next Steps</h6>
                                </div>
                                <div class="card-body">
                                    <p>Your application has been approved! Here's what's next:</p>
                                    <ol>
                                        <li>Review your lease agreement</li>
                                        <li>Sign the lease digitally</li>
                                        <li>Pay any required deposits</li>
                                        <li>Prepare for your move-in date</li>
                                    </ol>
                                    <a href="leases.php" class="btn btn-success">
                                        <i class="fas fa-file-contract me-2"></i>View Lease Agreement
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <h5>Accommodation Details</h5>
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><?php echo $application['accommodation_name']; ?></h6>
                            </div>
                            <?php if (!empty($application['image_path'])): ?>
                                <img src="../uploads/accommodations/<?php echo $application['image_path']; ?>" class="card-img-top" alt="<?php echo $application['accommodation_name']; ?>">
                            <?php endif; ?>
                            <div class="card-body">
                                <p><i class="fas fa-map-marker-alt me-2"></i><?php echo $application['address']; ?></p>
                                <p><i class="fas fa-money-bill-wave me-2"></i><?php echo formatCurrency($application['price']); ?> per month</p>
                                <p><?php echo $application['description']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Cancel Application Modal -->
        <?php if ($application['status'] === STATUS_PENDING): ?>
            <div class="modal fade" id="cancelApplicationModal" tabindex="-1" aria-labelledby="cancelApplicationModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="cancelApplicationModalLabel">Cancel Application</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to cancel your application for <strong><?php echo $application['accommodation_name']; ?></strong>?</p>
                            <p>This action cannot be undone. If you wish to apply again later, you will need to submit a new application.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Application</button>
                            <form method="post" action="">
                                <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                                <button type="submit" name="cancel_application" class="btn btn-danger">Cancel Application</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script src="../js/application.js"></script>

<?php require_once '../includes/footer.php'; ?>
