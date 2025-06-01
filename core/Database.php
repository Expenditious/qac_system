<?php
// ===========================================
// core/Database.php - Database Operations Class
// ===========================================

class Database {
    private $connection;
    private $lastInsertId;
    private $queryCount = 0;
    
    public function __construct() {
        if (!defined('QAC_SYSTEM')) {
            die('Direct access not allowed');
        }
        
        $this->connection = getDB();
    }
    
    /**
     * Execute a prepared statement
     */
    public function query($sql, $params = []) {
        try {
            $this->queryCount++;
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            
            // Store last insert ID if applicable
            if (stripos($sql, 'INSERT') === 0) {
                $this->lastInsertId = $this->connection->lastInsertId();
            }
            
            return $stmt;
        } catch (PDOException $e) {
            $this->handleError($e, $sql, $params);
        }
    }
    
    /**
     * Fetch single row
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Fetch single column value
     */
    public function fetchColumn($sql, $params = [], $columnIndex = 0) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn($columnIndex);
    }
    
    /**
     * Insert record
     */
    public function insert($table, $data) {
        // Remove null values if not explicitly needed
        $data = array_filter($data, function($value) {
            return $value !== null && $value !== '';
        });
        
        $columns = array_keys($data);
        $placeholders = array_map(function($col) { return ':' . $col; }, $columns);
        
        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $this->query($sql, $data);
        return $this->getLastInsertId();
    }
    
    /**
     * Update record
     */
    public function update($table, $data, $where, $whereParams = []) {
        // Remove null values if not explicitly needed
        $data = array_filter($data, function($value) {
            return $value !== null && $value !== '';
        });
        
        $setParts = array_map(function($col) { return $col . ' = :' . $col; }, array_keys($data));
        
        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$where}";
        
        $allParams = array_merge($data, $whereParams);
        $stmt = $this->query($sql, $allParams);
        
        return $stmt->rowCount();
    }
    
    /**
     * Delete record
     */
    public function delete($table, $where, $whereParams = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $whereParams);
        return $stmt->rowCount();
    }
    
    /**
     * Check if record exists
     */
    public function exists($table, $where, $whereParams = []) {
        $sql = "SELECT 1 FROM {$table} WHERE {$where} LIMIT 1";
        $result = $this->fetchOne($sql, $whereParams);
        return $result !== false;
    }
    
    /**
     * Count records
     */
    public function count($table, $where = '1=1', $whereParams = []) {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        return (int) $this->fetchColumn($sql, $whereParams);
    }
    
    /**
     * Get table columns
     */
    public function getColumns($table) {
        $sql = "DESCRIBE {$table}";
        $columns = $this->fetchAll($sql);
        return array_column($columns, 'Field');
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->connection->rollback();
    }
    
    /**
     * Execute multiple queries in transaction
     */
    public function transaction(callable $callback) {
        try {
            $this->beginTransaction();
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Get last insert ID
     */
    public function getLastInsertId() {
        return $this->lastInsertId;
    }
    
    /**
     * Get query count (for debugging)
     */
    public function getQueryCount() {
        return $this->queryCount;
    }
    
    /**
     * Build WHERE clause from array
     */
    public function buildWhere($conditions, $operator = 'AND') {
        if (empty($conditions)) {
            return ['1=1', []];
        }
        
        $where = [];
        $params = [];
        
        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                // Handle IN clause
                $placeholders = [];
                foreach ($value as $i => $val) {
                    $key = $column . '_' . $i;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $val;
                }
                $where[] = $column . ' IN (' . implode(',', $placeholders) . ')';
            } else {
                $where[] = $column . ' = :' . $column;
                $params[$column] = $value;
            }
        }
        
        return [implode(' ' . $operator . ' ', $where), $params];
    }
    
    /**
     * Build pagination
     */
    public function paginate($sql, $params, $page = 1, $perPage = 20) {
        // Get total count
        $countSql = "SELECT COUNT(*) FROM (" . $sql . ") as count_table";
        $total = (int) $this->fetchColumn($countSql, $params);
        
        // Calculate pagination
        $page = max(1, (int) $page);
        $perPage = min(MAX_PAGE_SIZE, max(1, (int) $perPage));
        $offset = ($page - 1) * $perPage;
        $totalPages = ceil($total / $perPage);
        
        // Get data with limit
        $dataSql = $sql . " LIMIT {$offset}, {$perPage}";
        $data = $this->fetchAll($dataSql, $params);
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages,
                'prev_page' => $page > 1 ? $page - 1 : null,
                'next_page' => $page < $totalPages ? $page + 1 : null
            ]
        ];
    }
    
    /**
     * Search functionality
     */
    public function search($table, $searchColumns, $searchTerm, $additionalWhere = '', $additionalParams = []) {
        $searchConditions = [];
        $searchParams = [];
        
        foreach ($searchColumns as $column) {
            $searchConditions[] = $column . ' LIKE :search_' . $column;
            $searchParams['search_' . $column] = '%' . $searchTerm . '%';
        }
        
        $whereClause = '(' . implode(' OR ', $searchConditions) . ')';
        
        if ($additionalWhere) {
            $whereClause .= ' AND ' . $additionalWhere;
            $searchParams = array_merge($searchParams, $additionalParams);
        }
        
        $sql = "SELECT * FROM {$table} WHERE {$whereClause}";
        return $this->fetchAll($sql, $searchParams);
    }
    
    /**
     * Handle database errors
     */
    private function handleError($e, $sql = '', $params = []) {
        $errorMsg = 'Database Error: ' . $e->getMessage();
        
        if (DEBUG_MODE) {
            $errorMsg .= "\nSQL: " . $sql;
            $errorMsg .= "\nParams: " . print_r($params, true);
        }
        
        error_log($errorMsg);
        
        if (DEBUG_MODE) {
            throw new Exception($errorMsg);
        } else {
            throw new Exception('เกิดข้อผิดพลาดในการประมวลผลข้อมูล กรุณาลองใหม่อีกครั้ง');
        }
    }
    
    /**
     * Log slow queries (for performance monitoring)
     */
    private function logSlowQuery($sql, $executionTime, $params = []) {
        if ($executionTime > 1.0) { // Log queries slower than 1 second
            $logMsg = "Slow Query ({$executionTime}s): {$sql}";
            if (DEBUG_MODE) {
                $logMsg .= "\nParams: " . print_r($params, true);
            }
            error_log($logMsg);
        }
    }
    
    /**
     * Backup table
     */
    public function backupTable($table, $outputFile) {
        $sql = "SELECT * FROM {$table}";
        $data = $this->fetchAll($sql);
        
        $backup = [
            'table' => $table,
            'created_at' => date('Y-m-d H:i:s'),
            'record_count' => count($data),
            'data' => $data
        ];
        
        return file_put_contents($outputFile, json_encode($backup, JSON_PRETTY_PRINT));
    }
    
    /**
     * Get connection info (for debugging)
     */
    public function getConnectionInfo() {
        return [
            'host' => DB_HOST,
            'database' => DB_NAME,
            'charset' => DB_CHARSET,
            'queries_executed' => $this->queryCount
        ];
    }
}

?>