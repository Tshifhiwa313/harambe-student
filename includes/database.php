<?php
require_once 'config.php';

/**
 * Connect to the database
 * @return PDO Database connection object
 */
function connectDB() {
    try {
        // Get database path - ensure it's writable in production
        $db_path = DB_TYPE === 'sqlite' ? __DIR__ . '/../' . DB_NAME : '';

        // Create database connection based on DB type
        if (DB_TYPE === 'sqlite') {
            $pdo = new PDO('sqlite:' . $db_path);
        } else {
            $dsn = DB_TYPE . ':host=' . DB_HOST . ';dbname=' . DB_NAME;
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
        }

        // Set PDO attributes
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

/**
 * Execute a query with parameters
 * @param string $sql SQL query
 * @param array $params Parameters for the query
 * @return PDOStatement Statement object
 */
function executeQuery($sql, $params = []) {
    try {
        $pdo = connectDB();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        die("Query execution failed: " . $e->getMessage());
    }
}

/**
 * Get a single record from database
 * @param string $sql SQL query
 * @param array $params Parameters for the query
 * @return array|null Record or null if not found
 */
function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch();
}

/**
 * Get all records from the query
 * @param string $sql SQL query
 * @param array $params Parameters for the query
 * @return array Records
 */
function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Insert a record into the database
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @return int Last insert ID
 */
function insert($table, $data) {
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));

    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

    $pdo = connectDB();
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($data));

    return $pdo->lastInsertId();
}

/**
 * Update a record in the database
 * @param string $table Table name
 * @param array $data Associative array of column => value
 * @param string $whereColumn Column for WHERE clause
 * @param mixed $whereValue Value for WHERE clause
 * @return int Number of affected rows
 */
function update($table, $data, $whereColumn, $whereValue) {
    $setClause = implode(' = ?, ', array_keys($data)) . ' = ?';
    $sql = "UPDATE $table SET $setClause WHERE $whereColumn = ?";

    $values = array_values($data);
    $values[] = $whereValue;

    $stmt = executeQuery($sql, $values);
    return $stmt->rowCount();
}

/**
 * Delete a record from the database
 * @param string $table Table name
 * @param string $column Column for WHERE clause
 * @param mixed $value Value for WHERE clause
 * @return int Number of affected rows
 */
function delete($table, $column, $value) {
    $sql = "DELETE FROM $table WHERE $column = ?";
    $stmt = executeQuery($sql, [$value]);
    return $stmt->rowCount();
}

/**
 * Check if a table exists in the database
 * @param string $table Table name
 * @return boolean True if table exists
 */
function tableExists($table) {
    try {
        $pdo = connectDB();
        if (DB_TYPE === 'sqlite') {
            $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$table]);
            return $stmt->fetchColumn() !== false;
        } else {
            $sql = "SHOW TABLES LIKE ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$table]);
            return $stmt->rowCount() > 0;
        }
    } catch (PDOException $e) {
        error_log("Error checking if table exists: " . $e->getMessage());
        return false;
    }
}
?>