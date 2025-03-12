<?php
/**
 * Admin Applications Management
 * 
 * This page allows administrators to view and manage accommodation applications.
 */

require_once '../includes/header.php';

// Require admin authentication
requireAuth([ROLE_MASTER_ADMIN, ROLE_ADMIN]);

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

// Handle application actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['application_id']) && isset($_POST['action'])) {
        $applicationId = (int)$_POST['application_id'];
        $action = $_POST['action'];
        
        // Get application details
        $application = getApplication($conn, $applicationId);
        
        if (!$application) {
            setFlashMessage('Application not found', 'danger');
            redirect('applications.php');
        }
        
        // Check permission for regular admins
        if ($userRole !== ROLE_MASTER_ADMIN && !isAdminAssignedToAccommodation($conn, $userId, $application['accommodation_id'])) {
            setFlashMessage('You do not have permission to manage this application', 'danger');
            redirect('applications.php');
        }
        
        if ($action === 'approve') {
            // Check if accommodation has available rooms
            if (!hasAvailableRooms($conn, $application['accommodation_id'])) {
                setFlashMessage('Cannot approve application. No rooms available in this accommodation.', 'danger');
                redirect('applications.php');
            }
            
            // Update application status
            $data = [
                'status' => STATUS_APPROVED,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (updateRow($conn, 'applications', $data, 'id', $applicationId)) {
                // Send notification
                sendApplicationStatusNotification($conn, $applicationId, STATUS_APPROVED);
                
                setFlashMessage('Application approved successfully', 'success');
            } else {
                setFlashMessage('Failed to approve application', 'danger');
            }
        } elseif ($action === 'reject') {
            // Update application status
            $data = [
                'status' => STATUS_REJECTED,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (updateRow($conn, 'applications', $data, 'id', $applicationId)) {
                // Send notification
                sendApplicationStatusNotification($conn, $applicationId, STATUS_REJECTED);
                
                setFlashMessage('Application rejected successfully', 'success');
            } else {
                setFlashMessage('Failed to reject application', 'danger');
            }
        }
        
        redirect('applications.php');
    }
}

