<?php
/**
 * Admin Leases Management
 * 
 * This page allows administrators to view, create, and manage lease agreements.
 */

require_once '../includes/header.php';
require_once '../includes/pdf_generator.php';

// Require admin authentication
requireAuth([ROLE_MASTER_ADMIN, ROLE_ADMIN]);

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

// Define action based on GET parameter
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$leaseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$applicationId = isset($_GET['application_id']) ? (int)$_GET['application_id'] : 0;

// Handle lease actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new lease
    if (isset($_POST['create_lease'])) {
        $studentId = (int)$_POST['student_id'];
        $accommodationId = (int)$_POST['accommodation_id'];
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $monthlyRent = (float)$_POST['monthly_rent'];
        $securityDeposit = (float)$_POST['security_deposit'];
        
        // Validation
        $errors = [];
        
        if (empty($startDate) || !isValidDate($startDate)) {
            $errors[] = 'Please enter a valid start date';
        }
        
        if (empty($endDate) || !isValidDate($endDate)) {
            $errors[] = 'Please enter a valid end date';
        }
        
        if (strtotime($endDate) <= strtotime($startDate)) {
            $errors[] = 'End date must be after start date';
        }
        
        if ($monthlyRent <= 0) {
            $errors[] = 'Monthly rent must be greater than zero';
        }
        
        if ($securityDeposit < 0) {
            $errors[] = 'Security deposit cannot be negative';
        }
        
        // Check if student exists
        $student = getUser($conn, $studentId);
        if (!$student || $student['role'] !== ROLE_STUDENT) {
            $errors[] = 'Invalid student selected';
        }
        
        // Check if accommodation exists
        $accommodation = getAccommodation($conn, $accommodationId);
        if (!$accommodation) {
            $errors[] = 'Invalid accommodation selected';
        }
        
        // Check permission for regular admin
        if ($userRole !== ROLE_MASTER_ADMIN && !isAdminAssignedToAccommodation($conn, $userId, $accommodationId)) {
            $errors[] = 'You do not have permission to create a lease for this accommodation';
        }
        
        // Check if rooms are available
        if (!hasAvailableRooms($conn, $accommodationId)) {
            $errors[] = 'No rooms available in this accommodation';
        }
        
        // Create lease if no errors
        if (empty($errors)) {
            $leaseData = [
                'user_id' => $studentId,
                'accommodation_id' => $accommodationId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'monthly_rent' => $monthlyRent,
                'security_deposit' => $securityDeposit,
                'signed' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $newLeaseId = insertRow($conn, 'leases', $leaseData);
            
            if ($newLeaseId) {
                // Generate lease PDF
                $pdfPath = generateLeasePDF($conn, $newLeaseId);
                
                if ($pdfPath) {
                    // Update lease with PDF path
                    updateRow($conn, 'leases', ['pdf_path' => $pdfPath], 'id', $newLeaseId);
                    
                    // If lease was created from an application, update the application status
                    if (isset($_POST['application_id']) && $_POST['application_id'] > 0) {
                        $applicationId = (int)$_POST['application_id'];
                        updateRow($conn, 'applications', ['status' => STATUS_APPROVED], 'id', $applicationId);
                    }
                    
                    // Create notification for student
                    $notificationData = [
                        'user_id' => $studentId,
                        'message' => "Your lease for {$accommodation['name']} has been created. Please sign it to complete the process.",
                        'type' => 'lease',
                        'is_read' => 0
                    ];
                    
                    insertRow($conn, 'notifications', $notificationData);
                    
                    setFlashMessage('Lease created successfully!', 'success');
                    redirect("leases.php?action=view&id=$newLeaseId");
                } else {
                    $errors[] = 'Failed to generate lease PDF';
                }
            } else {
                $errors[] = 'Failed to create lease';
            }
        }
    }
    
    // Generate invoice for lease
    if (isset($_POST['generate_invoice'])) {
        $leaseId = (int)$_POST['lease_id'];
        $amount = (float)$_POST['amount'];
        $dueDate = $_POST['due_date'];
        
        // Validation
        $errors = [];
        
        if (empty($dueDate) || !isValidDate($dueDate)) {
            $errors[] = 'Please enter a valid due date';
        }
        
        if ($amount <= 0) {
            $errors[] = 'Amount must be greater than zero';
        }
        
        // Get lease details
        $lease = getLease($conn, $leaseId);
        
        if (!$lease) {
            $errors[] = 'Lease not found';
        } else {
            // Check permission for regular admin
            if ($userRole !== ROLE_MASTER_ADMIN && !isAdminAssignedToAccommodation($conn, $userId, $lease['accommodation_id'])) {
                $errors[] = 'You do not have permission to generate an invoice for this lease';
            }
        }
        
        // Create invoice if no errors
        if (empty($errors)) {
            $invoiceData = [
                'lease_id' => $leaseId,
                'amount' => $amount,
                'due_date' => $dueDate,
                'status' => INVOICE_UNPAID,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $newInvoiceId = insertRow($conn, 'invoices', $invoiceData);
            
            if ($newInvoiceId) {
                // Generate invoice PDF
                $pdfPath = generateInvoicePDF($conn, $newInvoiceId);
                
                if ($pdfPath) {
                    // Update invoice with PDF path
                    updateRow($conn, 'invoices', ['pdf_path' => $pdfPath], 'id', $newInvoiceId);
                    
                    // Get student details for notification
                    $student = getUser($conn, $lease['user_id']);
                    $accommodation = getAccommodation($conn, $lease['accommodation_id']);
                    
                    // Create notification for student
                    $notificationData = [
                        'user_id' => $lease['user_id'],
                        'message' => "A new invoice for {$accommodation['name']} has been generated. Due date: " . formatDate($dueDate),
                        'type' => 'invoice',
                        'is_read' => 0
                    ];
                    
                    insertRow($conn, 'notifications', $notificationData);
                    
                    // Send email notification
                    if ($student) {
                        $subject = "New Invoice for " . $accommodation['name'];
                        $message = "Hello {$student['first_name']},<br><br>";
                        $message .= "A new invoice has been generated for your accommodation at {$accommodation['name']}.<br>";
                        $message .= "Amount Due: " . formatCurrency($amount) . "<br>";
                        $message .= "Due Date: " . formatDate($dueDate) . "<br><br>";
                        $message .= "Please log in to your account to view and pay the invoice.<br><br>";
                        $message .= "Regards,<br>" . APP_NAME;
                        
                        sendEmail($student['email'], $subject, $message);
                        
                        // Send SMS if phone number available
                        if (!empty($student['phone'])) {
                            $smsMessage = "New invoice for " . $accommodation['name'] . ". Amount: " . formatCurrency($amount) . ", Due: " . formatDate($dueDate);
                            sendTwilioSMS($student['phone'], $smsMessage);
                        }
                    }
                    
                    setFlashMessage('Invoice generated successfully!', 'success');
                    redirect("leases.php?action=view&id=$leaseId");
                } else {
                    $errors[] = 'Failed to generate invoice PDF';
                }
            } else {
                $errors[] = 'Failed to create invoice';
            }
        }
    }
    
    // Terminate lease
    if (isset($_POST['terminate_lease'])) {
        $leaseId = (int)$_POST['lease_id'];
        $terminationDate = $_POST['termination_date'];
        
        // Validation
        $errors = [];
        
        if (empty($terminationDate) || !isValidDate($terminationDate)) {
            $errors[] = 'Please enter a valid termination date';
        }
        
        // Get lease details
        $lease = getLease($conn, $leaseId);
        
        if (!$lease) {
            $errors[] = 'Lease not found';
        } else {
            // Check permission for regular admin
            if ($userRole !== ROLE_MASTER_ADMIN && !isAdminAssignedToAccommodation($conn, $userId, $lease['accommodation_id'])) {
                $errors[] = 'You do not have permission to terminate this lease';
            }
            
            // Check if termination date is valid
            if (strtotime($terminationDate) <= strtotime($lease['start_date']) || strtotime($terminationDate) >= strtotime($lease['end_date'])) {
                $errors[] = 'Termination date must be between start date and end date';
            }
        }
        
        // Terminate lease if no errors
        if (empty($errors)) {
            $leaseData = [
                'end_date' => $terminationDate,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (updateRow($conn, 'leases', $leaseData, 'id', $leaseId)) {
                // Get student details for notification
                $student = getUser($conn, $lease['user_id']);
                $accommodation = getAccommodation($conn, $lease['accommodation_id']);
                
                // Create notification for student
                $notificationData = [
                    'user_id' => $lease['user_id'],
                    'message' => "Your lease for {$accommodation['name']} has been terminated early. New end date: " . formatDate($terminationDate),
                    'type' => 'lease',
                    'is_read' => 0
                ];
                
                insertRow($conn, 'notifications', $notificationData);
                
                // Send email notification
                if ($student) {
                    $subject = "Lease Termination Notice for " . $accommodation['name'];
                    $message = "Hello {$student['first_name']},<br><br>";
                    $message .= "Your lease for {$accommodation['name']} has been terminated early.<br>";
                    $message .= "New End Date: " . formatDate($terminationDate) . "<br><br>";
                    $message .= "Please contact the administration if you have any questions.<br><br>";
                    $message .= "Regards,<br>" . APP_NAME;
                    
                    sendEmail($student['email'], $subject, $message);
                    
                    // Send SMS if phone number available
                    if (!empty($student['phone'])) {
                        $smsMessage = "Your lease for " . $accommodation['name'] . " has been terminated. New end date: " . formatDate($terminationDate);
                        sendTwilioSMS($student['phone'], $smsMessage);
                    }
                }
                
                setFlashMessage('Lease terminated successfully!', 'success');
                redirect('leases.php');
            } else {
                $errors[] = 'Failed to terminate lease';
            }
        }
    }
}

// Get application details if creating lease from application
$application = null;
if ($action === 'create' && $applicationId > 0) {
    $application = fetchRow($conn, 
        "SELECT a.*, u.first_name, u.last_name, ac.name as accommodation_name, ac.price 
        FROM applications a 
        JOIN users u ON a.user_id = u.id 
        JOIN accommodations ac ON a.accommodation_id = ac.id 
        WHERE a.id = :id", 
        ['id' => $applicationId]
    );
    
    if (!$application) {
        setFlashMessage('Application not found', 'danger');
        redirect('applications.php');
    }
    
    // Check permission for regular admin
    if ($userRole !== ROLE_MASTER_ADMIN && !isAdminAssignedToAccommodation($conn, $userId, $application['accommodation_id'])) {
        setFlashMessage('You do not have permission to create a lease for this application', 'danger');
        redirect('applications.php');
    }
    
    // Check if application is approved
    if ($application['status'] !== STATUS_APPROVED) {
        setFlashMessage('Cannot create lease for an application that is not approved', 'danger');
        redirect('applications.php');
    }
}

// Get lease details if viewing or editing
$lease = null;
if (($action === 'view' || $action === 'edit') && $leaseId > 0) {
    $lease = fetchRow($conn, 
        "SELECT l.*, u.first_name, u.last_name, u.email, u.phone, 
        a.name as accommodation_name, a.address 
        FROM leases l 
        JOIN users u ON l.user_id = u.id 
        JOIN accommodations a ON l.accommodation_id = a.id 
        WHERE l.id = :id", 
        ['id' => $leaseId]
    );
    
    if (!$lease) {
        setFlashMessage('Lease not found', 'danger');
        redirect('leases.php');
    }
    
    // Check permission for regular admin
    if ($userRole !== ROLE_MASTER_ADMIN && !isAdminAssignedToAccommodation($conn, $userId, $lease['accommodation_id'])) {
        setFlashMessage('You do not have permission to view this lease', 'danger');
        redirect('leases.php');
    }
    
    // Get invoices for this lease
    $invoices = fetchAll($conn, 
        "SELECT * FROM invoices WHERE lease_id = :lease_id ORDER BY due_date DESC", 
        ['lease_id' => $leaseId]
    );
}

// Get leases based on user role
if ($userRole === ROLE_MASTER_ADMIN) {
    // Master admin sees all leases
    $leases = fetchAll($conn, 
        "SELECT l.*, u.first_name, u.last_name, a.name as accommodation_name 
        FROM leases l 
        JOIN users u ON l.user_id = u.id 
        JOIN accommodations a ON l.accommodation_id = a.id 
        ORDER BY l.created_at DESC"
    );
} else {
    // Regular admin sees leases for assigned accommodations
    $adminAccommodations = getAdminAccommodations($conn, $userId);
    
    if (empty($adminAccommodations)) {
        $leases = [];
    } else {
        $accommodationIds = array_map(function($acc) { return $acc['id']; }, $adminAccommodations);
        $placeholders = implode(',', array_fill(0, count($accommodationIds), '?'));
        
        $stmt = $conn->prepare(
            "SELECT l.*, u.first_name, u.last_name, a.name as accommodation_name 
            FROM leases l 
            JOIN users u ON l.user_id = u.id 
            JOIN accommodations a ON l.accommodation_id = a.id 
            WHERE l.accommodation_id IN ($placeholders) 
            ORDER BY l.created_at DESC"
        );
        
        $stmt->execute($accommodationIds);
        $leases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get available accommodations and students for new lease form
$accommodations = [];
$students = [];

if ($action === 'create' && !$application) {
    if ($userRole === ROLE_MASTER_ADMIN) {
        $accommodations = fetchAll($conn, "SELECT * FROM accommodations WHERE rooms_available > 0 ORDER BY name");
    } else {
        $adminAccommodations = getAdminAccommodations($conn, $userId);
        $accommodations = array_filter($adminAccommodations, function($acc) {
            return $acc['rooms_available'] > 0;
        });
    }
    
    $students = fetchAll($conn, "SELECT id, username, first_name, last_name, email FROM users WHERE role = :role ORDER BY first_name, last_name", ['role' => ROLE_STUDENT]);
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Manage Leases</h2>
        <div>
            <?php if ($action === 'list'): ?>
                <a href="?action=create" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i>Create Lease
                </a>
                <a href="dashboard.php" class="btn btn-outline-primary ms-2">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            <?php else: ?>
                <a href="leases.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Leases
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
        <!-- Leases List -->
        <?php if (empty($leases)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>No leases found.
                <?php if ($userRole === ROLE_ADMIN && empty($adminAccommodations)): ?>
                    Please contact the master administrator to assign accommodations to you.
                <?php else: ?>
                    You can create a new lease by clicking the "Create Lease" button above.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Student</th>
                                    <th>Accommodation</th>
                                    <th>Period</th>
                                    <th>Monthly Rent</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leases as $lease): ?>
                                    <tr>
                                        <td><?php echo $lease['id']; ?></td>
                                        <td><?php echo $lease['first_name'] . ' ' . $lease['last_name']; ?></td>
                                        <td><?php echo $lease['accommodation_name']; ?></td>
                                        <td>
                                            <?php echo formatDate($lease['start_date']); ?> - 
                                            <?php echo formatDate($lease['end_date']); ?>
                                        </td>
                                        <td><?php echo formatCurrency($lease['monthly_rent']); ?></td>
                                        <td>
                                            <?php
                                            $now = time();
                                            $startDate = strtotime($lease['start_date']);
                                            $endDate = strtotime($lease['end_date']);
                                            
                                            if ($now < $startDate) {
                                                echo '<span class="badge bg-info">Upcoming</span>';
                                            } elseif ($now > $endDate) {
                                                echo '<span class="badge bg-secondary">Expired</span>';
                                            } else {
                                                echo '<span class="badge bg-success">Active</span>';
                                            }
                                            
                                            if (!$lease['signed']) {
                                                echo ' <span class="badge bg-warning text-dark">Unsigned</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="?action=view&id=<?php echo $lease['id']; ?>" class="btn btn-sm btn-primary">
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
        
    <?php elseif ($action === 'create'): ?>
        <!-- Create Lease Form -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h4 class="mb-0">
                    <i class="fas fa-file-contract me-2"></i>
                    <?php echo $application ? 'Create Lease from Application' : 'Create New Lease'; ?>
                </h4>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <?php if ($application): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Creating a lease for application #<?php echo $application['id']; ?> from 
                            <strong><?php echo $application['first_name'] . ' ' . $application['last_name']; ?></strong> 
                            for <strong><?php echo $application['accommodation_name']; ?></strong>.
                        </div>
                        
                        <input type="hidden" name="student_id" value="<?php echo $application['user_id']; ?>">
                        <input type="hidden" name="accommodation_id" value="<?php echo $application['accommodation_id']; ?>">
                        <input type="hidden" name="application_id" value="<?php echo $application['id']; ?>">
                    <?php else: ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="student_id" class="form-label">Student</label>
                                <select class="form-select" id="student_id" name="student_id" required>
                                    <option value="">-- Select Student --</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo $student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['email'] . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="accommodation_id" class="form-label">Accommodation</label>
                                <select class="form-select" id="accommodation_id" name="accommodation_id" required>
                                    <option value="">-- Select Accommodation --</option>
                                    <?php foreach ($accommodations as $accommodation): ?>
                                        <option value="<?php echo $accommodation['id']; ?>" data-price="<?php echo $accommodation['price']; ?>">
                                            <?php echo $accommodation['name'] . ' (' . $accommodation['rooms_available'] . ' rooms available)'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                value="<?php echo $application ? $application['move_in_date'] : date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                value="<?php echo date('Y-m-d', strtotime('+12 months')); ?>" required>
                            <div class="form-text">Default lease term is 12 months</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="monthly_rent" class="form-label">Monthly Rent (R)</label>
                            <input type="number" class="form-control" id="monthly_rent" name="monthly_rent" 
                                value="<?php echo $application ? $application['price'] : ''; ?>" min="0" step="0.01" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="security_deposit" class="form-label">Security Deposit (R)</label>
                            <input type="number" class="form-control" id="security_deposit" name="security_deposit" 
                                value="<?php echo $application ? $application['price'] : ''; ?>" min="0" step="0.01" required>
                            <div class="form-text">Typically equal to one month's rent</div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" name="create_lease" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Lease
                        </button>
                        <a href="<?php echo $application ? 'applications.php' : 'leases.php'; ?>" class="btn btn-secondary ms-2">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
    <?php elseif ($action === 'view' && $lease): ?>
        <!-- View Lease Details -->
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0">
                            <i class="fas fa-file-contract me-2"></i>Lease Agreement Details
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Student Information</h5>
                                <p><strong>Name:</strong> <?php echo $lease['first_name'] . ' ' . $lease['last_name']; ?></p>
                                <p><strong>Email:</strong> <?php echo $lease['email']; ?></p>
                                <p><strong>Phone:</strong> <?php echo !empty($lease['phone']) ? $lease['phone'] : 'Not provided'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5>Accommodation</h5>
                                <p><strong>Name:</strong> <?php echo $lease['accommodation_name']; ?></p>
                                <p><strong>Address:</strong> <?php echo $lease['address']; ?></p>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Lease Terms</h5>
                                <p><strong>Start Date:</strong> <?php echo formatDate($lease['start_date']); ?></p>
                                <p><strong>End Date:</strong> <?php echo formatDate($lease['end_date']); ?></p>
                                <p><strong>Duration:</strong> <?php echo dateDiffInDays($lease['start_date'], $lease['end_date']) / 30; ?> months</p>
                            </div>
                            <div class="col-md-6">
                                <h5>Financial Details</h5>
                                <p><strong>Monthly Rent:</strong> <?php echo formatCurrency($lease['monthly_rent']); ?></p>
                                <p><strong>Security Deposit:</strong> <?php echo formatCurrency($lease['security_deposit']); ?></p>
                                <p><strong>Total Lease Value:</strong> <?php echo formatCurrency($lease['monthly_rent'] * (dateDiffInDays($lease['start_date'], $lease['end_date']) / 30)); ?></p>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5>Status</h5>
                                <p>
                                    <strong>Signing Status:</strong>
                                    <?php if ($lease['signed']): ?>
                                        <span class="badge bg-success">Signed</span> on <?php echo formatDate($lease['signed_date']); ?>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Unsigned</span>
                                    <?php endif; ?>
                                </p>
                                <p>
                                    <strong>Lease Status:</strong>
                                    <?php
                                    $now = time();
                                    $startDate = strtotime($lease['start_date']);
                                    $endDate = strtotime($lease['end_date']);
                                    
                                    if ($now < $startDate) {
                                        echo '<span class="badge bg-info">Upcoming</span>';
                                        echo ' (Starts in ' . round(($startDate - $now) / 86400) . ' days)';
                                    } elseif ($now > $endDate) {
                                        echo '<span class="badge bg-secondary">Expired</span>';
                                        echo ' (Ended ' . round(($now - $endDate) / 86400) . ' days ago)';
                                    } else {
                                        echo '<span class="badge bg-success">Active</span>';
                                        echo ' (' . round(($endDate - $now) / 86400) . ' days remaining)';
                                    }
                                    ?>
                                </p>
                                <p><strong>Created:</strong> <?php echo formatDate($lease['created_at']); ?></p>
                                <p><strong>Last Updated:</strong> <?php echo formatDate($lease['updated_at']); ?></p>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <h5>Actions</h5>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if (!empty($lease['pdf_path'])): ?>
                                        <a href="../uploads/leases/<?php echo $lease['pdf_path']; ?>" class="btn btn-primary" target="_blank">
                                            <i class="fas fa-file-pdf me-2"></i>View Lease PDF
                                        </a>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#generateInvoiceModal">
                                        <i class="fas fa-file-invoice-dollar me-2"></i>Generate Invoice
                                    </button>
                                    
                                    <?php if (strtotime($lease['end_date']) > time()): ?>
                                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#terminateLeaseModal">
                                            <i class="fas fa-times-circle me-2"></i>Terminate Lease
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Invoices Section -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0">
                            <i class="fas fa-file-invoice-dollar me-2"></i>Invoices
                        </h4>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($invoices)): ?>
                            <div class="p-4 text-center">
                                <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                                <p class="mb-0">No invoices generated for this lease yet.</p>
                                <p class="mt-2">
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#generateInvoiceModal">
                                        <i class="fas fa-plus-circle me-2"></i>Generate Invoice
                                    </button>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Invoice ID</th>
                                            <th>Amount</th>
                                            <th>Due Date</th>
                                            <th>Status</th>
                                            <th>Generated On</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoices as $invoice): ?>
                                            <tr>
                                                <td>#INV-<?php echo str_pad($invoice['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                                <td><?php echo formatCurrency($invoice['amount']); ?></td>
                                                <td><?php echo formatDate($invoice['due_date']); ?></td>
                                                <td>
                                                    <?php
                                                    switch ($invoice['status']) {
                                                        case INVOICE_PAID:
                                                            echo '<span class="badge bg-success">Paid</span>';
                                                            break;
                                                        case INVOICE_UNPAID:
                                                            if (strtotime($invoice['due_date']) < time()) {
                                                                echo '<span class="badge bg-danger">Overdue</span>';
                                                            } else {
                                                                echo '<span class="badge bg-warning text-dark">Unpaid</span>';
                                                            }
                                                            break;
                                                        case INVOICE_OVERDUE:
                                                            echo '<span class="badge bg-danger">Overdue</span>';
                                                            break;
                                                        default:
                                                            echo '<span class="badge bg-secondary">' . ucfirst($invoice['status']) . '</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo formatDate($invoice['created_at'], 'short'); ?></td>
                                                <td>
                                                    <?php if (!empty($invoice['pdf_path'])): ?>
                                                        <a href="../uploads/invoices/<?php echo $invoice['pdf_path']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                                            <i class="fas fa-file-pdf"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <a href="../admin/invoices.php?action=view&id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <?php if ($invoice['status'] !== INVOICE_PAID): ?>
                                                        <a href="../admin/invoices.php?action=edit&id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-warning">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>Lease Timeline
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-marker bg-primary"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">Lease Created</h6>
                                    <p class="mb-2 text-muted"><?php echo formatDate($lease['created_at']); ?></p>
                                </div>
                            </div>
                            
                            <?php if ($lease['signed']): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-success"></div>
                                    <div class="timeline-content">
                                        <h6 class="mb-0">Lease Signed</h6>
                                        <p class="mb-2 text-muted"><?php echo formatDate($lease['signed_date']); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="timeline-item">
                                <div class="timeline-marker <?php echo time() >= strtotime($lease['start_date']) ? 'bg-success' : 'bg-info'; ?>"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">Lease Start Date</h6>
                                    <p class="mb-2 text-muted"><?php echo formatDate($lease['start_date']); ?></p>
                                </div>
                            </div>
                            
                            <?php
                            // Show invoice timeline items
                            if (!empty($invoices)) {
                                foreach ($invoices as $invoice) {
                                    $markerClass = 'bg-warning';
                                    if ($invoice['status'] === INVOICE_PAID) {
                                        $markerClass = 'bg-success';
                                    } elseif (strtotime($invoice['due_date']) < time() && $invoice['status'] !== INVOICE_PAID) {
                                        $markerClass = 'bg-danger';
                                    }
                            ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker <?php echo $markerClass; ?>"></div>
                                    <div class="timeline-content">
                                        <h6 class="mb-0">Invoice #INV-<?php echo str_pad($invoice['id'], 6, '0', STR_PAD_LEFT); ?></h6>
                                        <p class="mb-0"><?php echo formatCurrency($invoice['amount']); ?> due on <?php echo formatDate($invoice['due_date']); ?></p>
                                        <p class="mb-2 text-muted">
                                            <?php
                                            switch ($invoice['status']) {
                                                case INVOICE_PAID:
                                                    echo 'Paid';
                                                    break;
                                                case INVOICE_UNPAID:
                                                    if (strtotime($invoice['due_date']) < time()) {
                                                        echo 'Overdue';
                                                    } else {
                                                        echo 'Unpaid';
                                                    }
                                                    break;
                                                default:
                                                    echo ucfirst($invoice['status']);
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            <?php
                                }
                            }
                            ?>
                            
                            <div class="timeline-item">
                                <div class="timeline-marker <?php echo time() > strtotime($lease['end_date']) ? 'bg-secondary' : 'bg-danger'; ?>"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">Lease End Date</h6>
                                    <p class="mb-2 text-muted"><?php echo formatDate($lease['end_date']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Lease Notes
                        </h4>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <strong>Lease PDF:</strong>
                                <?php if (!empty($lease['pdf_path'])): ?>
                                    <span class="text-success">Generated</span>
                                <?php else: ?>
                                    <span class="text-danger">Not Generated</span>
                                <?php endif; ?>
                            </li>
                            <li class="list-group-item">
                                <strong>Signing Status:</strong>
                                <?php if ($lease['signed']): ?>
                                    <span class="text-success">Signed</span> on <?php echo formatDate($lease['signed_date']); ?>
                                <?php else: ?>
                                    <span class="text-warning">Pending Signature</span>
                                <?php endif; ?>
                            </li>
                            <li class="list-group-item">
                                <strong>Total Invoices:</strong> <?php echo count($invoices); ?>
                            </li>
                            <li class="list-group-item">
                                <strong>Remaining Balance:</strong>
                                <?php
                                $totalDue = 0;
                                foreach ($invoices as $invoice) {
                                    if ($invoice['status'] !== INVOICE_PAID) {
                                        $totalDue += $invoice['amount'];
                                    }
                                }
                                echo formatCurrency($totalDue);
                                ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Generate Invoice Modal -->
        <div class="modal fade" id="generateInvoiceModal" tabindex="-1" aria-labelledby="generateInvoiceModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="generateInvoiceModalLabel">Generate Invoice</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="post" action="">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount (R)</label>
                                <input type="number" class="form-control" id="amount" name="amount" min="0" step="0.01" value="<?php echo $lease['monthly_rent']; ?>" required>
                                <div class="form-text">Default is the monthly rent amount</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="date" class="form-control" id="due_date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                                <div class="form-text">Default is 7 days from today</div>
                            </div>
                            
                            <input type="hidden" name="lease_id" value="<?php echo $lease['id']; ?>">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="generate_invoice" class="btn btn-primary">
                                <i class="fas fa-file-invoice-dollar me-2"></i>Generate Invoice
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Terminate Lease Modal -->
        <div class="modal fade" id="terminateLeaseModal" tabindex="-1" aria-labelledby="terminateLeaseModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="terminateLeaseModalLabel">Terminate Lease</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="post" action="">
                        <div class="modal-body">
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Warning:</strong> Terminating a lease early will affect billing and may have legal implications. Make sure this action complies with your lease terms and local regulations.
                            </div>
                            
                            <div class="mb-3">
                                <label for="termination_date" class="form-label">Termination Date</label>
                                <input type="date" class="form-control" id="termination_date" name="termination_date" 
                                    min="<?php echo $lease['start_date']; ?>" 
                                    max="<?php echo $lease['end_date']; ?>" 
                                    value="<?php echo date('Y-m-d'); ?>" 
                                    required>
                                <div class="form-text">This will be the new end date for the lease</div>
                            </div>
                            
                            <input type="hidden" name="lease_id" value="<?php echo $lease['id']; ?>">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="terminate_lease" class="btn btn-danger">
                                <i class="fas fa-times-circle me-2"></i>Terminate Lease
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* Timeline styling */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline:before {
    content: '';
    position: absolute;
    top: 0;
    left: 15px;
    height: 100%;
    width: 2px;
    background-color: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 25px;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    width: 15px;
    height: 15px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #dee2e6;
}

.timeline-content {
    padding-bottom: 5px;
}
</style>

<script>
// Set monthly rent based on selected accommodation
document.addEventListener('DOMContentLoaded', function() {
    const accommodationSelect = document.getElementById('accommodation_id');
    const monthlyRentInput = document.getElementById('monthly_rent');
    const securityDepositInput = document.getElementById('security_deposit');
    
    if (accommodationSelect && monthlyRentInput && securityDepositInput) {
        accommodationSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            
            if (price) {
                monthlyRentInput.value = price;
                securityDepositInput.value = price;
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
