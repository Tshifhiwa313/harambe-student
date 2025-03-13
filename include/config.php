<?php
// Configuration settings for Harambee Student Living Management System

// Database Configuration
define('DB_TYPE', 'sqlite'); // Use 'sqlite' or 'mysql'
define('DB_PATH', 'database/harambee.sqlite'); // SQLite database file
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
define('UPLOAD_DIR', 'uploads/accommodations/');
define('MAX_FILE_SIZE', 5000000); // 5MB max file size
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Session Configuration
define('SESSION_LIFETIME', 60 * 60 * 2); // 2 hours

// Email Configuration
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'notification@harambee.com');
define('SMTP_PASSWORD', 'password');

// Twilio SMS Configuration
define('TWILIO_ENABLED', false); // Set to true to enable SMS functionality
define('TWILIO_ACCOUNT_SID', getenv('TWILIO_ACCOUNT_SID') ?: '');
define('TWILIO_AUTH_TOKEN', getenv('TWILIO_AUTH_TOKEN') ?: '');
define('TWILIO_PHONE_NUMBER', getenv('TWILIO_PHONE_NUMBER') ?: '');

// User Roles
define('ROLE_MASTER_ADMIN', 'master_admin');
define('ROLE_ADMIN', 'admin');
define('ROLE_STUDENT', 'student');

// Security
define('HASH_ALGO', PASSWORD_BCRYPT);
define('HASH_COST', 12);

// Error Handling
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error.log');

// Time Zone
date_default_timezone_set('Africa/Johannesburg');
die("Config file is loading correctly.");

