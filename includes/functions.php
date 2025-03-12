<?php
require_once 'config.php';
require_once 'database.php';
require_once 'authentication.php';

/**
 * Display a formatted error message
 * @param string $message Error message
 */
function showError($message) {
    echo '<div class="alert alert-danger" role="alert">';
    echo $message;
    echo '</div>';
}

/**
 * Display a formatted success message
 * @param string $message Success message
 */
function showSuccess($message) {
    echo '<div class="alert alert-success" role="alert">';
    echo $message;
    echo '</div>';
}

/**
 * Sanitize user input
 * @param string $input User input
 * @return string Sanitized input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Format a date for display
 * @param string $date Date in Y-m-d format
 * @param string $format Output format
 * @return string Formatted date
 */
function formatDate($date, $format = 'd F, Y') {
    if (empty($date)) return '';
    
    $dateObj = new DateTime($date);
    return $dateObj->format($format);
}

/**
 * Format currency for display
 * @param float $amount Amount
 * @param string $symbol Currency symbol
 * @return string Formatted currency
 */
function formatCurrency($amount, $symbol = 'R') {
    return $symbol . ' ' . number_format($amount, 2, '.', ',');
}

/**
 * Generate a unique filename for uploads
 * @param string $originalName Original filename
 * @return string Unique filename
 */
function generateUniqueFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

/**
 * Upload a file to the server
 * @param array $file File from $_FILES array
 * @param string $targetDir Target directory
 * @param array $allowedTypes Allowed file types
 * @param int $maxSize Maximum file size in bytes
 * @return string|false Filename on success, false on failure
 */
function uploadFile($file, $targetDir, $allowedTypes = ['jpg', 'jpeg', 'png'], $maxSize = 5000000) {
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return false;
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        return false;
    }
    
    // Check file type
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileType, $allowedTypes)) {
        return false;
    }
    
    // Create a unique filename
    $filename = generateUniqueFilename($file['name']);
    $targetPath = $targetDir . $filename;
    
    // Upload the file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $filename;
    }
    
    return false;
}

/**
 * Get user role name by role ID
 * @param int $roleId Role ID
 * @return string Role name
 */
function getRoleName($roleId) {
    global $role_names;
    return isset($role_names[$roleId]) ? $role_names[$roleId] : 'Unknown';
}

/**
 * Get all accommodations or those assigned to an admin
 * @param int|null $adminId Admin ID or null for all
 * @return array Accommodations
 */
function getAccommodations($adminId = null) {
    $sql = "SELECT * FROM accommodations";
    $params = [];
    
    if ($adminId !== null) {
        $sql .= " WHERE admin_id = ?";
        $params = [$adminId];
    }
    
    $sql .= " ORDER BY name ASC";
    
    return fetchAll($sql, $params);
}

/**
 * Get accommodation details by ID
 * @param int $id Accommodation ID
 * @return array|null Accommodation details
 */
function getAccommodationById($id) {
    return fetchOne("SELECT * FROM accommodations WHERE id = ?", [$id]);
}

/**
 * Get applications for a specific accommodation or admin
 * @param int|null $accommodationId Accommodation ID or null
 * @param int|null $adminId Admin ID or null
 * @return array Applications
 */
