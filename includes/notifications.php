<?php
/**
 * Notification functions
 * 
 * This file contains functions for sending notifications via email and SMS.
 */

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email notification
 *
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email message
 * @param string $fromName Sender name
 * @param string $fromEmail Sender email
 * @return bool True on success, false on failure
 */
function sendEmail($to, $subject, $message, $fromName = MAIL_FROM_NAME, $fromEmail = MAIL_FROM_ADDRESS) {
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = 'tls';
        $mail->Port = MAIL_PORT;
        
        // Recipients
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        logMessage("Email could not be sent. Mailer Error: {$mail->ErrorInfo}", 'error');
        return false;
    }
}

/**
 * Send a SMS notification via Twilio
 *
 * @param string $to Recipient phone number
 * @param string $message SMS message
 * @return bool True on success, false on failure
 */
function sendSMS($to, $message) {
    $accountSid = getenv('TWILIO_ACCOUNT_SID');
    $authToken = getenv('TWILIO_AUTH_TOKEN');
    $twilioNumber = getenv('TWILIO_PHONE_NUMBER');
    
    if (empty($accountSid) || empty($authToken) || empty($twilioNumber)) {
        logMessage("Twilio credentials not configured", 'error');
        return false;
    }
    
    try {
        // Load Twilio
        require_once __DIR__ . '/../vendor/autoload.php';
        
        // Create a new Twilio client
        $client = new \Twilio\Rest\Client($accountSid, $authToken);
        
        // Send the message
        $client->messages->create(
            $to,
            [
                'from' => $twilioNumber,
                'body' => $message
            ]
        );
        
        return true;
    } catch (Exception $e) {
        logMessage("SMS could not be sent. Error: {$e->getMessage()}", 'error');
        return false;
    }
}

/**
 * Create a notification in the database
 *
 * @param PDO $conn Database connection
 * @param int $userId User ID
 * @param string $message Notification message
 * @param string $type Notification type
 * @return int|false Notification ID on success, false on failure
 */
function createNotification($conn, $userId, $message, $type) {
    $data = [
        'user_id' => $userId,
        'message' => $message,
        'type' => $type,
        'is_read' => 0
    ];
    
    return insertRow($conn, 'notifications', $data);
}

/**
 * Get notifications for a user
 *
 * @param PDO $conn Database connection
 * @param int $userId User ID
 * @param bool $unreadOnly Get only unread notifications
 * @param int $limit Limit the number of notifications
 * @return array The notifications
 */
