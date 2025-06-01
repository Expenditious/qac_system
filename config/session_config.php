<?php
// ===========================================
// config/session_config.php - Session Management
// ===========================================

// Prevent direct access
if (!defined('QAC_SYSTEM')) {
    die('Direct access not allowed');
}

require_once 'config.php';
require_once 'database.php';

class SessionManager {
    
    public static function start() {
        // Session configuration
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
        ini_set('session.cookie_lifetime', SESSION_TIMEOUT);
        
        // Set session name
        session_name(SESSION_NAME);
        
        // Start session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate session ID for security
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
        
        // Check session timeout
        self::checkTimeout();
        
        // Update last activity
        $_SESSION['last_activity'] = time();
    }
    
    public static function checkTimeout() {
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
            
            self::destroy();
            header('Location: ' . SITE_URL . '/login/login.php?timeout=1');
            exit();
        }
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && 
               isset($_SESSION['username']) && 
               isset($_SESSION['logged_in']) && 
               $_SESSION['logged_in'] === true;
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            $redirectUrl = isset($_SERVER['REQUEST_URI']) ? 
                          urlencode($_SERVER['REQUEST_URI']) : '';
            
            header('Location: ' . SITE_URL . '/login/login.php' . 
                   ($redirectUrl ? '?redirect=' . $redirectUrl : ''));
            exit();
        }
    }
    
    public static function login($user) {
        // Store user data in session
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['department'] = $user['department'];
        $_SESSION['position'] = $user['position'];
        $_SESSION['permissions'] = json_decode($user['permissions'], true) ?: [];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Regenerate session ID after login
        session_regenerate_id(true);
        
        // Update last login in database
        self::updateLastLogin($user['user_id']);
        
        // Log login activity
        self::logActivity('login', 'User logged in successfully');
    }
    
    public static function logout() {
        if (self::isLoggedIn()) {
            // Log logout activity
            self::logActivity('logout', 'User logged out');
            
            // Update logout time in database
            self::updateLogoutTime();
        }
        
        self::destroy();
    }
    
    public static function destroy() {
        // Clear session data
        $_SESSION = [];
        
        // Delete session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        
        // Destroy session
        session_destroy();
    }
    
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public static function getUsername() {
        return $_SESSION['username'] ?? null;
    }
    
    public static function getUserFullName() {
        if (isset($_SESSION['first_name']) && isset($_SESSION['last_name'])) {
            return $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
        }
        return $_SESSION['username'] ?? 'Unknown User';
    }
    
    public static function getUserRole() {
        return $_SESSION['role'] ?? 'user';
    }
    
    public static function hasPermission($permission) {
        $permissions = $_SESSION['permissions'] ?? [];
        return in_array($permission, $permissions) || 
               $_SESSION['role'] === 'admin';
    }
    
    public static function isAdmin() {
        return $_SESSION['role'] === 'admin';
    }
    
    public static function isSupervisor() {
        return in_array($_SESSION['role'], ['admin', 'supervisor']);
    }
    
    public static function canEdit($recordUserId = null) {
        // Admin can edit everything
        if (self::isAdmin()) {
            return true;
        }
        
        // Supervisor can edit their department's records
        if (self::isSupervisor()) {
            return true;
        }
        
        // Users can only edit their own records
        if ($recordUserId) {
            return $_SESSION['user_id'] == $recordUserId;
        }
        
        return false;
    }
    
    public static function setFlashMessage($type, $message) {
        $_SESSION['flash_messages'][] = [
            'type' => $type,
            'message' => $message,
            'timestamp' => time()
        ];
    }
    
    public static function getFlashMessages() {
        $messages = $_SESSION['flash_messages'] ?? [];
        unset($_SESSION['flash_messages']);
        return $messages;
    }
    
    public static function hasFlashMessages() {
        return !empty($_SESSION['flash_messages']);
    }
    
    private static function updateLastLogin($userId) {
        try {
            $sql = "UPDATE users SET 
                    last_login = NOW(),
                    failed_login_attempts = 0,
                    locked_until = NULL
                    WHERE user_id = :user_id";
            
            executeQuery($sql, ['user_id' => $userId]);
        } catch (Exception $e) {
            error_log('Failed to update last login: ' . $e->getMessage());
        }
    }
    
    private static function updateLogoutTime() {
        // This could be implemented to track logout times if needed
        // For now, we'll just log the activity
    }
    
    public static function logActivity($action, $details = '', $tableInfo = []) {
        if (!ENABLE_AUDIT_LOG) {
            return;
        }
        
        try {
            $data = [
                'user_id' => self::getUserId(),
                'username' => self::getUsername(),
                'action' => $action,
                'details' => $details,
                'ip_address' => self::getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Add table-specific information if provided
            if (!empty($tableInfo)) {
                $data['table_name'] = $tableInfo['table'] ?? '';
                $data['record_id'] = $tableInfo['id'] ?? null;
            }
            
            insertRecord('user_activity_logs', $data);
            
        } catch (Exception $e) {
            error_log('Failed to log activity: ' . $e->getMessage());
        }
    }
    
    private static function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, 
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    public static function preventCSRF() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCSRF($token) {
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function getCSRFToken() {
        return $_SESSION['csrf_token'] ?? self::preventCSRF();
    }
}

// Initialize session
if (!headers_sent()) {
    SessionManager::start();
}

// Helper functions for easy access
function isLoggedIn() {
    return SessionManager::isLoggedIn();
}

function requireLogin() {
    SessionManager::requireLogin();
}

function getUserId() {
    return SessionManager::getUserId();
}

function getUsername() {
    return SessionManager::getUsername();
}

function getUserFullName() {
    return SessionManager::getUserFullName();
}

function getUserRole() {
    return SessionManager::getUserRole();
}

function isAdmin() {
    return SessionManager::isAdmin();
}

function isSupervisor() {
    return SessionManager::isSupervisor();
}

function hasPermission($permission) {
    return SessionManager::hasPermission($permission);
}

function canEdit($recordUserId = null) {
    return SessionManager::canEdit($recordUserId);
}

function setFlashMessage($type, $message) {
    SessionManager::setFlashMessage($type, $message);
}

function getFlashMessages() {
    return SessionManager::getFlashMessages();
}

function logActivity($action, $details = '', $tableInfo = []) {
    SessionManager::logActivity($action, $details, $tableInfo);
}

function getCSRFToken() {
    return SessionManager::getCSRFToken();
}

?>