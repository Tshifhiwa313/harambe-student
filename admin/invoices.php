<?php
/**
 * Admin Invoices Management
 * 
 * This page allows administrators to view, create, and manage invoices.
 */

require_once '../includes/header.php';
require_once '../includes/pdf_generator.php';

// Require admin authentication
requireAuth([ROLE_MASTER_ADMIN, ROLE_ADMIN]);

$userId = getCurrentUserId();
$userRole = getCurrentUserRole();

// Define action based on GET parameter
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle invoice actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update invoice status
    if (isset($_POST['update_status'])) {
        $invoiceId = (int)$_POST['invoice_id'];
        $newStatus = $_POST['status'];
        
        // Validation
        $errors = [];
        
        if (!in_array($newStatus, [INVOICE_PAID, INVOICE_UNPAID, INVOICE_OVERDUE])) {
            $errors[] = 'Invalid status selected';
        }
        
        // Get invoice details
        $invoice = getInvoice($conn, $invoiceId);
        
        if (!$invoice) {
            $errors[] = 'Invoice not found';
        } else {
            // Get lease details to check permission
            $lease = getLease($conn, $invoice['lease_id']);
            
            if (!$lease) {
                $errors[] = 'Associated lease not found';
            } else {
                // Check permission for regular admin
                if ($userRole !== ROLE_MASTER_ADMIN && !isAdminAssignedToAccommodation($conn, $userId, $lease['accommodation_id'])) {
                    $errors[] = 'You do not have permission to update this invoice';
                }
            }
        }
        
        // Update invoice if no errors
        if (empty($errors)) {
            $invoiceData = [
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (updateRow($conn, 'invoices', $invoiceData, 'id', $invoiceId)) {
                // Get student details for notification
                $student = getUser($conn, $lease['user_id']);
                $accommodation = getAccommodation($conn, $lease['accommodation_id']);
                
                // Create notification for student
                $notificationData = [
                    'user_id' => $lease['user_id'],
                    'message' => "Your invoice status has been updated to " . ucfirst($newStatus) . " for " . $accommodation['name'],
                    'type' => 'invoice',
                    'is_read' => 0
                ];
                
                insertRow($conn, 'notifications', $notificationData);
                
                // Send email notification if status changed to paid
                if ($newStatus === INVOICE_PAID && $student) {
                    $subject = "Invoice Payment Confirmed for " . $accommodation['name'];
                    $message = "Hello {$student['first_name']},<br><br>";
                    $message .= "Your payment of " . formatCurrency($invoice['amount']) . " for your accommodation at {$accommodation['name']} has been received and confirmed.<br><br>";
                    $message .= "Thank you for your payment.<br><br>";
                    $message .= "Regards,<br>" . APP_NAME;
                    
                    sendEmail($student['email'], $subject, $message);
                    
                    // Send SMS if phone number available
                    if (!empty($student['phone'])) {
                        $smsMessage = "Payment confirmed: " . formatCurrency($invoice['amount']) . " for " . $accommodation['name'] . ". Thank you.";
                        sendTwilioSMS($student['phone'], $smsMessage);
                    }
                }
                
                setFlashMessage('Invoice status updated successfully!', 'success');
                redirect("invoices.php?action=view&id=$invoiceId");
            } else {
                $errors[] = 'Failed to update invoice status';
            }
        }
    }
    
    // Send payment reminder
    if (isset($_POST['send_reminder'])) {
        $invoiceId = (int)$_POST['invoice_id'];
        
        // Get invoice details
        $invoice = getInvoice($conn, $invoiceId);
        
        if (!$invoice) {
            setFlashMessage('Invoice not found', 'danger');
            redirect('invoices.php');
        }
        
        // Get lease details to check permission
        $lease = getLease($conn, $invoice['lease_id']);
        
        if (!$lease) {
            setFlashMessage('Associated lease not found', 'danger');
            redirect('invoices.php');
        }
        
        // Check permission for regular admin
        if ($userRole !== ROLE_MASTER_ADMIN && !isAdminAssignedToAccommodation($conn, $userId, $lease['accommodation_id'])) {
            setFlashMessage('You do not have permission to send reminders for this invoice', 'danger');
            redirect('invoices.php');
        }
        
        // Check if invoice is unpaid
        if ($invoice['status'] !== INVOICE_UNPAID && $invoice['status'] !== INVOICE_OVERDUE) {
            setFlashMessage('Cannot send reminder for paid invoices', 'warning');
            redirect("invoices.php?action=view&id=$invoiceId");
        }
        
        // Send reminder
        if (sendInvoiceReminderNotification($conn, $invoiceId)) {
            setFlashMessage('Payment reminder sent successfully!', 'success');
        } else {
            setFlashMessage('Failed to send payment reminder', 'danger');
        }
        
        redirect("invoices.php?action=view&id=$invoiceId");
    }
}

