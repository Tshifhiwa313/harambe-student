<?php
/**
 * Student Dashboard
 * 
 * This page displays the student dashboard with an overview of their accommodations,
 * applications, leases, invoices, and maintenance requests.
 */

require_once '../includes/header.php';

// Require student authentication
requireAuth(ROLE_STUDENT);

$userId = getCurrentUserId();

// Get student profile information
$student = getUser($conn, $userId);

// Get active lease (if any)
$activeLease = getActiveLease($conn, $userId);

// Get active accommodation details (if has active lease)
$activeAccommodation = null;
if ($activeLease) {
    $activeAccommodation = getAccommodation($conn, $activeLease['accommodation_id']);
}

// Get pending applications
$pendingApplications = fetchAll($conn, 
    "SELECT app.*, a.name as accommodation_name 
    FROM applications app 
    JOIN accommodations a ON app.accommodation_id = a.id 
    WHERE app.user_id = :userId AND app.status = :status", 
    ['userId' => $userId, 'status' => STATUS_PENDING]
);

// Get recent applications
$recentApplications = fetchAll($conn, 
    "SELECT app.*, a.name as accommodation_name 
    FROM applications app 
    JOIN accommodations a ON app.accommodation_id = a.id 
    WHERE app.user_id = :userId 
    ORDER BY app.created_at DESC LIMIT 5", 
    ['userId' => $userId]
);

// Get unpaid invoices
$unpaidInvoices = fetchAll($conn, 
    "SELECT i.*, l.monthly_rent, a.name as accommodation_name 
    FROM invoices i 
    JOIN leases l ON i.lease_id = l.id 
    JOIN accommodations a ON l.accommodation_id = a.id 
    WHERE l.user_id = :userId AND i.status != :status 
    ORDER BY i.due_date ASC", 
    ['userId' => $userId, 'status' => INVOICE_PAID]
);

// Calculate total due
$totalDue = calculateTotalDue($conn, $userId);

// Get maintenance requests
$maintenanceRequests = fetchAll($conn, 
    "SELECT m.*, a.name as accommodation_name 
    FROM maintenance_requests m 
    JOIN accommodations a ON m.accommodation_id = a.id 
    WHERE m.user_id = :userId 
    ORDER BY 
        CASE 
            WHEN m.status = 'pending' THEN 1
            WHEN m.status = 'in_progress' THEN 2
            WHEN m.status = 'completed' THEN 3
            WHEN m.status = 'cancelled' THEN 4
            ELSE 5
        END, 
        m.created_at DESC 
    LIMIT 5", 
    ['userId' => $userId]
);

// Get unread notifications count
$notificationsCount = countUnreadNotifications($conn, $userId);

