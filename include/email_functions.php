<?php
// Email functions and utilities
require_once 'config.php';
require_once 'functions.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if PHPMailer is installed
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    // Auto-install PHPMailer if not present
    if (!file_exists('vendor/phpmailer/phpmailer')) {
        mkdir('vendor/phpmailer/phpmailer', 0755, true);
    }
    
    // Download PHPMailer files
    $phpmailer_files = [
        'src/PHPMailer.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/PHPMailer.php',
        'src/SMTP.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/SMTP.php',
        'src/Exception.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/Exception.php'
    ];
    
    foreach ($phpmailer_files as $path => $url) {
        if (!file_exists('vendor/phpmailer/phpmailer/' . $path)) {
            $dir = dirname('vendor/phpmailer/phpmailer/' . $path);
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents('vendor/phpmailer/phpmailer/' . $path, file_get_contents($url));
        }
    }
    
    // Create autoload file
    $autoload_content = "<?php\nrequire_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';\nrequire_once 'vendor/phpmailer/phpmailer/src/SMTP.php';\nrequire_once 'vendor/phpmailer/phpmailer/src/Exception.php';\n";
    file_put_contents('vendor/autoload.php', $autoload_content);
    
    // Include the files
    require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once 'vendor/phpmailer/phpmailer/src/Exception.php';
}

/**
 * Send an email using PHPMailer
 * @param string $to_email Recipient email address
 * @param string $to_name Recipient name
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $alt_body Plain text alternative body
 * @return bool True on success, false on failure
 */
function send_email($to_email, $to_name, $subject, $body, $alt_body = '') {
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_USER, SITE_NAME);
        $mail->addAddress($to_email, $to_name);
        $mail->addReplyTo(ADMIN_EMAIL, SITE_NAME . ' Support');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $alt_body ?: strip_tags($body);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send a welcome email to a new user
 * @param int $user_id User ID
 * @return bool True on success, false on failure
 */
