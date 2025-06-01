<?php
// ===========================================
// templates/header.php - Modern Header Template
// ===========================================

// Prevent direct access
if (!defined('QAC_SYSTEM')) {
    die('Direct access not allowed');
}

// Get current page info
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$page_title = $page_title ?? 'QAC System';
$breadcrumbs = $breadcrumbs ?? [];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo SITE_DESCRIPTION; ?>">
    <meta name="author" content="QAC System">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- Preload critical resources -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" as="style">
    
    <!-- CSS Framework -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --secondary: #6B7280;
            --success: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
            --info: #06B6D4;
            --light: #F9FAFB;
            --dark: #111827;
            --border-color: #E5E7EB;
            --sidebar-width: 280px;
            --header-height: 70px;
            --sidebar-collapsed-width: 80px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #F8FAFC;
            color: var(--dark);
            overflow-x: hidden;
        }
        
        /* Header Styles */
        .main-header {
            height: var(--header-height);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            transition: all 0.3s ease;
        }
        
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: white !important;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        
        .navbar-brand .logo {
            width: 45px;
            height: 45px;
            background: rgba(255,255,255,0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            backdrop-filter: blur(10px);
        }
        
        .navbar-brand .logo i {
            font-size: 24px;
            color: white;
        }
        
        .sidebar-toggle {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white !important;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .sidebar-toggle:hover {
            background: rgba(255,255,255,0.2);
            transform: scale(1.05);
        }
        
        .navbar-nav .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            padding: 10px 16px !important;
            border-radius: 10px;
            transition: all 0.3s ease;
            margin-right: 8px;
        }
        
        .navbar-nav .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white !important;
            transform: translateY(-1px);
        }
        
        .user-menu .dropdown-toggle {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: white !important;
            border-radius: 25px;
            padding: 8px 20px !important;
            backdrop-filter: blur(10px);
        }
        
        .user-menu .dropdown-toggle:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .notification-badge {
            position: relative;
        }
        
        .notification-badge .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            font-size: 0.7rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        /* Breadcrumb Section */
        .breadcrumb-section {
            background: white;
            padding: 20px 0;
            border-bottom: 1px solid var(--border-color);
            margin-top: var(--header-height);
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        
        .breadcrumb {
            margin: 0;
            background: none;
        }
        
        .breadcrumb-item a {
            color: var(--secondary);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .breadcrumb-item a:hover {
            color: var(--primary);
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .page-subtitle {
            color: var(--secondary);
            font-size: 1rem;
            margin-top: 5px;
        }
        
        /* Mobile responsive */
        @media (max-width: 991.98px) {
            .main-header {
                height: 65px;
            }
            
            .navbar-brand {
                font-size: 1.25rem;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .breadcrumb-section {
                margin-top: 65px;
                padding: 15px 0;
            }
        }
        
        /* Dropdown menus */
        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            border-radius: 12px;
            padding: 8px;
            backdrop-filter: blur(20px);
            background: rgba(255,255,255,0.95);
        }
        
        .dropdown-item {
            border-radius: 8px;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }
        
        .dropdown-item:hover {
            background: var(--light);
            transform: translateX(4px);
        }
    </style>
    
    <!-- Meta tags for security -->
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#667eea">
    <meta name="csrf-token" content="<?php echo getCSRFToken(); ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo ASSETS_URL; ?>/images/favicon.png">
</head>
<body>
    <!-- Main Header -->
    <nav class="navbar navbar-expand-lg main-header">
        <div class="container-fluid">
            <!-- Sidebar Toggle & Brand -->
            <div class="d-flex align-items-center">
                <button class="btn sidebar-toggle me-3" type="button" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <a class="navbar-brand" href="<?php echo SITE_URL; ?>/modules/dashboard/index.php">
                    <div class="logo">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <?php echo SITE_NAME; ?>
                </a>
            </div>
            
            <!-- Mobile toggle for menu -->
            <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="fas fa-ellipsis-v text-white"></i>
            </button>
            
            <!-- Navigation -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <!-- Quick actions -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/modules/forms/qa/fm_qa_23/form.php">
                            <i class="fas fa-plus-circle me-2"></i>
                            <span class="d-none d-lg-inline">บันทึกข้อมูลใหม่</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/modules/reports/index.php">
                            <i class="fas fa-chart-bar me-2"></i>
                            <span class="d-none d-lg-inline">รายงาน</span>
                        </a>
                    </li>
                </ul>
                
                <!-- Right side menu -->
                <ul class="navbar-nav">
                    <!-- Notifications -->
                    <li class="nav-item dropdown">
                        <a class="nav-link notification-badge" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="badge bg-danger">3</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">การแจ้งเตือน</h6></li>
                            <li><a class="dropdown-item" href="#">
                                <i class="fas fa-info-circle text-info me-2"></i>
                                <div>
                                    <strong>อัพเดทระบบ</strong>
                                    <small class="text-muted d-block">5 นาทีที่แล้ว</small>
                                </div>
                            </a></li>
                            <li><a class="dropdown-item" href="#">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <div>
                                    <strong>ข้อมูลถูกบันทึก</strong>
                                    <small class="text-muted d-block">10 นาทีที่แล้ว</small>
                                </div>
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="#">ดูทั้งหมด</a></li>
                        </ul>
                    </li>
                    
                    <!-- User menu -->
                    <li class="nav-item dropdown user-menu">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i>
                            <span class="d-none d-md-inline"><?php echo getUserFullName(); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">สวัสดี, <?php echo getUserFullName(); ?></h6></li>
                            <li><span class="dropdown-item-text small text-muted">
                                <?php echo ucfirst(getUserRole()); ?> | 
                                เข้าสู่ระบบ: <?php echo date('H:i'); ?>
                            </span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#">
                                <i class="fas fa-user me-2"></i>โปรไฟล์
                            </a></li>
                            <li><a class="dropdown-item" href="#">
                                <i class="fas fa-cog me-2"></i>ตั้งค่า
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo SITE_URL; ?>/login/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Breadcrumb Section -->
    <?php if (!empty($breadcrumbs) || !empty($page_title)): ?>
    <div class="breadcrumb-section">
        <div class="container-fluid">
            <div class="page-header">
                <div>
                    <?php if (!empty($page_title)): ?>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                    <?php if (!empty($page_subtitle)): ?>
                    <p class="page-subtitle"><?php echo $page_subtitle; ?></p>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($breadcrumbs)): ?>
                    <nav aria-label="breadcrumb" class="mt-2">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="<?php echo SITE_URL; ?>/modules/dashboard/index.php">
                                    <i class="fas fa-home"></i> หน้าแรก
                                </a>
                            </li>
                            <?php foreach ($breadcrumbs as $index => $breadcrumb): ?>
                                <?php if ($index === count($breadcrumbs) - 1): ?>
                                    <li class="breadcrumb-item active" aria-current="page">
                                        <?php echo $breadcrumb['title']; ?>
                                    </li>
                                <?php else: ?>
                                    <li class="breadcrumb-item">
                                        <a href="<?php echo $breadcrumb['url']; ?>">
                                            <?php echo $breadcrumb['title']; ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                    <?php endif; ?>
                </div>
                
                <!-- Page actions -->
                <div class="page-actions">
                    <?php if (isset($page_actions)): ?>
                        <?php echo $page_actions; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Flash Messages -->
    <?php if (SessionManager::hasFlashMessages()): ?>
    <div class="container-fluid mt-3">
        <?php foreach (SessionManager::getFlashMessages() as $message): ?>
        <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $message['type'] === 'success' ? 'check-circle' : ($message['type'] === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
            <?php echo htmlspecialchars($message['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Main Layout Container -->
    <div class="main-layout">
        <!-- Sidebar will be included here -->
        <!-- Content will follow -->