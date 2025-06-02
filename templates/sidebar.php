<?php
// ===========================================
// templates/sidebar.php - Modern Sidebar Template
// ===========================================

// Prevent direct access
if (!defined('QAC_SYSTEM')) {
    die('Direct access not allowed');
}

// Get current page for active menu highlighting
$current_page = $_SERVER['REQUEST_URI'];
$current_module = '';

// Determine current module from URL
if (strpos($current_page, '/dashboard/') !== false) {
    $current_module = 'dashboard';
} elseif (strpos($current_page, '/forms/') !== false) {
    $current_module = 'forms';
} elseif (strpos($current_page, '/reports/') !== false) {
    $current_module = 'reports';
} elseif (strpos($current_page, '/admin/') !== false) {
    $current_module = 'admin';
}

// Helper function to check if menu is active
function isActiveMenu($module, $submenu = '') {
    global $current_page, $current_module;
    
    if (!empty($submenu)) {
        return strpos($current_page, $submenu) !== false;
    }
    
    return $current_module === $module;
}

// Helper function to get menu class
function getMenuClass($module, $submenu = '') {
    return isActiveMenu($module, $submenu) ? 'nav-link active' : 'nav-link';
}
?>

<style>
/* Modern Sidebar Styles */
.sidebar {
    width: var(--sidebar-width);
    height: calc(100vh - var(--header-height));
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border-right: 1px solid var(--border-color);
    position: fixed;
    top: var(--header-height);
    left: 0;
    z-index: 1020;
    overflow-y: auto;
    overflow-x: hidden;
    transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    box-shadow: 2px 0 20px rgba(0,0,0,0.08);
    backdrop-filter: blur(20px);
}

.sidebar.collapsed {
    width: var(--sidebar-collapsed-width);
}

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}

/* User Info Section */
.user-info {
    padding: 25px 20px;
    border-bottom: 1px solid var(--border-color);
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(37, 99, 235, 0.05) 100%);
    position: relative;
    overflow: hidden;
}

.user-info::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, var(--primary) 0%, var(--primary-dark) 100%);
}

.user-avatar {
    width: 55px;
    height: 55px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 12px;
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
    transition: all 0.3s ease;
}

.sidebar.collapsed .user-avatar {
    width: 45px;
    height: 45px;
    font-size: 1.2rem;
    margin: 0 auto 10px;
}

.user-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 4px;
    font-size: 1.1rem;
    transition: all 0.3s ease;
}

.user-role {
    font-size: 0.875rem;
    color: var(--secondary);
    background: rgba(59, 130, 246, 0.1);
    padding: 4px 12px;
    border-radius: 20px;
    display: inline-block;
    font-weight: 500;
}

.sidebar.collapsed .user-name,
.sidebar.collapsed .user-role {
    display: none;
}

/* Quick Stats */
.quick-stats {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.03) 0%, rgba(6, 182, 212, 0.03) 100%);
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    margin-bottom: 8px;
    background: white;
    border-radius: 12px;
    font-size: 0.875rem;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    position: relative;
    overflow: hidden;
}

.stat-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 3px;
    height: 100%;
    background: linear-gradient(180deg, var(--primary) 0%, var(--info) 100%);
}

.stat-item:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.stat-item:last-child {
    margin-bottom: 0;
}

.stat-value {
    font-weight: 700;
    font-size: 1.1rem;
}

.stat-value.text-warning {
    color: var(--warning) !important;
}

.stat-value.text-danger {
    color: var(--danger) !important;
}

.stat-value.text-success {
    color: var(--success) !important;
}

.sidebar.collapsed .quick-stats {
    padding: 15px 10px;
}

.sidebar.collapsed .stat-item {
    flex-direction: column;
    padding: 8px;
    margin-bottom: 6px;
}

.sidebar.collapsed .stat-item span:first-child {
    font-size: 0.7rem;
    margin-bottom: 4px;
}

/* Navigation Menu */
.sidebar-nav {
    padding: 20px 0;
}

.nav-section {
    margin-bottom: 25px;
}

.nav-section-title {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--secondary);
    padding: 0 20px 12px;
    margin-bottom: 8px;
    letter-spacing: 1px;
    position: relative;
    transition: all 0.3s ease;
}

.nav-section-title::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 20px;
    width: 30px;
    height: 2px;
    background: linear-gradient(90deg, var(--primary) 0%, transparent 100%);
}

.sidebar.collapsed .nav-section-title {
    opacity: 0;
    height: 0;
    padding: 0;
    margin: 0;
}

.nav-item {
    margin-bottom: 4px;
}

.nav-link {
    color: var(--secondary);
    padding: 14px 20px;
    border-radius: 0;
    font-weight: 500;
    transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    display: flex;
    align-items: center;
    position: relative;
    text-decoration: none;
    font-size: 0.95rem;
    border-left: 3px solid transparent;
}

