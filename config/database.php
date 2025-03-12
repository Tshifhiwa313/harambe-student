<?php
/**
 * Database configuration and connection setup
 * 
 * This file handles the database connection and provides utility functions
 * for database operations. It supports both SQLite and MySQL.
 */

// Database configuration
define('DB_TYPE', 'sqlite'); // 'sqlite' or 'mysql'

// MySQL configuration (if using MySQL)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'harambee_student_living');

// SQLite configuration (if using SQLite)
define('SQLITE_PATH', __DIR__ . '/../db/harambee.db');

// Create database connection
$conn = null;

try {
    if (DB_TYPE === 'sqlite') {
        // Create the db directory if it doesn't exist
        if (!file_exists(__DIR__ . '/../db')) {
            mkdir(__DIR__ . '/../db', 0755, true);
        }
        
        // Connect to SQLite database
        $conn = new PDO('sqlite:' . SQLITE_PATH);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create tables if they don't exist
        createTables($conn);
    } else {
        // Connect to MySQL database
        $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create tables if they don't exist
        createTables($conn);
    }
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

/**
 * Creates all required tables if they don't exist
 *
 * @param PDO $conn Database connection
 */
function createTables($conn) {
    // Create users table
    $query = "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY " . (DB_TYPE === 'mysql' ? "AUTO_INCREMENT" : "AUTOINCREMENT") . ",
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        phone VARCHAR(20),
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        role VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($query);
    
    // Create accommodations table
    $query = "CREATE TABLE IF NOT EXISTS accommodations (
        id INTEGER PRIMARY KEY " . (DB_TYPE === 'mysql' ? "AUTO_INCREMENT" : "AUTOINCREMENT") . ",
        name VARCHAR(100) NOT NULL,
        description TEXT,
        address TEXT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        rooms_available INTEGER NOT NULL,
        image_path TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($query);
    
    // Create accommodation_admins table (for mapping admins to accommodations)
    $query = "CREATE TABLE IF NOT EXISTS accommodation_admins (
        id INTEGER PRIMARY KEY " . (DB_TYPE === 'mysql' ? "AUTO_INCREMENT" : "AUTOINCREMENT") . ",
        user_id INTEGER NOT NULL,
        accommodation_id INTEGER NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (accommodation_id) REFERENCES accommodations(id) ON DELETE CASCADE
    )";
    $conn->exec($query);
    
    // Create applications table
    $query = "CREATE TABLE IF NOT EXISTS applications (
        id INTEGER PRIMARY KEY " . (DB_TYPE === 'mysql' ? "AUTO_INCREMENT" : "AUTOINCREMENT") . ",
        user_id INTEGER NOT NULL,
        accommodation_id INTEGER NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        move_in_date DATE NOT NULL,
        additional_info TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (accommodation_id) REFERENCES accommodations(id) ON DELETE CASCADE
    )";
    $conn->exec($query);
    
    // Create leases table
    $query = "CREATE TABLE IF NOT EXISTS leases (
        id INTEGER PRIMARY KEY " . (DB_TYPE === 'mysql' ? "AUTO_INCREMENT" : "AUTOINCREMENT") . ",
        user_id INTEGER NOT NULL,
        accommodation_id INTEGER NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        monthly_rent DECIMAL(10,2) NOT NULL,
        security_deposit DECIMAL(10,2) NOT NULL,
        pdf_path TEXT,
        signed BOOLEAN DEFAULT 0,
        signed_date TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (accommodation_id) REFERENCES accommodations(id) ON DELETE CASCADE
    )";
    $conn->exec($query);
    
    // Create invoices table
    $query = "CREATE TABLE IF NOT EXISTS invoices (
        id INTEGER PRIMARY KEY " . (DB_TYPE === 'mysql' ? "AUTO_INCREMENT" : "AUTOINCREMENT") . ",
        lease_id INTEGER NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        due_date DATE NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
        pdf_path TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (lease_id) REFERENCES leases(id) ON DELETE CASCADE
    )";
    $conn->exec($query);
    
    // Create maintenance_requests table
    $query = "CREATE TABLE IF NOT EXISTS maintenance_requests (
        id INTEGER PRIMARY KEY " . (DB_TYPE === 'mysql' ? "AUTO_INCREMENT" : "AUTOINCREMENT") . ",
        user_id INTEGER NOT NULL,
        accommodation_id INTEGER NOT NULL,
        title VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        priority VARCHAR(20) NOT NULL DEFAULT 'medium',
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (accommodation_id) REFERENCES accommodations(id) ON DELETE CASCADE
    )";
    $conn->exec($query);
    
    // Create notifications table
    $query = "CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY " . (DB_TYPE === 'mysql' ? "AUTO_INCREMENT" : "AUTOINCREMENT") . ",
        user_id INTEGER NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(50) NOT NULL,
        is_read BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $conn->exec($query);
    
    // Insert default master admin if not exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'master_admin'");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, first_name, last_name, role) 
                                VALUES ('admin', :password, 'admin@harambee.com', 'Master', 'Admin', 'master_admin')");
        $stmt->bindParam(':password', $password);
        $stmt->execute();
    }
}

/**
 * Execute a prepared statement with parameters
 *
 * @param PDO $conn Database connection
 * @param string $query SQL query with placeholders
 * @param array $params Parameters for the query
 * @return PDOStatement The executed statement
 */
function executeQuery($conn, $query, $params = []) {
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        die("Query execution failed: " . $e->getMessage());
    }
}

/**
 * Get a single row from a query
 *
 * @param PDO $conn Database connection
 * @param string $query SQL query with placeholders
 * @param array $params Parameters for the query
 * @return array|null The fetched row or null if not found
 */
function fetchRow($conn, $query, $params = []) {
    $stmt = executeQuery($conn, $query, $params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get all rows from a query
 *
 * @param PDO $conn Database connection
 * @param string $query SQL query with placeholders
 * @param array $params Parameters for the query
 * @return array The fetched rows
 */
function fetchAll($conn, $query, $params = []) {
    $stmt = executeQuery($conn, $query, $params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Insert a row into a table
 *
 * @param PDO $conn Database connection
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @return int The last inserted ID
 */
function insertRow($conn, $table, $data) {
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    
    $query = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    executeQuery($conn, $query, $data);
    
    return $conn->lastInsertId();
}

/**
 * Update a row in a table
 *
 * @param PDO $conn Database connection
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @param string $whereCol The column to use in the WHERE clause
 * @param mixed $whereVal The value to match in the WHERE clause
 * @return bool True on success, false on failure
 */
function updateRow($conn, $table, $data, $whereCol, $whereVal) {
    $setClauses = [];
    foreach (array_keys($data) as $column) {
        $setClauses[] = "$column = :$column";
    }
    $setClause = implode(', ', $setClauses);
    
    $query = "UPDATE $table SET $setClause WHERE $whereCol = :whereVal";
    $data['whereVal'] = $whereVal;
    
    executeQuery($conn, $query, $data);
    return true;
}

/**
 * Delete a row from a table
 *
 * @param PDO $conn Database connection
 * @param string $table Table name
 * @param string $whereCol The column to use in the WHERE clause
 * @param mixed $whereVal The value to match in the WHERE clause
 * @return bool True on success, false on failure
 */
function deleteRow($conn, $table, $whereCol, $whereVal) {
    $query = "DELETE FROM $table WHERE $whereCol = :whereVal";
    executeQuery($conn, $query, ['whereVal' => $whereVal]);
    return true;
}
?>
