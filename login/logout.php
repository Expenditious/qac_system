<?php
// ===========================================
// login/logout.php - Logout Handler
// ===========================================

define('QAC_SYSTEM', true);
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/session_config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get user info before logout
$userId = getUserId();
$username = getUsername();

try {
    // Log logout activity
    logActivity('logout', 'User logged out successfully');
    
    // Perform logout
    SessionManager::logout();
    
    // Redirect to login page with success message
    header('Location: login.php?logout=success');
    exit();
    
} catch (Exception $e) {
    error_log('Logout error: ' . $e->getMessage());
    
    // Force session destruction
    SessionManager::destroy();
    
    // Still redirect to login
    header('Location: login.php?logout=success');
    exit();
}
?>