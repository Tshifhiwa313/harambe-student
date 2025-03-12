<?php
/**
 * Admin Maintenance Requests Management
 * 
 * This page allows administrators to view and manage maintenance requests.
 */

require_once '../includes/header.php';

// Require admin authentication
requireAuth([ROLE_MASTER_ADMIN, ROLE_ADMIN]);

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

// Handle maintenance request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update maintenance request status
    if (isset($_POST['update_request'])) {
        $requestId = (int)$_POST['request_id'];
        $newStatus = $_POST['status'];
        $comments = sanitize($_POST['comments'] ?? '');
        
        // Validation
        $errors = [];
        
        if (!in_array($newStatus, [MAINTENANCE_PENDING, MAINTENANCE_IN_PROGRESS, MAINTENANCE_COMPLETED, MAINTENANCE_CANCELLED])) {
            $errors[] = 'Invalid status selected';
        }
        
        // Get maintenance request details
        $request = getMaintenanceRequest($conn, $requestId);
        
        if (!$request) {
            $errors[] = 'Maintenance request not found';
        } else {
            // Check permission for regular admin
            if ($userRole !== ROLE_MASTER_ADMIN && !isAdminAssignedToAccommodation($conn, $userId, $request['accommodation_id'])) {
                $errors[] = 'You do not have permission to update this maintenance request';
            }
        }
        
        // Update request if no errors
        if (empty($errors)) {
            $requestData = [
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (!empty($comments)) {
                $requestData['admin_comments'] = $comments;
            }
            
            if (updateRow($conn, 'maintenance_requests', $requestData, 'id', $requestId)) {
                // Send notification to student
                sendMaintenanceUpdateNotification($conn, $requestId, $newStatus);
                
                setFlashMessage('Maintenance request updated successfully!', 'success');
                redirect("maintenance.php?action=view&id=$requestId");
            } else {
                $errors[] = 'Failed to update maintenance request';
            }
        }
    }
}

// Define action based on GET parameter
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$requestId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get maintenance request details if viewing or editing
$request = null;
if (($action === 'view' || $action === 'edit') && $requestId > 0) {
    $request = fetchRow($conn, 
        "SELECT m.*, u.first_name, u.last_name, u.email, u.phone, 
        a.name as accommodation_name, a.address 
        FROM maintenance_requests m 
        JOIN users u ON m.user_id = u.id 
        JOIN accommodations a ON m.accommodation_id = a.id 
        WHERE m.id = :id", 
        ['id' => $requestId]
    );
    
    if (!$request) {
        setFlashMessage('Maintenance request not found', 'danger');
        redirect('maintenance.php');
    }
    
    // Check permission for regular admin
    if ($userRole !== ROLE_MASTER_ADMIN && !isAdminAssignedToAccommodation($conn, $userId, $request['accommodation_id'])) {
        setFlashMessage('You do not have permission to view this maintenance request', 'danger');
        redirect('maintenance.php');
    }
}