// Get invoice details if viewing
$invoice = null;
if (($action === 'view' || $action === 'edit') && $invoiceId > 0) {
    $invoice = fetchRow($conn, 
        "SELECT i.*, l.user_id, l.accommodation_id, l.monthly_rent, l.start_date, l.end_date, 
        u.first_name, u.last_name, u.email, u.phone, 
        a.name as accommodation_name, a.address 
        FROM invoices i 
        JOIN leases l ON i.lease_id = l.id 
        JOIN users u ON l.user_id = u.id 
        JOIN accommodations a ON l.accommodation_id = a.id 
        WHERE i.id = :id", 
        ['id' => $invoiceId]
    );
    
    if (!$invoice) {
        setFlashMessage('Invoice not found', 'danger');
        redirect('invoices.php');
    }
    
    // Check permission for regular admin
    if ($userRole !== ROLE_MASTER_ADMIN && !isAdminAssignedToAccommodation($conn, $userId, $invoice['accommodation_id'])) {
        setFlashMessage('You do not have permission to view this invoice', 'danger');
        redirect('invoices.php');
    }
}

// Get invoices based on user role
if ($userRole === ROLE_MASTER_ADMIN) {
    // Master admin sees all invoices
    $invoices = fetchAll($conn, 
        "SELECT i.*, l.user_id, u.first_name, u.last_name, a.name as accommodation_name 
        FROM invoices i 
        JOIN leases l ON i.lease_id = l.id 
        JOIN users u ON l.user_id = u.id 
        JOIN accommodations a ON l.accommodation_id = a.id 
        ORDER BY i.due_date DESC"
    );
} else {
    // Regular admin sees invoices for assigned accommodations
    $adminAccommodations = getAdminAccommodations($conn, $userId);
    
    if (empty($adminAccommodations)) {
        $invoices = [];
    } else {
        $accommodationIds = array_map(function($acc) { return $acc['id']; }, $adminAccommodations);
        $placeholders = implode(',', array_fill(0, count($accommodationIds), '?'));
        
        $stmt = $conn->prepare(
            "SELECT i.*, l.user_id, u.first_name, u.last_name, a.name as accommodation_name 
            FROM invoices i 
            JOIN leases l ON i.lease_id = l.id 
            JOIN users u ON l.user_id = u.id 
            JOIN accommodations a ON l.accommodation_id = a.id 
            WHERE l.accommodation_id IN ($placeholders) 
            ORDER BY i.due_date DESC"
        );
        
        $stmt->execute($accommodationIds);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Filter invoices based on status if requested
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
if (!empty($statusFilter)) {
    $invoices = array_filter($invoices, function($inv) use ($statusFilter) {
        if ($statusFilter === 'overdue') {
            return $inv['status'] === INVOICE_UNPAID && strtotime($inv['due_date']) < time();
        }
        return $inv['status'] === $statusFilter;
    });
}
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Manage Invoices</h2>
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
        <!-- Status Filters -->
        <div class="mb-4">
            <div class="btn-group" role="group" aria-label="Filter by status">
                <a href="invoices.php" class="btn <?php echo empty($statusFilter) ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    All
                </a>
                <a href="?status=<?php echo INVOICE_UNPAID; ?>" class="btn <?php echo $statusFilter === INVOICE_UNPAID ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    Unpaid
                </a>
                <a href="?status=paid" class="btn <?php echo $statusFilter === 'paid' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    Paid
                </a>
                <a href="?status=overdue" class="btn <?php echo $statusFilter === 'overdue' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    Overdue
                </a>
            </div>
        </div>
        
        <!-- Invoices List -->
        <?php if (empty($invoices)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>No invoices found.
                <?php if (!empty($statusFilter)): ?>
                    Try removing the status filter or check back later.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Invoice ID</th>
                                    <th>Student</th>
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
                                        <td>#INV-<?php echo str_pad($inv['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo $inv['first_name'] . ' ' . $inv['last_name']; ?></td>
                                        <td><?php echo $inv['accommodation_name']; ?></td>
                                        <td><?php echo formatCurrency($inv['amount']); ?></td>
                                        <td><?php echo formatDate($inv['due_date']); ?></td>
                                        <td>
                                            <?php
                                            switch ($inv['status']) {
                                                case INVOICE_PAID:
                                                    echo '<span class="badge bg-success">Paid</span>';
                                                    break;
                                                case INVOICE_UNPAID:
                                                    if (strtotime($inv['due_date']) < time()) {
                                                        echo '<span class="badge bg-danger">Overdue</span>';
                                                    } else {
                                                        echo '<span class="badge bg-warning text-dark">Unpaid</span>';
                                                    }
                                                    break;
                                                case INVOICE_OVERDUE:
                                                    echo '<span class="badge bg-danger">Overdue</span>';
                                                    break;
                                                default:
                                                    echo '<span class="badge bg-secondary">' . ucfirst($inv['status']) . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="?action=view&id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($inv['status'] !== INVOICE_PAID): ?>
                                                <a href="?action=edit&id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-warning">
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
        
    <?php elseif ($action === 'view' && $invoice): ?>
        <!-- View Invoice Details -->
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-file-invoice-dollar me-2"></i>Invoice #INV-<?php echo str_pad($invoice['id'], 6, '0', STR_PAD_LEFT); ?>
                        </h4>
                        <div>
                            <?php if (!empty($invoice['pdf_path'])): ?>
                                <a href="../uploads/invoices/<?php echo $invoice['pdf_path']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                    <i class="fas fa-file-pdf me-2"></i>View PDF
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($invoice['status'] !== INVOICE_PAID): ?>
                                <a href="?action=edit&id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit me-2"></i>Edit
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Invoice Details</h5>
                                <table class="table table-borderless table-sm">
                                    <tr>
                                        <td><strong>Invoice ID:</strong></td>
                                        <td>#INV-<?php echo str_pad($invoice['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Amount:</strong></td>
                                        <td><?php echo formatCurrency($invoice['amount']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Due Date:</strong></td>
                                        <td><?php echo formatDate($invoice['due_date']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
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
                                    </tr>
                                    <tr>
                                        <td><strong>Created:</strong></td>
                                        <td><?php echo formatDate($invoice['created_at']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Last Updated:</strong></td>
                                        <td><?php echo formatDate($invoice['updated_at']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Student Information</h5>
                                <table class="table table-borderless table-sm">
                                    <tr>
                                        <td><strong>Name:</strong></td>
                                        <td><?php echo $invoice['first_name'] . ' ' . $invoice['last_name']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td><?php echo $invoice['email']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Phone:</strong></td>
                                        <td><?php echo !empty($invoice['phone']) ? $invoice['phone'] : 'Not provided'; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Accommodation:</strong></td>
                                        <td><?php echo $invoice['accommodation_name']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Lease Period:</strong></td>
                                        <td><?php echo formatDate($invoice['start_date']); ?> - <?php echo formatDate($invoice['end_date']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Monthly Rent:</strong></td>
                                        <td><?php echo formatCurrency($invoice['monthly_rent']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <h5>Payment Information</h5>
                                <div class="alert <?php echo $invoice['status'] === INVOICE_PAID ? 'alert-success' : 'alert-info'; ?>">
                                    <?php if ($invoice['status'] === INVOICE_PAID): ?>
                                        <i class="fas fa-check-circle me-2"></i>This invoice has been paid.
                                    <?php else: ?>
                                        <i class="fas fa-info-circle me-2"></i>This invoice is currently <?php echo strtotime($invoice['due_date']) < time() ? 'overdue' : 'unpaid'; ?>.
                                        <?php if (strtotime($invoice['due_date']) < time()): ?>
                                            The due date was <?php echo formatDate($invoice['due_date']); ?>, which is <?php echo round((time() - strtotime($invoice['due_date'])) / 86400); ?> days ago.
                                        <?php else: ?>
                                            Payment is due in <?php echo round((strtotime($invoice['due_date']) - time()) / 86400); ?> days.
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($invoice['status'] !== INVOICE_PAID): ?>
                                    <div class="mt-3">
                                        <form method="post" action="" class="d-inline">
                                            <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                                            <button type="submit" name="send_reminder" class="btn btn-info">
                                                <i class="fas fa-bell me-2"></i>Send Payment Reminder
                                            </button>
                                        </form>
                                        
                                        <button type="button" class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#markAsPaidModal">
                                            <i class="fas fa-check-circle me-2"></i>Mark as Paid
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0">
                            <i class="fas fa-credit-card me-2"></i>Payment Details
                        </h4>
                    </div>
                    <div class="card-body">
                        <h5>Payment Instructions</h5>
                        <p>The student can make payment using one of the following methods:</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6><i class="fas fa-university me-2"></i>Bank Transfer</h6>
                                        <p class="mb-1"><strong>Account Name:</strong> Harambee Student Living</p>
                                        <p class="mb-1"><strong>Bank:</strong> Example Bank</p>
                                        <p class="mb-1"><strong>Account Number:</strong> 1234567890</p>
                                        <p class="mb-1"><strong>Branch Code:</strong> 12345</p>
                                        <p class="mb-0"><strong>Reference:</strong> INV-<?php echo str_pad($invoice['id'], 6, '0', STR_PAD_LEFT); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6><i class="fas fa-money-bill-wave me-2"></i>Cash Payment</h6>
                                        <p>Students can pay in cash at our office during business hours:</p>
                                        <p class="mb-1"><strong>Address:</strong> 123 University Avenue, Harambee</p>
                                        <p class="mb-1"><strong>Hours:</strong> Monday - Friday, 8:00 AM - 5:00 PM</p>
                                        <p class="mb-0"><strong>Note:</strong> Please bring your invoice number.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
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
                            <?php if (!empty($invoice['pdf_path'])): ?>
                                <a href="../uploads/invoices/<?php echo $invoice['pdf_path']; ?>" class="list-group-item list-group-item-action" target="_blank">
                                    <i class="fas fa-file-pdf me-2"></i>View Invoice PDF
                                </a>
                            <?php endif; ?>
                            
                            <a href="../admin/leases.php?action=view&id=<?php echo $invoice['lease_id']; ?>" class="list-group-item list-group-item-action">
                                <i class="fas fa-file-contract me-2"></i>View Associated Lease
                            </a>
                            
                            <?php if ($invoice['status'] !== INVOICE_PAID): ?>
                                <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#sendReminderModal">
                                    <i class="fas fa-bell me-2"></i>Send Payment Reminder
                                </a>
                                
                                <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#markAsPaidModal">
                                    <i class="fas fa-check-circle me-2"></i>Mark as Paid
                                </a>
                            <?php endif; ?>
                            
                            <a href="invoices.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-arrow-left me-2"></i>Back to Invoices List
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Invoice Status
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Current Status:</span>
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
                            </div>
                            
                            <div class="progress">
                                <?php
                                $progressClass = 'bg-warning';
                                $progressWidth = 50;
                                
                                if ($invoice['status'] === INVOICE_PAID) {
                                    $progressClass = 'bg-success';
                                    $progressWidth = 100;
                                } elseif (strtotime($invoice['due_date']) < time()) {
                                    $progressClass = 'bg-danger';
                                    $progressWidth = 75;
                                }
                                ?>
                                <div 
                                    class="progress-bar <?php echo $progressClass; ?>" 
                                    role="progressbar" 
                                    style="width: <?php echo $progressWidth; ?>%" 
                                    aria-valuenow="<?php echo $progressWidth; ?>" 
                                    aria-valuemin="0" 
                                    aria-valuemax="100">
                                </div>
                            </div>
                        </div>
                        
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Created</span>
                                <span><?php echo formatDate($invoice['created_at'], 'short'); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Due Date</span>
                                <span><?php echo formatDate($invoice['due_date'], 'short'); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>Amount</span>
                                <span><?php echo formatCurrency($invoice['amount']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>PDF Generated</span>
                                <span><?php echo !empty($invoice['pdf_path']) ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-times-circle text-danger"></i>'; ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mark as Paid Modal -->
        <div class="modal fade" id="markAsPaidModal" tabindex="-1" aria-labelledby="markAsPaidModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="markAsPaidModalLabel">Mark Invoice as Paid</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="post" action="">
                        <div class="modal-body">
                            <p>Are you sure you want to mark this invoice as paid?</p>
                            <p>This will update the invoice status and notify the student.</p>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>Invoice details:
                                <ul class="mb-0">
                                    <li>Invoice ID: #INV-<?php echo str_pad($invoice['id'], 6, '0', STR_PAD_LEFT); ?></li>
                                    <li>Amount: <?php echo formatCurrency($invoice['amount']); ?></li>
                                    <li>Student: <?php echo $invoice['first_name'] . ' ' . $invoice['last_name']; ?></li>
                                </ul>
                            </div>
                            
                            <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                            <input type="hidden" name="status" value="<?php echo INVOICE_PAID; ?>">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_status" class="btn btn-success">
                                <i class="fas fa-check-circle me-2"></i>Mark as Paid
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Send Reminder Modal -->
        <div class="modal fade" id="sendReminderModal" tabindex="-1" aria-labelledby="sendReminderModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="sendReminderModalLabel">Send Payment Reminder</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="post" action="">
                        <div class="modal-body">
                            <p>Send a payment reminder to the student for this invoice?</p>
                            <p>This will send an email and SMS (if phone number is available) to the student.</p>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>Reminder details:
                                <ul class="mb-0">
                                    <li>Student: <?php echo $invoice['first_name'] . ' ' . $invoice['last_name']; ?></li>
                                    <li>Email: <?php echo $invoice['email']; ?></li>
                                    <li>Phone: <?php echo !empty($invoice['phone']) ? $invoice['phone'] : 'Not available'; ?></li>
                                    <li>Amount Due: <?php echo formatCurrency($invoice['amount']); ?></li>
                                    <li>Due Date: <?php echo formatDate($invoice['due_date']); ?></li>
                                </ul>
                            </div>
                            
                            <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="send_reminder" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Send Reminder
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
    <?php elseif ($action === 'edit' && $invoice): ?>
        <!-- Edit Invoice Status -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h4 class="mb-0">
                    <i class="fas fa-edit me-2"></i>Update Invoice Status
                </h4>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    You are updating the status for Invoice #INV-<?php echo str_pad($invoice['id'], 6, '0', STR_PAD_LEFT); ?> 
                    for <?php echo $invoice['first_name'] . ' ' . $invoice['last_name']; ?>.
                    Amount: <?php echo formatCurrency($invoice['amount']); ?>
                </div>
                
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="status" class="form-label">Invoice Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="<?php echo INVOICE_PAID; ?>" <?php echo $invoice['status'] === INVOICE_PAID ? 'selected' : ''; ?>>Paid</option>
                            <option value="<?php echo INVOICE_UNPAID; ?>" <?php echo $invoice['status'] === INVOICE_UNPAID ? 'selected' : ''; ?>>Unpaid</option>
                            <option value="<?php echo INVOICE_OVERDUE; ?>" <?php echo $invoice['status'] === INVOICE_OVERDUE ? 'selected' : ''; ?>>Overdue</option>
                        </select>
                    </div>
                    
                    <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                    
                    <div class="mt-4">
                        <button type="submit" name="update_status" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Status
                        </button>
                        <a href="?action=view&id=<?php echo $invoice['id']; ?>" class="btn btn-secondary ms-2">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
