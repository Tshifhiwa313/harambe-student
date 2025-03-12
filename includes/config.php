<?php
// Configuration settings for the application
session_start();

// Error reporting settings
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database configuration
define('DB_TYPE', 'sqlite'); // Options: 'sqlite' or 'mysql'

if (DB_TYPE === 'sqlite') {
    define('DB_PATH', __DIR__ . '/../database.sqlite');
} else {
    // MySQL Configuration
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'harambee_student_living');
}

// Application settings
define('APP_NAME', 'Harambee Student Living Management System');
define('APP_URL', 'http://' . $_SERVER['HTTP_HOST']);
define('UPLOADS_DIR', __DIR__ . '/../uploads/accommodations/');

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0777, true);
}

// Email settings for PHPMailer
define('MAIL_HOST', 'smtp.example.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'admin@example.com');
define('MAIL_PASSWORD', 'password');
define('MAIL_FROM', 'admin@example.com');
define('MAIL_FROM_NAME', APP_NAME);

// Twilio settings for SMS notifications
define('TWILIO_SID', ''); // Get from environment variable
define('TWILIO_TOKEN', ''); // Get from environment variable
define('TWILIO_PHONE', ''); // Get from environment variable

// User roles
define('ROLE_MASTER_ADMIN', 1);
define('ROLE_ADMIN', 2);
define('ROLE_STUDENT', 3);

// Define role names
$role_names = [
    ROLE_MASTER_ADMIN => 'Master Admin',
    ROLE_ADMIN => 'Admin',
    ROLE_STUDENT => 'Student'
];

// Application status constants
define('STATUS_PENDING', 1);
define('STATUS_APPROVED', 2);
define('STATUS_REJECTED', 3);

// Maintenance status constants
define('MAINTENANCE_PENDING', 1);
define('MAINTENANCE_IN_PROGRESS', 2);
define('MAINTENANCE_COMPLETED', 3);
?>
