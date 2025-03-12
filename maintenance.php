<?php
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

// Handle maintenance request actions
$action = $_GET['action'] ?? '';
$requestId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Process new maintenance request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request']) && hasRole(ROLE_STUDENT)) {
    $accommodationId = intval($_POST['accommodation_id'] ?? 0);
    $issue = $_POST['issue'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if ($accommodationId && !empty($issue) && !empty($description)) {
        // Check if student has a lease for this accommodation
        $lease = fetchOne("SELECT id FROM leases WHERE user_id = ? AND accommodation_id = ?", 
                         [$_SESSION['user_id'], $accommodationId]);
        
        if ($lease) {
            // Create maintenance request
            $requestId = insert('maintenance_requests', [
                'user_id' => $_SESSION['user_id'],
                'accommodation_id' => $accommodationId,
                'issue' => $issue,
                'description' => $description,
                'status' => MAINTENANCE_PENDING,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($requestId) {
                $success = 'Maintenance request submitted successfully.';
                
                // Notify admin
                $accommodation = getAccommodationById($accommodationId);
                if ($accommodation && $accommodation['admin_id']) {
                    $admin = fetchOne("SELECT * FROM users WHERE id = ?", [$accommodation['admin_id']]);
                    
                    if ($admin) {
                        // In a real application, we would send a notification email to the admin here
                    }
                }
                
                // Redirect to view the request
                header('Location: maintenance.php?id=' . $requestId);
                exit;
            } else {
                $error = 'Failed to submit maintenance request.';
            }
        } else {
            $error = 'You can only submit maintenance requests for accommodations you are leasing.';
        }
    } else {
        $error = 'Please fill all required fields.';
    }
}

// Process maintenance request update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])) {
    $id = intval($_POST['request_id'] ?? 0);
    $status = intval($_POST['status'] ?? 0);
    $notes = $_POST['notes'] ?? '';
    
    if ($id && $status) {
        $request = getMaintenanceRequestById($id);
        
        if ($request) {
            // Check if admin is authorized to manage this request
            $canManage = hasRole(ROLE_MASTER_ADMIN) || 
                         $request['admin_id'] == $_SESSION['user_id'];
            
            if ($canManage) {
                $updateData = [
                    'status' => $status,
                    'notes' => $notes,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                if ($status == MAINTENANCE_COMPLETED) {
                    $updateData['completed_at'] = date('Y-m-d H:i:s');
                }
                
                // Update request status
                update('maintenance_requests', $updateData, 'id', $id);
                
                $success = 'Maintenance request status updated successfully.';
                
                // Send notification to student
                $user = fetchOne("SELECT * FROM users WHERE id = ?", [$request['user_id']]);
                
                if ($user) {
                    sendMaintenanceUpdateEmail($user['email'], $user['username'], $request);
                    
                    if (!empty($user['phone'])) {
                        sendMaintenanceUpdateSMS($user['phone'], $user['username'], $request);
                    }
                }
            } else {
                $error = 'You are not authorized to update this maintenance request.';
            }
        } else {
            $error = 'Maintenance request not found.';
        }
    } else {
        $error = 'Invalid request data.';
    }
}

// Get maintenance request data based on role and context
if ($requestId) {
    // View specific maintenance request
    $request = getMaintenanceRequestById($requestId);
    
    if (!$request) {
        header('Location: maintenance.php');
        exit;
    }
    
    // Check permission to view this request
    $canView = false;
    
    if (hasRole(ROLE_STUDENT)) {
        $canView = $request['user_id'] == $_SESSION['user_id'];
    } elseif (hasRole(ROLE_ADMIN)) {
        $canView = $request['admin_id'] == $_SESSION['user_id'];
    } elseif (hasRole(ROLE_MASTER_ADMIN)) {
        $canView = true;
    }
    
    if (!$canView) {
        header('Location: maintenance.php');
        exit;
    }
} elseif ($action === 'new' && hasRole(ROLE_STUDENT)) {
    // New maintenance request form
    
    // Get student's leased accommodations
    $studentAccommodations = fetchAll(
        "SELECT a.id, a.name 
        FROM accommodations a
        JOIN leases l ON a.id = l.accommodation_id
        WHERE l.user_id = ? AND l.is_signed = 1
        GROUP BY a.id
        ORDER BY a.name ASC", 
        [$_SESSION['user_id']]
    );
    
    if (empty($studentAccommodations)) {
        $error = 'You must have an active lease to submit a maintenance request.';
    }
} else {
    // Maintenance requests list
    if (hasRole(ROLE_MASTER_ADMIN)) {
        $requests = getMaintenanceRequests();
    } elseif (hasRole(ROLE_ADMIN)) {
        $adminId = $_SESSION['user_id'];
        $requests = getMaintenanceRequests(null, null);
        
        // Filter for admin's accommodations
        $adminAccommodations = getAdminAccommodations($adminId);
        $adminAccommodationIds = array_column($adminAccommodations, 'id');
        
        $filteredRequests = [];
        foreach ($requests as $request) {
            if (in_array($request['accommodation_id'], $adminAccommodationIds)) {
                $filteredRequests[] = $request;
            }
        }
        
        $requests = $filteredRequests;
    } else {
        $requests = getMaintenanceRequests($_SESSION['user_id']);
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
    
    <?php if ($requestId && $request): ?>
        <!-- View Single Maintenance Request -->
        <div class="row mb-4">
            <div class="col-md-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?= hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN]) ? 'admin.php' : 'dashboard.php' ?>">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="maintenance.php">Maintenance Requests</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Request #<?= $request['id'] ?></li>
                    </ol>
                </nav>
                <h1>Maintenance Request</h1>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Maintenance Request #<?= $request['id'] ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h6>Status</h6>
                            <?php if ($request['status'] == MAINTENANCE_PENDING): ?>
                                <span class="badge bg-warning">Pending</span>
                            <?php elseif ($request['status'] == MAINTENANCE_IN_PROGRESS): ?>
                                <span class="badge bg-info">In Progress</span>
                            <?php elseif ($request['status'] == MAINTENANCE_COMPLETED): ?>
                                <span class="badge bg-success">Completed</span>
                                <?php if (!empty($request['completed_at'])): ?>
                                    <p class="text-muted small">Completed on <?= formatDate($request['completed_at']) ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-4">
                            <h6>Issue</h6>
                            <p><?= $request['issue'] ?></p>
                        </div>
                        
                        <div class="mb-4">
                            <h6>Description</h6>
                            <p><?= nl2br($request['description']) ?></p>
                        </div>
                        
                        <?php if (!empty($request['notes'])): ?>
                            <div class="mb-4">
                                <h6>Notes</h6>
                                <div class="bg-light p-3 rounded">
                                    <?= nl2br($request['notes']) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6>Accommodation</h6>
                                <p><?= $request['accommodation_name'] ?></p>
                            </div>
                            <div class="col-md-6">
                                <?php if (hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])): ?>
                                    <h6>Submitted By</h6>
                                    <p><?= $request['username'] ?> (<?= $request['email'] ?>)</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6>Submitted On</h6>
                                <p><?= formatDate($request['created_at']) ?></p>
                            </div>
                            <?php if (!empty($request['updated_at']) && $request['updated_at'] != $request['created_at']): ?>
                                <div class="col-md-6">
                                    <h6>Last Updated</h6>
                                    <p><?= formatDate($request['updated_at']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if (hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN]) && $request['status'] != MAINTENANCE_COMPLETED): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Update Status</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="maintenance.php">
                                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="<?= MAINTENANCE_PENDING ?>" <?= $request['status'] == MAINTENANCE_PENDING ? 'selected' : '' ?>>Pending</option>
                                        <option value="<?= MAINTENANCE_IN_PROGRESS ?>" <?= $request['status'] == MAINTENANCE_IN_PROGRESS ? 'selected' : '' ?>>In Progress</option>
                                        <option value="<?= MAINTENANCE_COMPLETED ?>">Completed</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"><?= $request['notes'] ?></textarea>
                                </div>
                                
                                <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Request Timeline</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <i class="fas fa-file-alt text-primary"></i> 
                                <strong>Request Submitted</strong>
                                <div class="text-muted"><?= formatDate($request['created_at']) ?></div>
                            </li>
                            
                            <?php if ($request['status'] >= MAINTENANCE_IN_PROGRESS): ?>
                                <li class="list-group-item">
                                    <i class="fas fa-tools text-info"></i> 
                                    <strong>In Progress</strong>
                                    <div class="text-muted"><?= formatDate($request['updated_at']) ?></div>
                                </li>
                            <?php endif; ?>
                            
                            <?php if ($request['status'] == MAINTENANCE_COMPLETED): ?>
                                <li class="list-group-item">
                                    <i class="fas fa-check-circle text-success"></i> 
                                    <strong>Completed</strong>
                                    <div class="text-muted"><?= formatDate($request['completed_at'] ?? $request['updated_at']) ?></div>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <?php if (hasRole(ROLE_STUDENT)): ?>
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Contact Information</h5>
                        </div>
                        <div class="card-body">
                            <p>For urgent maintenance issues, please contact:</p>
                            <p><i class="fas fa-phone"></i> +27 12 345 6789</p>
                            <p><i class="fas fa-envelope"></i> maintenance@harambee.co.za</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    <?php elseif ($action === 'new' && hasRole(ROLE_STUDENT)): ?>
        <!-- New Maintenance Request Form -->
        <div class="row mb-4">
            <div class="col-md-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="maintenance.php">Maintenance Requests</a></li>
                        <li class="breadcrumb-item active" aria-current="page">New Request</li>
                    </ol>
                </nav>
                <h1>Submit Maintenance Request</h1>
            </div>
        </div>
        
        <?php if (!empty($studentAccommodations)): ?>
            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Maintenance Request Form</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="maintenance.php" id="maintenance_form">
                                <div class="mb-3">
                                    <label for="accommodation_id" class="form-label">Accommodation *</label>
                                    <select class="form-select" id="accommodation_id" name="accommodation_id" required>
                                        <option value="">Select Accommodation</option>
                                        <?php foreach ($studentAccommodations as $accommodation): ?>
                                            <option value="<?= $accommodation['id'] ?>"><?= $accommodation['name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="issue" class="form-label">Issue Title *</label>
                                    <input type="text" class="form-control" id="issue" name="issue" placeholder="e.g., Leaking Faucet, Broken Light, etc." required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description *</label>
                                    <textarea class="form-control" id="description" name="description" rows="5" placeholder="Please describe the issue in detail..." required></textarea>
                                    <div class="form-text">Provide as much detail as possible to help us address the issue promptly.</div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" name="submit_request" class="btn btn-primary btn-lg">Submit Request</button>
                                    <a href="maintenance.php" class="btn btn-outline-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Maintenance Guidelines</h5>
                        </div>
                        <div class="card-body">
                            <h6>Urgent Issues</h6>
                            <p>For emergencies like major water leaks, electrical failures, or security concerns, please call the emergency maintenance line in addition to submitting a request.</p>
                            
                            <h6>Response Times</h6>
                            <ul>
                                <li>Emergency: 1-24 hours</li>
                                <li>Urgent: 24-48 hours</li>
                                <li>Routine: 3-5 business days</li>
                            </ul>
                            
                            <h6>What to Include</h6>
                            <ul>
                                <li>Clear description of the problem</li>
                                <li>Location (room, bathroom, kitchen, etc.)</li>
                                <li>When you first noticed the issue</li>
                                <li>Any attempts you've made to fix it</li>
                            </ul>
                            
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i> You will receive email notifications as your maintenance request status is updated.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <p>You must have an active lease to submit a maintenance request. Please contact the administration if you believe this is an error.</p>
                <a href="accommodations.php" class="btn btn-primary mt-2">Browse Accommodations</a>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- Maintenance Requests List -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h1><?= hasRole(ROLE_STUDENT) ? 'My Maintenance Requests' : 'Maintenance Requests' ?></h1>
                <p class="lead">
                    <?= hasRole(ROLE_STUDENT) ? 'View and manage your maintenance requests.' : 'View and manage student maintenance requests.' ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <?php if (hasRole(ROLE_STUDENT)): ?>
                    <a href="maintenance.php?action=new" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Request
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (empty($requests)): ?>
            <div class="alert alert-info">
                <?php if (hasRole(ROLE_STUDENT)): ?>
                    <p class="text-center">You haven't submitted any maintenance requests yet.</p>
                    <div class="text-center mt-3">
                        <a href="maintenance.php?action=new" class="btn btn-primary">Submit a Request</a>
                    </div>
                <?php else: ?>
                    <p class="text-center">No maintenance requests found.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?= hasRole(ROLE_STUDENT) ? 'My Maintenance Requests' : 'Maintenance Requests List' ?></h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="btn-group" role="group" aria-label="Filter by status">
                            <button type="button" class="btn btn-outline-primary active" data-status="all">All</button>
                            <button type="button" class="btn btn-outline-warning" data-status="pending">Pending</button>
                            <button type="button" class="btn btn-outline-info" data-status="in-progress">In Progress</button>
                            <button type="button" class="btn btn-outline-success" data-status="completed">Completed</button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <?php if (hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])): ?>
                                        <th>Student</th>
                                    <?php endif; ?>
                                    <th>Accommodation</th>
                                    <th>Issue</th>
                                    <th>Status</th>
                                    <th>Submitted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $req): ?>
                                    <tr class="status-<?= $req['status'] == MAINTENANCE_PENDING ? 'pending' : ($req['status'] == MAINTENANCE_IN_PROGRESS ? 'in-progress' : 'completed') ?>">
                                        <td><?= $req['id'] ?></td>
                                        <?php if (hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])): ?>
                                            <td><?= $req['username'] ?></td>
                                        <?php endif; ?>
                                        <td><?= $req['accommodation_name'] ?></td>
                                        <td><?= $req['issue'] ?></td>
                                        <td>
                                            <?php if ($req['status'] == MAINTENANCE_PENDING): ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php elseif ($req['status'] == MAINTENANCE_IN_PROGRESS): ?>
                                                <span class="badge bg-info">In Progress</span>
                                            <?php elseif ($req['status'] == MAINTENANCE_COMPLETED): ?>
                                                <span class="badge bg-success">Completed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= formatDate($req['created_at']) ?></td>
                                        <td>
                                            <a href="maintenance.php?id=<?= $req['id'] ?>" class="btn btn-sm btn-outline-primary">
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
            
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Status filter functionality
                    const filterButtons = document.querySelectorAll('[data-status]');
                    const rows = document.querySelectorAll('tbody tr');
                    
                    filterButtons.forEach(button => {
                        button.addEventListener('click', function() {
                            // Update active button
                            filterButtons.forEach(btn => btn.classList.remove('active'));
                            this.classList.add('active');
                            
                            const status = this.dataset.status;
                            
                            // Show/hide rows based on status
                            rows.forEach(row => {
                                if (status === 'all' || row.classList.contains('status-' + status)) {
                                    row.style.display = '';
                                } else {
                                    row.style.display = 'none';
                                }
                            });
                        });
                    });
                });
            </script>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
