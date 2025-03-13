<?php
// Configuration settings for Harambee Student Living Management System

// Define Application Name
define('APP_NAME', 'Harambee Student Living Management System');

// Database Configuration
define('DB_TYPE', 'sqlite'); // Use 'sqlite' or 'mysql'
define('DB_PATH', __DIR__ . '/../database.sqlite'); // SQLite database file

// MySQL Credentials (if using MySQL instead of SQLite)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'harambee');

// Site Configuration
define('SITE_NAME', 'Harambee Student Living');
define('SITE_URL', 'http://localhost:5000');
define('ADMIN_EMAIL', 'admin@harambee.com');

// File Paths
define('UPLOAD_DIR', __DIR__ . '/../uploads/accommodations/');
define('MAX_FILE_SIZE', 5000000); // 5MB max file size
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Ensure upload directory exists
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Session Configuration
define('SESSION_LIFETIME', 60 * 60 * 2); // 2 hours
session_set_cookie_params(SESSION_LIFETIME);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Email Configuration
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.example.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USER', getenv('SMTP_USER') ?: 'notification@harambee.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: 'password');

// Twilio SMS Configuration
define('TWILIO_ENABLED', false); // Set to true to enable SMS functionality
define('TWILIO_ACCOUNT_SID', getenv('TWILIO_ACCOUNT_SID') ?: '');
define('TWILIO_AUTH_TOKEN', getenv('TWILIO_AUTH_TOKEN') ?: '');
define('TWILIO_PHONE_NUMBER', getenv('TWILIO_PHONE_NUMBER') ?: '');

// User Roles
define('ROLE_MASTER_ADMIN', 'master_admin');
define('ROLE_ADMIN', 'admin');
define('ROLE_STUDENT', 'student');

// Security Settings
define('HASH_ALGO', PASSWORD_BCRYPT);
define('HASH_COST', 12);

// Error Handling
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Ensure logs directory exists
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
}

// Time Zone
date_default_timezone_set('Africa/Johannesburg');