// Get applications based on user role
if ($userRole === ROLE_MASTER_ADMIN) {
    // Master admin sees all applications
    $applications = fetchAll($conn, 
        "SELECT a.*, u.first_name, u.last_name, u.email, u.phone, ac.name as accommodation_name 
        FROM applications a
        JOIN users u ON a.user_id = u.id
        JOIN accommodations ac ON a.accommodation_id = ac.id
        ORDER BY a.created_at DESC");
} else {
    // Regular admin sees applications for assigned accommodations
    $adminAccommodations = getAdminAccommodations($conn, $userId);
    
    if (empty($adminAccommodations)) {
        $applications = [];
    } else {
        $accommodationIds = array_map(function($acc) { return $acc['id']; }, $adminAccommodations);
        $placeholders = implode(',', array_fill(0, count($accommodationIds), '?'));
        
        $stmt = $conn->prepare(
            "SELECT a.*, u.first_name, u.last_name, u.email, u.phone, ac.name as accommodation_name 
            FROM applications a
            JOIN users u ON a.user_id = u.id
            JOIN accommodations ac ON a.accommodation_id = ac.id
            WHERE a.accommodation_id IN ($placeholders)
            ORDER BY a.created_at DESC");
        
        $stmt->execute($accommodationIds);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Filter applications based on status if requested
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
if (!empty($statusFilter)) {
    $applications = array_filter($applications, function($app) use ($statusFilter) {
        return $app['status'] === $statusFilter;
    });
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Manage Applications</h2>
        <div>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
    
    <!-- Status Filters -->
    <div class="mb-4">
        <div class="btn-group" role="group" aria-label="Filter by status">
            <a href="applications.php" class="btn <?php echo empty($statusFilter) ? 'btn-primary' : 'btn-outline-primary'; ?>">
                All
            </a>
            <a href="?status=<?php echo STATUS_PENDING; ?>" class="btn <?php echo $statusFilter === STATUS_PENDING ? 'btn-primary' : 'btn-outline-primary'; ?>">
                Pending
            </a>
            <a href="?status=<?php echo STATUS_APPROVED; ?>" class="btn <?php echo $statusFilter === STATUS_APPROVED ? 'btn-primary' : 'btn-outline-primary'; ?>">
                Approved
            </a>
            <a href="?status=<?php echo STATUS_REJECTED; ?>" class="btn <?php echo $statusFilter === STATUS_REJECTED ? 'btn-primary' : 'btn-outline-primary'; ?>">
                Rejected
            </a>
        </div>
    </div>
    
    <?php if (empty($applications)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <?php if (!empty($statusFilter)): ?>
                No applications found with status: <?php echo ucfirst($statusFilter); ?>.
            <?php else: ?>
                No applications found.
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
                                <th>Move-in Date</th>
                                <th>Status</th>
                                <th>Applied On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td><?php echo $app['id']; ?></td>
                                    <td>
                                        <?php echo $app['first_name'] . ' ' . $app['last_name']; ?><br>
                                        <small class="text-muted"><?php echo $app['email']; ?></small>
                                    </td>
                                    <td><?php echo $app['accommodation_name']; ?></td>
                                    <td><?php echo formatDate($app['move_in_date']); ?></td>
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
                                    <td><?php echo formatDate($app['created_at'], 'short'); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#viewApplicationModal<?php echo $app['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <?php if ($app['status'] === STATUS_PENDING): ?>
                                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveApplicationModal<?php echo $app['id']; ?>">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectApplicationModal<?php echo $app['id']; ?>">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                
                                <!-- View Application Modal -->
                                <div class="modal fade" id="viewApplicationModal<?php echo $app['id']; ?>" tabindex="-1" aria-labelledby="viewApplicationModalLabel<?php echo $app['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="viewApplicationModalLabel<?php echo $app['id']; ?>">Application Details</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <h6>Student Information</h6>
                                                        <p><strong>Name:</strong> <?php echo $app['first_name'] . ' ' . $app['last_name']; ?></p>
                                                        <p><strong>Email:</strong> <?php echo $app['email']; ?></p>
                                                        <p><strong>Phone:</strong> <?php echo !empty($app['phone']) ? $app['phone'] : 'Not provided'; ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <h6>Application Information</h6>
                                                        <p><strong>Status:</strong> 
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
                                                        </p>
                                                        <p><strong>Applied On:</strong> <?php echo formatDate($app['created_at']); ?></p>
                                                        <p><strong>Last Updated:</strong> <?php echo formatDate($app['updated_at']); ?></p>
                                                    </div>
                                                </div>
                                                <hr>
                                                <div class="row">
                                                    <div class="col-12">
                                                        <h6>Accommodation</h6>
                                                        <p><strong>Name:</strong> <?php echo $app['accommodation_name']; ?></p>
                                                        <p><strong>Requested Move-in Date:</strong> <?php echo formatDate($app['move_in_date']); ?></p>
                                                    </div>
                                                </div>
                                                <?php if (!empty($app['additional_info'])): ?>
                                                <hr>
                                                <div class="row">
                                                    <div class="col-12">
                                                        <h6>Additional Information</h6>
                                                        <p><?php echo nl2br($app['additional_info']); ?></p>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <?php if ($app['status'] === STATUS_APPROVED): ?>
                                                    <a href="leases.php?action=create&application_id=<?php echo $app['id']; ?>" class="btn btn-primary">
                                                        <i class="fas fa-file-contract me-2"></i>Create Lease
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Approve Application Modal -->
                                <?php if ($app['status'] === STATUS_PENDING): ?>
                                <div class="modal fade" id="approveApplicationModal<?php echo $app['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Approve Application</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to approve the application from <strong><?php echo $app['first_name'] . ' ' . $app['last_name']; ?></strong> for <strong><?php echo $app['accommodation_name']; ?></strong>?</p>
                                                <p>Upon approval, the student will be notified and you will be able to create a lease agreement.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form method="post" action="">
                                                    <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="fas fa-check me-2"></i>Approve Application
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Reject Application Modal -->
                                <div class="modal fade" id="rejectApplicationModal<?php echo $app['id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reject Application</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to reject the application from <strong><?php echo $app['first_name'] . ' ' . $app['last_name']; ?></strong> for <strong><?php echo $app['accommodation_name']; ?></strong>?</p>
                                                <p>This action cannot be undone. The student will be notified of the rejection.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form method="post" action="">
                                                    <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn btn-danger">
                                                        <i class="fas fa-times me-2"></i>Reject Application
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
