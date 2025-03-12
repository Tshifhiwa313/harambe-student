<?php
// Database connection and utility functions
require_once 'config.php';

// Global database connection variable
$conn = null;

/**
 * Establish database connection
 * @return PDO|mysqli Database connection
 */
function db_connect() {
    global $conn;
    
    if ($conn !== null) {
        return $conn;
    }
    
    try {
        if (DB_TYPE === 'sqlite') {
            // Create the database directory if it doesn't exist
            $db_dir = dirname(DB_PATH);
            if (!file_exists($db_dir)) {
                mkdir($db_dir, 0755, true);
            }
            
            $conn = new PDO('sqlite:' . DB_PATH);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Initialize the database if not already set up
            init_database($conn);
        } else {
            // MySQL connection
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }
            
            // Initialize the database if not already set up
            init_mysql_database($conn);
        }
        
        return $conn;
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        die("Database connection failed. Please check the logs for more information.");
    }
}

/**
 * Initialize SQLite database tables if they don't exist
 * @param PDO $conn Database connection
 */
function init_database($conn) {
    // Users table
    $conn->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        phone_number TEXT,
        full_name TEXT NOT NULL,
        role TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Accommodations table
    $conn->exec("CREATE TABLE IF NOT EXISTS accommodations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT NOT NULL,
        location TEXT NOT NULL,
        capacity INTEGER NOT NULL,
        price_per_month REAL NOT NULL,
        available_units INTEGER NOT NULL,
        admin_id INTEGER,
        image_path TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES users(id)
    )");
    
    // Applications table
    $conn->exec("CREATE TABLE IF NOT EXISTS applications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL,
        accommodation_id INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id),
        FOREIGN KEY (accommodation_id) REFERENCES accommodations(id)
    )");
    
    // Leases table
    $conn->exec("CREATE TABLE IF NOT EXISTS leases (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL,
        accommodation_id INTEGER NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        monthly_rent REAL NOT NULL,
        status TEXT NOT NULL DEFAULT 'draft',
        pdf_path TEXT,
        signed_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id),
        FOREIGN KEY (accommodation_id) REFERENCES accommodations(id)
    )");
    
    // Invoices table
    $conn->exec("CREATE TABLE IF NOT EXISTS invoices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lease_id INTEGER NOT NULL,
        amount REAL NOT NULL,
        description TEXT NOT NULL,
        due_date DATE NOT NULL,
        status TEXT NOT NULL DEFAULT 'unpaid',
        pdf_path TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (lease_id) REFERENCES leases(id)
    )");
    
    // Maintenance requests table
    $conn->exec("CREATE TABLE IF NOT EXISTS maintenance_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL,
        accommodation_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        priority TEXT NOT NULL DEFAULT 'medium',
        status TEXT NOT NULL DEFAULT 'pending',
        admin_notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id),
        FOREIGN KEY (accommodation_id) REFERENCES accommodations(id)
    )");
    
    // Notifications table
    $conn->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        message TEXT NOT NULL,
        type TEXT NOT NULL,
        is_read INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    
    // Create a master admin user if none exists
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = ?");
    $stmt->execute([ROLE_MASTER_ADMIN]);
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        // Create default master admin
        $password_hash = password_hash('admin123', HASH_ALGO, ['cost' => HASH_COST]);
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, full_name, role, phone_number) 
                                VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['masteradmin', $password_hash, 'masteradmin@harambee.com', 
                        'Master Administrator', ROLE_MASTER_ADMIN, '+27123456789']);
    }
}

/**
 * Initialize MySQL database tables if they don't exist
 * @param mysqli $conn Database connection
 */
