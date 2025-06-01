<?php
// ===========================================
// login/login.php - Login Page
// ===========================================

define('QAC_SYSTEM', true);
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/session_config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . SITE_URL . '/modules/dashboard/index.php');
    exit();
}

$error_message = '';
$success_message = '';
$show_timeout_message = false;

// Check for timeout parameter
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $show_timeout_message = true;
}

// Check for logout success
if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
    $success_message = 'ออกจากระบบเรียบร้อยแล้ว';
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!SessionManager::validateCSRF($csrf_token)) {
        $error_message = 'Invalid security token. Please try again.';
    }
    // Validate input
    elseif (empty($username) || empty($password)) {
        $error_message = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    }
    else {
        try {
            // Get user from database
            $sql = "SELECT * FROM users WHERE username = :username AND is_active = 1";
            $user = fetchOne($sql, ['username' => $username]);
            
            if ($user) {
                // Check if account is locked
                if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                    $remaining_time = ceil((strtotime($user['locked_until']) - time()) / 60);
                    $error_message = "บัญชีถูกล็อค กรุณารอ {$remaining_time} นาทีแล้วลองใหม่";
                }
                // Verify password
                elseif (password_verify($password, $user['password'])) {
                    // Login successful
                    SessionManager::login($user);
                    
                    // Reset failed attempts
                    $sql = "UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE user_id = :user_id";
                    executeQuery($sql, ['user_id' => $user['user_id']]);
                    
                    // Redirect to intended page or dashboard
                    $redirect = $_GET['redirect'] ?? '/modules/dashboard/index.php';
                    header('Location: ' . SITE_URL . $redirect);
                    exit();
                }
                else {
                    // Invalid password
                    $failed_attempts = $user['failed_login_attempts'] + 1;
                    
                    // Update failed attempts
                    $sql = "UPDATE users SET failed_login_attempts = :attempts";
                    $params = ['attempts' => $failed_attempts, 'user_id' => $user['user_id']];
                    
                    // Lock account if too many attempts
                    if ($failed_attempts >= MAX_LOGIN_ATTEMPTS) {
                        $lock_until = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
                        $sql .= ", locked_until = :lock_until";
                        $params['lock_until'] = $lock_until;
                        
                        $error_message = "รหัสผ่านไม่ถูกต้อง บัญชีถูกล็อคเป็นเวลา " . (LOCKOUT_DURATION / 60) . " นาที";
                    } else {
                        $remaining_attempts = MAX_LOGIN_ATTEMPTS - $failed_attempts;
                        $error_message = "รหัสผ่านไม่ถูกต้อง เหลือโอกาส {$remaining_attempts} ครั้ง";
                    }
                    
                    $sql .= " WHERE user_id = :user_id";
                    executeQuery($sql, $params);
                    
                    // Log failed attempt
                    SessionManager::logActivity('failed_login', "Failed login attempt for user: {$username}");
                }
            } else {
                $error_message = 'ไม่พบผู้ใช้ในระบบ';
                
                // Log failed attempt
                SessionManager::logActivity('failed_login', "Login attempt with non-existent username: {$username}");
            }
            
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            $error_message = 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - <?php echo SITE_NAME; ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #0d6efd;
            --primary-dark: #0b5ed7;
            --secondary: #6c757d;
            --success: #198754;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #0dcaf0;
            --light: #f8f9fa;
            --dark: #212529;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #667eea 0%, #4c63d2 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Prompt', sans-serif;
            background: var(--gradient-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* Background Animation */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="rgba(255,255,255,0.02)" points="0,1000 1000,0 1000,1000"/></svg>');
            animation: backgroundMove 20s ease-in-out infinite;
        }

        @keyframes backgroundMove {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .login-container {
            width: 100%;
            max-width: 450px;
            margin: 0 20px;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--gradient-secondary);
        }

        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: var(--gradient-secondary);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .logo i {
            font-size: 35px;
            color: white;
        }

        .system-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .system-subtitle {
            color: var(--secondary);
            font-weight: 400;
            font-size: 14px;
        }

        .form-title {
            text-align: center;
            margin-bottom: 30px;
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-floating {
            margin-bottom: 20px;
            position: relative;
        }

        .form-floating input {
            height: 60px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }

        .form-floating input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
            background: white;
        }

        .form-floating label {
            font-weight: 500;
            color: var(--secondary);
        }

        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary);
            z-index: 5;
        }

        .btn-login {
            width: 100%;
            height: 55px;
            background: var(--gradient-secondary);
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert {
            border: none;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
        }

        .alert-success {
            background: linear-gradient(135deg, #51cf66 0%, #40c057 100%);
            color: white;
        }

        .alert-warning {
            background: linear-gradient(135deg, #ffd43b 0%, #fab005 100%);
            color: #495057;
        }

        .version-info {
            text-align: center;
            margin-top: 30px;
            color: var(--secondary);
            font-size: 12px;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 576px) {
            .login-card {
                padding: 25px;
                margin: 15px;
            }
            
            .system-title {
                font-size: 24px;
            }
        }

        /* Accessibility */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center">
            <div class="loading-spinner"></div>
            <p class="mt-3">กำลังเข้าสู่ระบบ...</p>
        </div>
    </div>

    <div class="login-container">
        <div class="login-card">
            <!-- Logo and Title -->
            <div class="logo-container">
                <div class="logo">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <h1 class="system-title"><?php echo SITE_NAME; ?></h1>
                <p class="system-subtitle">Quality Assurance Control System</p>
            </div>

            <h2 class="form-title">เข้าสู่ระบบ</h2>

            <!-- Alert Messages -->
            <?php if ($show_timeout_message): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-clock me-2"></i>
                    เซสชันของคุณหมดอายุ กรุณาเข้าสู่ระบบอีกครั้ง
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="login.php" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                
                <!-- Username -->
                <div class="form-floating">
                    <input type="text" 
                           class="form-control" 
                           id="username" 
                           name="username" 
                           placeholder="ชื่อผู้ใช้" 
                           required 
                           autofocus
                           autocomplete="username"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    <label for="username">ชื่อผู้ใช้</label>
                    <i class="fas fa-user input-icon"></i>
                </div>

                <!-- Password -->
                <div class="form-floating">
                    <input type="password" 
                           class="form-control" 
                           id="password" 
                           name="password" 
                           placeholder="รหัสผ่าน" 
                           required
                           autocomplete="current-password">
                    <label for="password">รหัสผ่าน</label>
                    <i class="fas fa-lock input-icon" id="passwordIcon"></i>
                </div>

                <!-- Login Button -->
                <button type="submit" class="btn btn-login" id="loginBtn">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    เข้าสู่ระบบ
                </button>
            </form>

            <!-- Version Info -->
            <div class="version-info">
                Version <?php echo SITE_VERSION; ?> | <?php echo date('Y'); ?>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loadingOverlay = document.getElementById('loadingOverlay');
            const loginBtn = document.getElementById('loginBtn');
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');

            // Show loading on form submit
            loginForm.addEventListener('submit', function() {
                loadingOverlay.style.display = 'flex';
                loginBtn.disabled = true;
                loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังเข้าสู่ระบบ...';
            });

            // Password visibility toggle
            passwordIcon.addEventListener('click', function() {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    passwordIcon.className = 'fas fa-eye-slash input-icon';
                } else {
                    passwordInput.type = 'password';
                    passwordIcon.className = 'fas fa-lock input-icon';
                }
            });

            // Auto-hide alerts
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.remove(), 300);
                });
            }, 5000);

            // Prevent multiple form submissions
            let formSubmitted = false;
            loginForm.addEventListener('submit', function(e) {
                if (formSubmitted) {
                    e.preventDefault();
                    return false;
                }
                formSubmitted = true;
            });

            // Focus management
            const usernameInput = document.getElementById('username');
            if (usernameInput.value === '') {
                usernameInput.focus();
            } else {
                passwordInput.focus();
            }
        });

        // Security: Clear form on page unload
        window.addEventListener('beforeunload', function() {
            document.getElementById('password').value = '';
        });
    </script>
</body>
</html>