.nav-link i {
    width: 20px;
    margin-right: 14px;
    text-align: center;
    font-size: 1.1rem;
    transition: all 0.3s ease;
}

.nav-link:hover {
    background: linear-gradient(90deg, rgba(59, 130, 246, 0.08) 0%, rgba(59, 130, 246, 0.02) 100%);
    color: var(--primary);
    border-left-color: var(--primary);
    transform: translateX(4px);
}

.nav-link:hover i {
    transform: scale(1.1);
    color: var(--primary);
}

.nav-link.active {
    background: linear-gradient(90deg, rgba(59, 130, 246, 0.12) 0%, rgba(59, 130, 246, 0.03) 100%);
    color: var(--primary);
    border-left-color: var(--primary);
    font-weight: 600;
    box-shadow: inset 0 1px 3px rgba(59, 130, 246, 0.1);
}

.nav-link.active i {
    color: var(--primary);
    transform: scale(1.05);
}

.sidebar.collapsed .nav-link {
    padding: 14px;
    text-align: center;
    justify-content: center;
    margin-bottom: 8px;
    border-radius: 12px;
    margin: 0 10px 8px;
    border-left: none;
}

.sidebar.collapsed .nav-link span {
    display: none;
}

.sidebar.collapsed .nav-link i {
    margin: 0;
    font-size: 1.2rem;
}

/* Submenu */
.submenu {
    background: rgba(59, 130, 246, 0.02);
    margin: 0;
    padding: 8px 0;
    border-left: 2px solid rgba(59, 130, 246, 0.1);
    margin-left: 20px;
    border-radius: 0 8px 8px 0;
}

.submenu .nav-link {
    padding: 10px 20px 10px 40px;
    font-size: 0.875rem;
    border-left: none;
    color: var(--secondary);
}

.submenu .nav-link:hover,
.submenu .nav-link.active {
    background: rgba(59, 130, 246, 0.08);
    border-left: none;
}

.sidebar.collapsed .submenu {
    display: none;
}

/* Sidebar Footer */
.sidebar-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20px;
    border-top: 1px solid var(--border-color);
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.8) 0%, rgba(241, 245, 249, 0.9) 100%);
    backdrop-filter: blur(10px);
}

.footer-content {
    text-align: center;
    color: var(--secondary);
    font-size: 0.8rem;
    font-weight: 500;
}

.sidebar.collapsed .footer-content {
    display: none;
}

/* Mobile Sidebar */
@media (max-width: 991.98px) {
    .sidebar {
        transform: translateX(-100%);
        z-index: 1050;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1040;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }
    
    .sidebar-overlay.show {
        opacity: 1;
        visibility: visible;
    }
}

/* Content wrapper */
.content-wrapper {
    margin-left: var(--sidebar-width);
    min-height: calc(100vh - var(--header-height));
    padding: 25px;
    transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    background: #F8FAFC;
}

.content-wrapper.sidebar-collapsed {
    margin-left: var(--sidebar-collapsed-width);
}

@media (max-width: 991.98px) {
    .content-wrapper {
        margin-left: 0;
        padding: 20px 15px;
    }
}

/* Tooltip for collapsed sidebar */
.sidebar.collapsed .nav-link {
    position: relative;
}

.sidebar.collapsed .nav-link:hover::after {
    content: attr(data-title);
    position: absolute;
    left: 100%;
    top: 50%;
    transform: translateY(-50%);
    margin-left: 15px;
    padding: 8px 12px;
    background: var(--dark);
    color: white;
    border-radius: 8px;
    font-size: 0.875rem;
    white-space: nowrap;
    z-index: 1000;
    opacity: 0;
    animation: tooltipFadeIn 0.3s ease forwards;
}

