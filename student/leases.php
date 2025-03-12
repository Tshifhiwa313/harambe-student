<?php
/**
 * Student Leases
 * 
 * This page allows students to view and manage their lease agreements.
 */

require_once '../includes/header.php';
require_once '../includes/pdf_generator.php';

// Require student authentication
requireAuth(ROLE_STUDENT);

$userId = getCurrentUserId();

// Define action based on GET parameter
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$leaseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sign lease agreement
    if (isset($_POST['sign_lease'])) {
        $leaseId = (int)$_POST['lease_id'];
        
        // Get lease details
        $lease = fetchRow($conn, 
            "SELECT * FROM leases WHERE id = :id AND user_id = :userId", 
            ['id' => $leaseId, 'userId' => $userId]
        );
        
        if (!$lease) {
            setFlashMessage('Lease not found', 'danger');
            redirect('leases.php');
        }
        
        // Check if lease is already signed
        if ($lease['signed']) {
            setFlashMessage('Lease is already signed', 'info');
            redirect("leases.php?action=view&id=$leaseId");
        }
        
        // Update lease to signed status
        $leaseData = [
            'signed' => 1,
            'signed_date' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if (updateRow($conn, 'leases', $leaseData, 'id', $leaseId)) {
            // Send notification
            sendLeaseSignedNotification($conn, $leaseId);
            
            setFlashMessage('Lease signed successfully!', 'success');
            redirect("leases.php?action=view&id=$leaseId");
        } else {
            setFlashMessage('Failed to sign lease. Please try again.', 'danger');
            redirect("leases.php?action=view&id=$leaseId");
        }
    }
}

// Get student's leases
$leases = fetchAll($conn, 
    "SELECT l.*, a.name as accommodation_name, a.address 
    FROM leases l 
    JOIN accommodations a ON l.accommodation_id = a.id 
    WHERE l.user_id = :userId 
    ORDER BY l.start_date DESC", 
    ['userId' => $userId]
);

// Get specific lease for viewing
$lease = null;
if ($action === 'view' && $leaseId > 0) {
    $lease = fetchRow($conn, 
        "SELECT l.*, a.name as accommodation_name, a.address, a.description, a.image_path 
        FROM leases l 
        JOIN accommodations a ON l.accommodation_id = a.id 
        WHERE l.id = :id AND l.user_id = :userId", 
        ['id' => $leaseId, 'userId' => $userId]
    );
    
    if (!$lease) {
        setFlashMessage('Lease not found', 'danger');
        redirect('leases.php');
    }
    
    // Get invoices for this lease
    $invoices = fetchAll($conn, 
        "SELECT * FROM invoices WHERE lease_id = :leaseId ORDER BY due_date DESC", 
        ['leaseId' => $leaseId]
    );
}

