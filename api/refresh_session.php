<?php
// ===========================================
// api/refresh_session.php - Session Refresh API
// ===========================================

define('QAC_SYSTEM', true);
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/session_config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Check if user is logged in
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Not authenticated'
        ]);
        exit();
    }
    
    // Validate CSRF token
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
    
    if (!SessionManager::validateCSRF($token)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSRF token'
        ]);
        exit();
    }
    
    // Refresh session activity
    $_SESSION['last_activity'] = time();
    
    // Log activity
    logActivity('session_refresh', 'Session refreshed via API');
    
    echo json_encode([
        'success' => true,
        'message' => 'Session refreshed successfully',
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    error_log('Session refresh error: ' . $e->getMessage());
    
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Internal server error'
        ]);
    }

