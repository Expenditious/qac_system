<?php
// ===========================================
// modules/forms/qa/fm_qa_23/index.php - FM-QA-23 Form Type Selection
// ===========================================

define('QAC_SYSTEM', true);
require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../config/session_config.php';

// Check authentication
requireLogin();

// Get form information
try {
    $sql = "SELECT * FROM qac_forms WHERE form_code = 'FM-QA-23' AND is_active = 1";
    $form = fetchOne($sql);
    
    if (!$form) {
        throw new Exception('ไม่พบแบบฟอร์ม FM-QA-23');
    }
    
    // Get inspection types for this form
    $sql = "SELECT * FROM qac_inspection_types 
            WHERE form_id = :form_id AND is_active = 1 
            ORDER BY sort_order ASC, type_id ASC";
    
    $types = fetchAll($sql, ['form_id' => $form['form_id']]);
    
    // Get statistics for each type
    foreach ($types as &$type) {
        $sql = "SELECT 
                    COUNT(*) as total_inspections,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_inspections,
                    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_inspections
                FROM qac_inspection_master 
                WHERE type_id = :type_id";
        
        $stats = fetchOne($sql, ['type_id' => $type['type_id']]);
        $type['stats'] = $stats;
    }
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FM-QA-23 - การตรวจสอบคุณภาพขวด | <?php echo SITE_NAME; ?></title>
    
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
            padding: 40px 20px;
        }
        
        .header-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            margin-bottom: 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }
        
        .form-title {
            font-size: 48px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }
        
        .form-subtitle {
            font-size: 24px;
            color: #666;
            margin-bottom: 20px;
        }
        
        .form-description {
            font-size: 16px;
            color: #888;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .types-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .type-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .type-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .type-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            border-color: #667eea;
        }
        
        .type-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 36px;
        }
        
        .type-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }
        
        .type-description {
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .type-frequency {
            background: #f8f9fa;
            color: #495057;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .type-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-item {
            background: #f8f9fa;
            padding: 15px 10px;
            border-radius: 12px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            display: block;
        }
        
        .stat-label {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }
        
        .type-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .btn-start {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            flex: 1;
        }
        
        .btn-start:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
        }
        
        .btn-history {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            border: none;
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-history:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
        }
        
        .disabled-card {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .coming-soon {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #ffc107;
            color: #212529;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .quick-actions {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .quick-actions h5 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .quick-btn {
            background: rgba(102, 126, 234, 0.1);
            border: 2px solid #667eea;
            color: #667eea;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            margin: 0 5px;
            transition: all 0.3s ease;
        }
        
        .quick-btn:hover {
            background: #667eea;
            color: white;
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
        <!-- Header -->
        <div class="header-card">
            <h1 class="form-title">FM-QA-23</h1>
            <h2 class="form-subtitle">การตรวจสอบคุณภาพขวด Inline/Offline</h2>
            <p class="form-description">
                เลือกประเภทการตรวจสอบตามความถี่ที่กำหนด เพื่อบันทึกข้อมูลการตรวจสอบคุณภาพขวดตามมาตรฐาน QA
            </p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php else: ?>

        <!-- Types Grid -->
        <div class="types-grid">
            <?php foreach ($types as $index => $type): ?>
            <div class="type-card <?php echo $index > 0 ? 'disabled-card' : ''; ?>">
                <?php if ($index > 0): ?>
                    <div class="coming-soon">Coming Soon</div>
                <?php endif; ?>
                
                <div class="type-icon">
                    <i class="fas fa-<?php echo ['clock', 'user-clock', 'calendar-day'][$index] ?? 'flask'; ?>"></i>
                </div>
                
                <h3 class="type-title"><?php echo htmlspecialchars($type['type_name']); ?></h3>
                <p class="type-description"><?php echo htmlspecialchars($type['description']); ?></p>
                
                <div class="type-frequency">
                    <i class="fas fa-sync-alt me-1"></i>
                    <?php echo htmlspecialchars($type['frequency']); ?>
                </div>
                
                <div class="type-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo number_format($type['stats']['total_inspections']); ?></span>
                        <div class="stat-label">ทั้งหมด</div>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo number_format($type['stats']['completed_inspections']); ?></span>
                        <div class="stat-label">เสร็จสิ้น</div>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo number_format($type['stats']['today_inspections']); ?></span>
                        <div class="stat-label">วันนี้</div>
                    </div>
                </div>
                
                <div class="type-actions">
                    <?php if ($index === 0): ?>
                        <a href="form.php?type=<?php echo $type['type_code']; ?>" class="btn-start">
                            <i class="fas fa-plus me-2"></i>เริ่มตรวจสอบ
                        </a>
                        <a href="history.php?type=<?php echo $type['type_code']; ?>" class="btn-history">
                            <i class="fas fa-history me-2"></i>ประวัติ
                        </a>
                    <?php else: ?>
                        <button class="btn-start" disabled>
                            <i class="fas fa-lock me-2"></i>เร็วๆ นี้
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h5><i class="fas fa-bolt me-2"></i>การดำเนินการด่วน</h5>
            <div>
                <a href="history.php" class="quick-btn">
                    <i class="fas fa-list me-2"></i>ดูประวัติทั้งหมด
                </a>
                <a href="../../../reports/index.php?form=FM-QA-23" class="quick-btn">
                    <i class="fas fa-chart-bar me-2"></i>รายงานสรุป
                </a>
                <a href="../../../dashboard/index.php" class="quick-btn">
                    <i class="fas fa-home me-2"></i>หน้าหลัก
                </a>
            </div>
        </div>

        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add some interactive effects
        document.querySelectorAll('.type-card:not(.disabled-card)').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-15px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Show tooltip for disabled cards
        document.querySelectorAll('.disabled-card').forEach(card => {
            card.addEventListener('click', function() {
                const tooltip = document.createElement('div');
                tooltip.className = 'alert alert-info position-fixed';
                tooltip.style.top = '20px';
                tooltip.style.right = '20px';
                tooltip.style.zIndex = '9999';
                tooltip.innerHTML = '<i class="fas fa-info-circle me-2"></i>ฟีเจอร์นี้จะเปิดใช้งานในเร็วๆ นี้';
                
                document.body.appendChild(tooltip);
                
                setTimeout(() => {
                    tooltip.remove();
                }, 3000);
            });
        });
    </script>
</body>
</html>