// Check if any approved applications without leases
$pendingApprovals = fetchAll($conn, 
    "SELECT a.*, acc.name as accommodation_name 
    FROM applications a 
    JOIN accommodations acc ON a.accommodation_id = acc.id 
    WHERE a.user_id = :userId AND a.status = :status 
    AND NOT EXISTS (
        SELECT 1 FROM leases l 
        WHERE l.user_id = a.user_id AND l.accommodation_id = a.accommodation_id
    )", 
    ['userId' => $userId, 'status' => STATUS_APPROVED]
);
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">My Leases</h2>
        <div>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
    
    <?php if (!empty($pendingApprovals)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            You have <?php echo count($pendingApprovals); ?> approved application<?php echo count($pendingApprovals) > 1 ? 's' : ''; ?> waiting for a lease agreement.
            Please check with administration.
        </div>
    <?php endif; ?>
    
    <?php if ($action === 'list'): ?>
        <!-- Leases List -->
        <?php if (empty($leases)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>You don't have any lease agreements yet.
                <?php if (empty($pendingApprovals)): ?>
                    <a href="../accommodations.php" class="alert-link">Browse accommodations</a> and apply for one.
                <?php else: ?>
                    Your approved application is being processed. A lease agreement will be generated soon.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 g-4">
                <?php foreach ($leases as $lease): ?>
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-header bg-white py-3">
                                <h5 class="mb-0"><?php echo $lease['accommodation_name']; ?></h5>
                            </div>
                            <div class="card-body">
                                <p><i class="fas fa-map-marker-alt me-2"></i><?php echo $lease['address']; ?></p>
                                <p>
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    <?php echo formatDate($lease['start_date']); ?> to <?php echo formatDate($lease['end_date']); ?>
                                </p>
                                <p><i class="fas fa-money-bill-wave me-2"></i><?php echo formatCurrency($lease['monthly_rent']); ?> per month</p>
                                <p>
                                    <i class="fas fa-shield-alt me-2"></i>Security Deposit: 
                                    <?php echo formatCurrency($lease['security_deposit']); ?>
                                </p>
                                <div class="d-flex align-items-center mt-3">
                                    <span class="me-2">Status:</span>
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
                                        echo ' <span class="badge bg-warning text-dark ms-2">Unsigned</span>';
                                    } else {
                                        echo ' <span class="badge bg-primary ms-2">Signed</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="card-footer bg-white">
                                <a href="?action=view&id=<?php echo $lease['id']; ?>" class="btn btn-primary w-100">
                                    <i class="fas fa-eye me-2"></i>View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
    <?php elseif ($action === 'view' && $lease): ?>
        <!-- View Lease Details -->
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0"><i class="fas fa-file-contract me-2"></i>Lease Agreement</h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h5><?php echo $lease['accommodation_name']; ?></h5>
                            <p><i class="fas fa-map-marker-alt me-2"></i><?php echo $lease['address']; ?></p>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Lease Terms</h6>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Start Date:</strong> <?php echo formatDate($lease['start_date']); ?></p>
                                        <p><strong>End Date:</strong> <?php echo formatDate($lease['end_date']); ?></p>
                                        <p><strong>Monthly Rent:</strong> <?php echo formatCurrency($lease['monthly_rent']); ?></p>
                                        <p><strong>Security Deposit:</strong> <?php echo formatCurrency($lease['security_deposit']); ?></p>
                                        <p>
                                            <strong>Status:</strong>
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
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Lease Document</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($lease['pdf_path'])): ?>
                                            <p>Your lease agreement is available for download:</p>
                                            <a href="../uploads/leases/<?php echo $lease['pdf_path']; ?>" class="btn btn-primary" target="_blank">
                                                <i class="fas fa-file-pdf me-2"></i>View Lease Agreement
                                            </a>
                                        <?php else: ?>
                                            <p class="text-warning">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                Lease document is not available yet. Please check back later.
                                            </p>
                                        <?php endif; ?>
                                        
                                        <div class="mt-3">
                                            <p><strong>Signing Status:</strong></p>
                                            <?php if (!$lease['signed']): ?>
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                                    This lease agreement has not been signed yet. Please sign it to complete the process.
                                                </div>
                                                
                                                <?php if (!empty($lease['pdf_path'])): ?>
                                                    <form method="post" action="">
                                                        <input type="hidden" name="lease_id" value="<?php echo $lease['id']; ?>">
                                                        <button type="submit" name="sign_lease" class="btn btn-success">
                                                            <i class="fas fa-signature me-2"></i>Sign Lease Agreement
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="alert alert-success">
                                                    <i class="fas fa-check-circle me-2"></i>
                                                    This lease agreement was signed on <?php echo formatDate($lease['signed_date']); ?>.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Accommodation Details</h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($lease['image_path'])): ?>
                                    <img src="../uploads/accommodations/<?php echo $lease['image_path']; ?>" class="img-fluid rounded mb-3" alt="<?php echo $lease['accommodation_name']; ?>">
                                <?php endif; ?>
                                
                                <p><?php echo $lease['description']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Related Invoices -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Related Invoices</h4>
                        <a href="invoices.php" class="btn btn-sm btn-outline-primary">View All Invoices</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($invoices)): ?>
                            <div class="p-4 text-center">
                                <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                                <p>No invoices have been generated for this lease yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Amount</th>
                                            <th>Due Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoices as $invoice): ?>
                                            <tr>
                                                <td>INV-<?php echo str_pad($invoice['id'], 6, '0', STR_PAD_LEFT); ?></td>
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
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <?php if (!empty($invoice['pdf_path'])): ?>
                                                            <a href="../uploads/invoices/<?php echo $invoice['pdf_path']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                                                <i class="fas fa-file-pdf"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="invoices.php?action=view&id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </div>
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
                        <h4 class="mb-0"><i class="fas fa-info-circle me-2"></i>Lease Information</h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h5 class="mb-3">Important Dates</h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Lease Created</span>
                                    <span><?php echo formatDate($lease['created_at']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Start Date</span>
                                    <span><?php echo formatDate($lease['start_date']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>End Date</span>
                                    <span><?php echo formatDate($lease['end_date']); ?></span>
                                </li>
                                <?php if ($lease['signed']): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Signed Date</span>
                                        <span><?php echo formatDate($lease['signed_date']); ?></span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        
                        <div class="mb-3">
                            <h5 class="mb-3">Financial Information</h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Monthly Rent</span>
                                    <span><?php echo formatCurrency($lease['monthly_rent']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Security Deposit</span>
                                    <span><?php echo formatCurrency($lease['security_deposit']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Total Lease Value</span>
                                    <span>
                                        <?php 
                                        $months = (strtotime($lease['end_date']) - strtotime($lease['start_date'])) / (30 * 24 * 60 * 60);
                                        echo formatCurrency($lease['monthly_rent'] * $months); 
                                        ?>
                                    </span>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="mb-3">
                            <h5 class="mb-3">Quick Links</h5>
                            <div class="d-grid gap-2">
                                <a href="invoices.php" class="btn btn-outline-primary">
                                    <i class="fas fa-file-invoice-dollar me-2"></i>View All Invoices
                                </a>
                                <a href="maintenance.php?action=add" class="btn btn-outline-primary">
                                    <i class="fas fa-tools me-2"></i>Submit Maintenance Request
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0"><i class="fas fa-question-circle me-2"></i>Need Help?</h4>
                    </div>
                    <div class="card-body">
                        <p>If you have any questions or need assistance with your lease, please contact the administration:</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-envelope me-2"></i>Email: admin@harambee.com</li>
                            <li><i class="fas fa-phone me-2"></i>Phone: +27 12 345 6789</li>
                            <li><i class="fas fa-clock me-2"></i>Office Hours: Mon-Fri, 8am-5pm</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
