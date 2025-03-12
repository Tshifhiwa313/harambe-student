import os

from twilio.rest import Client

TWILIO_ACCOUNT_SID = os.environ.get("TWILIO_ACCOUNT_SID")
TWILIO_AUTH_TOKEN = os.environ.get("TWILIO_AUTH_TOKEN")
TWILIO_PHONE_NUMBER = os.environ.get("TWILIO_PHONE_NUMBER")


def send_twilio_message(to_phone_number: str, message: str) -> None:
    client = Client(TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN)

    # Sending the SMS message
    message = client.messages.create(
        body=message, from_=TWILIO_PHONE_NUMBER, to=to_phone_number
    )

    print(f"Message sent with SID: {message.sid}")


if __name__ == "__main__":
    # Example usage:
    # This will only execute if the script is run directly (not imported)
    # Replace with a valid phone number when testing
    recipient = "+12345678901"  # Replace with the recipient's phone number
    message_text = "Hello from Harambee Student Living Management System! This is a test message."
    
    # Uncomment the following line to test sending an SMS
    # send_twilio_message(recipient, message_text)
    print("To test SMS functionality, edit this script with a valid phone number and uncomment the send_twilio_message line.")