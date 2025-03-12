<?php
/**
 * Database Schema Setup
 * This file creates the database tables if they don't exist
 */
require_once 'includes/config.php';
require_once 'includes/database.php';

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
    
    echo "Database tables created successfully!";
}

// Run the table creation
createTables();

// Redirect to the index page
header('Location: index.php');
exit;
?>