function getApplications($accommodationId = null, $adminId = null) {
    $sql = "SELECT a.*, u.username, u.email, ac.name as accommodation_name 
            FROM applications a
            JOIN users u ON a.user_id = u.id
            JOIN accommodations ac ON a.accommodation_id = ac.id";
    
    $params = [];
    $conditions = [];
    
    if ($accommodationId !== null) {
        $conditions[] = "a.accommodation_id = ?";
        $params[] = $accommodationId;
    }
    
    if ($adminId !== null) {
        $conditions[] = "ac.admin_id = ?";
        $params[] = $adminId;
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY a.created_at DESC";
    
    return fetchAll($sql, $params);
}

/**
 * Get application by ID
 * @param int $id Application ID
 * @return array|null Application details
 */
function getApplicationById($id) {
    return fetchOne("SELECT a.*, u.username, u.email, ac.name as accommodation_name 
                    FROM applications a
                    JOIN users u ON a.user_id = u.id
                    JOIN accommodations ac ON a.accommodation_id = ac.id
                    WHERE a.id = ?", [$id]);
}

/**
 * Get applications for a student
 * @param int $userId User ID
 * @return array Applications
 */
function getStudentApplications($userId) {
    return fetchAll("SELECT a.*, ac.name as accommodation_name, ac.location, ac.price_per_month 
                    FROM applications a
                    JOIN accommodations ac ON a.accommodation_id = ac.id
                    WHERE a.user_id = ?
                    ORDER BY a.created_at DESC", [$userId]);
}

/**
 * Get status text based on status ID
 * @param int $statusId Status ID
 * @return string Status text
 */
function getStatusText($statusId) {
    $statuses = [
        STATUS_PENDING => 'Pending',
        STATUS_APPROVED => 'Approved',
        STATUS_REJECTED => 'Rejected',
        MAINTENANCE_PENDING => 'Pending',
        MAINTENANCE_IN_PROGRESS => 'In Progress',
        MAINTENANCE_COMPLETED => 'Completed'
    ];
    
    return isset($statuses[$statusId]) ? $statuses[$statusId] : 'Unknown';
}

/**
 * Get all leases or those for a specific user or accommodation
 * @param int|null $userId User ID or null
 * @param int|null $accommodationId Accommodation ID or null
 * @return array Leases
 */
function getLeases($userId = null, $accommodationId = null) {
    $sql = "SELECT l.*, u.username, u.email, ac.name as accommodation_name, ac.admin_id 
            FROM leases l
            JOIN users u ON l.user_id = u.id
            JOIN accommodations ac ON l.accommodation_id = ac.id";
    
    $params = [];
    $conditions = [];
    
    if ($userId !== null) {
        $conditions[] = "l.user_id = ?";
        $params[] = $userId;
    }
    
    if ($accommodationId !== null) {
        $conditions[] = "l.accommodation_id = ?";
        $params[] = $accommodationId;
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY l.created_at DESC";
    
    return fetchAll($sql, $params);
}

/**
 * Get lease by ID
 * @param int $id Lease ID
 * @return array|null Lease details
 */
function getLeaseById($id) {
    return fetchOne("SELECT l.*, u.username, u.email, ac.name as accommodation_name, ac.location 
                    FROM leases l
                    JOIN users u ON l.user_id = u.id
                    JOIN accommodations ac ON l.accommodation_id = ac.id
                    WHERE l.id = ?", [$id]);
}

/**
 * Get all invoices or those for a specific user or accommodation
 * @param int|null $userId User ID or null
 * @param int|null $accommodationId Accommodation ID or null
 * @return array Invoices
 */
function getInvoices($userId = null, $accommodationId = null) {
    $sql = "SELECT i.*, u.username, u.email, ac.name as accommodation_name, ac.admin_id 
            FROM invoices i
            JOIN users u ON i.user_id = u.id
            JOIN accommodations ac ON i.accommodation_id = ac.id";
    
    $params = [];
    $conditions = [];
    
    if ($userId !== null) {
        $conditions[] = "i.user_id = ?";
        $params[] = $userId;
    }
    
    if ($accommodationId !== null) {
        $conditions[] = "i.accommodation_id = ?";
        $params[] = $accommodationId;
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY i.created_at DESC";
    
    return fetchAll($sql, $params);
}

/**
 * Get all maintenance requests or those for a specific user or accommodation
 * @param int|null $userId User ID or null
 * @param int|null $accommodationId Accommodation ID or null
 * @return array Maintenance requests
 */
function getMaintenanceRequests($userId = null, $accommodationId = null) {
    $sql = "SELECT m.*, u.username, u.email, ac.name as accommodation_name, ac.admin_id 
            FROM maintenance_requests m
            JOIN users u ON m.user_id = u.id
            JOIN accommodations ac ON m.accommodation_id = ac.id";
    
    $params = [];
    $conditions = [];
    
    if ($userId !== null) {
        $conditions[] = "m.user_id = ?";
        $params[] = $userId;
    }
    
    if ($accommodationId !== null) {
        $conditions[] = "m.accommodation_id = ?";
        $params[] = $accommodationId;
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY m.created_at DESC";
    
    return fetchAll($sql, $params);
}

/**
 * Get maintenance request by ID
 * @param int $id Maintenance request ID
 * @return array|null Maintenance request details
 */
function getMaintenanceRequestById($id) {
    return fetchOne("SELECT m.*, u.username, u.email, ac.name as accommodation_name 
                    FROM maintenance_requests m
                    JOIN users u ON m.user_id = u.id
                    JOIN accommodations ac ON m.accommodation_id = ac.id
                    WHERE m.id = ?", [$id]);
}

/**
 * Check if an admin is assigned to an accommodation
 * @param int $adminId Admin ID
 * @param int $accommodationId Accommodation ID
 * @return bool Is assigned
 */
function isAdminAssignedToAccommodation($adminId, $accommodationId) {
    $result = fetchOne("SELECT id FROM accommodations WHERE id = ? AND admin_id = ?", 
                       [$accommodationId, $adminId]);
    return $result !== false;
}

/**
 * Get all admins
 * @return array Admins
 */
function getAdmins() {
    return fetchAll("SELECT * FROM users WHERE role = ? ORDER BY username ASC", [ROLE_ADMIN]);
}

/**
 * Get admin by ID
 * @param int $id Admin ID
 * @return array|null Admin details
 */
function getAdminById($id) {
    return fetchOne("SELECT * FROM users WHERE id = ? AND role = ?", [$id, ROLE_ADMIN]);
}

/**
 * Get accommodations assigned to an admin
 * @param int $adminId Admin ID
 * @return array Accommodations
 */
function getAdminAccommodations($adminId) {
    return fetchAll("SELECT * FROM accommodations WHERE admin_id = ? ORDER BY name ASC", [$adminId]);
}
?>
