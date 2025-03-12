<?php
/**
 * Database Update Script for Harambee Student Living Management System
 * This script updates the existing database structure with new fields
 */
require_once 'includes/config.php';
require_once 'includes/database.php';

// Display header for the update page
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Update</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
    <div class='container mt-5'>
        <div class='card'>
            <div class='card-header bg-primary text-white'>
                <h2>Database Update</h2>
            </div>
            <div class='card-body'>";

// Connect to database
$pdo = connectDB();
$updateComplete = false;
$errorMessage = null;

try {
    // Check if college column already exists in student_profiles
    $stmt = $pdo->query("PRAGMA table_info(student_profiles)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasCollegeColumn = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'college') {
            $hasCollegeColumn = true;
            break;
        }
    }
    
    if ($hasCollegeColumn) {
        echo "<div class='alert alert-info'>College column already exists in student_profiles table.</div>";
    } else {
        // Add college column to student_profiles table
        $pdo->exec("ALTER TABLE student_profiles ADD COLUMN college VARCHAR(100) DEFAULT ''");
        echo "<div class='alert alert-success'>Successfully added college column to student_profiles table.</div>";
    }
    
    // Update student_number column to be required (NOT NULL)
    $studentNumberColumnInfo = null;
    foreach ($columns as $column) {
        if ($column['name'] === 'student_number') {
            $studentNumberColumnInfo = $column;
            break;
        }
    }
    
    if ($studentNumberColumnInfo && $studentNumberColumnInfo['notnull'] == 0) {
        // For SQLite, we need to recreate the table to change NOT NULL constraint
        // First, create a temporary table with the new structure
        $pdo->exec("CREATE TABLE student_profiles_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            student_number VARCHAR(50) NOT NULL,
            college VARCHAR(100) NOT NULL DEFAULT '',
            date_of_birth DATE,
            gender VARCHAR(10),
            id_number VARCHAR(20),
            address TEXT,
            emergency_contact_name VARCHAR(100),
            emergency_contact_phone VARCHAR(20),
            created_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )");
        
        // Copy data from old table to new table
        $pdo->exec("INSERT INTO student_profiles_new (id, user_id, student_number, college, date_of_birth, 
                    gender, id_number, address, emergency_contact_name, emergency_contact_phone, created_at)
                    SELECT id, user_id, COALESCE(student_number, ''), COALESCE(college, ''), date_of_birth, 
                    gender, id_number, address, emergency_contact_name, emergency_contact_phone, created_at 
                    FROM student_profiles");
        
        // Drop old table
        $pdo->exec("DROP TABLE student_profiles");
        
        // Rename new table to original name
        $pdo->exec("ALTER TABLE student_profiles_new RENAME TO student_profiles");
        
        echo "<div class='alert alert-success'>Successfully updated student_number field to be required.</div>";
    } else if ($studentNumberColumnInfo && $studentNumberColumnInfo['notnull'] == 1) {
        echo "<div class='alert alert-info'>student_number field is already set as required.</div>";
    }
    
    // Update schema_version table
    $pdo->exec("INSERT INTO schema_version (version, applied_at) 
                VALUES ('1.1', '" . date('Y-m-d H:i:s') . "')");
    
    $updateComplete = true;
    
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
                    <a href='/register.php' class='btn btn-success'>Go to Registration</a>
                </div>
            </div>
        </div>";

if ($updateComplete) {
    echo "
        <div class='alert alert-info mt-4'>
            <h4>Database Update Summary</h4>
            <p>The database has been successfully updated to support the new student profile fields.</p>
            <p>The registration form now requires student number and college information.</p>
        </div>";
}

echo "
    </div>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>";
?>