function getUserNotifications($conn, $userId, $unreadOnly = false, $limit = 10) {
    $query = "SELECT * FROM notifications 
              WHERE user_id = :userId";
    
    if ($unreadOnly) {
        $query .= " AND is_read = 0";
    }
    
    $query .= " ORDER BY created_at DESC LIMIT :limit";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Mark a notification as read
 *
 * @param PDO $conn Database connection
 * @param int $notificationId Notification ID
 * @return bool True on success, false on failure
 */
function markNotificationAsRead($conn, $notificationId) {
    $data = ['is_read' => 1];
    return updateRow($conn, 'notifications', $data, 'id', $notificationId);
}

/**
 * Mark all notifications as read for a user
 *
 * @param PDO $conn Database connection
 * @param int $userId User ID
 * @return bool True on success, false on failure
 */
function markAllNotificationsAsRead($conn, $userId) {
    $query = "UPDATE notifications SET is_read = 1 WHERE user_id = :userId AND is_read = 0";
    executeQuery($conn, $query, ['userId' => $userId]);
    return true;
}

/**
 * Count unread notifications for a user
 *
 * @param PDO $conn Database connection
 * @param int $userId User ID
 * @return int The number of unread notifications
 */
function countUnreadNotifications($conn, $userId) {
    $query = "SELECT COUNT(*) FROM notifications WHERE user_id = :userId AND is_read = 0";
    $stmt = executeQuery($conn, $query, ['userId' => $userId]);
    return $stmt->fetchColumn();
}

/**
 * Send application status notification
 *
 * @param PDO $conn Database connection
 * @param int $applicationId Application ID
 * @param string $status New status
 * @return bool True on success, false on failure
 */
function sendApplicationStatusNotification($conn, $applicationId, $status) {
    // Get application details
    $query = "SELECT a.*, u.email, u.phone, u.first_name, acc.name as accommodation_name 
              FROM applications a
              JOIN users u ON a.user_id = u.id
              JOIN accommodations acc ON a.accommodation_id = acc.id
              WHERE a.id = :applicationId";
    
    $application = fetchRow($conn, $query, ['applicationId' => $applicationId]);
    
    if (!$application) {
        return false;
    }
    
    $studentName = $application['first_name'];
    $accommodationName = $application['accommodation_name'];
    
    // Create notification message
    if ($status === STATUS_APPROVED) {
        $subject = "Your application for $accommodationName has been approved";
        $message = "Hello $studentName,<br><br>";
        $message .= "Congratulations! Your application for $accommodationName has been approved. ";
        $message .= "You can now proceed to sign the lease agreement.<br><br>";
        $message .= "Please log in to your account to complete the process.<br><br>";
        $message .= "Kind regards,<br>";
        $message .= APP_NAME . " Team";
        
        $smsMessage = "Good news! Your application for $accommodationName has been approved. Log in to sign your lease agreement.";
    } elseif ($status === STATUS_REJECTED) {
        $subject = "Your application for $accommodationName has been rejected";
        $message = "Hello $studentName,<br><br>";
        $message .= "We regret to inform you that your application for $accommodationName has been rejected. ";
        $message .= "Please log in to your account for more details or to apply for other accommodations.<br><br>";
        $message .= "Kind regards,<br>";
        $message .= APP_NAME . " Team";
        
        $smsMessage = "Your application for $accommodationName has been rejected. Please log in for more details.";
    } else {
        $subject = "Your application for $accommodationName has been updated";
        $message = "Hello $studentName,<br><br>";
        $message .= "Your application for $accommodationName has been updated to status: $status. ";
        $message .= "Please log in to your account for more details.<br><br>";
        $message .= "Kind regards,<br>";
        $message .= APP_NAME . " Team";
        
        $smsMessage = "Your application for $accommodationName has been updated to: $status. Please log in for details.";
    }
    
    // Send email notification
    $emailSent = sendEmail($application['email'], $subject, $message);
    
    // Send SMS notification if phone number is provided
    $smsSent = false;
    if (!empty($application['phone'])) {
        $smsSent = sendSMS($application['phone'], $smsMessage);
    }
    
    // Create database notification
    $dbNotification = createNotification($conn, $application['user_id'], $subject, 'application');
    
    return $emailSent || $smsSent || $dbNotification;
}

/**
 * Send invoice reminder notification
 *
 * @param PDO $conn Database connection
 * @param int $invoiceId Invoice ID
 * @return bool True on success, false on failure
 */
function sendInvoiceReminderNotification($conn, $invoiceId) {
    // Get invoice details
    $query = "SELECT i.*, l.user_id, l.monthly_rent, u.email, u.phone, u.first_name, acc.name as accommodation_name 
              FROM invoices i
              JOIN leases l ON i.lease_id = l.id
              JOIN users u ON l.user_id = u.id
              JOIN accommodations acc ON l.accommodation_id = acc.id
              WHERE i.id = :invoiceId";
    
    $invoice = fetchRow($conn, $query, ['invoiceId' => $invoiceId]);
    
    if (!$invoice) {
        return false;
    }
    
    $studentName = $invoice['first_name'];
    $accommodationName = $invoice['accommodation_name'];
    $amount = formatCurrency($invoice['amount']);
    $dueDate = formatDate($invoice['due_date']);
    
    // Create notification message
    $subject = "Payment Reminder: Invoice #$invoiceId for $accommodationName";
    $message = "Hello $studentName,<br><br>";
    $message .= "This is a friendly reminder that your payment of $amount for $accommodationName is due on $dueDate. ";
    $message .= "Please ensure that your payment is made on time to avoid any late fees.<br><br>";
    $message .= "You can log in to your account to view and download the invoice.<br><br>";
    $message .= "Kind regards,<br>";
    $message .= APP_NAME . " Team";
    
    $smsMessage = "Payment Reminder: Your payment of $amount for $accommodationName is due on $dueDate. Please log in to view details.";
    
    // Send email notification
    $emailSent = sendEmail($invoice['email'], $subject, $message);
    
    // Send SMS notification if phone number is provided
    $smsSent = false;
    if (!empty($invoice['phone'])) {
        $smsSent = sendSMS($invoice['phone'], $smsMessage);
    }
    
    // Create database notification
    $dbNotification = createNotification($conn, $invoice['user_id'], $subject, 'invoice');
    
    return $emailSent || $smsSent || $dbNotification;
}

/**
 * Send maintenance request update notification
 *
 * @param PDO $conn Database connection
 * @param int $requestId Maintenance request ID
 * @param string $status New status
 * @return bool True on success, false on failure
 */
function sendMaintenanceUpdateNotification($conn, $requestId, $status) {
    // Get maintenance request details
    $query = "SELECT mr.*, u.email, u.phone, u.first_name, acc.name as accommodation_name 
              FROM maintenance_requests mr
              JOIN users u ON mr.user_id = u.id
              JOIN accommodations acc ON mr.accommodation_id = acc.id
              WHERE mr.id = :requestId";
    
    $request = fetchRow($conn, $query, ['requestId' => $requestId]);
    
    if (!$request) {
        return false;
    }
    
    $studentName = $request['first_name'];
    $accommodationName = $request['accommodation_name'];
    $requestTitle = $request['title'];
    
    // Create notification message
    $subject = "Update on Maintenance Request: $requestTitle";
    $message = "Hello $studentName,<br><br>";
    $message .= "Your maintenance request for $accommodationName has been updated to: $status.<br><br>";
    
    if ($status === MAINTENANCE_IN_PROGRESS) {
        $message .= "Our maintenance team is now working on your request. ";
    } elseif ($status === MAINTENANCE_COMPLETED) {
        $message .= "Your maintenance request has been completed. If you're still experiencing issues, please submit a new request. ";
    } elseif ($status === MAINTENANCE_CANCELLED) {
        $message .= "Your maintenance request has been cancelled. If you believe this is in error, please contact us. ";
    }
    
    $message .= "You can log in to your account to view more details.<br><br>";
    $message .= "Kind regards,<br>";
    $message .= APP_NAME . " Team";
    
    $smsMessage = "Your maintenance request for $accommodationName has been updated to: $status. Please log in for details.";
    
    // Send email notification
    $emailSent = sendEmail($request['email'], $subject, $message);
    
    // Send SMS notification if phone number is provided
    $smsSent = false;
    if (!empty($request['phone'])) {
        $smsSent = sendSMS($request['phone'], $smsMessage);
    }
    
    // Create database notification
    $dbNotification = createNotification($conn, $request['user_id'], $subject, 'maintenance');
    
    return $emailSent || $smsSent || $dbNotification;
}

/**
 * Send lease signed notification
 *
 * @param PDO $conn Database connection
 * @param int $leaseId Lease ID
 * @return bool True on success, false on failure
 */
function sendLeaseSignedNotification($conn, $leaseId) {
    // Get lease details
    $query = "SELECT l.*, u.email, u.phone, u.first_name, acc.name as accommodation_name 
              FROM leases l
              JOIN users u ON l.user_id = u.id
              JOIN accommodations acc ON l.accommodation_id = acc.id
              WHERE l.id = :leaseId";
    
    $lease = fetchRow($conn, $query, ['leaseId' => $leaseId]);
    
    if (!$lease) {
        return false;
    }
    
    $studentName = $lease['first_name'];
    $accommodationName = $lease['accommodation_name'];
    $startDate = formatDate($lease['start_date']);
    $endDate = formatDate($lease['end_date']);
    
    // Create notification message
    $subject = "Lease Agreement Signed for $accommodationName";
    $message = "Hello $studentName,<br><br>";
    $message .= "Thank you for signing your lease agreement for $accommodationName. ";
    $message .= "Your lease period is from $startDate to $endDate.<br><br>";
    $message .= "You can log in to your account to view and download the signed lease agreement at any time.<br><br>";
    $message .= "Welcome to $accommodationName! We hope you enjoy your stay.<br><br>";
    $message .= "Kind regards,<br>";
    $message .= APP_NAME . " Team";
    
    $smsMessage = "Your lease for $accommodationName has been signed successfully. Lease period: $startDate to $endDate. Welcome!";
    
    // Send email notification
    $emailSent = sendEmail($lease['email'], $subject, $message);
    
    // Send SMS notification if phone number is provided
    $smsSent = false;
    if (!empty($lease['phone'])) {
        $smsSent = sendSMS($lease['phone'], $smsMessage);
    }
    
    // Create database notification
    $dbNotification = createNotification($conn, $lease['user_id'], $subject, 'lease');
    
    return $emailSent || $smsSent || $dbNotification;
}

/**
 * Send bulk notification to students
 *
 * @param PDO $conn Database connection
 * @param array $userIds Array of user IDs
 * @param string $subject Email subject
 * @param string $message Email message
 * @param string $smsMessage SMS message
 * @return array Results array with user ID as key and success as value
 */
function sendBulkNotification($conn, $userIds, $subject, $message, $smsMessage = null) {
    $results = [];
    
    foreach ($userIds as $userId) {
        // Get user details
        $user = getUser($conn, $userId);
        
        if (!$user) {
            $results[$userId] = false;
            continue;
        }
        
        // Send email notification
        $emailSent = sendEmail($user['email'], $subject, $message);
        
        // Send SMS notification if message provided and phone number exists
        $smsSent = false;
        if ($smsMessage && !empty($user['phone'])) {
            $smsSent = sendSMS($user['phone'], $smsMessage);
        }
        
        // Create database notification
        $dbNotification = createNotification($conn, $userId, $subject, 'bulk');
        
        $results[$userId] = $emailSent || $smsSent || $dbNotification;
    }
    
    return $results;
}
?>
