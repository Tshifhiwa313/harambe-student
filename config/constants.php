<?php
/**
 * Constants configuration
 * 
 * This file defines all the application constants used throughout the application.
 */

// Application name
define('APP_NAME', 'Harambee Student Living');

// Application URL (change this to your actual domain in production)
define('APP_URL', 'http://' . $_SERVER['HTTP_HOST']);

// Upload directories
define('UPLOADS_DIR', __DIR__ . '/../uploads');
define('ACCOMMODATION_UPLOADS', UPLOADS_DIR . '/accommodations');
define('LEASE_UPLOADS', UPLOADS_DIR . '/leases');
define('INVOICE_UPLOADS', UPLOADS_DIR . '/invoices');

// Ensure upload directories exist
if (!file_exists(ACCOMMODATION_UPLOADS)) {
    mkdir(ACCOMMODATION_UPLOADS, 0755, true);
}
if (!file_exists(LEASE_UPLOADS)) {
    mkdir(LEASE_UPLOADS, 0755, true);
}
if (!file_exists(INVOICE_UPLOADS)) {
    mkdir(INVOICE_UPLOADS, 0755, true);
}

// User roles
define('ROLE_MASTER_ADMIN', 'master_admin');
define('ROLE_ADMIN', 'admin');
define('ROLE_STUDENT', 'student');

// Application statuses
define('STATUS_PENDING', 'pending');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');

// Invoice statuses
define('INVOICE_UNPAID', 'unpaid');
define('INVOICE_PAID', 'paid');
define('INVOICE_OVERDUE', 'overdue');

// Maintenance request priorities
define('PRIORITY_LOW', 'low');
define('PRIORITY_MEDIUM', 'medium');
define('PRIORITY_HIGH', 'high');
define('PRIORITY_EMERGENCY', 'emergency');

// Maintenance request statuses
define('MAINTENANCE_PENDING', 'pending');
define('MAINTENANCE_IN_PROGRESS', 'in_progress');
define('MAINTENANCE_COMPLETED', 'completed');
define('MAINTENANCE_CANCELLED', 'cancelled');

// Email settings (for PHPMailer)
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', '');  // Set this in production
define('MAIL_PASSWORD', '');  // Set this in production
define('MAIL_FROM_ADDRESS', 'noreply@harambee.com');
define('MAIL_FROM_NAME', APP_NAME);

// Maximum file upload size in bytes (5MB)
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// Allowed image types
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Session timeout in seconds (2 hours)
define('SESSION_TIMEOUT', 7200);
?>