function send_welcome_email($user_id) {
    $user = get_user($user_id);
    if (!$user) {
        return false;
    }
    
    $subject = 'Welcome to ' . SITE_NAME;
    
    $body = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { width: 100%; max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4A6572; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { background-color: #f1f1f1; padding: 10px; text-align: center; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . SITE_NAME . '</h1>
            </div>
            <div class="content">
                <h2>Welcome to ' . SITE_NAME . ', ' . $user['full_name'] . '!</h2>
                <p>Thank you for joining ' . SITE_NAME . '. We are excited to have you as part of our community.</p>
                <p>Your account has been created with the following details:</p>
                <ul>
                    <li><strong>Username:</strong> ' . $user['username'] . '</li>
                    <li><strong>Email:</strong> ' . $user['email'] . '</li>
                </ul>
                <p>You can now log in to your account and start exploring our platform.</p>
                <p>If you have any questions or need assistance, please don\'t hesitate to contact us.</p>
                <p>Best regards,<br>The ' . SITE_NAME . ' Team</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return send_email($user['email'], $user['full_name'], $subject, $body);
}

/**
 * Send an application status update email
 * @param int $application_id Application ID
 * @param string $status New status (approved, rejected)
 * @return bool True on success, false on failure
 */
function send_application_status_email($application_id, $status) {
    $application = get_application($application_id);
    if (!$application) {
        return false;
    }
    
    $student = get_user($application['student_id']);
    $accommodation = get_accommodation($application['accommodation_id']);
    
    if (!$student || !$accommodation) {
        return false;
    }
    
    $status_text = $status === 'approved' ? 'Approved' : 'Rejected';
    $subject = 'Application Status Update: ' . $status_text;
    
    $body = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { width: 100%; max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4A6572; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { background-color: #f1f1f1; padding: 10px; text-align: center; font-size: 12px; }
            .approved { color: #28a745; }
            .rejected { color: #dc3545; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . SITE_NAME . '</h1>
            </div>
            <div class="content">
                <h2>Application Status Update</h2>
                <p>Dear ' . $student['full_name'] . ',</p>';
    
    if ($status === 'approved') {
        $body .= '
                <p>We are pleased to inform you that your application for <strong>' . $accommodation['name'] . '</strong> has been <span class="approved">APPROVED</span>.</p>
                <p>You will receive a lease agreement soon. Please review and sign it to secure your accommodation.</p>
                <p>If you have any questions or need assistance, please don\'t hesitate to contact us.</p>';
    } else {
        $body .= '
                <p>We regret to inform you that your application for <strong>' . $accommodation['name'] . '</strong> has been <span class="rejected">REJECTED</span>.</p>
                <p>This could be due to various reasons such as availability, eligibility criteria, or other factors.</p>
                <p>If you have any questions or would like to explore other accommodation options, please feel free to contact us.</p>';
    }
    
    $body .= '
                <p>Best regards,<br>The ' . SITE_NAME . ' Team</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return send_email($student['email'], $student['full_name'], $subject, $body);
}

/**
 * Send a lease agreement notification email
 * @param int $lease_id Lease ID
 * @return bool True on success, false on failure
 */
function send_lease_notification_email($lease_id) {
    $lease = get_lease($lease_id);
    if (!$lease) {
        return false;
    }
    
    $student = get_user($lease['student_id']);
    $accommodation = get_accommodation($lease['accommodation_id']);
    
    if (!$student || !$accommodation) {
        return false;
    }
    
    $subject = 'Lease Agreement Ready for Signature';
    
    $body = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { width: 100%; max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4A6572; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { background-color: #f1f1f1; padding: 10px; text-align: center; font-size: 12px; }
            .button { display: inline-block; background-color: #4A6572; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . SITE_NAME . '</h1>
            </div>
            <div class="content">
                <h2>Lease Agreement Ready for Signature</h2>
                <p>Dear ' . $student['full_name'] . ',</p>
                <p>Your lease agreement for <strong>' . $accommodation['name'] . '</strong> is now ready for your signature.</p>
                <p>Lease Details:</p>
                <ul>
                    <li><strong>Start Date:</strong> ' . format_date($lease['start_date']) . '</li>
                    <li><strong>End Date:</strong> ' . format_date($lease['end_date']) . '</li>
                    <li><strong>Monthly Rent:</strong> ' . format_currency($lease['monthly_rent']) . '</li>
                </ul>
                <p>Please log in to your account to review and sign the lease agreement. The lease needs to be signed within 7 days to secure your accommodation.</p>
                <p><a href="' . SITE_URL . '/leases.php" class="button">Sign Lease Agreement</a></p>
                <p>If you have any questions or need assistance, please don\'t hesitate to contact us.</p>
                <p>Best regards,<br>The ' . SITE_NAME . ' Team</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return send_email($student['email'], $student['full_name'], $subject, $body);
}

/**
 * Send an invoice notification email
 * @param int $invoice_id Invoice ID
 * @return bool True on success, false on failure
 */
function send_invoice_notification_email($invoice_id) {
    $invoice = get_invoice($invoice_id);
    if (!$invoice) {
        return false;
    }
    
    $lease = get_lease($invoice['lease_id']);
    if (!$lease) {
        return false;
    }
    
    $student = get_user($lease['student_id']);
    $accommodation = get_accommodation($lease['accommodation_id']);
    
    if (!$student || !$accommodation) {
        return false;
    }
    
    $subject = 'New Invoice: ' . $invoice['description'];
    
    $body = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { width: 100%; max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4A6572; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { background-color: #f1f1f1; padding: 10px; text-align: center; font-size: 12px; }
            .button { display: inline-block; background-color: #4A6572; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
            .invoice-details { background-color: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . SITE_NAME . '</h1>
            </div>
            <div class="content">
                <h2>New Invoice</h2>
                <p>Dear ' . $student['full_name'] . ',</p>
                <p>A new invoice has been generated for your lease at <strong>' . $accommodation['name'] . '</strong>.</p>
                <div class="invoice-details">
                    <p><strong>Invoice Details:</strong></p>
                    <p><strong>Description:</strong> ' . $invoice['description'] . '</p>
                    <p><strong>Amount:</strong> ' . format_currency($invoice['amount']) . '</p>
                    <p><strong>Due Date:</strong> ' . format_date($invoice['due_date']) . '</p>
                </div>
                <p>Please log in to your account to view and pay this invoice.</p>
                <p><a href="' . SITE_URL . '/invoices.php" class="button">View Invoice</a></p>
                <p>If you have any questions or need assistance, please don\'t hesitate to contact us.</p>
                <p>Best regards,<br>The ' . SITE_NAME . ' Team</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return send_email($student['email'], $student['full_name'], $subject, $body);
}

/**
 * Send a payment reminder email
 * @param int $invoice_id Invoice ID
 * @return bool True on success, false on failure
 */
function send_payment_reminder_email($invoice_id) {
    $invoice = get_invoice($invoice_id);
    if (!$invoice) {
        return false;
    }
    
    $lease = get_lease($invoice['lease_id']);
    if (!$lease) {
        return false;
    }
    
    $student = get_user($lease['student_id']);
    $accommodation = get_accommodation($lease['accommodation_id']);
    
    if (!$student || !$accommodation) {
        return false;
    }
    
    $subject = 'Payment Reminder: ' . $invoice['description'];
    
    $days_overdue = floor((time() - strtotime($invoice['due_date'])) / (60 * 60 * 24));
    $urgency = $days_overdue > 14 ? 'high' : ($days_overdue > 7 ? 'medium' : 'low');
    
    $body = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { width: 100%; max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4A6572; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { background-color: #f1f1f1; padding: 10px; text-align: center; font-size: 12px; }
            .button { display: inline-block; background-color: #4A6572; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
            .invoice-details { background-color: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin: 15px 0; }
            .urgency-low { color: #28a745; }
            .urgency-medium { color: #ffc107; }
            .urgency-high { color: #dc3545; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . SITE_NAME . '</h1>
            </div>
            <div class="content">
                <h2>Payment Reminder</h2>
                <p>Dear ' . $student['full_name'] . ',</p>';
    
    if ($days_overdue > 0) {
        $body .= '
                <p class="urgency-' . $urgency . '">This is a reminder that your invoice is <strong>' . $days_overdue . ' days overdue</strong>.</p>';
    } else {
        $body .= '
                <p>This is a reminder that your invoice is due soon.</p>';
    }
    
    $body .= '
                <div class="invoice-details">
                    <p><strong>Invoice Details:</strong></p>
                    <p><strong>Description:</strong> ' . $invoice['description'] . '</p>
                    <p><strong>Amount:</strong> ' . format_currency($invoice['amount']) . '</p>
                    <p><strong>Due Date:</strong> ' . format_date($invoice['due_date']) . '</p>
                </div>
                <p>Please log in to your account to view and pay this invoice as soon as possible.</p>
                <p><a href="' . SITE_URL . '/invoices.php" class="button">View Invoice</a></p>
                <p>If you have already made the payment, please disregard this reminder.</p>
                <p>If you have any questions or need assistance, please don\'t hesitate to contact us.</p>
                <p>Best regards,<br>The ' . SITE_NAME . ' Team</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return send_email($student['email'], $student['full_name'], $subject, $body);
}

/**
 * Send a maintenance request update email
 * @param int $request_id Maintenance request ID
 * @param string $status New status
 * @return bool True on success, false on failure
 */
function send_maintenance_update_email($request_id, $status) {
    $request = get_maintenance_request($request_id);
    if (!$request) {
        return false;
    }
    
    $student = get_user($request['student_id']);
    $accommodation = get_accommodation($request['accommodation_id']);
    
    if (!$student || !$accommodation) {
        return false;
    }
    
    $status_text = format_maintenance_status($status);
    $subject = 'Maintenance Request Update: ' . $status_text;
    
    $body = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { width: 100%; max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4A6572; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { background-color: #f1f1f1; padding: 10px; text-align: center; font-size: 12px; }
            .button { display: inline-block; background-color: #4A6572; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
            .status-pending { color: #ffc107; }
            .status-in_progress { color: #17a2b8; }
            .status-completed { color: #28a745; }
            .status-cancelled { color: #dc3545; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . SITE_NAME . '</h1>
            </div>
            <div class="content">
                <h2>Maintenance Request Update</h2>
                <p>Dear ' . $student['full_name'] . ',</p>
                <p>Your maintenance request for <strong>' . $accommodation['name'] . '</strong> has been updated.</p>
                <p>Request Title: <strong>' . $request['title'] . '</strong></p>
                <p>Status: <span class="status-' . $status . '"><strong>' . $status_text . '</strong></span></p>';
    
    if (!empty($request['admin_notes'])) {
        $body .= '
                <p>Notes:</p>
                <p>' . nl2br(htmlspecialchars($request['admin_notes'])) . '</p>';
    }
    
    $body .= '
                <p>Please log in to your account to view more details.</p>
                <p><a href="' . SITE_URL . '/maintenance.php" class="button">View Maintenance Requests</a></p>
                <p>If you have any questions or need assistance, please don\'t hesitate to contact us.</p>
                <p>Best regards,<br>The ' . SITE_NAME . ' Team</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return send_email($student['email'], $student['full_name'], $subject, $body);
}

/**
 * Send a bulk email to multiple recipients
 * @param array $user_ids Array of user IDs
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @return array Array with success and failure counts
 */
function send_bulk_email($user_ids, $subject, $body) {
    $results = [
        'success' => 0,
        'failure' => 0
    ];
    
    foreach ($user_ids as $user_id) {
        $user = get_user($user_id);
        if (!$user) {
            $results['failure']++;
            continue;
        }
        
        $success = send_email($user['email'], $user['full_name'], $subject, $body);
        
        if ($success) {
            $results['success']++;
        } else {
            $results['failure']++;
        }
    }
    
    return $results;
}
