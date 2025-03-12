<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/authentication.php';
require_once 'includes/email.php';
require_once 'includes/pdf.php';

// Require login
requireLogin();

$error = '';
$success = '';

// Handle lease actions
$action = $_GET['action'] ?? '';
$leaseId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Process lease signing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sign_lease'])) {
    $id = intval($_POST['lease_id'] ?? 0);
    
    if ($id) {
        $lease = getLeaseById($id);
        
        if ($lease && $lease['user_id'] == $_SESSION['user_id'] && !$lease['is_signed']) {
            // Update lease to signed status
            update('leases', [
                'is_signed' => 1,
                'signed_at' => date('Y-m-d H:i:s')
            ], 'id', $id);
            
            // Generate PDF for the lease (in a real app, we'd save the actual PDF)
            $pdfContent = generateLeasePDF($lease);
            
            // In a real application with a PDF library, we would save the PDF file here
            // For this implementation, we'll just set a placeholder path
            $pdfPath = 'lease_' . $id . '_' . time() . '.pdf';
            update('leases', ['pdf_path' => $pdfPath], 'id', $id);
            
            $success = 'Lease signed successfully!';
            
            // Create initial invoice
            $invoiceId = insert('invoices', [
                'user_id' => $lease['user_id'],
                'accommodation_id' => $lease['accommodation_id'],
                'lease_id' => $lease['id'],
                'amount' => $lease['monthly_rent'],
                'period_start' => $lease['start_date'],
                'period_end' => date('Y-m-d', strtotime($lease['start_date'] . ' +1 month -1 day')),
                'due_date' => date('Y-m-d', strtotime($lease['start_date'] . ' -7 days')),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($invoiceId) {
                // Get user info for notification
                $user = fetchOne("SELECT * FROM users WHERE id = ?", [$lease['user_id']]);
                
                if ($user) {
                    // Send invoice notification
                    $invoice = fetchOne("SELECT i.*, a.name as accommodation_name 
                                        FROM invoices i 
                                        JOIN accommodations a ON i.accommodation_id = a.id 
                                        WHERE i.id = ?", [$invoiceId]);
                                        
                    sendInvoiceEmail($user['email'], $user['username'], $invoice);
                    
                    if (!empty($user['phone'])) {
                        sendInvoiceSMS($user['phone'], $user['username'], $invoice);
                    }
                }
            }
        } else {
            $error = 'Invalid lease or lease is already signed.';
        }
    } else {
        $error = 'Invalid lease ID.';
    }
}

// Download lease
if ($action === 'download' && $leaseId) {
    $lease = getLeaseById($leaseId);
    
    if ($lease) {
        // Check permissions
        $canAccess = false;
        
        if (hasRole(ROLE_STUDENT)) {
            $canAccess = $lease['user_id'] == $_SESSION['user_id'];
        } elseif (hasRole(ROLE_ADMIN)) {
            $canAccess = $lease['admin_id'] == $_SESSION['user_id'];
        } elseif (hasRole(ROLE_MASTER_ADMIN)) {
            $canAccess = true;
        }
        
        if ($canAccess) {
            // Generate PDF content
            $pdfContent = generateLeasePDF($lease);
            
            // Set headers for download
            header('Content-Type: text/html');
            header('Content-Disposition: attachment; filename="lease_agreement_' . $lease['id'] . '.html"');
            
            // Output the lease HTML (in a real app, this would be a PDF)
            echo $pdfContent;
            exit;
        }
    }
    
    // If we get here, there was an error
    $error = 'Unable to download lease agreement.';
}

// Get lease data based on role and context
if ($leaseId) {
    // View specific lease
    $lease = getLeaseById($leaseId);
    
    if (!$lease) {
        header('Location: leases.php');
        exit;
    }
    
    // Check permission to view this lease
    $canView = false;
    
    if (hasRole(ROLE_STUDENT)) {
        $canView = $lease['user_id'] == $_SESSION['user_id'];
    } elseif (hasRole(ROLE_ADMIN)) {
        $canView = $lease['admin_id'] == $_SESSION['user_id'];
    } elseif (hasRole(ROLE_MASTER_ADMIN)) {
        $canView = true;
    }
    
    if (!$canView) {
        header('Location: leases.php');
        exit;
    }
    
    // Get associated invoices
    $invoices = fetchAll("SELECT * FROM invoices WHERE lease_id = ? ORDER BY created_at DESC", [$lease['id']]);
} else {
    // Leases list
    if (hasRole(ROLE_MASTER_ADMIN)) {
        $leases = getLeases();
    } elseif (hasRole(ROLE_ADMIN)) {
        $adminAccommodations = getAdminAccommodations($_SESSION['user_id']);
        $adminAccommodationIds = array_column($adminAccommodations, 'id');
        
        $leases = [];
        if (!empty($adminAccommodationIds)) {
            $placeholders = implode(',', array_fill(0, count($adminAccommodationIds), '?'));
            $leases = fetchAll("SELECT l.*, u.username, u.email, ac.name as accommodation_name
                               FROM leases l
                               JOIN users u ON l.user_id = u.id
                               JOIN accommodations ac ON l.accommodation_id = ac.id
                               WHERE l.accommodation_id IN ($placeholders)
                               ORDER BY l.created_at DESC", $adminAccommodationIds);
        }
    } else {
        $leases = getLeases($_SESSION['user_id']);
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
    
    <?php if ($leaseId && $lease): ?>
        <!-- View Single Lease -->
        <div class="row mb-4">
            <div class="col-md-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?= hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN]) ? 'admin.php' : 'dashboard.php' ?>">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="leases.php">Leases</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Lease #<?= $lease['id'] ?></li>
                    </ol>
                </nav>
                <h1>Lease Agreement</h1>
            </div>
            <div class="col-md-4 text-end">
                <a href="leases.php?action=download&id=<?= $lease['id'] ?>" class="btn btn-outline-primary">
                    <i class="fas fa-download"></i> Download Agreement
                </a>
                <button class="btn btn-outline-secondary btn-print">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8 order-md-1 order-2">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Lease Agreement Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6>Status</h6>
                                <?php if ($lease['is_signed']): ?>
                                    <span class="badge bg-success">Signed</span>
                                    <p class="text-muted small">Signed on <?= formatDate($lease['signed_at']) ?></p>
                                <?php else: ?>
                                    <span class="badge bg-warning">Pending Signature</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6>Created On</h6>
                                <p><?= formatDate($lease['created_at']) ?></p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6>Accommodation</h6>
                                <p><?= $lease['accommodation_name'] ?></p>
                                <p><i class="fas fa-map-marker-alt"></i> <?= $lease['location'] ?></p>
                            </div>
                            <div class="col-md-6">
                                <?php if (hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])): ?>
                                    <h6>Tenant</h6>
                                    <p><?= $lease['username'] ?> (<?= $lease['email'] ?>)</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6>Lease Period</h6>
                                <p>From <?= formatDate($lease['start_date']) ?> to <?= formatDate($lease['end_date']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6>Duration</h6>
                                <?php
                                $start = new DateTime($lease['start_date']);
                                $end = new DateTime($lease['end_date']);
                                $interval = $start->diff($end);
                                $months = ($interval->y * 12) + $interval->m;
                                ?>
                                <p><?= $months ?> months</p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6>Monthly Rent</h6>
                                <p><?= formatCurrency($lease['monthly_rent']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6>Security Deposit</h6>
                                <p><?= formatCurrency($lease['security_deposit']) ?></p>
                            </div>
                        </div>
                        
                        <?php if (hasRole(ROLE_STUDENT) && !$lease['is_signed']): ?>
                            <div class="mt-4">
                                <form method="post" action="leases.php">
                                    <input type="hidden" name="lease_id" value="<?= $lease['id'] ?>">
                                    <div class="alert alert-info">
                                        <p>Please review the lease agreement carefully before signing. By signing, you agree to all terms and conditions.</p>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" name="sign_lease" class="btn btn-primary btn-lg">
                                            <i class="fas fa-signature"></i> Sign Lease Agreement
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($invoices)): ?>
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Related Invoices</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Amount</th>
                                            <th>Period</th>
                                            <th>Due Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoices as $invoice): ?>
                                            <tr>
                                                <td><?= $invoice['id'] ?></td>
                                                <td><?= formatCurrency($invoice['amount']) ?></td>
                                                <td><?= formatDate($invoice['period_start']) ?> to <?= formatDate($invoice['period_end']) ?></td>
                                                <td><?= formatDate($invoice['due_date']) ?></td>
                                                <td>
                                                    <?php if ($invoice['paid']): ?>
                                                        <span class="badge bg-success">Paid</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Unpaid</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="invoices.php?id=<?= $invoice['id'] ?>" class="btn btn-sm btn-outline-primary">
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
                <?php endif; ?>
            </div>
            
            <div class="col-md-4 order-md-2 order-1 mb-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Lease Summary</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Lease ID</span>
                                <span><?= $lease['id'] ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Status</span>
                                <span><?= $lease['is_signed'] ? 'Signed' : 'Pending Signature' ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Start Date</span>
                                <span><?= formatDate($lease['start_date']) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>End Date</span>
                                <span><?= formatDate($lease['end_date']) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Monthly Rent</span>
                                <span><?= formatCurrency($lease['monthly_rent']) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Security Deposit</span>
                                <span><?= formatCurrency($lease['security_deposit']) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Total Contract Value</span>
                                <?php
                                $start = new DateTime($lease['start_date']);
                                $end = new DateTime($lease['end_date']);
                                $interval = $start->diff($end);
                                $months = ($interval->y * 12) + $interval->m;
                                $totalValue = $lease['monthly_rent'] * $months;
                                ?>
                                <span><?= formatCurrency($totalValue) ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Important Information</h5>
                    </div>
                    <div class="card-body">
                        <p><i class="fas fa-info-circle"></i> Rent is due on the 1st of each month.</p>
                        <p><i class="fas fa-info-circle"></i> Late payments may incur additional fees.</p>
                        <p><i class="fas fa-info-circle"></i> Security deposit will be returned at the end of lease term, less any deductions for damages.</p>
                        <p><i class="fas fa-info-circle"></i> Maintenance requests can be submitted through your dashboard.</p>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Leases List -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h1><?= hasRole(ROLE_STUDENT) ? 'My Lease Agreements' : 'Lease Agreements' ?></h1>
                <p class="lead">
                    <?= hasRole(ROLE_STUDENT) ? 'View and manage your lease agreements.' : 'View and manage student lease agreements.' ?>
                </p>
            </div>
        </div>
        
        <?php if (empty($leases)): ?>
            <div class="alert alert-info">
                <?php if (hasRole(ROLE_STUDENT)): ?>
                    <p class="text-center">You don't have any lease agreements yet. Once your application is approved, a lease will be created.</p>
                <?php else: ?>
                    <p class="text-center">No lease agreements found.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?= hasRole(ROLE_STUDENT) ? 'My Lease Agreements' : 'Lease Agreements List' ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <?php if (hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])): ?>
                                        <th>Student</th>
                                    <?php endif; ?>
                                    <th>Accommodation</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Monthly Rent</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leases as $l): ?>
                                    <tr>
                                        <td><?= $l['id'] ?></td>
                                        <?php if (hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])): ?>
                                            <td><?= $l['username'] ?></td>
                                        <?php endif; ?>
                                        <td><?= $l['accommodation_name'] ?></td>
                                        <td><?= formatDate($l['start_date']) ?></td>
                                        <td><?= formatDate($l['end_date']) ?></td>
                                        <td><?= formatCurrency($l['monthly_rent']) ?></td>
                                        <td>
                                            <?php if ($l['is_signed']): ?>
                                                <span class="badge bg-success">Signed</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Pending Signature</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="leases.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="leases.php?action=download&id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-download"></i> Download
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
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
