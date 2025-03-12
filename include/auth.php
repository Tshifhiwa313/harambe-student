<?php
// Authentication and authorization functions
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Register a new user
 * @param string $username Username
 * @param string $password Password
 * @param string $email Email address
 * @param string $full_name Full name
 * @param string $phone_number Phone number
 * @param string $role User role
 * @return int|false User ID on success, false on failure
 */
function register_user($username, $password, $email, $full_name, $phone_number, $role = ROLE_STUDENT) {
    // Validate input
    if (empty($username) || empty($password) || empty($email) || empty($full_name)) {
        return false;
    }
    
    // Check if username or email already exists
    $existing_user = get_user_by_username($username);
    if ($existing_user) {
        return false;
    }
    
    $existing_email = get_user_by_email($email);
    if ($existing_email) {
        return false;
    }
    
    // Hash the password
    $password_hash = password_hash($password, HASH_ALGO, ['cost' => HASH_COST]);
    
    // Insert the new user
    $query = "INSERT INTO users (username, password, email, full_name, phone_number, role) 
              VALUES (?, ?, ?, ?, ?, ?)";
    db_query($query, [$username, $password_hash, $email, $full_name, $phone_number, $role]);
    
    return db_last_insert_id();
}

/**
 * Authenticate a user
 * @param string $username Username
 * @param string $password Password
 * @return array|false User data on success, false on failure
 */
function login_user($username, $password) {
    // Get user by username
    $user = get_user_by_username($username);
    if (!$user) {
        return false;
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        return false;
    }
    
    // Set session data
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    
    return $user;
}

/**
 * Check if a user is logged in
 * @return bool True if logged in, false otherwise
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user data
 * @return array|null User data if logged in, null otherwise
 */
function get_current_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email'],
        'full_name' => $_SESSION['full_name'],
        'role' => $_SESSION['role']
    ];
}

/**
 * Get current user ID
 * @return int|null User ID if logged in, null otherwise
 */
function get_current_user_id() {
    return is_logged_in() ? $_SESSION['user_id'] : null;
}

/**
 * Get current user role
 * @return string|null User role if logged in, null otherwise
 */
function get_current_user_role() {
    return is_logged_in() ? $_SESSION['role'] : null;
}

/**
 * Log out the current user
 */
function logout_user() {
    // Unset all session variables
    $_SESSION = [];
    
    // If it's desired to kill the session, also delete the session cookie.
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Finally, destroy the session.
    session_destroy();
}

/**
 * Check if the current user has a specific role
 * @param string|array $roles Role or array of roles to check
 * @return bool True if user has role, false otherwise
 */
function user_has_role($roles) {
    if (!is_logged_in()) {
        return false;
    }
    
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['role'], $roles);
}

/**
 * Check if the current user is a master admin
 * @return bool True if master admin, false otherwise
 */
function is_master_admin() {
    return is_logged_in() && $_SESSION['role'] === ROLE_MASTER_ADMIN;
}

/**
 * Check if the current user is an admin
 * @return bool True if admin, false otherwise
 */
function is_admin() {
    return is_logged_in() && ($_SESSION['role'] === ROLE_ADMIN || $_SESSION['role'] === ROLE_MASTER_ADMIN);
}

/**
 * Check if the current user is a student
 * @return bool True if student, false otherwise
 */
function is_student() {
    return is_logged_in() && $_SESSION['role'] === ROLE_STUDENT;
}

/**
 * Require user to be logged in
 * Redirects to login page if not logged in
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash_message('warning', 'Please log in to access this page.');
        redirect('login.php');
    }
    
    // Check if session has expired
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        logout_user();
        set_flash_message('warning', 'Your session has expired. Please log in again.');
        redirect('login.php');
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
}

/**
 * Require user to have a specific role
 * Redirects to appropriate page if not authorized
 * @param string|array $roles Role or array of roles required
 */
function require_role($roles) {
    require_login();
    
    if (!user_has_role($roles)) {
        set_flash_message('error', 'You do not have permission to access this page.');
        redirect('index.php');
    }
}

/**
 * Check if user has access to a specific accommodation
 * @param int $accommodation_id Accommodation ID
 * @return bool True if has access, false otherwise
 */
function has_accommodation_access($accommodation_id) {
    if (!is_logged_in()) {
        return false;
    }
    
    return user_has_accommodation_access(
        $_SESSION['user_id'],
        $accommodation_id,
        $_SESSION['role']
    );
}

/**
 * Require access to a specific accommodation
 * @param int $accommodation_id Accommodation ID
 */
function require_accommodation_access($accommodation_id) {
    require_login();
    
    if (!has_accommodation_access($accommodation_id)) {
        set_flash_message('error', 'You do not have access to this accommodation.');
        redirect('index.php');
    }
}

/**
 * Update user profile
 * @param int $user_id User ID
 * @param array $data User data to update
 * @return bool True on success, false on failure
 */
function update_user_profile($user_id, $data) {
    // Validate input
    if (empty($data['full_name']) || empty($data['email'])) {
        return false;
    }
    
    // Check if email already exists for another user
    $existing_email = get_user_by_email($data['email']);
    if ($existing_email && $existing_email['id'] != $user_id) {
        return false;
    }
    
    // Update the user
    $query = "UPDATE users SET full_name = ?, email = ?, phone_number = ? WHERE id = ?";
    $params = [$data['full_name'], $data['email'], $data['phone_number'], $user_id];
    
    // Update password if provided
    if (!empty($data['password'])) {
        $password_hash = password_hash($data['password'], HASH_ALGO, ['cost' => HASH_COST]);
        $query = "UPDATE users SET full_name = ?, email = ?, phone_number = ?, password = ? WHERE id = ?";
        $params = [$data['full_name'], $data['email'], $data['phone_number'], $password_hash, $user_id];
    }
    
    db_query($query, $params);
    
    // Update session data if it's the current user
    if (is_logged_in() && $_SESSION['user_id'] == $user_id) {
        $_SESSION['full_name'] = $data['full_name'];
        $_SESSION['email'] = $data['email'];
    }
    
    return true;
}

/**
 * Change user password
 * @param int $user_id User ID
 * @param string $current_password Current password
 * @param string $new_password New password
 * @return bool True on success, false on failure
 */
function change_user_password($user_id, $current_password, $new_password) {
    // Get user
    $user = get_user($user_id);
    if (!$user) {
        return false;
    }
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        return false;
    }
    
    // Hash the new password
    $password_hash = password_hash($new_password, HASH_ALGO, ['cost' => HASH_COST]);
    
    // Update the password
    $query = "UPDATE users SET password = ? WHERE id = ?";
    db_query($query, [$password_hash, $user_id]);
    
    return true;
}
