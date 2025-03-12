<?php
// Utility functions for the application
require_once 'config.php';
require_once 'db.php';

/**
 * Sanitize input data to prevent XSS attacks
 * @param string $data Input data to sanitize
 * @return string Sanitized data
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email address
 * @param string $email Email address to validate
 * @return bool True if valid, false otherwise
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number
 * @param string $phone Phone number to validate
 * @return bool True if valid, false otherwise
 */
function is_valid_phone($phone) {
    // Simple validation - can be expanded based on requirements
    return preg_match('/^\+?[0-9]{10,15}$/', $phone);
}

/**
 * Generate a random string
 * @param int $length Length of the random string
 * @return string Random string
 */
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $random_string = '';
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $random_string;
}

/**
 * Format a date for display
 * @param string $date Date to format
 * @param string $format Date format
 * @return string Formatted date
 */
function format_date($date, $format = 'd M Y') {
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Format currency for display
 * @param float $amount Amount to format
 * @param string $currency Currency symbol
 * @return string Formatted currency
 */
function format_currency($amount, $currency = 'R') {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Upload and process an image file
 * @param array $file File information from $_FILES
 * @param string $destination Destination directory
 * @return string|false Path to the uploaded file or false on failure
 */
function upload_image($file, $destination = UPLOAD_DIR) {
    // Create the destination directory if it doesn't exist
    if (!file_exists($destination)) {
        mkdir($destination, 0755, true);
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Validate file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }
    
    // Validate file extension
    $file_info = pathinfo($file['name']);
    $extension = strtolower($file_info['extension']);
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return false;
    }
    
    // Generate a unique filename
    $new_filename = generate_random_string() . '_' . time() . '.' . $extension;
    $upload_path = $destination . $new_filename;
    
    // Move the uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return $upload_path;
    }
    
    return false;
}

/**
 * Create a system notification
 * @param int $user_id User ID to notify
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $type Notification type (info, warning, success, error)
 * @return int|false Notification ID or false on failure
 */
function create_notification($user_id, $title, $message, $type = 'info') {
    $query = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)";
    db_query($query, [$user_id, $title, $message, $type]);
    
    return db_last_insert_id();
}

/**
 * Get user notifications
 * @param int $user_id User ID
 * @param bool $unread_only Get only unread notifications
 * @return array Notifications
 */
function get_user_notifications($user_id, $unread_only = false) {
    $query = "SELECT * FROM notifications WHERE user_id = ?";
    $params = [$user_id];
    
    if ($unread_only) {
        $query .= " AND is_read = 0";
    }
    
    $query .= " ORDER BY created_at DESC";
    
    return db_fetch_all($query, $params);
}

/**
 * Mark notification as read
 * @param int $notification_id Notification ID
 * @param int $user_id User ID (for security)
 * @return bool True on success, false on failure
 */
function mark_notification_read($notification_id, $user_id) {
    $query = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
    db_query($query, [$notification_id, $user_id]);
    
    return true;
}

/**
 * Get accommodation details
 * @param int $accommodation_id Accommodation ID
 * @return array|null Accommodation details or null if not found
 */
function get_accommodation($accommodation_id) {
    return db_fetch("SELECT * FROM accommodations WHERE id = ?", [$accommodation_id]);
}

/**
 * Get accommodations managed by an admin
 * @param int $admin_id Admin user ID
 * @return array Accommodations
 */
function get_admin_accommodations($admin_id) {
    return db_fetch_all("SELECT * FROM accommodations WHERE admin_id = ?", [$admin_id]);
}

/**
 * Get all accommodations (for master admin)
 * @return array All accommodations
 */
