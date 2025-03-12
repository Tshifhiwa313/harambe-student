<?php
require_once 'config.php';

// Include PHPMailer autoloader
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email using PHPMailer
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email message (HTML)
 * @param string $altMessage Plain text alternative
 * @return bool Success status
 */
function sendEmail($to, $subject, $message, $altMessage = '') {
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        
        // Recipients
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        if (!empty($altMessage)) {
            $mail->AltBody = $altMessage;
        } else {
            $mail->AltBody = strip_tags($message);
        }
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Send a welcome email to a new user
 * @param string $email User email
 * @param string $username Username
 * @param string $password Plain text password
 * @param int $role User role
 * @return bool Success status
 */
function sendWelcomeEmail($email, $username, $password, $role) {
    $subject = "Welcome to " . APP_NAME;
    
    $roleName = getRoleName($role);
    
    $message = "
    <html>
    <head>
        <title>Welcome to " . APP_NAME . "</title>
    </head>
    <body>
        <h2>Welcome to " . APP_NAME . "!</h2>
        <p>Hello $username,</p>
        <p>Your account has been created successfully. Below are your login details:</p>
        <ul>
            <li><strong>Username:</strong> $username</li>
            <li><strong>Password:</strong> $password</li>
            <li><strong>Role:</strong> $roleName</li>
        </ul>
        <p>Please login at: <a href='" . APP_URL . "/login.php'>" . APP_URL . "/login.php</a></p>
        <p>We recommend that you change your password after your first login.</p>
        <p>Thank you for joining us!</p>
        <p>Regards,<br>The " . APP_NAME . " Team</p>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $message);
}

/**
 * Send an application status update email
 * @param string $email User email
 * @param string $username Username
 * @param string $accommodationName Accommodation name
 * @param int $status Application status
 * @return bool Success status
 */
function sendApplicationStatusEmail($email, $username, $accommodationName, $status) {
    $statusText = getStatusText($status);
    $subject = "Application Status Update - " . APP_NAME;
    
    $message = "
    <html>
    <head>
        <title>Application Status Update</title>
    </head>
    <body>
        <h2>Application Status Update</h2>
        <p>Hello $username,</p>
        <p>Your application for <strong>$accommodationName</strong> has been <strong>$statusText</strong>.</p>";
    
    if ($status == STATUS_APPROVED) {
        $message .= "<p>Congratulations! You can now log in to your account to sign the lease agreement and complete the process.</p>";
    } elseif ($status == STATUS_REJECTED) {
        $message .= "<p>We're sorry, but your application couldn't be approved at this time. You can contact us for more information or apply for other available accommodations.</p>";
    }
    
    $message .= "
        <p>Login at: <a href='" . APP_URL . "/login.php'>" . APP_URL . "/login.php</a></p>
        <p>Regards,<br>The " . APP_NAME . " Team</p>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $message);
}

/**
 * Send an invoice notification email
 * @param string $email User email
 * @param string $username Username
 * @param array $invoice Invoice data
 * @return bool Success status
 */
function sendInvoiceEmail($email, $username, $invoice) {
    $subject = "New Invoice - " . APP_NAME;
    
    $dueDate = formatDate($invoice['due_date']);
    $amount = formatCurrency($invoice['amount']);
    
    $message = "
    <html>
    <head>
        <title>New Invoice</title>
    </head>
    <body>
        <h2>New Invoice Generated</h2>
        <p>Hello $username,</p>
        <p>A new invoice has been generated for your accommodation:</p>
        <ul>
            <li><strong>Invoice Number:</strong> {$invoice['id']}</li>
            <li><strong>Amount:</strong> $amount</li>
            <li><strong>Due Date:</strong> $dueDate</li>
            <li><strong>Accommodation:</strong> {$invoice['accommodation_name']}</li>
        </ul>
        <p>Please login to view and download your invoice: <a href='" . APP_URL . "/invoices.php'>" . APP_URL . "/invoices.php</a></p>
        <p>Regards,<br>The " . APP_NAME . " Team</p>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $message);
}

/**
 * Send a lease agreement notification email
 * @param string $email User email
 * @param string $username Username
 * @param array $lease Lease data
 * @return bool Success status
 */
