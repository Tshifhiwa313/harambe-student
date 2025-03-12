<?php
/**
 * Admin Dashboard
 * 
 * This page displays the admin dashboard with statistics and recent activities.
 */

require_once '../includes/header.php';

// Require admin authentication
requireAuth([ROLE_MASTER_ADMIN, ROLE_ADMIN]);

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

// Get statistics based on user role
if ($userRole === ROLE_MASTER_ADMIN) {
    // Master Admin sees all statistics
    $accommodationsCount = executeQuery($conn, "SELECT COUNT(*) FROM accommodations")->fetchColumn();
    $studentsCount = executeQuery($conn, "SELECT COUNT(*) FROM users WHERE role = :role", ['role' => ROLE_STUDENT])->fetchColumn();
    $adminsCount = executeQuery($conn, "SELECT COUNT(*) FROM users WHERE role = :role", ['role' => ROLE_ADMIN])->fetchColumn();
    $pendingApplicationsCount = executeQuery($conn, "SELECT COUNT(*) FROM applications WHERE status = :status", ['status' => STATUS_PENDING])->fetchColumn();
    $activeLeaseCount = executeQuery($conn, "SELECT COUNT(*) FROM leases WHERE end_date >= CURRENT_DATE")->fetchColumn();
    $unpaidInvoicesCount = executeQuery($conn, "SELECT COUNT(*) FROM invoices WHERE status = :status", ['status' => INVOICE_UNPAID])->fetchColumn();
    $pendingMaintenanceCount = executeQuery($conn, "SELECT COUNT(*) FROM maintenance_requests WHERE status = :status", ['status' => MAINTENANCE_PENDING])->fetchColumn();
    
    // Get recent applications
    $recentApplications = fetchAll($conn, 
        "SELECT a.*, u.first_name, u.last_name, ac.name as accommodation_name 
        FROM applications a 
        JOIN users u ON a.user_id = u.id 
        JOIN accommodations ac ON a.accommodation_id = ac.id 
        ORDER BY a.created_at DESC LIMIT 5");
    
    // Get recent maintenance requests
    $recentMaintenance = fetchAll($conn, 
        "SELECT m.*, u.first_name, u.last_name, ac.name as accommodation_name 
        FROM maintenance_requests m 
        JOIN users u ON m.user_id = u.id 
        JOIN accommodations ac ON m.accommodation_id = ac.id 
        ORDER BY m.created_at DESC LIMIT 5");
} else {
    // Admin sees only their assigned accommodations statistics
    $adminAccommodations = getAdminAccommodations($conn, $userId);
    $accommodationIds = array_map(function($acc) { return $acc['id']; }, $adminAccommodations);
    
    if (empty($accommodationIds)) {
        $accommodationsCount = 0;
        $pendingApplicationsCount = 0;
        $activeLeaseCount = 0;
        $unpaidInvoicesCount = 0;
        $pendingMaintenanceCount = 0;
        $recentApplications = [];
        $recentMaintenance = [];
    } else {
        $accommodationsCount = count($accommodationIds);
        
        $placeholders = implode(',', array_fill(0, count($accommodationIds), '?'));
        
        // Get counts using prepared statements with multiple placeholders
        $stmt = $conn->prepare("SELECT COUNT(*) FROM applications WHERE accommodation_id IN ($placeholders) AND status = ?");
        $params = array_merge($accommodationIds, [STATUS_PENDING]);
        $stmt->execute($params);
        $pendingApplicationsCount = $stmt->fetchColumn();
        
        $stmt = $conn->prepare("SELECT COUNT(*) FROM leases WHERE accommodation_id IN ($placeholders) AND end_date >= CURRENT_DATE");
        $stmt->execute($accommodationIds);
        $activeLeaseCount = $stmt->fetchColumn();
        
        $stmt = $conn->prepare("SELECT COUNT(*) FROM invoices i JOIN leases l ON i.lease_id = l.id WHERE l.accommodation_id IN ($placeholders) AND i.status = ?");
        $params = array_merge($accommodationIds, [INVOICE_UNPAID]);
        $stmt->execute($params);
        $unpaidInvoicesCount = $stmt->fetchColumn();
        
        $stmt = $conn->prepare("SELECT COUNT(*) FROM maintenance_requests WHERE accommodation_id IN ($placeholders) AND status = ?");
        $params = array_merge($accommodationIds, [MAINTENANCE_PENDING]);
        $stmt->execute($params);
        $pendingMaintenanceCount = $stmt->fetchColumn();
        
        // Get recent applications for assigned accommodations
        $stmt = $conn->prepare(
            "SELECT a.*, u.first_name, u.last_name, ac.name as accommodation_name 
            FROM applications a 
            JOIN users u ON a.user_id = u.id 
            JOIN accommodations ac ON a.accommodation_id = ac.id 
            WHERE a.accommodation_id IN ($placeholders)
            ORDER BY a.created_at DESC LIMIT 5");
        $stmt->execute($accommodationIds);
        $recentApplications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get recent maintenance requests for assigned accommodations
        $stmt = $conn->prepare(
            "SELECT m.*, u.first_name, u.last_name, ac.name as accommodation_name 
            FROM maintenance_requests m 
            JOIN users u ON m.user_id = u.id 
            JOIN accommodations ac ON m.accommodation_id = ac.id 
            WHERE m.accommodation_id IN ($placeholders)
            ORDER BY m.created_at DESC LIMIT 5");
        $stmt->execute($accommodationIds);
        $recentMaintenance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // For regular admin, these are only visible to master admin
    $studentsCount = 0;
    $adminsCount = 0;
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Admin Dashboard</h2>
        <div>
            <a href="../index.php" class="btn btn-outline-primary">
                <i class="fas fa-home me-2"></i>Go to Home
            </a>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row dashboard-stats">
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="stat-card">
                <div class="stat-icon text-primary">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-value"><?php echo $accommodationsCount; ?></div>
                <div class="stat-label">Accommodations</div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="stat-card">
                <div class="stat-icon text-warning">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-value"><?php echo $pendingApplicationsCount; ?></div>
                <div class="stat-label">Pending Applications</div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="stat-card">
                <div class="stat-icon text-success">
                    <i class="fas fa-file-contract"></i>
                </div>
                <div class="stat-value"><?php echo $activeLeaseCount; ?></div>
                <div class="stat-label">Active Leases</div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="stat-card">
                <div class="stat-icon text-danger">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="stat-value"><?php echo $unpaidInvoicesCount; ?></div>
                <div class="stat-label">Unpaid Invoices</div>
            </div>
        </div>
        
        <?php if ($userRole === ROLE_MASTER_ADMIN): ?>
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="stat-card">
                <div class="stat-icon text-info">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-value"><?php echo $studentsCount; ?></div>
                <div class="stat-label">Students</div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="stat-card">
                <div class="stat-icon text-secondary">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-value"><?php echo $adminsCount; ?></div>
                <div class="stat-label">Administrators</div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="stat-card">
                <div class="stat-icon text-primary">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="stat-value"><?php echo $pendingMaintenanceCount; ?></div>
                <div class="stat-label">Pending Maintenance</div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="row">
        <!-- Recent Applications -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-file-alt text-primary me-2"></i>Recent Applications
                        </h5>
                        <a href="applications.php" class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentApplications)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Accommodation</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentApplications as $application): ?>
                                        <tr>
                                            <td><?php echo $application['first_name'] . ' ' . $application['last_name']; ?></td>
                                            <td><?php echo $application['accommodation_name']; ?></td>
                                            <td>
                                                <?php
                                                switch ($application['status']) {
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
                                                        echo '<span class="badge bg-secondary">' . ucfirst($application['status']) . '</span>';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo formatDate($application['created_at'], 'short'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>No recent applications found.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Maintenance Requests -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-tools text-primary me-2"></i>Recent Maintenance Requests
                        </h5>
                        <a href="maintenance.php" class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentMaintenance)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Student</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentMaintenance as $maintenance): ?>
                                        <tr>
                                            <td><?php echo $maintenance['title']; ?></td>
                                            <td><?php echo $maintenance['first_name'] . ' ' . $maintenance['last_name']; ?></td>
                                            <td>
                                                <?php
                                                switch ($maintenance['priority']) {
                                                    case PRIORITY_LOW:
                                                        echo '<span class="badge bg-info">Low</span>';
                                                        break;
                                                    case PRIORITY_MEDIUM:
                                                        echo '<span class="badge bg-warning text-dark">Medium</span>';
                                                        break;
                                                    case PRIORITY_HIGH:
                                                        echo '<span class="badge bg-danger">High</span>';
                                                        break;
                                                    case PRIORITY_EMERGENCY:
                                                        echo '<span class="badge bg-danger">Emergency</span>';
                                                        break;
                                                    default:
                                                        echo '<span class="badge bg-secondary">' . ucfirst($maintenance['priority']) . '</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                switch ($maintenance['status']) {
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
                                                        echo '<span class="badge bg-secondary">' . ucfirst($maintenance['status']) . '</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>No recent maintenance requests found.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($userRole === ROLE_MASTER_ADMIN): ?>
    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt text-primary me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                            <a href="accommodations.php?action=add" class="btn btn-outline-primary btn-lg w-100 py-3">
                                <i class="fas fa-plus-circle mb-2 d-block fa-2x"></i>
                                Add Accommodation
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                            <a href="users.php?action=add" class="btn btn-outline-primary btn-lg w-100 py-3">
                                <i class="fas fa-user-plus mb-2 d-block fa-2x"></i>
                                Add Admin
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                            <a href="applications.php" class="btn btn-outline-primary btn-lg w-100 py-3">
                                <i class="fas fa-check-circle mb-2 d-block fa-2x"></i>
                                Review Applications
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <a href="notifications.php" class="btn btn-outline-primary btn-lg w-100 py-3">
                                <i class="fas fa-envelope mb-2 d-block fa-2x"></i>
                                Send Notifications
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
</div>

<?php require_once '../includes/footer.php'; ?>