// Get maintenance requests based on user role
if ($userRole === ROLE_MASTER_ADMIN) {
    // Master admin sees all maintenance requests
    $requests = fetchAll($conn, 
        "SELECT m.*, u.first_name, u.last_name, a.name as accommodation_name 
        FROM maintenance_requests m 
        JOIN users u ON m.user_id = u.id 
        JOIN accommodations a ON m.accommodation_id = a.id 
        ORDER BY 
            CASE 
                WHEN m.priority = 'emergency' THEN 1
                WHEN m.priority = 'high' THEN 2
                WHEN m.priority = 'medium' THEN 3
                WHEN m.priority = 'low' THEN 4
                ELSE 5
            END, 
            m.created_at DESC"
    );
} else {
    // Regular admin sees maintenance requests for assigned accommodations
    $adminAccommodations = getAdminAccommodations($conn, $userId);
    
    if (empty($adminAccommodations)) {
        $requests = [];
    } else {
        $accommodationIds = array_map(function($acc) { return $acc['id']; }, $adminAccommodations);
        $placeholders = implode(',', array_fill(0, count($accommodationIds), '?'));
        
        $stmt = $conn->prepare(
            "SELECT m.*, u.first_name, u.last_name, a.name as accommodation_name 
            FROM maintenance_requests m 
            JOIN users u ON m.user_id = u.id 
            JOIN accommodations a ON m.accommodation_id = a.id 
            WHERE m.accommodation_id IN ($placeholders) 
            ORDER BY 
                CASE 
                    WHEN m.priority = 'emergency' THEN 1
                    WHEN m.priority = 'high' THEN 2
                    WHEN m.priority = 'medium' THEN 3
                    WHEN m.priority = 'low' THEN 4
                    ELSE 5
                END, 
                m.created_at DESC"
        );
        
        $stmt->execute($accommodationIds);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Filter maintenance requests based on status if requested
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
if (!empty($statusFilter)) {
    $requests = array_filter($requests, function($req) use ($statusFilter) {
        return $req['status'] === $statusFilter;
    });
}

// Filter maintenance requests based on priority if requested
$priorityFilter = isset($_GET['priority']) ? $_GET['priority'] : '';
if (!empty($priorityFilter)) {
    $requests = array_filter($requests, function($req) use ($priorityFilter) {
        return $req['priority'] === $priorityFilter;
    });
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Manage Maintenance Requests</h2>
        <div>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
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
        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Status Filter</h5>
                    </div>
                    <div class="card-body">
                        <div class="btn-group w-100" role="group" aria-label="Filter by status">
                            <a href="maintenance.php" class="btn <?php echo empty($statusFilter) ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                All
                            </a>
                            <a href="?status=<?php echo MAINTENANCE_PENDING; ?>" class="btn <?php echo $statusFilter === MAINTENANCE_PENDING ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                Pending
                            </a>
                            <a href="?status=<?php echo MAINTENANCE_IN_PROGRESS; ?>" class="btn <?php echo $statusFilter === MAINTENANCE_IN_PROGRESS ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                In Progress
                            </a>
                            <a href="?status=<?php echo MAINTENANCE_COMPLETED; ?>" class="btn <?php echo $statusFilter === MAINTENANCE_COMPLETED ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                Completed
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Priority Filter</h5>
                    </div>
                    <div class="card-body">
                        <div class="btn-group w-100" role="group" aria-label="Filter by priority">
                            <a href="maintenance.php" class="btn <?php echo empty($priorityFilter) ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                All
                            </a>
                            <a href="?priority=<?php echo PRIORITY_EMERGENCY; ?>" class="btn <?php echo $priorityFilter === PRIORITY_EMERGENCY ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                Emergency
                            </a>
                            <a href="?priority=<?php echo PRIORITY_HIGH; ?>" class="btn <?php echo $priorityFilter === PRIORITY_HIGH ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                High
                            </a>
                            <a href="?priority=<?php echo PRIORITY_MEDIUM; ?>" class="btn <?php echo $priorityFilter === PRIORITY_MEDIUM ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                Medium
                            </a>
                            <a href="?priority=<?php echo PRIORITY_LOW; ?>" class="btn <?php echo $priorityFilter === PRIORITY_LOW ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                Low
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Maintenance Requests List -->
        <?php if (empty($requests)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>No maintenance requests found.
                <?php if (!empty($statusFilter) || !empty($priorityFilter)): ?>
                    Try removing the filters to see all requests.
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
                                    <th>Title</th>
                                    <th>Student</th>
                                    <th>Accommodation</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $req): ?>
                                    <tr>
                                        <td><?php echo $req['id']; ?></td>
                                        <td><?php echo $req['title']; ?></td>
                                        <td><?php echo $req['first_name'] . ' ' . $req['last_name']; ?></td>
                                        <td><?php echo $req['accommodation_name']; ?></td>
                                        <td>
                                            <?php
                                            switch ($req['priority']) {
                                                case PRIORITY_EMERGENCY:
                                                    echo '<span class="badge bg-danger">Emergency</span>';
                                                    break;
                                                case PRIORITY_HIGH:
                                                    echo '<span class="badge bg-danger">High</span>';
                                                    break;
                                                case PRIORITY_MEDIUM:
                                                    echo '<span class="badge bg-warning text-dark">Medium</span>';
                                                    break;
                                                case PRIORITY_LOW:
                                                    echo '<span class="badge bg-info">Low</span>';
                                                    break;
                                                default:
                                                    echo '<span class="badge bg-secondary">' . ucfirst($req['priority']) . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            switch ($req['status']) {
                                                case MAINTENANCE_PENDING:
                                                    echo '<span class="badge bg-warning text-dark">Pending</span>';
                                                    break;
                                                case MAINTENANCE_IN_PROGRESS:
                                                    echo '<span class="badge bg-primary">In Progress</span>';
                                                    break;
                                                case MAINTENANCE_COMPLETED:
                                                    echo '<span class="badge bg-success">Completed</span>';
                                                    break;
                                                case MAINTENANCE_CANCELLED:
                                                    echo '<span class="badge bg-secondary">Cancelled</span>';
                                                    break;
                                                default:
                                                    echo '<span class="badge bg-secondary">' . ucfirst($req['status']) . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo formatDate($req['created_at'], 'short'); ?></td>
                                        <td>
                                            <a href="?action=view&id=<?php echo $req['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($req['status'] !== MAINTENANCE_COMPLETED && $req['status'] !== MAINTENANCE_CANCELLED): ?>
                                                <a href="?action=edit&id=<?php echo $req['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
    <?php elseif ($action === 'view' && $request): ?>
        <!-- View Maintenance Request Details -->
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-tools me-2"></i>Maintenance Request #<?php echo $request['id']; ?>
                        </h4>
                        <div>
                            <?php if ($request['status'] !== MAINTENANCE_COMPLETED && $request['status'] !== MAINTENANCE_CANCELLED): ?>
                                <a href="?action=edit&id=<?php echo $request['id']; ?>" class="btn btn-warning">
                                    <i class="fas fa-edit me-2"></i>Update Status
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2"><?php echo $request['title']; ?></h5>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge 
                                    <?php 
                                    switch ($request['priority']) {
                                        case PRIORITY_EMERGENCY:
                                            echo 'bg-danger';
                                            break;
                                        case PRIORITY_HIGH:
                                            echo 'bg-danger';
                                            break;
                                        case PRIORITY_MEDIUM:
                                            echo 'bg-warning text-dark';
                                            break;
                                        case PRIORITY_LOW:
                                            echo 'bg-info';
                                            break;
                                        default:
                                            echo 'bg-secondary';
                                    }
                                    ?>">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <?php echo ucfirst($request['priority']); ?> Priority
                                </span>
                                
                                <span class="badge 
                                    <?php 
                                    switch ($request['status']) {
                                        case MAINTENANCE_PENDING:
                                            echo 'bg-warning text-dark';
                                            break;
                                        case MAINTENANCE_IN_PROGRESS:
                                            echo 'bg-primary';
                                            break;
                                        case MAINTENANCE_COMPLETED:
                                            echo 'bg-success';
                                            break;
                                        case MAINTENANCE_CANCELLED:
                                            echo 'bg-secondary';
                                            break;
                                        default:
                                            echo 'bg-secondary';
                                    }
                                    ?>">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                                
                                <span class="badge bg-secondary">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    Submitted: <?php echo formatDate($request['created_at']); ?>
                                </span>
                            </div>
                            
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-3 text-muted">Description:</h6>
                                    <p class="card-text"><?php echo nl2br($request['description']); ?></p>
                                </div>
                            </div>
                            
                            <?php if (!empty($request['admin_comments'])): ?>
                                <div class="card bg-light mb-4">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted">Admin Comments:</h6>
                                        <p class="card-text"><?php echo nl2br($request['admin_comments']); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Student Information</h5>
                                    <p><strong>Name:</strong> <?php echo $request['first_name'] . ' ' . $request['last_name']; ?></p>
                                    <p><strong>Email:</strong> <?php echo $request['email']; ?></p>
                                    <p><strong>Phone:</strong> <?php echo !empty($request['phone']) ? $request['phone'] : 'Not provided'; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h5>Accommodation</h5>
                                    <p><strong>Name:</strong> <?php echo $request['accommodation_name']; ?></p>
                                    <p><strong>Address:</strong> <?php echo $request['address']; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($request['status'] !== MAINTENANCE_COMPLETED && $request['status'] !== MAINTENANCE_CANCELLED): ?>
                            <div class="border-top pt-3">
                                <h5>Quick Status Update</h5>
                                <form method="post" action="">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <input type="hidden" name="comments" value="">
                                    
                                    <div class="btn-group">
                                        <?php if ($request['status'] === MAINTENANCE_PENDING): ?>
                                            <button type="submit" name="update_request" class="btn btn-primary" onclick="setStatus('in_progress')">
                                                <i class="fas fa-play-circle me-2"></i>Start Work
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($request['status'] === MAINTENANCE_PENDING || $request['status'] === MAINTENANCE_IN_PROGRESS): ?>
                                            <button type="submit" name="update_request" class="btn btn-success" onclick="setStatus('completed')">
                                                <i class="fas fa-check-circle me-2"></i>Mark as Completed
                                            </button>
                                            <button type="submit" name="update_request" class="btn btn-danger" onclick="setStatus('cancelled')">
                                                <i class="fas fa-times-circle me-2"></i>Cancel Request
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <input type="hidden" id="status-input" name="status" value="">
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0">
                            <i class="fas fa-cog me-2"></i>Actions
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php if ($request['status'] !== MAINTENANCE_COMPLETED && $request['status'] !== MAINTENANCE_CANCELLED): ?>
                                <a href="?action=edit&id=<?php echo $request['id']; ?>" class="list-group-item list-group-item-action">
                                    <i class="fas fa-edit me-2"></i>Update Status and Add Comments
                                </a>
                            <?php endif; ?>
                            
                            <a href="javascript:void(0);" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#contactStudentModal">
                                <i class="fas fa-envelope me-2"></i>Contact Student
                            </a>
                            
                            <a href="maintenance.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-arrow-left me-2"></i>Back to Maintenance Requests
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0">
                            <i class="fas fa-history me-2"></i>Request Timeline
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-marker bg-primary"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-0">Request Submitted</h6>
                                    <p class="text-muted mb-0"><?php echo formatDate($request['created_at']); ?></p>
                                </div>
                            </div>
                            
                            <?php if ($request['status'] !== MAINTENANCE_PENDING): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-info"></div>
                                    <div class="timeline-content">
                                        <h6 class="mb-0">Status Updated</h6>
                                        <p class="text-muted mb-0"><?php echo formatDate($request['updated_at']); ?></p>
                                        <p>
                                            <?php
                                            switch ($request['status']) {
                                                case MAINTENANCE_IN_PROGRESS:
                                                    echo 'Work started on the request';
                                                    break;
                                                case MAINTENANCE_COMPLETED:
                                                    echo 'Work completed successfully';
                                                    break;
                                                case MAINTENANCE_CANCELLED:
                                                    echo 'Request was cancelled';
                                                    break;
                                                default:
                                                    echo 'Status changed to ' . ucfirst($request['status']);
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact Student Modal -->
        <div class="modal fade" id="contactStudentModal" tabindex="-1" aria-labelledby="contactStudentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="contactStudentModalLabel">Contact Student</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <p><strong>Student:</strong> <?php echo $request['first_name'] . ' ' . $request['last_name']; ?></p>
                            <p><strong>Email:</strong> <?php echo $request['email']; ?></p>
                            <p><strong>Phone:</strong> <?php echo !empty($request['phone']) ? $request['phone'] : 'Not provided'; ?></p>
                        </div>
                        
                        <form id="contactForm">
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" value="RE: Maintenance Request #<?php echo $request['id']; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">Message</label>
                                <textarea class="form-control" id="message" rows="5" placeholder="Enter your message to the student"></textarea>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="sendEmail" checked>
                                <label class="form-check-label" for="sendEmail">Send via Email</label>
                            </div>
                            
                            <?php if (!empty($request['phone'])): ?>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="sendSMS">
                                    <label class="form-check-label" for="sendSMS">Send via SMS</label>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="sendMessageBtn">
                            <i class="fas fa-paper-plane me-2"></i>Send Message
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
    <?php elseif ($action === 'edit' && $request): ?>
        <!-- Update Maintenance Request Status -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h4 class="mb-0">
                    <i class="fas fa-edit me-2"></i>Update Maintenance Request
                </h4>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    You are updating the status for Maintenance Request #<?php echo $request['id']; ?>: 
                    <strong><?php echo $request['title']; ?></strong> from 
                    <strong><?php echo $request['first_name'] . ' ' . $request['last_name']; ?></strong>.
                </div>
                
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="<?php echo MAINTENANCE_PENDING; ?>" <?php echo $request['status'] === MAINTENANCE_PENDING ? 'selected' : ''; ?>>Pending</option>
                            <option value="<?php echo MAINTENANCE_IN_PROGRESS; ?>" <?php echo $request['status'] === MAINTENANCE_IN_PROGRESS ? 'selected' : ''; ?>>In Progress</option>
                            <option value="<?php echo MAINTENANCE_COMPLETED; ?>" <?php echo $request['status'] === MAINTENANCE_COMPLETED ? 'selected' : ''; ?>>Completed</option>
                            <option value="<?php echo MAINTENANCE_CANCELLED; ?>" <?php echo $request['status'] === MAINTENANCE_CANCELLED ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="comments" class="form-label">Admin Comments</label>
                        <textarea class="form-control" id="comments" name="comments" rows="5"><?php echo $request['admin_comments'] ?? ''; ?></textarea>
                        <div class="form-text">These comments will be visible to the student and help explain the status update.</div>
                    </div>
                    
                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                    
                    <div class="mt-4">
                        <button type="submit" name="update_request" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Request
                        </button>
                        <a href="?action=view&id=<?php echo $request['id']; ?>" class="btn btn-secondary ms-2">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
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
function setStatus(status) {
    document.getElementById('status-input').value = status;
}

// Contact Student Form handling
document.addEventListener('DOMContentLoaded', function() {
    const sendMessageBtn = document.getElementById('sendMessageBtn');
    if (sendMessageBtn) {
        sendMessageBtn.addEventListener('click', function() {
            // This would typically send an AJAX request to a backend endpoint
            alert('Message sending functionality would be implemented here.');
            
            // Close the modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('contactStudentModal'));
            modal.hide();
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
