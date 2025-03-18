<?php
require_once 'authentication.php';
require_once 'config.php';
require_once 'database.php';
/**
 * Register a new user
 * @param string $username Username
 * @param string $email Email
 * @param string $password Password
 * @param int $role User role
 * @param array $userData Additional user data
 * @return int|false User ID or false on failure
 */
function registerNewUser($username, $email, $password, $role, $userData = []) {
    // Check if username or email already exists
    $existingUser = fetchOne("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
    if ($existingUser) {
        return false; // User already exists
    }
    
    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Create user data array
    $data = [
        'username' => $username,
        'email' => $email,
        'password' => $hashedPassword,
        'role' => $role,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Merge additional user data
    $data = array_merge($data, $userData);
    
    // Insert the user
    $userId = insert('users', $data);
    
    // Create profile based on role
    if ($userId && $role == ROLE_STUDENT) {
        $profileData = [
            'user_id' => $userId,
            'student_number' => $userData['student_number'] ?? '',
            'college' => $userData['college'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Remove these fields from userData to avoid duplicate storage
        unset($userData['student_number'], $userData['college']);
        
        insert('student_profiles', $profileData);
    }
    
    return $userId;
}

/**
 * Log in a user
 * @param string $username Username or email
 * @param string $password Password
 * @return bool Login success
 */
function loginUser($username, $password) {
    // Find user by username or email
    $user = fetchOne("SELECT * FROM users WHERE username = ? OR email = ?", [$username, $username]);
    
    if (!$user) {
        return false; // User not found
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        return false; // Password incorrect
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    
    // Update last login time
    update('users', ['last_login' => date('Y-m-d H:i:s')], 'id', $user['id']);
    
    return true;
}

/**
 * Check if user is logged in
 * @return bool User logged in status
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user data
 * @return array|null User data or null if not logged in
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

/**
 * Check if current user has a specific role
 * @param int|array $roles Role or array of roles to check
 * @return bool Has role status
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['role'], $roles);
}

/**
 * Log out the current user
 */
function logoutUser() {
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy the session
    session_destroy();
}

/**
 * Require user to be logged in
 * Redirects to login page if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Require user to have a specific role
 * @param int|array $roles Role or array of roles
 * @param string $redirect Redirect URL if not authorized
 */
function requireRole($roles, $redirect = 'index.php') {
    requireLogin();
    
    if (!hasRole($roles)) {
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Generate a random password
 * @param int $length Password length
 * @return string Random password
 */
function generateRandomPassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    
    return $password;
}
?>