function get_all_accommodations() {
    return db_fetch_all("SELECT a.*, u.full_name as admin_name 
                         FROM accommodations a 
                         LEFT JOIN users u ON a.admin_id = u.id 
                         ORDER BY a.name");
}

/**
 * Get available accommodations for students to apply
 * @return array Available accommodations
 */
function get_available_accommodations() {
    return db_fetch_all("SELECT * FROM accommodations WHERE available_units > 0 ORDER BY name");
}

/**
 * Get user by ID
 * @param int $user_id User ID
 * @return array|null User details or null if not found
 */
function get_user($user_id) {
    return db_fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
}

/**
 * Get user by username
 * @param string $username Username
 * @return array|null User details or null if not found
 */
function get_user_by_username($username) {
    return db_fetch("SELECT * FROM users WHERE username = ?", [$username]);
}

/**
 * Get user by email
 * @param string $email Email address
 * @return array|null User details or null if not found
 */
function get_user_by_email($email) {
    return db_fetch("SELECT * FROM users WHERE email = ?", [$email]);
}

/**
 * Get admin users
 * @return array Admin users
 */
function get_admin_users() {
    return db_fetch_all("SELECT * FROM users WHERE role = ?", [ROLE_ADMIN]);
}

/**
 * Get student application
 * @param int $application_id Application ID
 * @return array|null Application details or null if not found
 */
function get_application($application_id) {
    return db_fetch("SELECT * FROM applications WHERE id = ?", [$application_id]);
}

/**
 * Get applications for a specific accommodation
 * @param int $accommodation_id Accommodation ID
 * @return array Applications
 */
function get_accommodation_applications($accommodation_id) {
    return db_fetch_all("SELECT a.*, u.full_name, u.email, u.phone_number 
                         FROM applications a 
                         JOIN users u ON a.student_id = u.id 
                         WHERE a.accommodation_id = ? 
                         ORDER BY a.created_at DESC", 
                         [$accommodation_id]);
}

/**
 * Get all applications (for master admin)
 * @return array All applications
 */
function get_all_applications() {
    return db_fetch_all("SELECT a.*, u.full_name, u.email, u.phone_number,
                         ac.name as accommodation_name
                         FROM applications a 
                         JOIN users u ON a.student_id = u.id 
                         JOIN accommodations ac ON a.accommodation_id = ac.id
                         ORDER BY a.created_at DESC");
}

/**
 * Get student applications
 * @param int $student_id Student user ID
 * @return array Student applications
 */
function get_student_applications($student_id) {
    return db_fetch_all("SELECT a.*, ac.name as accommodation_name, ac.location
                         FROM applications a 
                         JOIN accommodations ac ON a.accommodation_id = ac.id
                         WHERE a.student_id = ? 
                         ORDER BY a.created_at DESC", 
                         [$student_id]);
}

/**
 * Get lease details
 * @param int $lease_id Lease ID
 * @return array|null Lease details or null if not found
 */
function get_lease($lease_id) {
    return db_fetch("SELECT * FROM leases WHERE id = ?", [$lease_id]);
}

/**
 * Get student leases
 * @param int $student_id Student user ID
 * @return array Student leases
 */
function get_student_leases($student_id) {
    return db_fetch_all("SELECT l.*, a.name as accommodation_name
                        FROM leases l
                        JOIN accommodations a ON l.accommodation_id = a.id
                        WHERE l.student_id = ?
                        ORDER BY l.created_at DESC", 
                        [$student_id]);
}

/**
 * Get leases for a specific accommodation
 * @param int $accommodation_id Accommodation ID
 * @return array Leases
 */
function get_accommodation_leases($accommodation_id) {
    return db_fetch_all("SELECT l.*, u.full_name, u.email, u.phone_number
                        FROM leases l
                        JOIN users u ON l.student_id = u.id
                        WHERE l.accommodation_id = ?
                        ORDER BY l.created_at DESC", 
                        [$accommodation_id]);
}

/**
 * Get all leases (for master admin)
 * @return array All leases
 */
function get_all_leases() {
    return db_fetch_all("SELECT l.*, u.full_name, u.email, u.phone_number,
                        a.name as accommodation_name
                        FROM leases l
                        JOIN users u ON l.student_id = u.id
                        JOIN accommodations a ON l.accommodation_id = a.id
                        ORDER BY l.created_at DESC");
}

/**
 * Get invoice details
 * @param int $invoice_id Invoice ID
 * @return array|null Invoice details or null if not found
 */
function get_invoice($invoice_id) {
    return db_fetch("SELECT * FROM invoices WHERE id = ?", [$invoice_id]);
}

/**
 * Get invoices for a lease
 * @param int $lease_id Lease ID
 * @return array Invoices
 */
function get_lease_invoices($lease_id) {
    return db_fetch_all("SELECT * FROM invoices WHERE lease_id = ? ORDER BY due_date", [$lease_id]);
}

/**
 * Get student invoices
 * @param int $student_id Student user ID
 * @return array Student invoices
 */
function get_student_invoices($student_id) {
    return db_fetch_all("SELECT i.*, l.monthly_rent, a.name as accommodation_name
                        FROM invoices i
                        JOIN leases l ON i.lease_id = l.id
                        JOIN accommodations a ON l.accommodation_id = a.id
                        WHERE l.student_id = ?
                        ORDER BY i.due_date DESC", 
                        [$student_id]);
}

/**
 * Get maintenance request
 * @param int $request_id Maintenance request ID
 * @return array|null Maintenance request details or null if not found
 */
function get_maintenance_request($request_id) {
    return db_fetch("SELECT * FROM maintenance_requests WHERE id = ?", [$request_id]);
}

/**
 * Get student maintenance requests
 * @param int $student_id Student user ID
 * @return array Student maintenance requests
 */
function get_student_maintenance_requests($student_id) {
    return db_fetch_all("SELECT m.*, a.name as accommodation_name
                        FROM maintenance_requests m
                        JOIN accommodations a ON m.accommodation_id = a.id
                        WHERE m.student_id = ?
                        ORDER BY m.created_at DESC", 
                        [$student_id]);
}

/**
 * Get maintenance requests for a specific accommodation
 * @param int $accommodation_id Accommodation ID
 * @return array Maintenance requests
 */
function get_accommodation_maintenance_requests($accommodation_id) {
    return db_fetch_all("SELECT m.*, u.full_name, u.email, u.phone_number
                        FROM maintenance_requests m
                        JOIN users u ON m.student_id = u.id
                        WHERE m.accommodation_id = ?
                        ORDER BY 
                            CASE m.status
                                WHEN 'pending' THEN 1
                                WHEN 'in_progress' THEN 2
                                WHEN 'completed' THEN 3
                                ELSE 4
                            END,
                            CASE m.priority
                                WHEN 'high' THEN 1
                                WHEN 'medium' THEN 2
                                WHEN 'low' THEN 3
                                ELSE 4
                            END,
                            m.created_at DESC", 
                        [$accommodation_id]);
}

/**
 * Get all maintenance requests (for master admin)
 * @return array All maintenance requests
 */
function get_all_maintenance_requests() {
    return db_fetch_all("SELECT m.*, u.full_name, u.email, u.phone_number,
                        a.name as accommodation_name
                        FROM maintenance_requests m
                        JOIN users u ON m.student_id = u.id
                        JOIN accommodations a ON m.accommodation_id = a.id
                        ORDER BY 
                            CASE m.status
                                WHEN 'pending' THEN 1
                                WHEN 'in_progress' THEN 2
                                WHEN 'completed' THEN 3
                                ELSE 4
                            END,
                            CASE m.priority
                                WHEN 'high' THEN 1
                                WHEN 'medium' THEN 2
                                WHEN 'low' THEN 3
                                ELSE 4
                            END,
                            m.created_at DESC");
}

/**
 * Check if a student has already applied for a specific accommodation
 * @param int $student_id Student user ID
 * @param int $accommodation_id Accommodation ID
 * @return bool True if already applied, false otherwise
 */
function has_student_already_applied($student_id, $accommodation_id) {
    $query = "SELECT COUNT(*) as count FROM applications 
              WHERE student_id = ? AND accommodation_id = ? AND status != 'rejected'";
    $result = db_fetch($query, [$student_id, $accommodation_id]);
    
    return $result['count'] > 0;
}

/**
 * Check if a user has access to a specific accommodation
 * @param int $user_id User ID
 * @param int $accommodation_id Accommodation ID
 * @param string $role User role
 * @return bool True if has access, false otherwise
 */
function user_has_accommodation_access($user_id, $accommodation_id, $role) {
    // Master admin has access to all accommodations
    if ($role === ROLE_MASTER_ADMIN) {
        return true;
    }
    
    // Admin can only access assigned accommodations
    if ($role === ROLE_ADMIN) {
        $query = "SELECT COUNT(*) as count FROM accommodations 
                  WHERE id = ? AND admin_id = ?";
        $result = db_fetch($query, [$accommodation_id, $user_id]);
        
        return $result['count'] > 0;
    }
    
    // Students can access accommodations they've applied to or leased
    if ($role === ROLE_STUDENT) {
        $query = "SELECT COUNT(*) as count FROM applications 
                  WHERE student_id = ? AND accommodation_id = ?";
        $result = db_fetch($query, [$user_id, $accommodation_id]);
        
        if ($result['count'] > 0) {
            return true;
        }
        
        $query = "SELECT COUNT(*) as count FROM leases 
                  WHERE student_id = ? AND accommodation_id = ?";
        $result = db_fetch($query, [$user_id, $accommodation_id]);
        
        return $result['count'] > 0;
    }
    
    return false;
}

/**
 * Generate a page title
 * @param string $page_title Page title
 * @return string Full page title with site name
 */
function page_title($page_title = '') {
    if (empty($page_title)) {
        return SITE_NAME;
    }
    
    return $page_title . ' | ' . SITE_NAME;
}

/**
 * Redirect to a URL
 * @param string $url URL to redirect to
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Set a flash message
 * @param string $type Message type (success, error, warning, info)
 * @param string $message Message text
 */
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 * @return array|null Flash message or null if none
 */
function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    
    return null;
}

/**
 * Check if a string starts with a specific substring
 * @param string $haystack String to search in
 * @param string $needle Substring to search for
 * @return bool True if string starts with substring, false otherwise
 */
function starts_with($haystack, $needle) {
    return substr($haystack, 0, strlen($needle)) === $needle;
}

/**
 * Check if a page is active
 * @param string $page_name Page name to check
 * @return bool True if active, false otherwise
 */
function is_page_active($page_name) {
    $current_page = basename($_SERVER['PHP_SELF']);
    return $current_page === $page_name || starts_with($current_page, $page_name);
}

/**
 * Get pagination information
 * @param int $total_items Total number of items
 * @param int $items_per_page Items per page
 * @param int $current_page Current page
 * @return array Pagination information
 */
function get_pagination($total_items, $items_per_page = 10, $current_page = 1) {
    $total_pages = ceil($total_items / $items_per_page);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $items_per_page;
    
    return [
        'total_items' => $total_items,
        'items_per_page' => $items_per_page,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'offset' => $offset
    ];
}

/**
 * Convert a user role to a human-readable format
 * @param string $role User role
 * @return string Human-readable role
 */
function format_role($role) {
    $roles = [
        ROLE_MASTER_ADMIN => 'Master Admin',
        ROLE_ADMIN => 'Admin',
        ROLE_STUDENT => 'Student'
    ];
    
    return isset($roles[$role]) ? $roles[$role] : ucfirst($role);
}

/**
 * Convert application status to a human-readable format
 * @param string $status Application status
 * @return string Human-readable status
 */
function format_application_status($status) {
    $statuses = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected'
    ];
    
    return isset($statuses[$status]) ? $statuses[$status] : ucfirst($status);
}

/**
 * Convert lease status to a human-readable format
 * @param string $status Lease status
 * @return string Human-readable status
 */
function format_lease_status($status) {
    $statuses = [
        'draft' => 'Draft',
        'pending' => 'Pending Signature',
        'active' => 'Active',
        'expired' => 'Expired',
        'terminated' => 'Terminated'
    ];
    
    return isset($statuses[$status]) ? $statuses[$status] : ucfirst($status);
}

/**
 * Convert invoice status to a human-readable format
 * @param string $status Invoice status
 * @return string Human-readable status
 */
function format_invoice_status($status) {
    $statuses = [
        'unpaid' => 'Unpaid',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'cancelled' => 'Cancelled'
    ];
    
    return isset($statuses[$status]) ? $statuses[$status] : ucfirst($status);
}

/**
 * Convert maintenance request status to a human-readable format
 * @param string $status Maintenance request status
 * @return string Human-readable status
 */
function format_maintenance_status($status) {
    $statuses = [
        'pending' => 'Pending',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled'
    ];
    
    return isset($statuses[$status]) ? $statuses[$status] : ucfirst($status);
}

/**
 * Convert maintenance request priority to a human-readable format
 * @param string $priority Maintenance request priority
 * @return string Human-readable priority
 */
function format_maintenance_priority($priority) {
    $priorities = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High'
    ];
    
    return isset($priorities[$priority]) ? $priorities[$priority] : ucfirst($priority);
}

/**
 * Get status CSS class for different statuses
 * @param string $status Status value
 * @param string $context Context (application, lease, invoice, maintenance)
 * @return string CSS class
 */
function get_status_class($status, $context = 'general') {
    $status_classes = [
        'general' => [
            'active' => 'success',
            'completed' => 'success',
            'approved' => 'success',
            'paid' => 'success',
            'pending' => 'warning',
            'in_progress' => 'info',
            'draft' => 'secondary',
            'rejected' => 'danger',
            'cancelled' => 'danger',
            'expired' => 'secondary',
            'terminated' => 'danger',
            'unpaid' => 'warning',
            'overdue' => 'danger'
        ],
        'application' => [
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger'
        ],
        'lease' => [
            'draft' => 'secondary',
            'pending' => 'warning',
            'active' => 'success',
            'expired' => 'secondary',
            'terminated' => 'danger'
        ],
        'invoice' => [
            'unpaid' => 'warning',
            'paid' => 'success',
            'overdue' => 'danger',
            'cancelled' => 'secondary'
        ],
        'maintenance' => [
            'pending' => 'warning',
            'in_progress' => 'info',
            'completed' => 'success',
            'cancelled' => 'secondary'
        ],
        'priority' => [
            'low' => 'success',
            'medium' => 'warning',
            'high' => 'danger'
        ]
    ];
    
    if (isset($status_classes[$context][$status])) {
        return 'text-' . $status_classes[$context][$status];
    } elseif (isset($status_classes['general'][$status])) {
        return 'text-' . $status_classes['general'][$status];
    }
    
    return 'text-secondary';
}

/**
 * Get badge CSS class for different statuses
 * @param string $status Status value
 * @param string $context Context (application, lease, invoice, maintenance)
 * @return string CSS class
 */
function get_badge_class($status, $context = 'general') {
    $status_classes = [
        'general' => [
            'active' => 'success',
            'completed' => 'success',
            'approved' => 'success',
            'paid' => 'success',
            'pending' => 'warning',
            'in_progress' => 'info',
            'draft' => 'secondary',
            'rejected' => 'danger',
            'cancelled' => 'danger',
            'expired' => 'secondary',
            'terminated' => 'danger',
            'unpaid' => 'warning',
            'overdue' => 'danger'
        ],
        'application' => [
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger'
        ],
        'lease' => [
            'draft' => 'secondary',
            'pending' => 'warning',
            'active' => 'success',
            'expired' => 'secondary',
            'terminated' => 'danger'
        ],
        'invoice' => [
            'unpaid' => 'warning',
            'paid' => 'success',
            'overdue' => 'danger',
            'cancelled' => 'secondary'
        ],
        'maintenance' => [
            'pending' => 'warning',
            'in_progress' => 'info',
            'completed' => 'success',
            'cancelled' => 'secondary'
        ],
        'priority' => [
            'low' => 'success',
            'medium' => 'warning',
            'high' => 'danger'
        ]
    ];
    
    if (isset($status_classes[$context][$status])) {
        return 'bg-' . $status_classes[$context][$status];
    } elseif (isset($status_classes['general'][$status])) {
        return 'bg-' . $status_classes['general'][$status];
    }
    
    return 'bg-secondary';
}