// Get upcoming notifications
$notifications = getUserNotifications($conn, $userId, false, 5);
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="page-title">Student Dashboard</h2>
            <p class="text-muted">Welcome back, <?php echo $student['first_name']; ?>!</p>
        </div>
        <div class="col-md-4">
            <div class="d-flex justify-content-end">
                <a href="../accommodations.php" class="btn btn-primary">
                    <i class="fas fa-building me-2"></i>Browse Accommodations
                </a>
            </div>
        </div>
    </div>
    
    <!-- Alert for pending applications -->
    <?php if (!empty($pendingApplications)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            You have <?php echo count($pendingApplications); ?> pending application<?php echo count($pendingApplications) > 1 ? 's' : ''; ?>.
            <a href="applications.php" class="alert-link">View details</a>.
        </div>
    <?php endif; ?>
    
    <!-- Alert for overdue invoices -->
    <?php 
    $overdueCount = 0;
    foreach ($unpaidInvoices as $invoice) {
        if (strtotime($invoice['due_date']) < time()) {
            $overdueCount++;
        }
    }
    if ($overdueCount > 0): 
    ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i>
            You have <?php echo $overdueCount; ?> overdue invoice<?php echo $overdueCount > 1 ? 's' : ''; ?> that require immediate attention.
            <a href="invoices.php" class="alert-link">View and pay now</a>.
        </div>
    <?php endif; ?>
    
    <!-- Current Accommodation Section -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <?php if ($activeAccommodation && $activeLease): ?>
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0">
                            <i class="fas fa-home me-2"></i>Your Current Accommodation
                        </h4>
                    </div>
                    <div class="card-body p-0">
                        <div class="row g-0">
                            <div class="col-md-4">
                                <?php if (!empty($activeAccommodation['image_path'])): ?>
                                    <img src="../uploads/accommodations/<?php echo $activeAccommodation['image_path']; ?>" class="img-fluid rounded-start" alt="<?php echo $activeAccommodation['name']; ?>" style="height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center h-100 bg-light" style="min-height: 200px;">
                                        <i class="fas fa-building fa-5x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-8">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo $activeAccommodation['name']; ?></h5>
                                    <p class="card-text">
                                        <i class="fas fa-map-marker-alt me-2"></i><?php echo $activeAccommodation['address']; ?>
                                    </p>
                                    <p class="card-text">
                                        <strong>Lease Period:</strong> <?php echo formatDate($activeLease['start_date']); ?> to <?php echo formatDate($activeLease['end_date']); ?>
                                    </p>
                                    <p class="card-text">
                                        <strong>Monthly Rent:</strong> <?php echo formatCurrency($activeLease['monthly_rent']); ?>
                                    </p>
                                    <div class="d-flex gap-2 mt-3">
                                        <a href="leases.php" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-file-contract me-1"></i>View Lease
                                        </a>
                                        <a href="maintenance.php?action=add" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-tools me-1"></i>Report Issue
                                        </a>
                                        <a href="invoices.php" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-file-invoice-dollar me-1"></i>View Invoices
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0">
                            <i class="fas fa-home me-2"></i>No Active Accommodation
                        </h4>
                    </div>
                    <div class="card-body text-center py-5">
                        <i class="fas fa-building fa-4x text-muted mb-3"></i>
                        <h5>You don't have an active lease yet</h5>
                        <p class="mb-4">Browse our accommodations and apply for one that suits your needs.</p>
                        <a href="../accommodations.php" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Find Accommodation
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-4">
            <div class="row h-100">
                <div class="col-12">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white py-3">
                            <h4 class="mb-0">
                                <i class="fas fa-file-invoice-dollar me-2"></i>Financial Summary
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-column h-100">
                                <div class="mb-3">
                                    <span class="d-block text-muted">Total Balance Due</span>
                                    <span class="d-block fs-4 fw-bold"><?php echo formatCurrency($totalDue); ?></span>
                                </div>
                                
                                <div class="mb-3">
                                    <span class="d-block text-muted">Unpaid Invoices</span>
                                    <span class="d-block fs-5"><?php echo count($unpaidInvoices); ?></span>
                                </div>
                                
                                <?php if (!empty($unpaidInvoices)): ?>
                                    <div class="mb-3">
                                        <span class="d-block text-muted">Next Payment Due</span>
                                        <span class="d-block">
                                            <?php echo formatCurrency($unpaidInvoices[0]['amount']); ?> on 
                                            <?php echo formatDate($unpaidInvoices[0]['due_date']); ?>
                                            <?php 
                                            $daysUntilDue = (strtotime($unpaidInvoices[0]['due_date']) - time()) / 86400;
                                            if ($daysUntilDue < 0) {
                                                echo ' <span class="badge bg-danger">Overdue</span>';
                                            } elseif ($daysUntilDue < 7) {
                                                echo ' <span class="badge bg-warning text-dark">Due soon</span>';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-auto">
                                    <a href="invoices.php" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-file-invoice-dollar me-2"></i>Manage Payments
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Applications and Maintenance Section -->
    <div class="row mb-4">
        <!-- Recent Applications -->
        <div class="col-lg-6 mb-4 mb-lg-0">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-file-alt me-2"></i>Recent Applications
                    </h4>
                    <a href="applications.php" class="btn btn-sm btn-outline-primary">
                        View All
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentApplications)): ?>
                        <div class="p-4 text-center">
                            <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                            <p>You haven't submitted any applications yet.</p>
                            <a href="../accommodations.php" class="btn btn-sm btn-primary">Browse Accommodations</a>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentApplications as $app): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><?php echo $app['accommodation_name']; ?></h6>
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
                                    </div>
                                    <small class="text-muted">Applied on: <?php echo formatDate($app['created_at']); ?></small>
                                    <?php if ($app['status'] === STATUS_APPROVED): ?>
                                        <div class="mt-2">
                                            <a href="leases.php" class="btn btn-sm btn-outline-success">View Lease</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Maintenance Requests -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fas fa-tools me-2"></i>Maintenance Requests
                    </h4>
                    <div>
                        <a href="maintenance.php?action=add" class="btn btn-sm btn-primary me-2">
                            New Request
                        </a>
                        <a href="maintenance.php" class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($maintenanceRequests)): ?>
                        <div class="p-4 text-center">
                            <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                            <p>You don't have any maintenance requests yet.</p>
                            <a href="maintenance.php?action=add" class="btn btn-sm btn-primary">Report an Issue</a>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($maintenanceRequests as $request): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><?php echo $request['title']; ?></h6>
                                        <?php
                                        switch ($request['status']) {
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
                                                echo '<span class="badge bg-secondary">' . ucfirst($request['status']) . '</span>';
                                        }
                                        ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo $request['accommodation_name']; ?> • 
                                        <?php echo formatDate($request['created_at']); ?>
                                    </small>
                                    <?php if ($request['status'] !== MAINTENANCE_COMPLETED && $request['status'] !== MAINTENANCE_CANCELLED): ?>
                                        <div class="mt-2">
                                            <a href="maintenance.php?action=view&id=<?php echo $request['id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Notifications Section -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h4 class="mb-0">
                        <i class="fas fa-bell me-2"></i>Recent Notifications
                        <?php if ($notificationsCount > 0): ?>
                            <span class="badge bg-danger ms-2"><?php echo $notificationsCount; ?></span>
                        <?php endif; ?>
                    </h4>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($notifications)): ?>
                        <div class="p-4 text-center">
                            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                            <p>You don't have any notifications yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="list-group-item <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>">
                                    <div class="d-flex align-items-center">
                                        <?php 
                                        $iconClass = 'fas fa-bell';
                                        switch ($notification['type']) {
                                            case 'application':
                                                $iconClass = 'fas fa-file-alt';
                                                break;
                                            case 'lease':
                                                $iconClass = 'fas fa-file-contract';
                                                break;
                                            case 'invoice':
                                                $iconClass = 'fas fa-file-invoice-dollar';
                                                break;
                                            case 'maintenance':
                                                $iconClass = 'fas fa-tools';
                                                break;
                                        }
                                        ?>
                                        <div class="flex-shrink-0 me-3">
                                            <i class="<?php echo $iconClass; ?> fa-lg"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div><?php echo $notification['message']; ?></div>
                                            <small class="text-muted"><?php echo formatDate($notification['created_at'], 'datetime'); ?></small>
                                        </div>
                                        <?php if (!$notification['is_read']): ?>
                                            <div class="flex-shrink-0 ms-3">
                                                <span class="badge bg-primary">New</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
