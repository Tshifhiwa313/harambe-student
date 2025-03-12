<?php
/**
 * Database Schema Setup
 * This file creates the database tables if they don't exist
 */
require_once 'includes/config.php';
require_once 'includes/database.php';

// Create a session flag to prevent redirect loops
session_start();

// Display header for the schema page
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Setup</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container mt-5'>
        <div class='card'>
            <div class='card-header bg-primary text-white'>
                <h2>Database Setup</h2>
            </div>
            <div class='card-body'>";

// Function to create tables
function createTables() {
    $pdo = connectDB();
    
    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY " . (DB_TYPE === 'mysql' ? "AUTO_INCREMENT" : "AUTOINCREMENT") . ",
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role INTEGER NOT NULL,
        phone VARCHAR(20),
        first_name VARCHAR(50),
        last_name VARCHAR(50),
        created_at DATETIME NOT NULL,
        last_login DATETIME
    )");
    
    // Create student_profiles table
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_profiles (
        id INTEGER PRIMARY KEY " . (DB_TYPE === 'mysql' ? "AUTO_INCREMENT" : "AUTOINCREMENT") . ",
        user_id INTEGER NOT NULL,
        student_number VARCHAR(50),
        date_of_birth DATE,
        gender VARCHAR(10),
        id_number VARCHAR(20),
        address TEXT,
        emergency_contact_name VARCHAR(100),
        emergency_contact_phone VARCHAR(20),
        created_at DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    )");
    
    // Create accommodations table
    $pdo->exec("CREATE TABLE IF NOT EXISTS accommodations (
        id INTEGER PRIMARY KEY " . (DB_TYPE === 'mysql' ? "AUTO_INCREMENT" : "AUTOINCREMENT") . ",
        name VARCHAR(100) NOT NULL,
        location TEXT NOT NULL,
        description TEXT,
        rooms_available INTEGER NOT NULL,
        price_per_month DECIMAL(10,2) NOT NULL,
        admin_id INTEGER,
        image_path VARCHAR(255),
        created_at DATETIME NOT NULL,
        FOREIGN KEY (admin_id) REFERENCES users (id)
    )");
    
    // Create applications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS applications (
        id INTEGER PRIMARY KEY " . (DB_TYPE === 'mysql' ? "AUTO_INCREMENT" : "AUTOINCREMENT") . ",
        user_id INTEGER NOT NULL,
        accommodation_id INTEGER NOT NULL,
        status INTEGER NOT NULL DEFAULT 1,
        notes TEXT,
        created_at DATETIME NOT NULL,
        updated_at DATETIME,
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        FOREIGN KEY (accommodation_id) REFERENCES accommodations (id) ON DELETE CASCADE
    )");
    
    // Create leases table
    $pdo->exec("CREATE TABLE IF NOT EXISTS leases (
        id INTEGER PRIMARY KEY " . (DB_TYPE === 'mysql' ? "AUTO_INCREMENT" : "AUTOINCREMENT") . ",
        user_id INTEGER NOT NULL,
        accommodation_id INTEGER NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        monthly_rent DECIMAL(10,2) NOT NULL,
        security_deposit DECIMAL(10,2) NOT NULL,
        is_signed BOOLEAN NOT NULL DEFAULT 0,
        signed_at DATETIME,
        pdf_path VARCHAR(255),
        created_at DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        FOREIGN KEY (accommodation_id) REFERENCES accommodations (id) ON DELETE CASCADE
    )");
    
    // Create invoices table
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
        id INTEGER PRIMARY KEY " . (DB_TYPE === 'mysql' ? "AUTO_INCREMENT" : "AUTOINCREMENT") . ",
        user_id INTEGER NOT NULL,
        accommodation_id INTEGER NOT NULL,
        lease_id INTEGER NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        late_fee DECIMAL(10,2) DEFAULT 0,
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        due_date DATE NOT NULL,
        paid BOOLEAN NOT NULL DEFAULT 0,
        paid_at DATETIME,
        payment_method VARCHAR(50),
        reference_number VARCHAR(50),
        created_at DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        FOREIGN KEY (accommodation_id) REFERENCES accommodations (id) ON DELETE CASCADE,
        FOREIGN KEY (lease_id) REFERENCES leases (id) ON DELETE CASCADE
    )");
    
    // Create maintenance_requests table
    $pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_requests (
        id INTEGER PRIMARY KEY " . (DB_TYPE === 'mysql' ? "AUTO_INCREMENT" : "AUTOINCREMENT") . ",
        user_id INTEGER NOT NULL,
        accommodation_id INTEGER NOT NULL,
        issue VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        status INTEGER NOT NULL DEFAULT 1,
        notes TEXT,
        created_at DATETIME NOT NULL,
        updated_at DATETIME,
        completed_at DATETIME,
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        FOREIGN KEY (accommodation_id) REFERENCES accommodations (id) ON DELETE CASCADE
    )");
    
    // Create notifications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY " . (DB_TYPE === 'mysql' ? "AUTO_INCREMENT" : "AUTOINCREMENT") . ",
        user_id INTEGER NOT NULL,
        subject VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    )");
    
    // Create a master admin user if no users exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
    
    if ($userCount == 0) {
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, email, password, role, created_at)
                   VALUES ('admin', 'admin@example.com', '$hashedPassword', 1, '" . date('Y-m-d H:i:s') . "')");
    }
    
    // Create a schema_version table to track database version
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_version (
        id INTEGER PRIMARY KEY " . (DB_TYPE === 'mysql' ? "AUTO_INCREMENT" : "AUTOINCREMENT") . ",
        version VARCHAR(10) NOT NULL,
        applied_at DATETIME NOT NULL
    )");
    
    // Insert the current schema version
    $pdo->exec("INSERT INTO schema_version (version, applied_at) 
                VALUES ('1.0', '" . date('Y-m-d H:i:s') . "')");
    
    return true;
}

