<?php
// SMS functions using Twilio API
require_once 'config.php';
require_once 'functions.php';

// Check if Twilio is enabled
if (TWILIO_ENABLED) {
    // Auto-install Twilio SDK if not present
    if (!file_exists('vendor/twilio/sdk')) {
        if (!file_exists('vendor')) {
            mkdir('vendor', 0755, true);
        }
        
        // Create simple autoloader for Twilio classes
        $twilio_autoloader = '<?php
class Autoloader {
    public static function register() {
        spl_autoload_register(function ($class) {
            $prefix = "Twilio\\\\";
            $base_dir = __DIR__ . "/twilio/sdk/src/Twilio/";
            
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }
            
            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace("\\\\", "/", $relative_class) . ".php";
            
            if (file_exists($file)) {
                require $file;
            }
        });
    }
}

Autoloader::register();';
        
        file_put_contents('vendor/twilio_autoloader.php', $twilio_autoloader);
        
        // Download minimal Twilio SDK files
        $twilio_files = [
            'rest/Client.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Rest/Client.php',
            'Rest.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Rest.php',
            'VersionInfo.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/VersionInfo.php',
            'Domain.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Domain.php',
            'Version.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Version.php',
            'Http/Response.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Http/Response.php',
            'Rest/Api.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Rest/Api.php',
            'Rest/Api/V2010.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Rest/Api/V2010.php',
            'Rest/Api/V2010/AccountContext.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Rest/Api/V2010/AccountContext.php',
            'Rest/Api/V2010/AccountInstance.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Rest/Api/V2010/AccountInstance.php',
            'Rest/Api/V2010/AccountList.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Rest/Api/V2010/AccountList.php',
            'Rest/Api/V2010/AccountPage.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Rest/Api/V2010/AccountPage.php',
            'Rest/Api/V2010/Account/MessageContext.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Rest/Api/V2010/Account/MessageContext.php',
            'Rest/Api/V2010/Account/MessageInstance.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Rest/Api/V2010/Account/MessageInstance.php',
            'Rest/Api/V2010/Account/MessageList.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Rest/Api/V2010/Account/MessageList.php',
            'Rest/Api/V2010/Account/MessagePage.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Rest/Api/V2010/Account/MessagePage.php',
            'Options.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Options.php',
            'Values.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Values.php',
            'Serialize.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Serialize.php',
            'Http/Client.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Http/Client.php',
            'Http/CurlClient.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Http/CurlClient.php',
            'Exceptions/TwilioException.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Exceptions/TwilioException.php',
            'Exceptions/RestException.php' => 'https://raw.githubusercontent.com/twilio/twilio-php/main/src/Twilio/Exceptions/RestException.php'
        ];
        
        foreach ($twilio_files as $path => $url) {
            $dir = dirname('vendor/twilio/sdk/src/Twilio/' . $path);
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents('vendor/twilio/sdk/src/Twilio/' . $path, file_get_contents($url));
        }
    }
    
    require_once 'vendor/twilio_autoloader.php';
}

/**
 * Send an SMS message using Twilio
 * @param string $to_number Recipient phone number (with country code)
 * @param string $message Message text
 * @return bool True on success, false on failure
 */
function send_sms($to_number, $message) {
    // Check if Twilio is enabled
    if (!TWILIO_ENABLED) {
        error_log("Twilio SMS is disabled in configuration.");
        return false;
    }
    
    // Validate phone number
    if (!is_valid_phone($to_number)) {
        error_log("Invalid phone number format: " . $to_number);
        return false;
    }
    
    try {
        // Create a Twilio client
        $client = new Twilio\Rest\Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN);
        
        // Send the SMS message
        $message = $client->messages->create(
            $to_number,
            [
                'from' => TWILIO_PHONE_NUMBER,
                'body' => $message
            ]
        );
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to send SMS: " . $e->getMessage());
        return false;
    }
}

/**
 * Send a welcome SMS to a new user
 * @param int $user_id User ID
 * @return bool True on success, false on failure
 */
function send_welcome_sms($user_id) {
    $user = get_user($user_id);
    if (!$user || empty($user['phone_number'])) {
        return false;
    }
    
    $message = "Welcome to " . SITE_NAME . ", " . $user['full_name'] . "! Your account has been created successfully. Log in to explore our platform.";
    
    return send_sms($user['phone_number'], $message);
}

/**
 * Send an application status update SMS
 * @param int $application_id Application ID
 * @param string $status New status (approved, rejected)
 * @return bool True on success, false on failure
 */
function send_application_status_sms($application_id, $status) {
    $application = get_application($application_id);
    if (!$application) {
        return false;
    }
    
    $student = get_user($application['student_id']);
    $accommodation = get_accommodation($application['accommodation_id']);
    
    if (!$student || !$accommodation || empty($student['phone_number'])) {
        return false;
    }
    
    $status_text = $status === 'approved' ? 'APPROVED' : 'REJECTED';
    $message = SITE_NAME . ": Your application for " . $accommodation['name'] . " has been " . $status_text . ". Log in for details.";
    
    return send_sms($student['phone_number'], $message);
}

