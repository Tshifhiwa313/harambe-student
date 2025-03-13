<?php
 // Set environment variables based on environment
 $is_production = (getenv('RENDER') == 'true');
 
 // Database configuration
 define('DB_TYPE', 'sqlite');
 define('DB_HOST', '');
 define('DB_NAME', 'database.sqlite');
 define('DB_USER', '');
 define('DB_PASS', '');
 
 // Site URL and paths
 define('SITE_URL', $is_production ? getenv('RENDER_EXTERNAL_URL') : 'http://localhost:5000');
 define('ROOT_PATH', dirname(__DIR__));
 define('UPLOADS_PATH', ROOT_PATH . '/uploads');
 
 // User roles
 define('ROLE_ADMIN', 'admin');
 define('ROLE_STAFF', 'staff');
 define('ROLE_STUDENT', 'student');
 
 // Email configuration
 define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.example.com');
 define('MAIL_PORT', getenv('MAIL_PORT') ?: 587);
 define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'user@example.com');
 define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: 'password');
 define('MAIL_FROM', getenv('MAIL_FROM') ?: 'noreply@example.com');
 define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Harambee Housing');
 
 // Twilio configuration for SMS
 define('TWILIO_SID', getenv('TWILIO_SID') ?: '');
 define('TWILIO_TOKEN', getenv('TWILIO_TOKEN') ?: '');
 define('TWILIO_PHONE', getenv('TWILIO_PHONE') ?: '');
 
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
 define('TWILIO_SID', getenv('TWILIO_ACCOUNT_SID') ?: '');
 define('TWILIO_TOKEN', getenv('TWILIO_AUTH_TOKEN') ?: '');
 define('TWILIO_PHONE', getenv('TWILIO_PHONE_NUMBER') ?: '');
 
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