// Function to check if database is properly set up
function isDatabaseSetup() {
    try {
        $pdo = connectDB();
        
        // Check for the schema_version table as a marker of a complete setup
        if (DB_TYPE === 'sqlite') {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='schema_version'");
        } else {
            $stmt = $pdo->query("SHOW TABLES LIKE 'schema_version'");
        }
        
        return $stmt->fetchColumn() !== false;
    } catch (PDOException $e) {
        return false;
    }
}

// Main logic
$setupComplete = false;
$errorMessage = null;

try {
    // Check if tables already exist
    if (isDatabaseSetup()) {
        echo "<div class='alert alert-success'>Database is already set up. All tables exist.</div>";
        $setupComplete = true;
    } else {
        // Create the tables
        if (createTables()) {
            echo "<div class='alert alert-success'>Database tables created successfully!</div>";
            $setupComplete = true;
            
            // Force the creation of a file if using SQLite
            if (DB_TYPE === 'sqlite') {
                $pdo = connectDB();
                $pdo->exec("PRAGMA journal_mode=WAL;"); // Set journaling mode
                $pdo = null; // Close the connection to ensure file is written
            }
        } else {
            echo "<div class='alert alert-danger'>Failed to create database tables.</div>";
        }
    }
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($errorMessage) . "</div>";
}

// Close HTML and provide navigation
echo "
            </div>
            <div class='card-footer'>
                <div class='d-flex justify-content-between'>
                    <a href='/' class='btn btn-primary'>Go to Homepage</a>
                    <a href='/login.php' class='btn btn-success'>Go to Login</a>
                </div>
            </div>
        </div>";

if ($setupComplete) {
    echo "
        <div class='alert alert-info mt-4'>
            <h4>Default Admin Login</h4>
            <p><strong>Username:</strong> admin</p>
            <p><strong>Password:</strong> admin123</p>
            <p class='text-danger'>Please change this password immediately after logging in!</p>
        </div>";
}

echo "
    </div>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>";
?>