/**
 * Send a lease agreement notification SMS
 * @param int $lease_id Lease ID
 * @return bool True on success, false on failure
 */
function send_lease_notification_sms($lease_id) {
    $lease = get_lease($lease_id);
    if (!$lease) {
        return false;
    }
    
    $student = get_user($lease['student_id']);
    $accommodation = get_accommodation($lease['accommodation_id']);
    
    if (!$student || !$accommodation || empty($student['phone_number'])) {
        return false;
    }
    
    $message = SITE_NAME . ": Your lease agreement for " . $accommodation['name'] . " is ready for signature. Please log in to review and sign.";
    
    return send_sms($student['phone_number'], $message);
}

/**
 * Send an invoice notification SMS
 * @param int $invoice_id Invoice ID
 * @return bool True on success, false on failure
 */
function send_invoice_notification_sms($invoice_id) {
    $invoice = get_invoice($invoice_id);
    if (!$invoice) {
        return false;
    }
    
    $lease = get_lease($invoice['lease_id']);
    if (!$lease) {
        return false;
    }
    
    $student = get_user($lease['student_id']);
    
    if (!$student || empty($student['phone_number'])) {
        return false;
    }
    
    $message = SITE_NAME . ": New invoice of " . format_currency($invoice['amount']) . " due by " . format_date($invoice['due_date']) . ". Log in to view details.";
    
    return send_sms($student['phone_number'], $message);
}

/**
 * Send a payment reminder SMS
 * @param int $invoice_id Invoice ID
 * @return bool True on success, false on failure
 */
function send_payment_reminder_sms($invoice_id) {
    $invoice = get_invoice($invoice_id);
    if (!$invoice) {
        return false;
    }
    
    $lease = get_lease($invoice['lease_id']);
    if (!$lease) {
        return false;
    }
    
    $student = get_user($lease['student_id']);
    
    if (!$student || empty($student['phone_number'])) {
        return false;
    }
    
    $days_overdue = floor((time() - strtotime($invoice['due_date'])) / (60 * 60 * 24));
    
    if ($days_overdue > 0) {
        $message = SITE_NAME . ": REMINDER: Invoice of " . format_currency($invoice['amount']) . " is " . $days_overdue . " days overdue. Please log in to make payment.";
    } else {
        $message = SITE_NAME . ": REMINDER: Invoice of " . format_currency($invoice['amount']) . " is due on " . format_date($invoice['due_date']) . ". Please log in to make payment.";
    }
    
    return send_sms($student['phone_number'], $message);
}

/**
 * Send a maintenance request update SMS
 * @param int $request_id Maintenance request ID
 * @param string $status New status
 * @return bool True on success, false on failure
 */
function send_maintenance_update_sms($request_id, $status) {
    $request = get_maintenance_request($request_id);
    if (!$request) {
        return false;
    }
    
    $student = get_user($request['student_id']);
    
    if (!$student || empty($student['phone_number'])) {
        return false;
    }
    
    $status_text = format_maintenance_status($status);
    $message = SITE_NAME . ": Maintenance request '" . $request['title'] . "' status updated to " . $status_text . ". Log in for details.";
    
    return send_sms($student['phone_number'], $message);
}

/**
 * Send a bulk SMS to multiple recipients
 * @param array $user_ids Array of user IDs
 * @param string $message Message text
 * @return array Array with success and failure counts
 */
function send_bulk_sms($user_ids, $message) {
    $results = [
        'success' => 0,
        'failure' => 0
    ];
    
    foreach ($user_ids as $user_id) {
        $user = get_user($user_id);
        if (!$user || empty($user['phone_number'])) {
            $results['failure']++;
            continue;
        }
        
        $success = send_sms($user['phone_number'], $message);
        
        if ($success) {
            $results['success']++;
        } else {
            $results['failure']++;
        }
    }
    
    return $results;
}

/**
 * Notify a user via SMS and email
 * @param int $user_id User ID
 * @param string $subject Email subject
 * @param string $email_body Email body (HTML)
 * @param string $sms_message SMS message
 * @return array Array with email and SMS success status
 */
function notify_user($user_id, $subject, $email_body, $sms_message) {
    $user = get_user($user_id);
    if (!$user) {
        return [
            'email' => false,
            'sms' => false
        ];
    }
    
    $email_success = send_email($user['email'], $user['full_name'], $subject, $email_body);
    
    $sms_success = false;
    if (TWILIO_ENABLED && !empty($user['phone_number'])) {
        $sms_success = send_sms($user['phone_number'], $sms_message);
    }
    
    return [
        'email' => $email_success,
        'sms' => $sms_success
    ];
}