@keyframes tooltipFadeIn {
    from {
        opacity: 0;
        transform: translateY(-50%) translateX(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(-50%) translateX(0);
    }
}
</style>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Main Sidebar -->
<aside class="sidebar" id="sidebar">
    <!-- User Info -->
    <div class="user-info">
        <div class="user-avatar">
            <?php echo strtoupper(substr(getUserFullName(), 0, 1)); ?>
        </div>
        <div class="user-name"><?php echo getUserFullName(); ?></div>
        <div class="user-role"><?php echo ucfirst(getUserRole()); ?></div>
    </div>
    
    
    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <!-- Dashboard Section -->
        <div class="nav-section">
            <div class="nav-section-title">หลัก</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="<?php echo getMenuClass('dashboard'); ?>" 
                       href="<?php echo SITE_URL; ?>/modules/dashboard/index.php"
                       data-title="แดชบอร์ด">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>แดชบอร์ด</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Forms Section -->
        <div class="nav-section">
            <div class="nav-section-title">การตรวจสอบ</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="<?php echo getMenuClass('forms'); ?>" 
                       href="<?php echo SITE_URL; ?>/modules/forms/index.php"
                       data-title="แบบฟอร์มทั้งหมด">
                        <i class="fas fa-clipboard-list"></i>
                        <span>แบบฟอร์มทั้งหมด</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="<?php echo getMenuClass('forms', 'fm_qa_23/form.php'); ?>" 
                       href="<?php echo SITE_URL; ?>/modules/forms/qa/fm_qa_23/form.php"
                       data-title="บันทึกข้อมูลใหม่">
                        <i class="fas fa-plus-circle"></i>
                        <span>บันทึกข้อมูลใหม่</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="<?php echo getMenuClass('forms', 'fm_qa_23/history.php'); ?>" 
                       href="<?php echo SITE_URL; ?>/modules/forms/qa/fm_qa_23/history.php"
                       data-title="ประวัติการตรวจสอบ">
                        <i class="fas fa-history"></i>
                        <span>ประวัติการตรวจสอบ</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Reports Section -->
        <div class="nav-section">
            <div class="nav-section-title">รายงาน</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="<?php echo getMenuClass('reports'); ?>" 
                       href="<?php echo SITE_URL; ?>/modules/reports/index.php"
                       data-title="รายงานทั้งหมด">
                        <i class="fas fa-chart-bar"></i>
                        <span>รายงานทั้งหมด</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="<?php echo getMenuClass('reports', 'daily.php'); ?>" 
                       href="<?php echo SITE_URL; ?>/modules/reports/daily.php"
                       data-title="รายงานรายวัน">
                        <i class="fas fa-calendar-day"></i>
                        <span>รายงานรายวัน</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="<?php echo getMenuClass('reports', 'monthly.php'); ?>" 
                       href="<?php echo SITE_URL; ?>/modules/reports/monthly.php"
                       data-title="รายงานรายเดือน">
                        <i class="fas fa-calendar-alt"></i>
                        <span>รายงานรายเดือน</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="<?php echo getMenuClass('reports', 'export.php'); ?>" 
                       href="<?php echo SITE_URL; ?>/modules/reports/export.php"
                       data-title="ส่งออกข้อมูล">
                        <i class="fas fa-download"></i>
                        <span>ส่งออกข้อมูล</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Admin Section (Admin only) -->
        <?php if (isAdmin()): ?>
        <div class="nav-section">
            <div class="nav-section-title">การจัดการ</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="<?php echo getMenuClass('admin'); ?>" 
                       href="<?php echo SITE_URL; ?>/modules/admin/dashboard.php"
                       data-title="จัดการระบบ">
                        <i class="fas fa-cogs"></i>
                        <span>จัดการระบบ</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="<?php echo getMenuClass('admin', 'users.php'); ?>" 
                       href="<?php echo SITE_URL; ?>/modules/admin/users.php"
                       data-title="จัดการผู้ใช้">
                        <i class="fas fa-users"></i>
                        <span>จัดการผู้ใช้</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="<?php echo getMenuClass('admin', 'settings.php'); ?>" 
                       href="<?php echo SITE_URL; ?>/modules/admin/settings.php"
                       data-title="ตั้งค่าระบบ">
                        <i class="fas fa-cog"></i>
                        <span>ตั้งค่าระบบ</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="<?php echo getMenuClass('admin', 'backup.php'); ?>" 
                       href="<?php echo SITE_URL; ?>/modules/admin/backup.php"
                       data-title="สำรองข้อมูล">
                        <i class="fas fa-database"></i>
                        <span>สำรองข้อมูล</span>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </nav>
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="footer-content">
            <?php echo SITE_NAME; ?> v<?php echo SITE_VERSION; ?><br>
            <small>© <?php echo date('Y'); ?></small>
        </div>
    </div>
</aside>

<script>
// Sidebar toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const contentWrapper = document.querySelector('.content-wrapper');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    // Toggle sidebar
    sidebarToggle.addEventListener('click', function() {
        if (window.innerWidth > 991.98) {
            // Desktop: collapse/expand
            sidebar.classList.toggle('collapsed');
            if (contentWrapper) {
                contentWrapper.classList.toggle('sidebar-collapsed');
            }
            
            // Save state to localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebar-collapsed', isCollapsed);
        } else {
            // Mobile: show/hide
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
        }
    });
    
    // Close sidebar when clicking overlay (mobile)
    sidebarOverlay.addEventListener('click', function() {
        sidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
        document.body.style.overflow = '';
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 991.98) {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
            document.body.style.overflow = '';
        } else {
            sidebar.classList.remove('collapsed');
            if (contentWrapper) {
                contentWrapper.classList.remove('sidebar-collapsed');
            }
        }
    });
    
    // Restore sidebar state on page load (desktop only)
    if (window.innerWidth > 991.98) {
        const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
            if (contentWrapper) {
                contentWrapper.classList.add('sidebar-collapsed');
            }
        }
    }
});
</script>