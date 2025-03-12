<?php
require_once 'config.php';

/**
 * Send an SMS using Twilio API
 * @param string $to Recipient phone number
 * @param string $message SMS message
 * @return bool Success status
 */
function sendSMS($to, $message) {
    // Check if Twilio credentials are configured
    if (empty(TWILIO_SID) || empty(TWILIO_TOKEN) || empty(TWILIO_PHONE)) {
        error_log('Twilio credentials not configured');
        return false;
    }
    
    // Include the Python integration script
    require_once __DIR__ . '/../send_sms.php';
    
    // Use the Python-based Twilio integration
    return send_twilio_sms($to, $message);
}

/**
 * Send an application status update SMS
 * @param string $phone User phone number
 * @param string $username Username
 * @param string $accommodationName Accommodation name
 * @param int $status Application status
 * @return bool Success status
 */
function sendApplicationStatusSMS($phone, $username, $accommodationName, $status) {
    $statusText = getStatusText($status);
    
    $message = "Hello $username, your application for $accommodationName has been $statusText. ";
    
    if ($status == STATUS_APPROVED) {
        $message .= "Congratulations! Login to your account to sign the lease agreement.";
    } elseif ($status == STATUS_REJECTED) {
        $message .= "We're sorry, but your application couldn't be approved at this time.";
    }
    
    return sendSMS($phone, $message);
}

/**
 * Send an invoice notification SMS
 * @param string $phone User phone number
 * @param string $username Username
 * @param array $invoice Invoice data
 * @return bool Success status
 */
function sendInvoiceSMS($phone, $username, $invoice) {
    $dueDate = formatDate($invoice['due_date']);
    $amount = formatCurrency($invoice['amount']);
    
    $message = "Hello $username, a new invoice of $amount has been generated for your accommodation. Due date: $dueDate. Please login to view details.";
    
    return sendSMS($phone, $message);
}

/**
 * Send a lease agreement notification SMS
 * @param string $phone User phone number
 * @param string $username Username
 * @param array $lease Lease data
 * @return bool Success status
 */
function sendLeaseSMS($phone, $username, $lease) {
    $message = "Hello $username, your lease agreement for {$lease['accommodation_name']} is now ready for signing. Please login to view and sign it.";
    
    return sendSMS($phone, $message);
}

/**
 * Send a maintenance request status update SMS
 * @param string $phone User phone number
 * @param string $username Username
 * @param array $maintenance Maintenance request data
 * @return bool Success status
 */
function sendMaintenanceUpdateSMS($phone, $username, $maintenance) {
    $statusText = getStatusText($maintenance['status']);
    
    $message = "Hello $username, your maintenance request for {$maintenance['accommodation_name']} has been updated. Status: $statusText.";
    
    return sendSMS($phone, $message);
}

/**
 * Send a payment reminder SMS
 * @param string $phone User phone number
 * @param string $username Username
 * @param array $invoice Invoice data
 * @return bool Success status
 */
function sendPaymentReminderSMS($phone, $username, $invoice) {
    $dueDate = formatDate($invoice['due_date']);
    $amount = formatCurrency($invoice['amount']);
    
    $message = "Hello $username, this is a reminder that payment of $amount for your accommodation is due on $dueDate. Please login to pay.";
    
    return sendSMS($phone, $message);
}

/**
 * Send a bulk SMS to multiple recipients
 * @param array $recipients Array of recipient phone numbers
 * @param string $message SMS message
 * @return array Status array with success and failure counts
 */
function sendBulkSMS($recipients, $message) {
    $results = [
        'success' => 0,
        'failure' => 0
    ];
    
    foreach ($recipients as $phone) {
        $success = sendSMS($phone, $message);
        
        if ($success) {
            $results['success']++;
        } else {
            $results['failure']++;
        }
    }
    
    return $results;
}
?>
