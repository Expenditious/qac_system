<?php
// ===========================================
// core/Auth.php - Authentication Class
// ===========================================

class Auth {
    private $db;
    
    public function __construct() {
        if (!defined('QAC_SYSTEM')) {
            die('Direct access not allowed');
        }
        
        $this->db = new Database();
    }
    
    /**
     * Authenticate user login
     */
    public function login($username, $password, $rememberMe = false) {
        try {
            // Check if user exists and is active
            $user = $this->db->fetchOne(
                "SELECT * FROM users WHERE username = :username AND is_active = 1",
                ['username' => $username]
            );
            
            if (!$user) {
                $this->logFailedAttempt($username, 'User not found or inactive');
                return ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
            }
            
            // Check if account is locked
            if ($this->isAccountLocked($user)) {
                $lockTimeRemaining = $this->getLockTimeRemaining($user);
                return [
                    'success' => false, 
                    'message' => "บัญชีถูกล็อค กรุณารอ {$lockTimeRemaining} นาที"
                ];
            }
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                $this->incrementFailedAttempts($user['user_id']);
                $this->logFailedAttempt($username, 'Invalid password');
                return ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
            }
            
            // Reset failed attempts on successful login
            $this->resetFailedAttempts($user['user_id']);
            
            // Create session
            SessionManager::login($user);
            
            // Handle remember me
            if ($rememberMe) {
                $this->setRememberToken($user['user_id']);
            }
            
            // Log successful login
            $this->logActivity('login_success', 'User logged in successfully', [
                'user_id' => $user['user_id'],
                'username' => $username
            ]);
            
            return [
                'success' => true, 
                'message' => 'เข้าสู่ระบบสำเร็จ',
                'user' => $this->sanitizeUserData($user)
            ];
            
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง'];
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        try {
            $userId = SessionManager::getUserId();
            $username = SessionManager::getUsername();
            
            // Remove remember token if exists
            if ($userId) {
                $this->removeRememberToken($userId);
                
                // Log logout
                $this->logActivity('logout', 'User logged out', [
                    'user_id' => $userId,
                    'username' => $username
                ]);
            }
            
            // Destroy session
            SessionManager::logout();
            
            return ['success' => true, 'message' => 'ออกจากระบบเรียบร้อย'];
            
        } catch (Exception $e) {
            error_log('Logout error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการออกจากระบบ'];
        }
    }
    
    /**
     * Register new user (Admin only)
     */
    public function register($userData) {
        try {
            // Validate input
            $validation = $this->validateUserData($userData);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }
            
            // Check if username already exists
            if ($this->userExists($userData['username'])) {
                return ['success' => false, 'message' => 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว'];
            }
            
            // Check if email already exists
            if (!empty($userData['email']) && $this->emailExists($userData['email'])) {
                return ['success' => false, 'message' => 'อีเมลนี้มีอยู่ในระบบแล้ว'];
            }
            
            // Hash password
            $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
            $userData['created_at'] = date('Y-m-d H:i:s');
            $userData['password_changed_at'] = date('Y-m-d H:i:s');
            
            // Insert user
            $userId = $this->db->insert('users', $userData);
            
            // Log activity
            $this->logActivity('user_created', 'New user registered', [
                'user_id' => $userId,
                'username' => $userData['username']
            ]);
            
            return [
                'success' => true, 
                'message' => 'สร้างผู้ใช้ใหม่เรียบร้อย',
                'user_id' => $userId
            ];
            
        } catch (Exception $e) {
            error_log('Registration error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการสร้างผู้ใช้ใหม่'];
        }
    }
    
    /**
     * Change password
     */
    public function changePassword($userId, $oldPassword, $newPassword) {
        try {
            // Get current user data
            $user = $this->db->fetchOne(
                "SELECT password FROM users WHERE user_id = :user_id",
                ['user_id' => $userId]
            );
            
            if (!$user) {
                return ['success' => false, 'message' => 'ไม่พบผู้ใช้'];
            }
            
            // Verify old password
            if (!password_verify($oldPassword, $user['password'])) {
                return ['success' => false, 'message' => 'รหัสผ่านเดิมไม่ถูกต้อง'];
            }
            
            // Validate new password
            if (!$this->validatePassword($newPassword)) {
                return ['success' => false, 'message' => 'รหัสผ่านใหม่ไม่ตรงตามเงื่อนไข'];
            }
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $this->db->update(
                'users',
                [
                    'password' => $hashedPassword,
                    'password_changed_at' => date('Y-m-d H:i:s')
                ],
                'user_id = :user_id',
                ['user_id' => $userId]
            );
            
            // Log activity
            $this->logActivity('password_changed', 'User changed password', [
                'user_id' => $userId
            ]);
            
            return ['success' => true, 'message' => 'เปลี่ยนรหัสผ่านเรียบร้อย'];
            
        } catch (Exception $e) {
            error_log('Change password error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน'];
        }
    }
    
    /**
     * Reset password (Admin only)
     */
    public function resetPassword($userId, $newPassword = null) {
        try {
            // Generate random password if not provided
            if (!$newPassword) {
                $newPassword = $this->generateRandomPassword();
            }
            
            // Hash password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password
            $this->db->update(
                'users',
                [
                    'password' => $hashedPassword,
                    'password_changed_at' => date('Y-m-d H:i:s'),
                    'failed_login_attempts' => 0,
                    'locked_until' => null
                ],
                'user_id = :user_id',
                ['user_id' => $userId]
            );
            
            // Log activity
            $this->logActivity('password_reset', 'Password reset by admin', [
                'user_id' => $userId
            ]);
            
            return [
                'success' => true, 
                'message' => 'รีเซ็ตรหัสผ่านเรียบร้อย',
                'new_password' => $newPassword
            ];
            
        } catch (Exception $e) {
            error_log('Reset password error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการรีเซ็ตรหัสผ่าน'];
        }
    }
    
