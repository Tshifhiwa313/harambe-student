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

// Handle invoice actions
$action = $_GET['action'] ?? '';
$invoiceId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$leaseId = isset($_GET['lease_id']) ? intval($_GET['lease_id']) : 0;

// Process new invoice creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invoice']) && hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])) {
    $lid = intval($_POST['lease_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $periodStart = $_POST['period_start'] ?? '';
    $periodEnd = $_POST['period_end'] ?? '';
    $dueDate = $_POST['due_date'] ?? '';
    
    if ($lid && $amount > 0 && !empty($periodStart) && !empty($periodEnd) && !empty($dueDate)) {
        $lease = getLeaseById($lid);
        
        if ($lease) {
            // Check if admin is authorized to manage this lease
            $canManage = hasRole(ROLE_MASTER_ADMIN) || 
                         isAdminAssignedToAccommodation($_SESSION['user_id'], $lease['accommodation_id']);
            
            if ($canManage) {
                // Create invoice
                $invoiceId = insert('invoices', [
                    'user_id' => $lease['user_id'],
                    'accommodation_id' => $lease['accommodation_id'],
                    'lease_id' => $lease['id'],
                    'amount' => $amount,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'due_date' => $dueDate,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                if ($invoiceId) {
                    $success = 'Invoice created successfully.';
                    
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
                } else {
                    $error = 'Failed to create invoice.';
                }
            } else {
                $error = 'You are not authorized to create invoices for this lease.';
            }
        } else {
            $error = 'Lease not found.';
        }
    } else {
        $error = 'Please fill all required fields with valid values.';
    }
}

// Process invoice payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid']) && hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])) {
    $id = intval($_POST['invoice_id'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? '';
    $referenceNumber = $_POST['reference_number'] ?? '';
    
    if ($id) {
        $invoice = fetchOne("SELECT i.*, a.admin_id FROM invoices i JOIN accommodations a ON i.accommodation_id = a.id WHERE i.id = ?", [$id]);
        
        if ($invoice) {
            // Check if admin is authorized to manage this invoice
            $canManage = hasRole(ROLE_MASTER_ADMIN) || 
                         $invoice['admin_id'] == $_SESSION['user_id'];
            
            if ($canManage) {
                // Mark invoice as paid
                update('invoices', [
                    'paid' => 1,
                    'paid_at' => date('Y-m-d H:i:s'),
                    'payment_method' => $paymentMethod,
                    'reference_number' => $referenceNumber
                ], 'id', $id);
                
                $success = 'Invoice marked as paid.';
                
                // Send notification to student
                $user = fetchOne("SELECT * FROM users WHERE id = ?", [$invoice['user_id']]);
                
                if ($user) {
                    // In a real application, we would send a payment confirmation email here
                }
            } else {
                $error = 'You are not authorized to manage this invoice.';
            }
        } else {
            $error = 'Invoice not found.';
        }
    } else {
        $error = 'Invalid invoice ID.';
    }
}

// Download invoice
if ($action === 'download' && $invoiceId) {
    $invoice = fetchOne("SELECT i.*, a.admin_id, a.name as accommodation_name 
                        FROM invoices i 
                        JOIN accommodations a ON i.accommodation_id = a.id 
                        WHERE i.id = ?", [$invoiceId]);
    
    if ($invoice) {
        // Check permissions
        $canAccess = false;
        
        if (hasRole(ROLE_STUDENT)) {
            $canAccess = $invoice['user_id'] == $_SESSION['user_id'];
        } elseif (hasRole(ROLE_ADMIN)) {
            $canAccess = $invoice['admin_id'] == $_SESSION['user_id'];
        } elseif (hasRole(ROLE_MASTER_ADMIN)) {
            $canAccess = true;
        }
        
        if ($canAccess) {
            // Generate PDF content
            $pdfContent = generateInvoicePDF($invoice);
            
            // Set headers for download
            header('Content-Type: text/html');
            header('Content-Disposition: attachment; filename="invoice_' . $invoice['id'] . '.html"');
            
            // Output the invoice HTML (in a real app, this would be a PDF)
            echo $pdfContent;
            exit;
        }
    }
    
    // If we get here, there was an error
    $error = 'Unable to download invoice.';
}

// Get invoice data based on role and context
if ($invoiceId) {
    // View specific invoice
    $invoice = fetchOne("SELECT i.*, a.name as accommodation_name, a.admin_id, a.location 
                        FROM invoices i 
                        JOIN accommodations a ON i.accommodation_id = a.id 
                        WHERE i.id = ?", [$invoiceId]);
    
    if (!$invoice) {
        header('Location: invoices.php');
        exit;
    }
    
    // Check permission to view this invoice
    $canView = false;
    
    if (hasRole(ROLE_STUDENT)) {
        $canView = $invoice['user_id'] == $_SESSION['user_id'];
    } elseif (hasRole(ROLE_ADMIN)) {
        $canView = $invoice['admin_id'] == $_SESSION['user_id'];
    } elseif (hasRole(ROLE_MASTER_ADMIN)) {
        $canView = true;
    }
    
    if (!$canView) {
        header('Location: invoices.php');
        exit;
    }
    
    // Get lease information
    $lease = getLeaseById($invoice['lease_id']);
    
    // Get student information
    $student = fetchOne("SELECT * FROM users WHERE id = ?", [$invoice['user_id']]);
} elseif ($leaseId && hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])) {
    // New invoice form
    $lease = getLeaseById($leaseId);
    
    if (!$lease) {
        header('Location: leases.php');
        exit;
    }
    
    // Check permission to create invoice for this lease
    $canCreate = hasRole(ROLE_MASTER_ADMIN) || 
                 isAdminAssignedToAccommodation($_SESSION['user_id'], $lease['accommodation_id']);
    
    if (!$canCreate) {
        header('Location: invoices.php');
        exit;
    }
    
    // Get student information
    $student = fetchOne("SELECT * FROM users WHERE id = ?", [$lease['user_id']]);
} else {
    // Invoices list
    if (hasRole(ROLE_MASTER_ADMIN)) {
        $invoices = getInvoices();
    } elseif (hasRole(ROLE_ADMIN)) {
        $adminId = $_SESSION['user_id'];
        $invoices = getInvoices(null, null);
        
        // Filter for admin's accommodations
        $adminAccommodations = getAdminAccommodations($adminId);
        $adminAccommodationIds = array_column($adminAccommodations, 'id');
        
        $filteredInvoices = [];
        foreach ($invoices as $invoice) {
            if (in_array($invoice['accommodation_id'], $adminAccommodationIds)) {
                $filteredInvoices[] = $invoice;
            }
        }
        
        $invoices = $filteredInvoices;
    } else {
        $invoices = getInvoices($_SESSION['user_id']);
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
    
    <?php if ($invoiceId && $invoice): ?>
        <!-- View Single Invoice -->
        <div class="row mb-4">
            <div class="col-md-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?= hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN]) ? 'admin.php' : 'dashboard.php' ?>">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="invoices.php">Invoices</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Invoice #<?= $invoice['id'] ?></li>
                    </ol>
                </nav>
                <h1>Invoice</h1>
            </div>
            <div class="col-md-4 text-end">
                <a href="invoices.php?action=download&id=<?= $invoice['id'] ?>" class="btn btn-outline-primary">
                    <i class="fas fa-download"></i> Download Invoice
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
                        <h5 class="mb-0">Invoice #<?= $invoice['id'] ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="text-muted">Billed To</h6>
                                <p>
                                    <strong><?= $student['username'] ?></strong><br>
                                    <?= $student['email'] ?><br>
                                    <?= !empty($student['phone']) ? $student['phone'] . '<br>' : '' ?>
                                </p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <h6 class="text-muted">Invoice Details</h6>
                                <p>
                                    <strong>Invoice #:</strong> <?= $invoice['id'] ?><br>
                                    <strong>Date Issued:</strong> <?= formatDate($invoice['created_at']) ?><br>
                                    <strong>Due Date:</strong> <?= formatDate($invoice['due_date']) ?><br>
                                    <strong>Status:</strong> 
                                    <?php if ($invoice['paid']): ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Unpaid</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-muted">Accommodation</h6>
                                <p>
                                    <strong><?= $invoice['accommodation_name'] ?></strong><br>
                                    <?= $invoice['location'] ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Description</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            Rent for period <?= formatDate($invoice['period_start']) ?> to <?= formatDate($invoice['period_end']) ?>
                                        </td>
                                        <td class="text-end"><?= formatCurrency($invoice['amount']) ?></td>
                                    </tr>
                                    <?php if (!empty($invoice['late_fee']) && $invoice['late_fee'] > 0): ?>
                                        <tr>
                                            <td>Late Fee</td>
                                            <td class="text-end"><?= formatCurrency($invoice['late_fee']) ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th class="text-end">Total</th>
                                        <th class="text-end"><?= formatCurrency($invoice['amount'] + ($invoice['late_fee'] ?? 0)) ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <?php if ($invoice['paid']): ?>
                            <div class="alert alert-success">
                                <h6><i class="fas fa-check-circle"></i> Payment Received</h6>
                                <p>
                                    Paid on: <?= formatDate($invoice['paid_at']) ?><br>
                                    Method: <?= $invoice['payment_method'] ?><br>
                                    <?php if (!empty($invoice['reference_number'])): ?>
                                        Reference: <?= $invoice['reference_number'] ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <h6><i class="fas fa-exclamation-circle"></i> Payment Due</h6>
                                <p>Please make payment by <?= formatDate($invoice['due_date']) ?> to avoid late fees.</p>
                            </div>
                            
                            <?php if (hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])): ?>
                                <div class="card mt-4">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0">Mark as Paid</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" action="invoices.php">
                                            <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                                            
                                            <div class="mb-3">
                                                <label for="payment_method" class="form-label">Payment Method</label>
                                                <select class="form-select" id="payment_method" name="payment_method" required>
                                                    <option value="">Select Payment Method</option>
                                                    <option value="Cash">Cash</option>
                                                    <option value="Bank Transfer">Bank Transfer</option>
                                                    <option value="Credit Card">Credit Card</option>
                                                    <option value="Debit Card">Debit Card</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="reference_number" class="form-label">Reference Number</label>
                                                <input type="text" class="form-control" id="reference_number" name="reference_number" placeholder="Enter payment reference (optional)">
                                            </div>
                                            
                                            <button type="submit" name="mark_paid" class="btn btn-success">Mark as Paid</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 order-md-2 order-1 mb-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Payment Information</h5>
                    </div>
                    <div class="card-body">
                        <p>Please use one of the following payment methods:</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-building"></i> <strong>Bank Transfer</strong></li>
                            <li>Bank: National Bank of South Africa</li>
                            <li>Account Name: Harambee Student Living</li>
                            <li>Account Number: 1234567890</li>
                            <li>Branch Code: 12345</li>
                            <li>Reference: INV-<?= $invoice['id'] ?></li>
                            <li class="mt-3"><i class="fas fa-credit-card"></i> <strong>Online Payment</strong></li>
                            <li>Visit our website and use the payment portal</li>
                            <li class="mt-3"><i class="fas fa-money-bill"></i> <strong>Cash Payment</strong></li>
                            <li>At our office during business hours</li>
                        </ul>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Lease Information</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Lease ID</span>
                                <span><?= $lease['id'] ?></span>
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
                        </ul>
                        <div class="mt-3">
                            <a href="leases.php?id=<?= $lease['id'] ?>" class="btn btn-outline-primary">View Lease</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    
    <?php elseif ($leaseId && $lease && hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])): ?>
        <!-- Create New Invoice -->
        <div class="row mb-4">
            <div class="col-md-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="admin.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="leases.php">Leases</a></li>
                        <li class="breadcrumb-item"><a href="leases.php?id=<?= $lease['id'] ?>">Lease #<?= $lease['id'] ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page">New Invoice</li>
                    </ol>
                </nav>
                <h1>Create New Invoice</h1>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Invoice Details</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="invoices.php">
                            <input type="hidden" name="lease_id" value="<?= $lease['id'] ?>">
                            
                            <div class="mb-3">
                                <label for="amount" class="form-label">Invoice Amount *</label>
                                <div class="input-group">
                                    <span class="input-group-text">R</span>
                                    <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="<?= $lease['monthly_rent'] ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="period_start" class="form-label">Period Start Date *</label>
                                    <input type="date" class="form-control datepicker" id="period_start" name="period_start" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="period_end" class="form-label">Period End Date *</label>
                                    <input type="date" class="form-control datepicker" id="period_end" name="period_end" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="due_date" class="form-label">Due Date *</label>
                                    <input type="date" class="form-control datepicker" id="due_date" name="due_date" required>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="create_invoice" class="btn btn-primary">Create Invoice</button>
                                <a href="leases.php?id=<?= $lease['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Lease Information</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Lease ID</span>
                                <span><?= $lease['id'] ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Student</span>
                                <span><?= $student['username'] ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Accommodation</span>
                                <span><?= $lease['accommodation_name'] ?></span>
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
                        </ul>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Invoice Tips</h5>
                    </div>
                    <div class="card-body">
                        <ul>
                            <li>The standard invoice amount is the monthly rent</li>
                            <li>Period should typically be one month</li>
                            <li>Due date is usually set to the 1st of the month</li>
                            <li>Late fees can be added after the due date</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Invoices List -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h1><?= hasRole(ROLE_STUDENT) ? 'My Invoices' : 'Invoices' ?></h1>
                <p class="lead">
                    <?= hasRole(ROLE_STUDENT) ? 'View and manage your accommodation invoices.' : 'View and manage student invoices.' ?>
                </p>
            </div>
        </div>
        
        <?php if (empty($invoices)): ?>
            <div class="alert alert-info">
                <?php if (hasRole(ROLE_STUDENT)): ?>
                    <p class="text-center">You don't have any invoices yet.</p>
                <?php else: ?>
                    <p class="text-center">No invoices found.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?= hasRole(ROLE_STUDENT) ? 'My Invoices' : 'Invoices List' ?></h5>
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
                                    <th>Amount</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $inv): ?>
                                    <tr>
                                        <td><?= $inv['id'] ?></td>
                                        <?php if (hasRole([ROLE_MASTER_ADMIN, ROLE_ADMIN])): ?>
                                            <td><?= $inv['username'] ?></td>
                                        <?php endif; ?>
                                        <td><?= $inv['accommodation_name'] ?></td>
                                        <td><?= formatCurrency($inv['amount']) ?></td>
                                        <td><?= formatDate($inv['due_date']) ?></td>
                                        <td>
                                            <?php if ($inv['paid']): ?>
                                                <span class="badge bg-success">Paid</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Unpaid</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="invoices.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="invoices.php?action=download&id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-secondary">
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
