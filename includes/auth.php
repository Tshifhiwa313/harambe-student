<?php
require_once 'authentication.php';
/**
 * Authentication functions
 * 
 * This file contains functions for user authentication, registration, and session management.
 */

// Start or resume the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Register a new user
 *
 * @param PDO $conn Database connection
 * @param array $userData User data array
 * @return int|false The new user ID on success, false on failure
 */
function registerUser($conn, $userData) {
    // Validate required fields
    $requiredFields = ['username', 'password', 'email', 'first_name', 'last_name', 'role'];
    foreach ($requiredFields as $field) {
        if (!isset($userData[$field]) || empty($userData[$field])) {
            return false;
        }
    }
    
    // Validate email format
    if (!isValidEmail($userData['email'])) {
        return false;
    }
    
    // Validate phone number if provided
    if (isset($userData['phone']) && !empty($userData['phone']) && !isValidPhone($userData['phone'])) {
        return false;
    }
    
    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :email");
    $stmt->bindParam(':username', $userData['username']);
    $stmt->bindParam(':email', $userData['email']);
    $stmt->execute();
    
    if ($stmt->fetchColumn() > 0) {
        return false; // Username or email already exists
    }
    
    // Hash password
    $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
    
    // Insert user into database
    return insertRow($conn, 'users', $userData);
}

/**
 * Authenticate a user
 *
 * @param PDO $conn Database connection
 * @param string $username Username
 * @param string $password Password
 * @return array|false User data on success, false on failure
 */
function loginNewUser($conn, $username, $password) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return false; // User not found
    }
    
    if (!password_verify($password, $user['password'])) {
        return false; // Password incorrect
    }
    
    // Update session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['last_activity'] = time();
    
    return $user;
}

/**
 * Log out a user
 *
 * @return void
 */
function logoutUser() {
    // Unset all session variables
    $_SESSION = [];
    
    // If it's desired to kill the session, also delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Finally, destroy the session
    session_destroy();
}

/**
 * Check if the user is logged in
 *
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get the current user's ID
 *
 * @return int|null The user ID or null if not logged in
 */
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Get the current user's role
 *
 * @return string|null The user role or null if not logged in
 */
function getCurrentUserRole() {
    return isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
}

/**
 * Get the current user's name
 *
 * @return string|null The user name or null if not logged in
 */
function getCurrentUserName() {
    return isset($_SESSION['user_name']) ? $_SESSION['user_name'] : null;
}

/**
 * Update user profile
 *
 * @param PDO $conn Database connection
 * @param int $userId User ID
 * @param array $userData User data to update
 * @return bool True on success, false on failure
 */
function updateUserProfile($conn, $userId, $userData) {
    // Validate required fields
    if (!isset($userData['first_name']) || empty($userData['first_name']) || 
        !isset($userData['last_name']) || empty($userData['last_name'])) {
        return false;
    }
    
    // Validate email if provided
    if (isset($userData['email']) && !empty($userData['email']) && !isValidEmail($userData['email'])) {
        return false;
    }
    
    // Validate phone if provided
    if (isset($userData['phone']) && !empty($userData['phone']) && !isValidPhone($userData['phone'])) {
        return false;
    }
    
    // Check if email already exists (if changing email)
    if (isset($userData['email'])) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND id != :userId");
        $stmt->bindParam(':email', $userData['email']);
        $stmt->bindParam(':userId', $userId);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            return false; // Email already in use by another user
        }
    }
    
    // Update user in database
    return updateRow($conn, 'users', $userData, 'id', $userId);
}

/**
 * Change user password
 *
 * @param PDO $conn Database connection
 * @param int $userId User ID
 * @param string $currentPassword Current password
 * @param string $newPassword New password
 * @return bool True on success, false on failure
 */
function changeUserPassword($conn, $userId, $currentPassword, $newPassword) {
    // Get user from database
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = :userId");
    $stmt->bindParam(':userId', $userId);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return false; // User not found
    }
    
    if (!password_verify($currentPassword, $user['password'])) {
        return false; // Current password incorrect
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password in database
    $data = ['password' => $hashedPassword];
    return updateRow($conn, 'users', $data, 'id', $userId);
}

/**
 * Check if the session has expired
 *
 * @return bool True if expired, false otherwise
 */
function isSessionExpired() {
    $lastActivity = isset($_SESSION['last_activity']) ? $_SESSION['last_activity'] : 0;
    $currentTime = time();
    
    return ($currentTime - $lastActivity) > SESSION_TIMEOUT;
}

/**
 * Update the last activity time
 *
 * @return void
 */
function updateLastActivity() {
    $_SESSION['last_activity'] = time();
}

/**
 * Check if user has permission to access a resource
 *
 * @param string $resource The resource to check
 * @param string $action The action to check
 * @return bool True if permitted, false otherwise
 */
function hasPermission($resource, $action) {
    $userRole = getCurrentUserRole();
    
    if (!$userRole) {
        return false;
    }
    
    // Master admin has all permissions
    if ($userRole === ROLE_MASTER_ADMIN) {
        return true;
    }
    
    // Define permissions for different roles
    $permissions = [
        ROLE_ADMIN => [
            'accommodation' => ['view', 'edit'],
            'application' => ['view', 'approve', 'reject'],
            'lease' => ['view', 'create', 'edit'],
            'invoice' => ['view', 'create', 'edit'],
            'maintenance' => ['view', 'update'],
            'notification' => ['view', 'create']
        ],
        ROLE_STUDENT => [
            'accommodation' => ['view'],
            'application' => ['view', 'create'],
            'lease' => ['view', 'sign'],
            'invoice' => ['view'],
            'maintenance' => ['view', 'create'],
            'notification' => ['view']
        ]
    ];
    
    if (isset($permissions[$userRole][$resource]) && 
        in_array($action, $permissions[$userRole][$resource])) {
        return true;
    }
    
    return false;
}

/**
 * Require authentication to access a page
 *
 * @param string|array $roles Required role(s)
 * @return void
 */
function requireAuth($roles = null) {
    if (!isLoggedIn()) {
        setFlashMessage('You must be logged in to access this page.', 'warning');
        redirect('/auth/login.php');
    }
    
    if (isSessionExpired()) {
        logoutUser();
        setFlashMessage('Your session has expired. Please log in again.', 'warning');
        redirect('/auth/login.php');
    }
    
    updateLastActivity();
    
    if ($roles !== null && !hasRole($roles)) {
        setFlashMessage('You do not have permission to access this page.', 'danger');
        redirect('/index.php');
    }
}
?>
