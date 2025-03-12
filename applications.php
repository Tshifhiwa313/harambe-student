<?php
$page_title = 'Applications';
require_once 'include/config.php';
require_once 'include/db.php';
require_once 'include/functions.php';
require_once 'include/auth.php';
require_once 'include/email_functions.php';
require_once 'include/sms_functions.php';

// Require login for this page
require_login();

// Check if viewing a specific application
$application_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$application = null;

if ($application_id > 0) {
    $application = get_application($application_id);
    
    // Verify access to this application
    if ($application) {
        $accommodation = get_accommodation($application['accommodation_id']);
        
        // Check access rights
        $has_access = false;
        if (is_master_admin()) {
            $has_access = true;
        } elseif (is_admin() && $accommodation && $accommodation['admin_id'] == get_current_user_id()) {
            $has_access = true;
        } elseif (is_student() && $application['student_id'] == get_current_user_id()) {
            $has_access = true;
        }
        
        if (!$has_access) {
            set_flash_message('error', 'You do not have permission to view this application.');
            redirect('applications.php');
        }
    } else {
        set_flash_message('error', 'Application not found.');
        redirect('applications.php');
    }
}

// Handle application actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_application']) && is_admin()) {
        $application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
        $application = get_application($application_id);
        
        if ($application) {
            $accommodation = get_accommodation($application['accommodation_id']);
            
            // Check if admin has access to this accommodation
            $has_access = is_master_admin() || ($accommodation && $accommodation['admin_id'] == get_current_user_id());
            
            if ($has_access) {
                // Update application status
                $query = "UPDATE applications SET status = 'approved', notes = ?, updated_at = datetime('now') WHERE id = ?";
                $notes = isset($_POST['notes']) ? sanitize_input($_POST['notes']) : '';
                db_query($query, [$notes, $application_id]);
                
                // Decrease available units
                if ($accommodation['available_units'] > 0) {
                    $query = "UPDATE accommodations SET available_units = available_units - 1 WHERE id = ? AND available_units > 0";
                    db_query($query, [$accommodation['id']]);
                }
                
                // Create notification for student
                create_notification(
                    $application['student_id'],
                    'Application Approved',
                    'Your application for ' . $accommodation['name'] . ' has been approved!',
                    'success'
                );
                
                // Send email notification
                send_application_status_email($application_id, 'approved');
                
                // Send SMS notification if enabled
                if (TWILIO_ENABLED) {
                    send_application_status_sms($application_id, 'approved');
                }
                
                // Set success message
                set_flash_message('success', 'Application approved successfully.');
                redirect('applications.php?id=' . $application_id);
            } else {
                set_flash_message('error', 'You do not have permission to approve this application.');
                redirect('applications.php');
            }
        } else {
            set_flash_message('error', 'Application not found.');
            redirect('applications.php');
        }
    } elseif (isset($_POST['reject_application']) && is_admin()) {
        $application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
        $application = get_application($application_id);
        
        if ($application) {
            $accommodation = get_accommodation($application['accommodation_id']);
            
            // Check if admin has access to this accommodation
            $has_access = is_master_admin() || ($accommodation && $accommodation['admin_id'] == get_current_user_id());
            
            if ($has_access) {
                // Update application status
                $query = "UPDATE applications SET status = 'rejected', notes = ?, updated_at = datetime('now') WHERE id = ?";
                $notes = isset($_POST['notes']) ? sanitize_input($_POST['notes']) : '';
                db_query($query, [$notes, $application_id]);
                
                // Create notification for student
                create_notification(
                    $application['student_id'],
                    'Application Rejected',
                    'Your application for ' . $accommodation['name'] . ' has been rejected.',
                    'error'
                );
                
                // Send email notification
                send_application_status_email($application_id, 'rejected');
                
                // Send SMS notification if enabled
                if (TWILIO_ENABLED) {
                    send_application_status_sms($application_id, 'rejected');
                }
                
                // Set success message
                set_flash_message('success', 'Application rejected successfully.');
                redirect('applications.php?id=' . $application_id);
            } else {
                set_flash_message('error', 'You do not have permission to reject this application.');
                redirect('applications.php');
            }
        } else {
            set_flash_message('error', 'Application not found.');
            redirect('applications.php');
        }
    } elseif (isset($_POST['create_lease']) && is_admin()) {
        $application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
        $application = get_application($application_id);
        
        if ($application && $application['status'] === 'approved') {
            $accommodation = get_accommodation($application['accommodation_id']);
            
            // Check if admin has access to this accommodation
            $has_access = is_master_admin() || ($accommodation && $accommodation['admin_id'] == get_current_user_id());
            
            if ($has_access) {
                // Get lease details from form
                $start_date = isset($_POST['start_date']) ? sanitize_input($_POST['start_date']) : '';
                $end_date = isset($_POST['end_date']) ? sanitize_input($_POST['end_date']) : '';
                $monthly_rent = isset($_POST['monthly_rent']) ? (float)$_POST['monthly_rent'] : $accommodation['price_per_month'];
                
                // Validate dates
                if (empty($start_date) || empty($end_date) || strtotime($start_date) >= strtotime($end_date)) {
                    set_flash_message('error', 'Invalid lease dates. End date must be after start date.');
                    redirect('applications.php?id=' . $application_id);
                }
                
                // Create lease
                $query = "INSERT INTO leases (student_id, accommodation_id, start_date, end_date, monthly_rent, status, created_at) 
                          VALUES (?, ?, ?, ?, ?, 'pending', datetime('now'))";
                db_query($query, [
                    $application['student_id'], $application['accommodation_id'], 
                    $start_date, $end_date, $monthly_rent
                ]);
                
                $lease_id = db_last_insert_id();
                
                // Create notification for student
                create_notification(
                    $application['student_id'],
                    'Lease Agreement Ready',
                    'Your lease agreement for ' . $accommodation['name'] . ' is ready for signature.',
                    'info'
                );
                
                // Send email notification
                send_lease_notification_email($lease_id);
                
                // Send SMS notification if enabled
                if (TWILIO_ENABLED) {
                    send_lease_notification_sms($lease_id);
                }
                
                // Set success message
                set_flash_message('success', 'Lease created successfully.');
                redirect('leases.php?id=' . $lease_id);
            } else {
                set_flash_message('error', 'You do not have permission to create a lease for this application.');
                redirect('applications.php');
            }
        } else {
            set_flash_message('error', 'Invalid application or application not approved.');
            redirect('applications.php');
        }
    }
}

