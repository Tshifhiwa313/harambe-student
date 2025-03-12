import os
import sys

# Try to import the Twilio client
try:
    from twilio.rest import Client
    TWILIO_AVAILABLE = True
except ImportError:
    TWILIO_AVAILABLE = False
    print("Warning: Twilio package not available. SMS functionality will be disabled.")

# Get Twilio credentials from environment variables
TWILIO_ACCOUNT_SID = os.environ.get("TWILIO_ACCOUNT_SID")
TWILIO_AUTH_TOKEN = os.environ.get("TWILIO_AUTH_TOKEN")
TWILIO_PHONE_NUMBER = os.environ.get("TWILIO_PHONE_NUMBER")

def send_twilio_message(to_phone_number: str, message_body: str) -> bool:
    """
    Send an SMS message using Twilio.
    
    Args:
        to_phone_number: The recipient's phone number with country code (e.g., +27123456789)
        message_body: The content of the SMS
        
    Returns:
        bool: True if message was sent successfully, False otherwise
    """
    # Check if Twilio package is available
    if not TWILIO_AVAILABLE:
        print("Error: Twilio package not installed. Run 'pip install twilio'")
        return False
    
    # Check if all required credentials are available
    if not all([TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_PHONE_NUMBER]):
        print("Error: Missing Twilio credentials. Set TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, and TWILIO_PHONE_NUMBER environment variables.")
        return False
    
    try:
        # Initialize the Twilio client
        client = Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN)
        
        # Send the SMS message
        sms_message = client.messages.create(
            body=message_body,
            from_=TWILIO_PHONE_NUMBER,
            to=to_phone_number
        )
        
        # Message sent successfully
        print(f"Message sent successfully to {to_phone_number}")
        print(f"Message SID: {sms_message.sid}")
        return True
        
    except Exception as e:
        print(f"Error sending SMS: {str(e)}")
        return False


if __name__ == "__main__":
    # Example usage:
    # This will only execute if the script is run directly (not imported)
    # Replace with a valid phone number when testing
    recipient = "+12345678901"  # Replace with the recipient's phone number
    message_text = "Hello from Harambee Student Living Management System! This is a test message."
    
    # Uncomment the following line to test sending an SMS
    # send_twilio_message(recipient, message_text)
    print("To test SMS functionality, edit this script with a valid phone number and uncomment the send_twilio_message line.")