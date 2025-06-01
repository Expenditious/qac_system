<?php
// ===========================================
// config/database.php - Database Connection
// ===========================================

// Prevent direct access
if (!defined('QAC_SYSTEM')) {
    die('Direct access not allowed');
}

require_once 'config.php';

class DatabaseConnection {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
                PDO::ATTR_TIMEOUT => 30,
                PDO::ATTR_PERSISTENT => false
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Set timezone for MySQL
            $this->connection->exec("SET time_zone = '+07:00'");
            
        } catch (PDOException $e) {
            $this->handleConnectionError($e);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    private function handleConnectionError($e) {
        // Log error
        error_log('Database Connection Error: ' . $e->getMessage());
        
        if (DEBUG_MODE) {
            die('Database Connection Error: ' . $e->getMessage());
        } else {
            die('ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาติดต่อผู้ดูแลระบบ');
        }
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Quick access function
function getDB() {
    return DatabaseConnection::getInstance()->getConnection();
}

// Test connection function
function testDatabaseConnection() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT 1");
        return $stmt !== false;
    } catch (Exception $e) {
        error_log('Database test failed: ' . $e->getMessage());
        return false;
    }
}

// Database utility functions
function executeQuery($sql, $params = []) {
    try {
        $db = getDB();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log('Query execution error: ' . $e->getMessage());
        throw $e;
    }
}

function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch();
}

function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}

function insertRecord($table, $data) {
    $columns = array_keys($data);
    $placeholders = array_map(function($col) { return ':' . $col; }, $columns);
    
    $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") 
            VALUES (" . implode(', ', $placeholders) . ")";
    
    $stmt = executeQuery($sql, $data);
    return getDB()->lastInsertId();
}

function updateRecord($table, $data, $where, $whereParams = []) {
    $setParts = array_map(function($col) { return $col . ' = :' . $col; }, array_keys($data));
    
    $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$where}";
    
    $allParams = array_merge($data, $whereParams);
    return executeQuery($sql, $allParams);
}

function deleteRecord($table, $where, $whereParams = []) {
    $sql = "DELETE FROM {$table} WHERE {$where}";
    return executeQuery($sql, $whereParams);
}

function getTableColumns($table) {
    $sql = "DESCRIBE {$table}";
    $stmt = executeQuery($sql);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function tableExists($table) {
    $sql = "SHOW TABLES LIKE :table";
    $stmt = executeQuery($sql, ['table' => $table]);
    return $stmt->rowCount() > 0;
}

?>