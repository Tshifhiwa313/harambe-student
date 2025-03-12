<?php
/**
 * Twilio SMS integration functions
 * 
 * This file contains functions for sending SMS notifications via Twilio.
 */

/**
 * Send a SMS message via Twilio
 *
 * @param string $toPhoneNumber Recipient phone number
 * @param string $message Message content
 * @return bool True on success, false on failure
 */
function sendTwilioSMS($toPhoneNumber, $message) {
    $accountSid = getenv('TWILIO_ACCOUNT_SID');
    $authToken = getenv('TWILIO_AUTH_TOKEN');
    $twilioNumber = getenv('TWILIO_PHONE_NUMBER');
    
    // Check if Twilio credentials are set
    if (empty($accountSid) || empty($authToken) || empty($twilioNumber)) {
        logMessage('Twilio credentials not set. SMS not sent.', 'warning');
        return false;
    }
    
    // Format phone number (ensure it includes country code)
    $toPhoneNumber = formatPhoneNumber($toPhoneNumber);
    
    try {
        // Ensure Twilio library is loaded
        require_once __DIR__ . '/../vendor/autoload.php';
        
        // Initialize Twilio client
        $client = new \Twilio\Rest\Client($accountSid, $authToken);
        
        // Send message
        $sms = $client->messages->create(
            $toPhoneNumber,
            [
                'from' => $twilioNumber,
                'body' => $message
            ]
        );
        
        logMessage('SMS sent to ' . $toPhoneNumber . ' with SID: ' . $sms->sid, 'info');
        return true;
    } catch (\Exception $e) {
        logMessage('Failed to send SMS: ' . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Format phone number to ensure it includes the country code
 *
 * @param string $phoneNumber Phone number to format
 * @return string Formatted phone number
 */
function formatPhoneNumber($phoneNumber) {
    // Remove any non-digit characters
    $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
    
    // Add South Africa country code if not present
    if (strlen($phoneNumber) === 9 && substr($phoneNumber, 0, 1) === '0') {
        $phoneNumber = '+27' . substr($phoneNumber, 1);
    } elseif (strlen($phoneNumber) === 10 && substr($phoneNumber, 0, 1) === '0') {
        $phoneNumber = '+27' . substr($phoneNumber, 1);
    } elseif (!preg_match('/^\+/', $phoneNumber)) {
        $phoneNumber = '+' . $phoneNumber;
    }
    
    return $phoneNumber;
}

/**
 * Send a bulk SMS to multiple recipients
 *
 * @param array $phoneNumbers Array of phone numbers
 * @param string $message Message content
 * @return array Results array with phone number as key and success as value
 */
function sendBulkSMS($phoneNumbers, $message) {
    $results = [];
    
    foreach ($phoneNumbers as $phoneNumber) {
        $results[$phoneNumber] = sendTwilioSMS($phoneNumber, $message);
    }
    
    return $results;
}
?>
