<?php
require_once 'includes/config.php';

/**
 * Send an SMS using the Python Twilio integration
 * 
 * @param string $to Recipient phone number
 * @param string $message SMS message
 * @return bool Success status
 */
function send_twilio_sms($to, $message) {
    // Escape the message for shell execution
    $escapedMessage = escapeshellarg($message);
    $escapedPhone = escapeshellarg($to);
    
    // Execute the Python script
    $command = "python3 -c \"
import os
from twilio.rest import Client

try:
    client = Client(os.environ.get('TWILIO_ACCOUNT_SID'), os.environ.get('TWILIO_AUTH_TOKEN'))
    message = client.messages.create(
        body={$escapedMessage},
        from_=os.environ.get('TWILIO_PHONE_NUMBER'),
        to={$escapedPhone}
    )
    print('Success: ' + message.sid)
    exit(0)
except Exception as e:
    print('Error: ' + str(e))
    exit(1)
\"";

    // Execute the command
    $output = [];
    $returnVar = 0;
    exec($command, $output, $returnVar);
    
    // Check if the command was successful
    if ($returnVar === 0) {
        return true;
    } else {
        error_log("SMS sending failed: " . implode("\n", $output));
        return false;
    }
}

// Test the SMS functionality if called directly
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    echo "<h1>SMS Test</h1>";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
        $phone = $_POST['phone'] ?? '';
        $message = $_POST['message'] ?? '';
        
        if (!empty($phone) && !empty($message)) {
            $result = send_twilio_sms($phone, $message);
            
            if ($result) {
                echo "<div style='color: green;'>Message sent successfully!</div>";
            } else {
                echo "<div style='color: red;'>Failed to send message. Check the logs for details.</div>";
            }
        } else {
            echo "<div style='color: red;'>Please fill in all fields.</div>";
        }
    }
    
    ?>
    <form method="post" action="">
        <div>
            <label for="phone">Phone Number (with country code, e.g., +27123456789):</label><br>
            <input type="text" id="phone" name="phone" required>
        </div>
        <div>
            <label for="message">Message:</label><br>
            <textarea id="message" name="message" rows="4" cols="50" required></textarea>
        </div>
        <div>
            <button type="submit" name="send_test">Send Test SMS</button>
        </div>
    </form>
    <?php
}
?>