    /**
     * Check if account is locked
     */
    private function isAccountLocked($user) {
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return true;
        }
        
        if ($user['failed_login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
            // Auto-lock account
            $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
            $this->db->update(
                'users',
                ['locked_until' => $lockUntil],
                'user_id = :user_id',
                ['user_id' => $user['user_id']]
            );
            return true;
        }
        
        return false;
    }
    
    /**
     * Get remaining lock time in minutes
     */
    private function getLockTimeRemaining($user) {
        $lockUntil = strtotime($user['locked_until']);
        $remaining = $lockUntil - time();
        return ceil($remaining / 60);
    }
    
    /**
     * Increment failed login attempts
     */
    private function incrementFailedAttempts($userId) {
        $this->db->query(
            "UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
    }
    
    /**
     * Reset failed login attempts
     */
    private function resetFailedAttempts($userId) {
        $this->db->update(
            'users',
            [
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'last_login' => date('Y-m-d H:i:s')
            ],
            'user_id = :user_id',
            ['user_id' => $userId]
        );
    }
    
    /**
     * Check if username exists
     */
    private function userExists($username) {
        return $this->db->exists('users', 'username = :username', ['username' => $username]);
    }
    
    /**
     * Check if email exists
     */
    private function emailExists($email) {
        return $this->db->exists('users', 'email = :email', ['email' => $email]);
    }
    
    /**
     * Validate user data
     */
    private function validateUserData($data) {
        $required = ['username', 'password', 'first_name', 'last_name'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['valid' => false, 'message' => 'กรุณากรอกข้อมูล: ' . $field];
            }
        }
        
        // Validate username
        if (!preg_match('/^[a-zA-Z0-9._]{3,50}$/', $data['username'])) {
            return ['valid' => false, 'message' => 'ชื่อผู้ใช้ต้องมี 3-50 ตัวอักษร (a-z, 0-9, ., _)'];
        }
        
        // Validate password
        if (!$this->validatePassword($data['password'])) {
            return ['valid' => false, 'message' => 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร'];
        }
        
        // Validate email if provided
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'รูปแบบอีเมลไม่ถูกต้อง'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Validate password strength
     */
    private function validatePassword($password) {
        return strlen($password) >= 6;
    }
    
    /**
     * Generate random password
     */
    private function generateRandomPassword($length = 8) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        return substr(str_shuffle($chars), 0, $length);
    }
    
    /**
     * Set remember me token
     */
    private function setRememberToken($userId) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days
        
        // Store token in database (you may need to create a remember_tokens table)
        // For now, we'll skip this implementation
        
        // Set cookie
        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
    }
    
    /**
     * Remove remember me token
     */
    private function removeRememberToken($userId) {
        // Remove from database
        // Remove cookie
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }
    
    /**
     * Sanitize user data for session
     */
    private function sanitizeUserData($user) {
        unset($user['password']);
        return $user;
    }
    
    /**
     * Log failed login attempt
     */
    private function logFailedAttempt($username, $reason) {
        $this->logActivity('login_failed', $reason, [
            'username' => $username,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
    
    /**
     * Log activity
     */
    private function logActivity($action, $details, $extraData = []) {
        try {
            $data = [
                'user_id' => $extraData['user_id'] ?? SessionManager::getUserId(),
                'username' => $extraData['username'] ?? SessionManager::getUsername(),
                'action' => $action,
                'details' => $details,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->insert('user_activity_logs', $data);
        } catch (Exception $e) {
            error_log('Failed to log activity: ' . $e->getMessage());
        }
    }
    
    /**
     * Get user permissions
     */
    public function getUserPermissions($userId) {
        $user = $this->db->fetchOne(
            "SELECT role, permissions FROM users WHERE user_id = :user_id",
            ['user_id' => $userId]
        );
        
        if (!$user) {
            return [];
        }
        
        $permissions = json_decode($user['permissions'], true) ?: [];
        
        // Add role-based permissions
        switch ($user['role']) {
            case 'admin':
                $permissions = array_merge($permissions, [
                    'user_management', 'system_settings', 'backup_restore',
                    'view_all_reports', 'edit_all_records', 'delete_records'
                ]);
                break;
            case 'supervisor':
                $permissions = array_merge($permissions, [
                    'view_department_reports', 'edit_department_records'
                ]);
                break;
        }
        
        return array_unique($permissions);
    }
    
    /**
     * Update user permissions
     */
    public function updateUserPermissions($userId, $permissions) {
        try {
            $this->db->update(
                'users',
                ['permissions' => json_encode($permissions)],
                'user_id = :user_id',
                ['user_id' => $userId]
            );
            
            $this->logActivity('permissions_updated', 'User permissions updated', [
                'user_id' => $userId
            ]);
            
            return ['success' => true, 'message' => 'อัปเดตสิทธิ์ผู้ใช้เรียบร้อย'];
        } catch (Exception $e) {
            error_log('Update permissions error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปเดตสิทธิ์'];
        }
    }
}

?>