<?php
// Set environment variables based on environment
$is_production = (getenv('RENDER') == 'true');

// Database configuration
if (!defined('DB_TYPE')) define('DB_TYPE', 'sqlite');
if (!defined('DB_HOST')) define('DB_HOST', '');
if (!defined('DB_NAME')) define('DB_NAME', 'database.sqlite');
if (!defined('DB_USER')) define('DB_USER', '');
if (!defined('DB_PASS')) define('DB_PASS', '');

// Site URL and paths
if (!defined('SITE_URL')) define('SITE_URL', $is_production ? getenv('RENDER_EXTERNAL_URL') : 'http://localhost:5000');
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
if (!defined('UPLOADS_PATH')) define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// User roles
if (!defined('ROLE_ADMIN')) define('ROLE_ADMIN', 2);
if (!defined('ROLE_STAFF')) define('ROLE_STAFF', 'staff');
if (!defined('ROLE_STUDENT')) define('ROLE_STUDENT', 3);

// Email configuration
if (!defined('MAIL_HOST')) define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.example.com');
if (!defined('MAIL_PORT')) define('MAIL_PORT', getenv('MAIL_PORT') ?: 587);
if (!defined('MAIL_USERNAME')) define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'user@example.com');
if (!defined('MAIL_PASSWORD')) define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: 'password');
if (!defined('MAIL_FROM')) define('MAIL_FROM', getenv('MAIL_FROM') ?: 'noreply@example.com');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Harambee Housing');

// Twilio configuration for SMS
if (!defined('TWILIO_SID')) define('TWILIO_SID', getenv('TWILIO_SID') ?: '');
if (!defined('TWILIO_TOKEN')) define('TWILIO_TOKEN', getenv('TWILIO_TOKEN') ?: '');
if (!defined('TWILIO_PHONE')) define('TWILIO_PHONE', getenv('TWILIO_PHONE') ?: '');

// Error reporting
if ($is_production) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Timezone
date_default_timezone_set('Africa/Johannesburg');

// Configuration settings for the application
session_start();

// Application settings
if (!defined('APP_NAME')) define('APP_NAME', 'Harambee Student Living Management System');
if (!defined('APP_URL')) define('APP_URL', 'http://' . $_SERVER['HTTP_HOST']);
if (!defined('UPLOADS_DIR')) define('UPLOADS_DIR', __DIR__ . '/../uploads/accommodations/');

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0777, true);
}

// Application status constants
if (!defined('STATUS_PENDING')) define('STATUS_PENDING', 1);
if (!defined('STATUS_APPROVED')) define('STATUS_APPROVED', 2);
if (!defined('STATUS_REJECTED')) define('STATUS_REJECTED', 3);

// Maintenance status constants
if (!defined('MAINTENANCE_PENDING')) define('MAINTENANCE_PENDING', 1);
if (!defined('MAINTENANCE_IN_PROGRESS')) define('MAINTENANCE_IN_PROGRESS', 2);
if (!defined('MAINTENANCE_COMPLETED')) define('MAINTENANCE_COMPLETED', 3);
?>
