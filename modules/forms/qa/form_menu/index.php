<?php
// ===========================================
// modules/forms/qa/form_menu/index.php - QA Forms Menu
// ===========================================

define('QAC_SYSTEM', true);
require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../config/session_config.php';

// Check authentication
requireLogin();

try {
    // Get all active forms with statistics
    $sql = "SELECT f.*, 
                   COUNT(DISTINCT m.inspection_id) as total_inspections,
                   COUNT(DISTINCT CASE WHEN m.status = 'completed' THEN m.inspection_id END) as completed_inspections,
                   COUNT(DISTINCT CASE WHEN DATE(m.created_at) = CURDATE() THEN m.inspection_id END) as today_inspections,
                   COUNT(DISTINCT t.type_id) as total_types,
                   MAX(m.created_at) as last_inspection
            FROM qac_forms f
            LEFT JOIN qac_inspection_master m ON f.form_id = m.form_id
            LEFT JOIN qac_inspection_types t ON f.form_id = t.form_id AND t.is_active = 1
            WHERE f.is_active = 1
            GROUP BY f.form_id
            ORDER BY f.form_code ASC";
    
    $forms = fetchAll($sql);
    
    // Get overall statistics
    $overall_stats_sql = "SELECT 
                            COUNT(*) as total_inspections,
                            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_inspections,
                            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_inspections,
                            COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_inspections
                         FROM qac_inspection_master";
    
    $overall_stats = fetchOne($overall_stats_sql);
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}

// Function to get form icon
function getFormIcon($form_code) {
    $icons = [
        'FM-QA-23' => 'flask',
        'FM-QA-24' => 'thermometer-half',
        'FM-QA-25' => 'weight-hanging',
        'FM-QA-26' => 'microscope',
        'FM-QA-27' => 'box',
        'FM-QA-28' => 'vial',
        'FM-QA-29' => 'cogs'
    ];
    return $icons[$form_code] ?? 'clipboard-check';
}