// Get applications based on user role
$applications = [];
if (is_master_admin()) {
    $applications = get_all_applications();
} elseif (is_admin()) {
    // Get admin's accommodations
    $admin_accommodations = get_admin_accommodations(get_current_user_id());
    $applications = [];
    
    foreach ($admin_accommodations as $accommodation) {
        $accommodation_applications = get_accommodation_applications($accommodation['id']);
        $applications = array_merge($applications, $accommodation_applications);
    }
} elseif (is_student()) {
    $applications = get_student_applications(get_current_user_id());
}

// Include header
include 'include/header.php';

// Include navbar
include 'include/navbar.php';
?>

<div class="container mt-4">
    <?php if ($application): ?>
        <!-- Single Application View -->
        <div class="row mb-4">
            <div class="col-md-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="applications.php">Applications</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Application #<?php echo $application['id']; ?></li>
                    </ol>
                </nav>
                <h1 class="mb-3">Application #<?php echo $application['id']; ?></h1>
            </div>
        </div>
        
        <?php 
        // Get additional details
        $student = get_user($application['student_id']);
        $accommodation = get_accommodation($application['accommodation_id']);
        ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Application Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Status</h6>
                                <span class="badge <?php echo get_badge_class($application['status'], 'application'); ?> fs-6">
                                    <?php echo format_application_status($application['status']); ?>
                                </span>
                            </div>
                            <div class="col-md-6">
                                <h6>Application Date</h6>
                                <p><?php echo format_date($application['created_at']); ?></p>
                            </div>
                        </div>
                        
                        <h6>Accommodation</h6>
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h5><?php echo $accommodation['name']; ?></h5>
                                        <p class="text-muted mb-2">
                                            <i class="fas fa-map-marker-alt me-2"></i><?php echo $accommodation['location']; ?>
                                        </p>
                                        <p class="mb-2">
                                            <span class="badge bg-primary"><?php echo format_currency($accommodation['price_per_month']); ?> /month</span>
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <a href="accommodations.php?id=<?php echo $accommodation['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-building me-2"></i>View Accommodation
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (is_admin()): ?>
                            <h6>Student Information</h6>
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Name:</strong> <?php echo $student['full_name']; ?></p>
                                            <p><strong>Email:</strong> <?php echo $student['email']; ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Phone:</strong> <?php echo $student['phone_number'] ?: 'Not provided'; ?></p>
                                            <p><strong>Username:</strong> <?php echo $student['username']; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($application['notes'])): ?>
                            <h6>Notes</h6>
                            <div class="card mb-4">
                                <div class="card-body">
                                    <p><?php echo nl2br($application['notes']); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($application['status'] === 'approved' && is_admin()): ?>
                            <div class="mt-4">
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createLeaseModal">
                                    <i class="fas fa-file-contract me-2"></i>Create Lease Agreement
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <?php if (is_admin() && $application['status'] === 'pending'): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">Application Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-3">
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
                                    <i class="fas fa-check-circle me-2"></i>Approve Application
                                </button>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    <i class="fas fa-times-circle me-2"></i>Reject Application
                                </button>
                            </div>
                        </div>
                    </div>
                <?php elseif ($application['status'] === 'approved'): ?>
                    <div class="card mb-4 border-success">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">Application Approved</h5>
                        </div>
                        <div class="card-body">
                            <p>Your application has been approved! The next step is to sign your lease agreement.</p>
                            <p>Once you have signed your lease agreement, you'll receive invoices for payment.</p>
                            <a href="leases.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-file-contract me-2"></i>View Lease Agreements
                            </a>
                        </div>
                    </div>
                <?php elseif ($application['status'] === 'rejected'): ?>
                    <div class="card mb-4 border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5 class="card-title mb-0">Application Rejected</h5>
                        </div>
                        <div class="card-body">
                            <p>Unfortunately, your application has been rejected.</p>
                            <?php if (!empty($application['notes'])): ?>
                                <p><strong>Reason:</strong> <?php echo $application['notes']; ?></p>
                            <?php endif; ?>
                            <p>You can apply for other available accommodations.</p>
                            <a href="accommodations.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-building me-2"></i>Browse Other Accommodations
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Timeline</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item px-0">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <span class="bg-success rounded-circle d-inline-block" style="width: 10px; height: 10px;"></span>
                                    </div>
                                    <div class="ms-3">
                                        <h6 class="mb-1">Application Submitted</h6>
                                        <p class="text-muted small mb-0"><?php echo format_date($application['created_at']); ?></p>
                                    </div>
                                </div>
                            </li>
                            
                            <?php if ($application['status'] !== 'pending'): ?>
                                <li class="list-group-item px-0">
                                    <div class="d-flex">
                                        <div class="flex-shrink-0">
                                            <span class="<?php echo $application['status'] === 'approved' ? 'bg-success' : 'bg-danger'; ?> rounded-circle d-inline-block" style="width: 10px; height: 10px;"></span>
                                        </div>
                                        <div class="ms-3">
                                            <h6 class="mb-1">Application <?php echo ucfirst($application['status']); ?></h6>
                                            <p class="text-muted small mb-0"><?php echo format_date($application['updated_at']); ?></p>
                                        </div>
                                    </div>
                                </li>
                            <?php endif; ?>
                            
                            <?php if ($application['status'] === 'approved'): ?>
                                <?php 
                                // Check if lease exists for this application
                                $query = "SELECT * FROM leases WHERE student_id = ? AND accommodation_id = ? ORDER BY created_at DESC LIMIT 1";
                                $lease = db_fetch($query, [$application['student_id'], $application['accommodation_id']]);
                                
                                if ($lease):
                                ?>
                                    <li class="list-group-item px-0">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0">
                                                <span class="bg-info rounded-circle d-inline-block" style="width: 10px; height: 10px;"></span>
                                            </div>
                                            <div class="ms-3">
                                                <h6 class="mb-1">Lease Agreement Created</h6>
                                                <p class="text-muted small mb-0"><?php echo format_date($lease['created_at']); ?></p>
                                                <p class="small mb-0">
                                                    <a href="leases.php?id=<?php echo $lease['id']; ?>">View Lease</a>
                                                </p>
                                            </div>
                                        </div>
                                    </li>
                                    
                                    <?php if ($lease['status'] !== 'draft' && $lease['status'] !== 'pending'): ?>
                                        <li class="list-group-item px-0">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0">
                                                    <span class="bg-success rounded-circle d-inline-block" style="width: 10px; height: 10px;"></span>
                                                </div>
                                                <div class="ms-3">
                                                    <h6 class="mb-1">Lease Agreement Signed</h6>
                                                    <p class="text-muted small mb-0"><?php echo format_date($lease['signed_at']); ?></p>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (is_admin() && $application['status'] === 'pending'): ?>
            <!-- Approve Application Modal -->
            <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="approveModalLabel">Approve Application</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post" action="applications.php">
                            <div class="modal-body">
                                <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                                
                                <p>Are you sure you want to approve this application for <?php echo $student['full_name']; ?>?</p>
                                
                                <div class="mb-3">
                                    <label for="approve_notes" class="form-label">Notes (Optional)</label>
                                    <textarea class="form-control" id="approve_notes" name="notes" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="approve_application" class="btn btn-success">
                                    <i class="fas fa-check-circle me-2"></i>Approve Application
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Reject Application Modal -->
            <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="rejectModalLabel">Reject Application</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post" action="applications.php">
                            <div class="modal-body">
                                <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                                
                                <p>Are you sure you want to reject this application for <?php echo $student['full_name']; ?>?</p>
                                
                                <div class="mb-3">
                                    <label for="reject_notes" class="form-label">Reason for Rejection</label>
                                    <textarea class="form-control" id="reject_notes" name="notes" rows="3" required></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="reject_application" class="btn btn-danger">
                                    <i class="fas fa-times-circle me-2"></i>Reject Application
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($application['status'] === 'approved' && is_admin()): ?>
            <!-- Create Lease Modal -->
            <div class="modal fade" id="createLeaseModal" tabindex="-1" aria-labelledby="createLeaseModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="createLeaseModalLabel">Create Lease Agreement</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="post" action="applications.php">
                            <div class="modal-body">
                                <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                                
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="monthly_rent" class="form-label">Monthly Rent (R)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="monthly_rent" name="monthly_rent" value="<?php echo $accommodation['price_per_month']; ?>" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="create_lease" class="btn btn-primary">
                                    <i class="fas fa-file-contract me-2"></i>Create Lease
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- Applications List View -->
        <div class="row mb-4">
            <div class="col">
                <h1 class="mb-3"><?php echo $page_title; ?></h1>
                <?php if (is_student()): ?>
                    <p class="lead">Manage your accommodation applications and track their status.</p>
                <?php else: ?>
                    <p class="lead">Review and manage student accommodation applications.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (empty($applications)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <?php if (is_student()): ?>
                    You haven't submitted any applications yet. <a href="accommodations.php">Browse available accommodations</a> to apply.
                <?php else: ?>
                    No applications found.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Filter/Search Options -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="input-group">
                                <input type="text" class="form-control" id="searchApplications" placeholder="Search applications...">
                                <button class="btn btn-outline-secondary" type="button">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" id="filterStatus">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Applications List -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Application List</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <?php if (is_admin()): ?>
                                    <th>Student</th>
                                <?php endif; ?>
                                <th>Accommodation</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td>#<?php echo $app['id']; ?></td>
                                    <?php if (is_admin()): ?>
                                        <td><?php echo $app['full_name']; ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if (is_student()): ?>
                                            <?php echo $app['accommodation_name']; ?>
                                        <?php else: ?>
                                            <a href="accommodations.php?id=<?php echo $app['accommodation_id']; ?>">
                                                <?php echo isset($app['accommodation_name']) ? $app['accommodation_name'] : 'Accommodation #' . $app['accommodation_id']; ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo format_date($app['created_at']); ?></td>
                                    <td>
                                        <span class="badge <?php echo get_badge_class($app['status'], 'application'); ?>">
                                            <?php echo format_application_status($app['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="applications.php?id=<?php echo $app['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// Include footer
include 'include/footer.php';
?>
