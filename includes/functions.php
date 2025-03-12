<?php
/**
 * Common utility functions
 * 
 * This file contains general utility functions used throughout the application.
 */

/**
 * Sanitize user input to prevent XSS attacks
 *
 * @param string $data The input data to sanitize
 * @return string The sanitized data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email format
 *
 * @param string $email The email to validate
 * @return bool True if valid, false otherwise
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number format
 *
 * @param string $phone The phone number to validate
 * @return bool True if valid, false otherwise
 */
function isValidPhone($phone) {
    // Remove non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if it has 10-15 digits
    return preg_match('/^[0-9]{10,15}$/', $phone);
}

/**
 * Validate date format (YYYY-MM-DD)
 *
 * @param string $date The date to validate
 * @return bool True if valid, false otherwise
 */
function isValidDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Generate a random string
 *
 * @param int $length The length of the string
 * @return string The random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Format currency
 *
 * @param float $amount The amount to format
 * @return string The formatted amount
 */
function formatCurrency($amount) {
    return 'R ' . number_format($amount, 2);
}

/**
 * Format date
 *
 * @param string $date The date to format
 * @param string $format The format to use
 * @return string The formatted date
 */
function formatDate($date, $format = 'd F Y') {
    $d = new DateTime($date);
    return $d->format($format);
}

/**
 * Calculate the difference between two dates in days
 *
 * @param string $date1 The first date
 * @param string $date2 The second date
 * @return int The difference in days
 */
function dateDiffInDays($date1, $date2) {
    $d1 = new DateTime($date1);
    $d2 = new DateTime($date2);
    $diff = $d1->diff($d2);
    return abs($diff->days);
}

/**
 * Upload a file
 *
 * @param array $file The $_FILES array element
 * @param string $uploadDir The directory to upload to
 * @param array $allowedTypes The allowed MIME types
 * @param int $maxSize The maximum file size
 * @return string|false The file path on success, false on failure
 */
function uploadFile($file, $uploadDir, $allowedTypes = ALLOWED_IMAGE_TYPES, $maxSize = MAX_FILE_SIZE) {
    // Check if file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        return false;
    }
    
    // Check file type
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $fileType = finfo_file($fileInfo, $file['tmp_name']);
    finfo_close($fileInfo);
    
    if (!in_array($fileType, $allowedTypes)) {
        return false;
    }
    
    // Generate a unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filePath = $uploadDir . '/' . $filename;
    
    // Upload the file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return false;
    }
    
    return $filename;
}

/**
 * Delete a file
 *
 * @param string $filePath The path of the file to delete
 * @return bool True on success, false on failure
 */
function deleteFile($filePath) {
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return false;
}

/**
 * Log a message to a file
 *
 * @param string $message The message to log
 * @param string $level The log level (info, warning, error)
 */
function logMessage($message, $level = 'info') {
    $logFile = __DIR__ . '/../logs/' . date('Y-m-d') . '.log';
    
    // Create logs directory if it doesn't exist
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Redirect to a URL
 *
 * @param string $url The URL to redirect to
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Display a flash message
 *
 * @param string $message The message to display
 * @param string $type The message type (success, warning, danger, info)
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Get and clear flash message
 *
 * @return array|null The flash message or null if none exists
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $flashMessage = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flashMessage;
    }
    return null;
}

/**
 * Display flash message HTML
 */