function init_mysql_database($conn) {
    // Create database if it doesn't exist
    $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $conn->select_db(DB_NAME);
    
    // Users table
    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        phone_number VARCHAR(20),
        full_name VARCHAR(100) NOT NULL,
        role VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Accommodations table
    $conn->query("CREATE TABLE IF NOT EXISTS accommodations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        location VARCHAR(255) NOT NULL,
        capacity INT NOT NULL,
        price_per_month DECIMAL(10,2) NOT NULL,
        available_units INT NOT NULL,
        admin_id INT,
        image_path VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES users(id)
    )");
    
    // Applications table
    $conn->query("CREATE TABLE IF NOT EXISTS applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        accommodation_id INT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id),
        FOREIGN KEY (accommodation_id) REFERENCES accommodations(id)
    )");
    
    // Leases table
    $conn->query("CREATE TABLE IF NOT EXISTS leases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        accommodation_id INT NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        monthly_rent DECIMAL(10,2) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        pdf_path VARCHAR(255),
        signed_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id),
        FOREIGN KEY (accommodation_id) REFERENCES accommodations(id)
    )");
    
    // Invoices table
    $conn->query("CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lease_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        description TEXT NOT NULL,
        due_date DATE NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
        pdf_path VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (lease_id) REFERENCES leases(id)
    )");
    
    // Maintenance requests table
    $conn->query("CREATE TABLE IF NOT EXISTS maintenance_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        accommodation_id INT NOT NULL,
        title VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        priority VARCHAR(20) NOT NULL DEFAULT 'medium',
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        admin_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id),
        FOREIGN KEY (accommodation_id) REFERENCES accommodations(id)
    )");
    
    // Notifications table
    $conn->query("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(50) NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    
    // Create a master admin user if none exists
    $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = '" . ROLE_MASTER_ADMIN . "'");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        // Create default master admin
        $password_hash = password_hash('admin123', HASH_ALGO, ['cost' => HASH_COST]);
        $conn->query("INSERT INTO users (username, password, email, full_name, role, phone_number) 
                     VALUES ('masteradmin', '$password_hash', 'masteradmin@harambee.com', 
                     'Master Administrator', '" . ROLE_MASTER_ADMIN . "', '+27123456789')");
    }
}

/**
 * Execute a database query with prepared statements (SQLite)
 * @param string $query SQL query with placeholders
 * @param array $params Parameters for prepared statement
 * @return PDOStatement Query result
 */
function db_query_sqlite($query, $params = []) {
    $conn = db_connect();
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
        die("Database query failed. Please check the logs for more information.");
    }
}

/**
 * Execute a database query with prepared statements (MySQL)
 * @param string $query SQL query with placeholders
 * @param array $params Parameters for prepared statement
 * @return mysqli_stmt|bool Query result
 */
function db_query_mysql($query, $params = []) {
    $conn = db_connect();
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Database preparation error: " . $conn->error);
        die("Database query failed. Please check the logs for more information.");
    }
    
    if (!empty($params)) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 'b';
            }
        }
        
        $stmt->bind_param($types, ...$params);
    }
    
    try {
        $stmt->execute();
        return $stmt;
    } catch (Exception $e) {
        error_log("Database query error: " . $e->getMessage());
        die("Database query failed. Please check the logs for more information.");
    }
}

/**
 * Execute a database query with prepared statements
 * @param string $query SQL query with placeholders
 * @param array $params Parameters for prepared statement
 * @return mixed Query result
 */
function db_query($query, $params = []) {
    if (DB_TYPE === 'sqlite') {
        return db_query_sqlite($query, $params);
    } else {
        return db_query_mysql($query, $params);
    }
}

/**
 * Get a single row from a database query
 * @param string $query SQL query with placeholders
 * @param array $params Parameters for prepared statement
 * @return array|null Row data or null if not found
 */
function db_fetch($query, $params = []) {
    if (DB_TYPE === 'sqlite') {
        $stmt = db_query_sqlite($query, $params);
        return $stmt->fetch();
    } else {
        $stmt = db_query_mysql($query, $params);
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}

/**
 * Get all rows from a database query
 * @param string $query SQL query with placeholders
 * @param array $params Parameters for prepared statement
 * @return array Array of rows
 */
function db_fetch_all($query, $params = []) {
    if (DB_TYPE === 'sqlite') {
        $stmt = db_query_sqlite($query, $params);
        return $stmt->fetchAll();
    } else {
        $stmt = db_query_mysql($query, $params);
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}

/**
 * Get the ID of the last inserted row
 * @return int Last inserted ID
 */
function db_last_insert_id() {
    $conn = db_connect();
    if (DB_TYPE === 'sqlite') {
        return $conn->lastInsertId();
    } else {
        return $conn->insert_id;
    }
}

/**
 * Begin a database transaction
 */
function db_begin_transaction() {
    $conn = db_connect();
    if (DB_TYPE === 'sqlite') {
        $conn->beginTransaction();
    } else {
        $conn->begin_transaction();
    }
}

/**
 * Commit a database transaction
 */
function db_commit() {
    $conn = db_connect();
    if (DB_TYPE === 'sqlite') {
        $conn->commit();
    } else {
        $conn->commit();
    }
}

/**
 * Rollback a database transaction
 */
function db_rollback() {
    $conn = db_connect();
    if (DB_TYPE === 'sqlite') {
        $conn->rollBack();
    } else {
        $conn->rollback();
    }
}

// Initialize database connection
db_connect();
