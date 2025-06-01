<?php
// ===========================================
// config/config.php - Main Configuration
// ===========================================

// Prevent direct access
if (!defined('QAC_SYSTEM')) {
    define('QAC_SYSTEM', true);
}

// Error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Timezone
date_default_timezone_set('Asia/Bangkok');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'qac_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Site Configuration
define('SITE_URL', 'http://localhost/qac_system');
define('SITE_NAME', 'QAC System');
define('SITE_DESCRIPTION', 'Quality Assurance Control System');
define('SITE_VERSION', '1.0.0');

// Path Configuration
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('CORE_PATH', ROOT_PATH . '/core');
define('MODULES_PATH', ROOT_PATH . '/modules');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('TEMPLATES_PATH', ROOT_PATH . '/templates');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('LOGS_PATH', ROOT_PATH . '/logs');

// URL Configuration
define('ASSETS_URL', SITE_URL . '/assets');
define('UPLOADS_URL', SITE_URL . '/uploads');

// Security Configuration
define('SECURITY_KEY', 'qac_system_2024_security_key_change_in_production');
define('SESSION_NAME', 'QAC_SESSION');
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 1800); // 30 minutes

// File Upload Configuration
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx']);

// Pagination Configuration
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// Log Configuration
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_ROTATION_SIZE', 10 * 1024 * 1024); // 10MB

// Cache Configuration
define('CACHE_ENABLED', false);
define('CACHE_DURATION', 3600); // 1 hour

// API Configuration
define('API_VERSION', 'v1');
define('API_RATE_LIMIT', 100); // requests per minute

// Backup Configuration
define('BACKUP_PATH', ROOT_PATH . '/backups');
define('BACKUP_RETENTION_DAYS', 30);

// Email Configuration (for future use)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_ENCRYPTION', 'tls');
define('FROM_EMAIL', 'noreply@qac-system.local');
define('FROM_NAME', 'QAC System');

// Default Form Settings for FM-QA-23
define('DEFAULT_FORM_CODE', 'FM-QA-23');
define('AUTO_GENERATE_INSPECTION_NO', true);
define('INSPECTION_NO_PREFIX', 'QAC');
define('INSPECTION_NO_FORMAT', 'QAC-{YYYYMMDD}-{HHmmss}-{XXX}');

// Quality Standards
define('DEFAULT_TEMPERATURE_UNIT', '°C');
define('DEFAULT_PRESSURE_UNIT', 'bar');
define('DEFAULT_HUMIDITY_UNIT', '%');

// System Features
define('ENABLE_AUDIT_LOG', true);
define('ENABLE_AUTO_BACKUP', true);
define('ENABLE_EMAIL_NOTIFICATIONS', false);
define('ENABLE_API', true);
define('ENABLE_MOBILE_APP', false);

// Development/Production Settings
define('ENVIRONMENT', 'development'); // development, staging, production
define('DEBUG_MODE', (ENVIRONMENT === 'development'));
define('MAINTENANCE_MODE', false);

// Localization
define('DEFAULT_LANGUAGE', 'th');
define('DEFAULT_TIMEZONE', 'Asia/Bangkok');
define('DATE_FORMAT', 'd/m/Y');
define('TIME_FORMAT', 'H:i');
define('DATETIME_FORMAT', 'd/m/Y H:i');

// Load environment-specific configuration if exists
$env_config = CONFIG_PATH . '/config.' . ENVIRONMENT . '.php';
if (file_exists($env_config)) {
    require_once $env_config;
}

// Auto-load core classes
spl_autoload_register(function ($class_name) {
    $file = CORE_PATH . '/' . $class_name . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

?>