function displayFlashMessage() {
    $flashMessage = getFlashMessage();
    if ($flashMessage) {
        echo '<div class="alert alert-' . $flashMessage['type'] . ' alert-dismissible fade show" role="alert">';
        echo $flashMessage['message'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}

/**
 * Check if the current user has a specific role
 *
 * @param string|array $roles The role(s) to check
 * @return bool True if the user has the role, false otherwise
 */
function hasRole($roles) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    if (is_array($roles)) {
        return in_array($_SESSION['user_role'], $roles);
    } else {
        return $_SESSION['user_role'] === $roles;
    }
}

/**
 * Get accommodation by ID
 *
 * @param PDO $conn Database connection
 * @param int $id Accommodation ID
 * @return array|null The accommodation or null if not found
 */
function getAccommodation($conn, $id) {
    return fetchRow($conn, "SELECT * FROM accommodations WHERE id = :id", ['id' => $id]);
}

/**
 * Get user by ID
 *
 * @param PDO $conn Database connection
 * @param int $id User ID
 * @return array|null The user or null if not found
 */
function getUser($conn, $id) {
    return fetchRow($conn, "SELECT * FROM users WHERE id = :id", ['id' => $id]);
}

/**
 * Check if an admin is assigned to an accommodation
 *
 * @param PDO $conn Database connection
 * @param int $userId User ID
 * @param int $accommodationId Accommodation ID
 * @return bool True if assigned, false otherwise
 */
function isAdminAssignedToAccommodation($conn, $userId, $accommodationId) {
    $query = "SELECT COUNT(*) FROM accommodation_admins 
              WHERE user_id = :userId AND accommodation_id = :accommodationId";
    $params = [
        'userId' => $userId,
        'accommodationId' => $accommodationId
    ];
    
    $count = executeQuery($conn, $query, $params)->fetchColumn();
    return $count > 0;
}

/**
 * Get accommodations managed by an admin
 *
 * @param PDO $conn Database connection
 * @param int $adminId Admin user ID
 * @return array The accommodations
 */
function getAdminAccommodations($conn, $adminId) {
    $query = "SELECT a.* FROM accommodations a
              JOIN accommodation_admins aa ON a.id = aa.accommodation_id
              WHERE aa.user_id = :adminId";
    return fetchAll($conn, $query, ['adminId' => $adminId]);
}

/**
 * Get application by ID
 *
 * @param PDO $conn Database connection
 * @param int $id Application ID
 * @return array|null The application or null if not found
 */
function getApplication($conn, $id) {
    return fetchRow($conn, "SELECT * FROM applications WHERE id = :id", ['id' => $id]);
}

/**
 * Get lease by ID
 *
 * @param PDO $conn Database connection
 * @param int $id Lease ID
 * @return array|null The lease or null if not found
 */
function getLease($conn, $id) {
    return fetchRow($conn, "SELECT * FROM leases WHERE id = :id", ['id' => $id]);
}

/**
 * Get invoice by ID
 *
 * @param PDO $conn Database connection
 * @param int $id Invoice ID
 * @return array|null The invoice or null if not found
 */
function getInvoice($conn, $id) {
    return fetchRow($conn, "SELECT * FROM invoices WHERE id = :id", ['id' => $id]);
}

/**
 * Get maintenance request by ID
 *
 * @param PDO $conn Database connection
 * @param int $id Maintenance request ID
 * @return array|null The maintenance request or null if not found
 */
function getMaintenanceRequest($conn, $id) {
    return fetchRow($conn, "SELECT * FROM maintenance_requests WHERE id = :id", ['id' => $id]);
}

/**
 * Check if accommodation has available rooms
 *
 * @param PDO $conn Database connection
 * @param int $accommodationId Accommodation ID
 * @return bool True if rooms are available, false otherwise
 */
function hasAvailableRooms($conn, $accommodationId) {
    $accommodation = getAccommodation($conn, $accommodationId);
    if (!$accommodation) {
        return false;
    }
    
    // Count active leases for this accommodation
    $query = "SELECT COUNT(*) FROM leases 
              WHERE accommodation_id = :accommodationId 
              AND end_date >= CURRENT_DATE";
    $params = ['accommodationId' => $accommodationId];
    
    $activeLeases = executeQuery($conn, $query, $params)->fetchColumn();
    
    return $accommodation['rooms_available'] > $activeLeases;
}

/**
 * Check if a student has an active lease
 *
 * @param PDO $conn Database connection
 * @param int $studentId Student user ID
 * @return bool True if the student has an active lease, false otherwise
 */
function hasActiveLease($conn, $studentId) {
    $query = "SELECT COUNT(*) FROM leases 
              WHERE user_id = :studentId 
              AND end_date >= CURRENT_DATE";
    $params = ['studentId' => $studentId];
    
    $count = executeQuery($conn, $query, $params)->fetchColumn();
    return $count > 0;
}

/**
 * Get active lease for a student
 *
 * @param PDO $conn Database connection
 * @param int $studentId Student user ID
 * @return array|null The active lease or null if none exists
 */
function getActiveLease($conn, $studentId) {
    $query = "SELECT * FROM leases 
              WHERE user_id = :studentId 
              AND end_date >= CURRENT_DATE
              ORDER BY end_date DESC
              LIMIT 1";
    $params = ['studentId' => $studentId];
    
    return fetchRow($conn, $query, $params);
}

/**
 * Calculate the total amount due for a student
 *
 * @param PDO $conn Database connection
 * @param int $studentId Student user ID
 * @return float The total amount due
 */
function calculateTotalDue($conn, $studentId) {
    $query = "SELECT SUM(i.amount) FROM invoices i
              JOIN leases l ON i.lease_id = l.id
              WHERE l.user_id = :studentId
              AND i.status = :status";
    $params = [
        'studentId' => $studentId,
        'status' => INVOICE_UNPAID
    ];
    
    $total = executeQuery($conn, $query, $params)->fetchColumn();
    return $total ? $total : 0;
}
?>
