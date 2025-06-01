<?php
// ===========================================
// login/auth_check.php - Authentication Check
// ===========================================

// Prevent direct access
if (!defined('QAC_SYSTEM')) {
    die('Direct access not allowed');
}

/**
 * Authentication and Authorization Helper Functions
 */

/**
 * Check if user is authenticated
 */
function checkAuthentication($redirect = true) {
    if (!isLoggedIn()) {
        if ($redirect) {
            $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
            $redirectUrl = $currentUrl ? '?redirect=' . urlencode($currentUrl) : '';
            
            header('Location: ' . SITE_URL . '/login/login.php' . $redirectUrl);
            exit();
        }
        return false;
    }
    return true;
}

/**
 * Check user role
 */
function checkRole($requiredRole, $redirect = true) {
    if (!checkAuthentication($redirect)) {
        return false;
    }
    
    $userRole = getUserRole();
    $roleHierarchy = [
        'admin' => 4,
        'supervisor' => 3,
        'inspector' => 2,
        'viewer' => 1
    ];
    
    $userLevel = $roleHierarchy[$userRole] ?? 0;
    $requiredLevel = $roleHierarchy[$requiredRole] ?? 999;
    
    if ($userLevel < $requiredLevel) {
        if ($redirect) {
            http_response_code(403);
            showAccessDeniedPage();
            exit();
        }
        return false;
    }
    
    return true;
}

/**
 * Check specific permission
 */
function checkPermission($permission, $redirect = true) {
    if (!checkAuthentication($redirect)) {
        return false;
    }
    
    if (!hasPermission($permission)) {
        if ($redirect) {
            http_response_code(403);
            showAccessDeniedPage();
            exit();
        }
        return false;
    }
    
    return true;
}

/**
 * Check if user can edit specific record
 */
function checkEditPermission($recordUserId = null, $redirect = true) {
    if (!checkAuthentication($redirect)) {
        return false;
    }
    
    if (!canEdit($recordUserId)) {
        if ($redirect) {
            http_response_code(403);
            showAccessDeniedPage();
            exit();
        }
        return false;
    }
    
    return true;
}

/**
 * Show access denied page
 */
function showAccessDeniedPage() {
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ไม่มีสิทธิ์เข้าถึง - <?php echo SITE_NAME; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Prompt', sans-serif;
            }
            .error-card {
                background: white;
                border-radius: 20px;
                padding: 40px;
                text-align: center;
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
                max-width: 500px;
            }
            .error-icon {
                font-size: 80px;
                color: #dc3545;
                margin-bottom: 20px;
            }
            .error-title {
                font-size: 28px;
                font-weight: 700;
                color: #333;
                margin-bottom: 15px;
            }
            .error-message {
                color: #666;
                font-size: 16px;
                margin-bottom: 30px;
            }
            .btn-back {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                border-radius: 12px;
                padding: 12px 30px;
                color: white;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.3s ease;
            }
            .btn-back:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
                color: white;
            }
        </style>
    </head>
    <body>
        <div class="error-card">
            <i class="fas fa-shield-alt error-icon"></i>
            <h1 class="error-title">ไม่มีสิทธิ์เข้าถึง</h1>
            <p class="error-message">
                คุณไม่มีสิทธิ์ในการเข้าถึงหน้านี้<br>
                กรุณาติดต่อผู้ดูแลระบบหากคุณคิดว่านี่เป็นข้อผิดพลาด
            </p>
            <a href="javascript:history.back()" class="btn-back">
                <i class="fas fa-arrow-left me-2"></i>ย้อนกลับ
            </a>
            <a href="<?php echo SITE_URL; ?>/modules/dashboard/index.php" class="btn-back ms-2">
                <i class="fas fa-home me-2"></i>หน้าแรก
            </a>
        </div>
        
        <script>
            // Auto redirect after 10 seconds
            setTimeout(function() {
                window.location.href = '<?php echo SITE_URL; ?>/modules/dashboard/index.php';
            }, 10000);
        </script>
    </body>
    </html>
    <?php
}

/**
 * Quick authentication check for pages
 */
function requireAuth($role = null, $permission = null) {
    checkAuthentication();
    
    if ($role) {
        checkRole($role);
    }
    
    if ($permission) {
        checkPermission($permission);
    }
}

/**
 * Check maintenance mode
 */
function checkMaintenanceMode() {
    if (defined('MAINTENANCE_MODE') && MAINTENANCE_MODE === true) {
        // Allow admin access during maintenance
        if (!isAdmin()) {
            showMaintenancePage();
            exit();
        }
    }
}

/**
 * Show maintenance page
 */
function showMaintenancePage() {
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ระบบอยู่ระหว่างการบำรุงรักษา - <?php echo SITE_NAME; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Prompt', sans-serif;
            }
            .maintenance-card {
                background: white;
                border-radius: 20px;
                padding: 40px;
                text-align: center;
                box-shadow: 0 25px            50px rgba(0, 0, 0, 0.15);
                max-width: 500px;
            }
            .maintenance-icon {
                font-size: 80px;
                color: #ffc107;
                margin-bottom: 20px;
            }
            .maintenance-title {
                font-size: 28px;
                font-weight: 700;
                color: #333;
                margin-bottom: 15px;
            }
            .maintenance-message {
                color: #666;
                font-size: 16px;
                margin-bottom: 30px;
            }
            .btn-refresh {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                border-radius: 12px;
                padding: 12px 30px;
                color: white;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.3s ease;
            }
            .btn-refresh:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
                color: white;
            }
        </style>
    </head>
     <body>
        <div class="maintenance-card">
            <i class="fas fa-tools maintenance-icon"></i>
            <h1 class="maintenance-title">ระบบอยู่ระหว่างการบำรุงรักษา</h1>
            <p class="maintenance-message">
                ขออภัยในความไม่สะดวก<br>
                ระบบกำลังอยู่ระหว่างการบำรุงรักษาและจะกลับมาใช้งานได้ในเร็วๆ นี้
            </p>
            <a href="javascript:location.reload()" class="btn-refresh">
                <i class="fas fa-sync-alt me-2"></i>ลองใหม่
            </a>
        </div>
        
        <script>
            // Auto refresh every 30 seconds
            setTimeout(function() {
                location.reload();
            }, 30000);
        </script>
    </body>
    </html>
    <?php
}