function sendLeaseEmail($email, $username, $lease) {
    $subject = "Lease Agreement - " . APP_NAME;
    
    $startDate = formatDate($lease['start_date']);
    $endDate = formatDate($lease['end_date']);
    
    $message = "
    <html>
    <head>
        <title>Lease Agreement</title>
    </head>
    <body>
        <h2>Lease Agreement Ready</h2>
        <p>Hello $username,</p>
        <p>Your lease agreement for <strong>{$lease['accommodation_name']}</strong> is now ready for signing.</p>
        <p>Lease details:</p>
        <ul>
            <li><strong>Start Date:</strong> $startDate</li>
            <li><strong>End Date:</strong> $endDate</li>
            <li><strong>Monthly Rent:</strong> " . formatCurrency($lease['monthly_rent']) . "</li>
        </ul>
        <p>Please login to view and sign your lease agreement: <a href='" . APP_URL . "/leases.php'>" . APP_URL . "/leases.php</a></p>
        <p>Regards,<br>The " . APP_NAME . " Team</p>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $message);
}

/**
 * Send a maintenance request status update email
 * @param string $email User email
 * @param string $username Username
 * @param array $maintenance Maintenance request data
 * @return bool Success status
 */
function sendMaintenanceUpdateEmail($email, $username, $maintenance) {
    $subject = "Maintenance Request Update - " . APP_NAME;
    
    $statusText = getStatusText($maintenance['status']);
    
    $message = "
    <html>
    <head>
        <title>Maintenance Request Update</title>
    </head>
    <body>
        <h2>Maintenance Request Update</h2>
        <p>Hello $username,</p>
        <p>Your maintenance request for <strong>{$maintenance['accommodation_name']}</strong> has been updated:</p>
        <ul>
            <li><strong>Status:</strong> $statusText</li>
            <li><strong>Issue:</strong> {$maintenance['issue']}</li>";
    
    if (!empty($maintenance['notes'])) {
        $message .= "<li><strong>Notes:</strong> {$maintenance['notes']}</li>";
    }
    
    $message .= "
        </ul>
        <p>Please login to view the status of your maintenance request: <a href='" . APP_URL . "/maintenance.php'>" . APP_URL . "/maintenance.php</a></p>
        <p>Regards,<br>The " . APP_NAME . " Team</p>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $message);
}

/**
 * Send a payment reminder email
 * @param string $email User email
 * @param string $username Username
 * @param array $invoice Invoice data
 * @return bool Success status
 */
function sendPaymentReminderEmail($email, $username, $invoice) {
    $subject = "Payment Reminder - " . APP_NAME;
    
    $dueDate = formatDate($invoice['due_date']);
    $amount = formatCurrency($invoice['amount']);
    
    $message = "
    <html>
    <head>
        <title>Payment Reminder</title>
    </head>
    <body>
        <h2>Payment Reminder</h2>
        <p>Hello $username,</p>
        <p>This is a friendly reminder that payment for your invoice is due soon:</p>
        <ul>
            <li><strong>Invoice Number:</strong> {$invoice['id']}</li>
            <li><strong>Amount:</strong> $amount</li>
            <li><strong>Due Date:</strong> $dueDate</li>
            <li><strong>Accommodation:</strong> {$invoice['accommodation_name']}</li>
        </ul>
        <p>Please login to view and pay your invoice: <a href='" . APP_URL . "/invoices.php'>" . APP_URL . "/invoices.php</a></p>
        <p>Regards,<br>The " . APP_NAME . " Team</p>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $message);
}

/**
 * Send a bulk email to multiple recipients
 * @param array $recipients Array of recipient emails
 * @param string $subject Email subject
 * @param string $message Email message
 * @return array Status array with success and failure counts
 */
function sendBulkEmail($recipients, $subject, $message) {
    $results = [
        'success' => 0,
        'failure' => 0
    ];
    
    foreach ($recipients as $email) {
        $success = sendEmail($email, $subject, $message);
        
        if ($success) {
            $results['success']++;
        } else {
            $results['failure']++;
        }
    }
    
    return $results;
}
?>