// Function to get form color
function getFormColor($form_code) {
    $colors = [
        'FM-QA-23' => ['from' => '#667eea', 'to' => '#764ba2'],
        'FM-QA-24' => ['from' => '#f093fb', 'to' => '#f5576c'],
        'FM-QA-25' => ['from' => '#4facfe', 'to' => '#00f2fe'],
        'FM-QA-26' => ['from' => '#43e97b', 'to' => '#38f9d7'],
        'FM-QA-27' => ['from' => '#fa709a', 'to' => '#fee140'],
        'FM-QA-28' => ['from' => '#a8edea', 'to' => '#fed6e3'],
        'FM-QA-29' => ['from' => '#ff9a9e', 'to' => '#fecfef']
    ];
    return $colors[$form_code] ?? ['from' => '#6c757d', 'to' => '#495057'];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แบบฟอร์ม QA - เลือกแบบฟอร์มการตรวจสอบ | <?php echo SITE_NAME; ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .navbar {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .navbar-brand, .nav-link {
            color: white !important;
        }
        
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .header-section {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .main-title {
            font-size: 36px;
            font-weight: 700;
            color: white;
            margin-bottom: 10px;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .main-subtitle {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 0;
        }
        
        .stats-summary {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            text-align: center;
        }
        
        .stat-item {
            padding: 15px;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #667eea;
            display: block;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 13px;
            font-weight: 500;
        }
        
        .forms-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .forms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .form-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .form-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            border-color: #667eea;
        }
        
        .form-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .form-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .form-info {
            flex: 1;
        }
        
        .form-code {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 2px;
        }
        
        .form-name {
            font-size: 13px;
            color: #666;
            line-height: 1.3;
        }
        
        .form-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 12px;
            color: #999;
        }
        
        .form-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-form {
            flex: 1;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-primary-form {
            background: #28a745;
            color: white;
        }
        
        .btn-primary-form:hover {
            background: #218838;
            color: white;
        }
        
        .btn-secondary-form {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary-form:hover {
            background: #5a6268;
            color: white;
        }
        
        .btn-disabled {
            background: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
        }
        
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-coming-soon {
            background: #fff3cd;
            color: #856404;
        }
        
        .quick-actions {
            margin-top: 20px;
            text-align: center;
        }
        
        .quick-btn {
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid #667eea;
            color: #667eea;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            margin: 0 5px;
            transition: all 0.3s ease;
        }
        
        .quick-btn:hover {
            background: #667eea;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .forms-grid {
                grid-template-columns: 1fr;
            }
            
            .main-title {
                font-size: 28px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>/modules/dashboard/index.php">
                <i class="fas fa-clipboard-check me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link">
                    <i class="fas fa-user me-1"></i><?php echo getUserFullName(); ?>
                </span>
                <a class="nav-link" href="<?php echo SITE_URL; ?>/login/logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>ออกจากระบบ
                </a>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <!-- Header Section -->
        <div class="header-section">
            <h1 class="main-title">
                <i class="fas fa-clipboard-list me-2"></i>แบบฟอร์ม QA
            </h1>
            <p class="main-subtitle">เลือกแบบฟอร์มการตรวจสอบคุณภาพที่ต้องการดำเนินการ</p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php else: ?>

        <!-- Statistics Summary -->
        <div class="stats-summary">
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($overall_stats['total_inspections'] ?? 0); ?></span>
                    <div class="stat-label">การตรวจสอบทั้งหมด</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($overall_stats['completed_inspections'] ?? 0); ?></span>
                    <div class="stat-label">เสร็จสิ้นแล้ว</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($overall_stats['today_inspections'] ?? 0); ?></span>
                    <div class="stat-label">ตรวจสอบวันนี้</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($overall_stats['week_inspections'] ?? 0); ?></span>
                    <div class="stat-label">สัปดาห์นี้</div>
                </div>
            </div>
        </div>

        <!-- Forms Section -->
        <div class="forms-section">
            <h2 class="section-title">แบบฟอร์มการตรวจสอบ</h2>
            
            <?php if (empty($forms)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h5>ไม่พบแบบฟอร์มการตรวจสอบ</h5>
                    <p>ยังไม่มีแบบฟอร์มการตรวจสอบในระบบ</p>
                </div>
            <?php else: ?>
                <div class="forms-grid">
                    <?php foreach ($forms as $index => $form): ?>
                        <?php 
                        $colors = getFormColor($form['form_code']);
                        $is_available = ($form['form_code'] === 'FM-QA-23'); // เฉพาะ FM-QA-23 เปิดใช้งาน
                        ?>
                        <div class="form-card">
                            <div class="status-badge <?php echo $is_available ? 'status-active' : 'status-coming-soon'; ?>">
                                <?php echo $is_available ? 'ใช้งานได้' : 'เร็วๆ นี้'; ?>
                            </div>
                            
                            <div class="form-header">
                                <div class="form-icon" style="background: linear-gradient(135deg, <?php echo $colors['from']; ?> 0%, <?php echo $colors['to']; ?> 100%);">
                                    <i class="fas fa-<?php echo getFormIcon($form['form_code']); ?>"></i>
                                </div>
                                <div class="form-info">
                                    <div class="form-code"><?php echo htmlspecialchars($form['form_code']); ?></div>
                                    <div class="form-name"><?php echo htmlspecialchars($form['form_name']); ?></div>
                                </div>
                            </div>
                            
                            <div class="form-stats">
                                <span><i class="fas fa-list me-1"></i><?php echo $form['total_types'] ?? 0; ?> ประเภท</span>
                                <span><i class="fas fa-clipboard me-1"></i><?php echo number_format($form['total_inspections'] ?? 0); ?> รายการ</span>
                                <?php if ($form['last_inspection']): ?>
                                    <span><i class="fas fa-clock me-1"></i><?php echo date('d/m/Y', strtotime($form['last_inspection'])); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-actions">
                                <?php if ($is_available): ?>
                                    <a href="../<?php echo strtolower(str_replace('-', '_', $form['form_code'])); ?>/index.php" 
                                       class="btn-form btn-primary-form">
                                        <i class="fas fa-play me-1"></i>เริ่มใช้งาน
                                    </a>
                                    <a href="../<?php echo strtolower(str_replace('-', '_', $form['form_code'])); ?>/history.php" 
                                       class="btn-form btn-secondary-form">
                                        <i class="fas fa-history me-1"></i>ประวัติ
                                    </a>
                                <?php else: ?>
                                    <button class="btn-form btn-disabled" disabled>
                                        <i class="fas fa-lock me-1"></i>เร็วๆ นี้
                                    </button>
                                    <button class="btn-form btn-disabled" disabled>
                                        <i class="fas fa-eye-slash me-1"></i>ไม่พร้อม
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <a href="../../../reports/index.php" class="quick-btn">
                        <i class="fas fa-chart-bar me-1"></i>รายงานสรุป
                    </a>
                    <a href="../../../dashboard/index.php" class="quick-btn">
                        <i class="fas fa-home me-1"></i>หน้าหลัก
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add interactive effects
        document.querySelectorAll('.form-card').forEach(card => {
            const isAvailable = !card.querySelector('button[disabled]');
            
            if (isAvailable) {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-15px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            } else {
                card.addEventListener('click', function() {
                    // Show coming soon message
                    const toast = document.createElement('div');
                    toast.className = 'position-fixed top-0 end-0 p-3';
                    toast.style.zIndex = '9999';
                    toast.innerHTML = `
                        <div class="toast show" role="alert">
                            <div class="toast-header">
                                <i class="fas fa-info-circle text-primary me-2"></i>
                                <strong class="me-auto">แจ้งเตือน</strong>
                                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                            </div>
                            <div class="toast-body">
                                แบบฟอร์มนี้จะเปิดใช้งานในเร็วๆ นี้
                            </div>
                        </div>
                    `;
                    
                    document.body.appendChild(toast);
                    
                    setTimeout(() => {
                        toast.remove();
                    }, 3000);
                });
            }
        });

        // Stats animation
        document.querySelectorAll('.stat-number').forEach(number => {
            const target = parseInt(number.textContent.replace(/,/g, ''));
            const duration = 2000;
            const increment = target / (duration / 16);
            let current = 0;
            
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                number.textContent = Math.floor(current).toLocaleString();
            }, 16);
        });
    </script>
</body>
</html>