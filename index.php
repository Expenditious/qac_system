<?php
// ===========================================
// index.php - Main Entry Point
// ===========================================

define('QAC_SYSTEM', true);
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/session_config.php';

// Check if user is already logged in
if (isLoggedIn()) {
    // Redirect to dashboard
    header('Location: ' . SITE_URL . '/modules/dashboard/index.php');
    exit();
} else {
    // Redirect to login page
    header('Location: ' . SITE_URL . '/login/login.php');
    exit();
}
?>