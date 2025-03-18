<?php
include_once __DIR__ . '/config/constants.php';
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/authentication.php';

// Require login
requireLogin();

// Redirect to admin dashboard for admin users
if (hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])) {
    header('Location: admin.php');
    exit;
}

// Get user info
$user = getCurrentUser();

// Get student applications
$applications = getStudentApplications($_SESSION['user_id']);

// Get student leases
$leases = getLeases($_SESSION['user_id']);

// Get student invoices
$invoices = getInvoices($_SESSION['user_id']);

// Get maintenance requests
$maintenanceRequests = getMaintenanceRequests($_SESSION['user_id']);

// Count unpaid invoices
$unpaidInvoices = 0;
foreach ($invoices as $invoice) {
    if (!$invoice['paid']) {
        $unpaidInvoices++;
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>Student Dashboard</h1>
            <p class="lead">Welcome back, <?= $user['username'] ?>!</p>
        </div>
    </div>
    
    <!-- Dashboard Summary -->
    <div class="row mb-5">
        <div class="col-md-3 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-body">
                    <div class="icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3 class="h5"><?= count($applications) ?></h3>
                    <p class="text-muted">Applications</p>
                    <a href="applications.php" class="btn btn-sm btn-outline-primary mt-2">View All</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-body">
                    <div class="icon">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <h3 class="h5"><?= count($leases) ?></h3>
                    <p class="text-muted">Leases</p>
                    <a href="leases.php" class="btn btn-sm btn-outline-primary mt-2">View All</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-body">
                    <div class="icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <h3 class="h5"><?= $unpaidInvoices ?></h3>
                    <p class="text-muted">Unpaid Invoices</p>
                    <a href="invoices.php" class="btn btn-sm btn-outline-primary mt-2">View All</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card dashboard-card h-100">
                <div class="card-body">
                    <div class="icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h3 class="h5"><?= count($maintenanceRequests) ?></h3>
                    <p class="text-muted">Maintenance</p>
                    <a href="maintenance.php" class="btn btn-sm btn-outline-primary mt-2">View All</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Applications -->
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Recent Applications</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($applications)): ?>
                        <p class="text-center text-muted">You haven't made any applications yet.</p>
                        <div class="text-center">
                            <a href="accommodations.php" class="btn btn-primary">Browse Accommodations</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Accommodation</th>
                                        <th>Location</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Applied On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($applications, 0, 5) as $application): ?>
                                        <tr>
                                            <td><?= $application['accommodation_name'] ?></td>
                                            <td><?= $application['location'] ?></td>
                                            <td><?= formatCurrency($application['price_per_month']) ?>/month</td>
                                            <td>
                                                <?php if ($application['status'] == STATUS_PENDING): ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php elseif ($application['status'] == STATUS_APPROVED): ?>
                                                    <span class="badge bg-success">Approved</span>
                                                <?php elseif ($application['status'] == STATUS_REJECTED): ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= formatDate($application['created_at']) ?></td>
                                            <td>
                                                <a href="applications.php?id=<?= $application['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($applications) > 5): ?>
                            <div class="text-center mt-3">
                                <a href="applications.php" class="btn btn-outline-primary">View All Applications</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Unpaid Invoices & Maintenance Requests -->
    <div class="row mb-5">
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Unpaid Invoices</h5>
                </div>
                <div class="card-body">
                    <?php 
                    $unpaidInvoicesList = [];
                    foreach ($invoices as $invoice) {
                        if (!$invoice['paid']) {
                            $unpaidInvoicesList[] = $invoice;
                        }
                    }
                    ?>
                    
                    <?php if (empty($unpaidInvoicesList)): ?>
                        <p class="text-center text-muted">You have no unpaid invoices.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Amount</th>
                                        <th>Due Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($unpaidInvoicesList, 0, 5) as $invoice): ?>
                                        <tr>
                                            <td><?= $invoice['id'] ?></td>
                                            <td><?= formatCurrency($invoice['amount']) ?></td>
                                            <td><?= formatDate($invoice['due_date']) ?></td>
                                            <td>
                                                <a href="invoices.php?id=<?= $invoice['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($unpaidInvoicesList) > 5): ?>
                            <div class="text-center mt-3">
                                <a href="invoices.php" class="btn btn-outline-primary">View All Invoices</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Recent Maintenance Requests</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($maintenanceRequests)): ?>
                        <p class="text-center text-muted">You haven't made any maintenance requests.</p>
                        <div class="text-center">
                            <a href="maintenance.php?action=new" class="btn btn-primary">Submit Request</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Issue</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($maintenanceRequests, 0, 5) as $request): ?>
                                        <tr>
                                            <td><?= $request['issue'] ?></td>
                                            <td>
                                                <?php if ($request['status'] == MAINTENANCE_PENDING): ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php elseif ($request['status'] == MAINTENANCE_IN_PROGRESS): ?>
                                                    <span class="badge bg-info">In Progress</span>
                                                <?php elseif ($request['status'] == MAINTENANCE_COMPLETED): ?>
                                                    <span class="badge bg-success">Completed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= formatDate($request['created_at']) ?></td>
                                            <td>
                                                <a href="maintenance.php?id=<?= $request['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($maintenanceRequests) > 5): ?>
                            <div class="text-center mt-3">
                                <a href="maintenance.php" class="btn btn-outline-primary">View All Requests</a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
