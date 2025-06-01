<?php
// ===========================================
// modules/dashboard/index.php - Modern Dashboard
// ===========================================

define('QAC_SYSTEM', true);
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/session_config.php';

// Require authentication
requireLogin();

// Page settings
$page_title = 'แดชบอร์ด';
$page_subtitle = 'ภาพรวมระบบการควบคุมคุณภาพ';
$breadcrumbs = [];
$show_sidebar = true;

// Get dashboard statistics
try {
    $db = getDB();
    
    // Today's inspections
    $today_count = fetchOne("
        SELECT COUNT(*) as count 
        FROM qac_inspection_master 
        WHERE DATE(inspection_date) = CURDATE()
    ")['count'] ?? 0;
    
    // This week's inspections
    $week_count = fetchOne("
        SELECT COUNT(*) as count 
        FROM qac_inspection_master 
        WHERE YEARWEEK(inspection_date) = YEARWEEK(NOW())
    ")['count'] ?? 0;
    
    // This month's inspections
    $month_count = fetchOne("
        SELECT COUNT(*) as count 
        FROM qac_inspection_master 
        WHERE YEAR(inspection_date) = YEAR(NOW()) 
        AND MONTH(inspection_date) = MONTH(NOW())
    ")['count'] ?? 0;
    
    // Failed inspections this week
    $failed_count = fetchOne("
        SELECT COUNT(*) as count 
        FROM qac_inspection_master 
        WHERE overall_result = 'fail' 
        AND YEARWEEK(inspection_date) = YEARWEEK(NOW())
    ")['count'] ?? 0;
    
    // Get all available forms
    $available_forms = fetchAll("
        SELECT f.*, COUNT(im.inspection_id) as total_inspections
        FROM qac_forms f
        LEFT JOIN qac_inspection_master im ON f.form_id = im.form_id
        WHERE f.is_active = 1
        GROUP BY f.form_id
        ORDER BY f.form_code
    ");
    
    // Recent inspections
    $recent_inspections = fetchAll("
        SELECT 
            m.*,
            f.form_name,
            t.type_name
        FROM qac_inspection_master m
        LEFT JOIN qac_forms f ON m.form_id = f.form_id
        LEFT JOIN qac_inspection_types t ON m.type_id = t.type_id
        ORDER BY m.created_at DESC
        LIMIT 8
    ");
    
    // Chart data for this week
    $chart_data = fetchAll("
        SELECT 
            DATE(inspection_date) as date,
            COUNT(*) as total,
            SUM(CASE WHEN overall_result = 'pass' THEN 1 ELSE 0 END) as passed,
            SUM(CASE WHEN overall_result = 'fail' THEN 1 ELSE 0 END) as failed
        FROM qac_inspection_master
        WHERE inspection_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(inspection_date)
        ORDER BY inspection_date
    ");
    
} catch (Exception $e) {
    error_log('Dashboard data error: ' . $e->getMessage());
    $today_count = $week_count = $month_count = $failed_count = 0;
    $available_forms = [];
    $recent_inspections = [];
    $chart_data = [];
}

// Include header and sidebar
include_once '../../templates/header.php';
include_once '../../templates/sidebar.php';
?>

<div class="content-wrapper">
    <div class="dashboard-container">
        
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-content">
                <div class="welcome-text">
                    <h2>สวัสดี, <?php echo getUserFullName(); ?>! 👋</h2>
                    <p>ยินดีต้อนรับสู่ระบบควบคุมคุณภาพ วันนี้เป็นวันที่ <?php echo date('d/m/Y'); ?></p>
                </div>
                <div class="welcome-actions">
                    <a href="<?php echo SITE_URL; ?>/modules/forms/qa/fm_qa_23/form.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-plus-circle me-2"></i>บันทึกข้อมูลใหม่
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($today_count); ?></h3>
                    <p>การตรวจสอบวันนี้</p>
                </div>
                <div class="stat-trend">
                    <span class="trend-up">
                        <i class="fas fa-arrow-up"></i> 12%
                    </span>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($week_count); ?></h3>
                    <p>การตรวจสอบสัปดาห์นี้</p>
                </div>
                <div class="stat-trend">
                    <span class="trend-up">
                        <i class="fas fa-arrow-up"></i> 8%
                    </span>
                </div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($month_count); ?></h3>
                    <p>การตรวจสอบเดือนนี้</p>
                </div>
                <div class="stat-trend">
                    <span class="trend-up">
                        <i class="fas fa-arrow-up"></i> 15%
                    </span>
                </div>
            </div>
            
            <div class="stat-card <?php echo $failed_count > 0 ? 'danger' : 'warning'; ?>">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($failed_count); ?></h3>
                    <p>ไม่ผ่านมาตรฐาน</p>
                </div>
                <div class="stat-trend">
                    <?php if ($failed_count > 0): ?>
                    <span class="trend-down">
                        <i class="fas fa-arrow-down"></i> 5%
                    </span>
                    <?php else: ?>
                    <span class="trend-neutral">
                        <i class="fas fa-check"></i> ดี
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Main Content Grid -->
        <div class="main-grid">
            <!-- Available Forms Section -->
            <div class="forms-section">
                <div class="section-header">
                    <h3><i class="fas fa-clipboard-list me-2"></i>แบบฟอร์มที่ใช้ได้</h3>
                    <p>เลือกแบบฟอร์มสำหรับการตรวจสอบคุณภาพ</p>
                </div>
                
                <div class="forms-grid">
                    <?php if (!empty($available_forms)): ?>
                        <?php foreach ($available_forms as $form): ?>
                        <div class="form-card">
                            <div class="form-header">
                                <div class="form-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="form-badge">
                                    <?php echo htmlspecialchars($form['form_code']); ?>
                                </div>
                            </div>
                            <div class="form-content">
                                <h4><?php echo htmlspecialchars($form['form_name']); ?></h4>
                                <p><?php echo htmlspecialchars($form['description'] ?: 'แบบฟอร์มสำหรับการตรวจสอบคุณภาพ'); ?></p>
                                <div class="form-stats">
                                    <span class="stat-item">
                                        <i class="fas fa-chart-line"></i>
                                        <?php echo number_format($form['total_inspections']); ?> ครั้ง
                                    </span>
                                </div>
                            </div>
                            <div class="form-actions">
                                <a href="<?php echo SITE_URL; ?>/modules/forms/qa/fm_qa_23/form.php" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> เริ่มตรวจสอบ
                                </a>
                                <a href="<?php echo SITE_URL; ?>/modules/forms/qa/fm_qa_23/history.php" 
                                   class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-history"></i> ประวัติ
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                            <h5>ไม่มีแบบฟอร์มที่ใช้งานได้</h5>
                            <p class="text-muted">กรุณาติดต่อผู้ดูแลระบบเพื่อเพิ่มแบบฟอร์ม</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chart Section -->
            <div class="chart-section">
                <div class="section-header">
                    <h3><i class="fas fa-chart-line me-2"></i>สถิติการตรวจสอบ</h3>
                    <p>ข้อมูล 7 วันที่ผ่านมา</p>
                </div>
                <div class="chart-container">
                    <canvas id="inspectionChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Recent Inspections -->
        <div class="recent-section">
            <div class="section-header">
                <h3><i class="fas fa-clock me-2"></i>การตรวจสอบล่าสุด</h3>
                <a href="<?php echo SITE_URL; ?>/modules/forms/qa/fm_qa_23/history.php" 
                   class="btn btn-outline-primary btn-sm">
                    ดูทั้งหมด
                </a>
            </div>
            
            <?php if (!empty($recent_inspections)): ?>
            <div class="recent-grid">
                <?php foreach ($recent_inspections as $inspection): ?>
                <div class="recent-card">
                    <div class="recent-header">
                        <div class="inspection-number">
                            <?php echo htmlspecialchars($inspection['inspection_no']); ?>
                        </div>
                        <div class="inspection-status">
                            <?php
                            $status_classes = [
                                'draft' => 'status-draft',
                                'completed' => 'status-completed',
                                'approved' => 'status-approved',
                                'rejected' => 'status-rejected'
                            ];
                            $status_text = [
                                'draft' => 'ร่าง',
                                'completed' => 'เสร็จสิ้น',
                                'approved' => 'อนุมัติ',
                                'rejected' => 'ปฏิเสธ'
                            ];
                            ?>
                            <span class="status-badge <?php echo $status_classes[$inspection['status']] ?? 'status-draft'; ?>">
                                <?php echo $status_text[$inspection['status']] ?? $inspection['status']; ?>
                            </span>
                        </div>
                    </div>
                    <div class="recent-content">
                        <h5><?php echo htmlspecialchars($inspection['form_name']); ?></h5>
                        <div class="inspection-meta">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($inspection['inspector']); ?></span>
                            <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($inspection['inspection_date'] . ' ' . $inspection['inspection_time'])); ?></span>
                        </div>
                        <div class="inspection-result">
                            <?php if ($inspection['overall_result'] === 'pass'): ?>
                                <span class="result-badge result-pass">ผ่าน</span>
                            <?php elseif ($inspection['overall_result'] === 'fail'): ?>
                                <span class="result-badge result-fail">ไม่ผ่าน</span>
                            <?php else: ?>
                                <span class="result-badge result-pending">รอผล</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="recent-actions">
                        <a href="<?php echo SITE_URL; ?>/modules/forms/qa/fm_qa_23/view.php?id=<?php echo $inspection['inspection_id']; ?>" 
                           class="btn btn-sm btn-outline-primary" title="ดู">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if (canEdit($inspection['created_by'])): ?>
                        <a href="<?php echo SITE_URL; ?>/modules/forms/qa/fm_qa_23/edit.php?id=<?php echo $inspection['inspection_id']; ?>" 
                           class="btn btn-sm btn-outline-warning" title="แก้ไข">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo SITE_URL; ?>/modules/forms/qa/fm_qa_23/print.php?id=<?php echo $inspection['inspection_id']; ?>" 
                           class="btn btn-sm btn-outline-success" target="_blank" title="พิมพ์">
                            <i class="fas fa-print"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state-large">
                <i class="fas fa-clipboard-list fa-4x text-muted mb-4"></i>
                <h4>ยังไม่มีการตรวจสอบ</h4>
                <p class="text-muted mb-4">เริ่มบันทึกข้อมูลการตรวจสอบคุณภาพแรกของคุณ</p>
                <a href="<?php echo SITE_URL; ?>/modules/forms/qa/fm_qa_23/form.php" 
                   class="btn btn-primary btn-lg">
                    <i class="fas fa-plus me-2"></i>เริ่มตรวจสอบ
                </a>
            </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Chart initialization
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('inspectionChart').getContext('2d');
    
    const chartData = <?php echo json_encode($chart_data); ?>;
    
    const labels = chartData.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('th-TH', { 
            day: '2-digit', 
            month: '2-digit' 
        });
    });
    
    const totalData = chartData.map(item => parseInt(item.total));
    const passedData = chartData.map(item => parseInt(item.passed));
    const failedData = chartData.map(item => parseInt(item.failed));
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'ทั้งหมด',
                data: totalData,
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'ผ่าน',
                data: passedData,
                borderColor: '#10B981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'ไม่ผ่าน',
                data: failedData,
                borderColor: '#EF4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#F3F4F6'
                    }
                },
                x: {
                    grid: {
                        color: '#F3F4F6'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                }
            },
            elements: {
                point: {
                    radius: 4,
                    hoverRadius: 6
                }
            }
        }
    });
    
    // Add animation effects
    document.querySelectorAll('.stat-card').forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('fade-in-up');
    });
    
    document.querySelectorAll('.form-card').forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('fade-in-up');
    });
    
    document.querySelectorAll('.recent-card').forEach((card, index) => {
        card.style.animationDelay = `${index * 0.05}s`;
        card.classList.add('fade-in-up');
    });
});
</script>

<style>
/* Dashboard Styles */
.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Welcome Section */
.welcome-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 30px;
    color: white;
    position: relative;
    overflow: hidden;
}

.welcome-section::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 200px;
    height: 200px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    animation: float 6s ease-in-out infinite;
}

.welcome-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 1;
}

.welcome-text h2 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 10px;
}

.welcome-text p {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0;
}

.welcome-actions .btn {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    backdrop-filter: blur(10px);
    font-weight: 600;
    padding: 12px 24px;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.welcome-actions .btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

/* Statistics Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    position: relative;
    overflow: hidden;
    border-left: 4px solid transparent;
}

.stat-card.primary { border-left-color: #3B82F6; }
.stat-card.success { border-left-color: #10B981; }
.stat-card.info { border-left-color: #06B6D4; }
.stat-card.warning { border-left-color: #F59E0B; }
.stat-card.danger { border-left-color: #EF4444; }

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, transparent 0%, var(--primary) 50%, transparent 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.stat-card:hover::before {
    opacity: 1;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 20px;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-card.primary .stat-icon { background: linear-gradient(135deg, #3B82F6, #2563EB); }
.stat-card.success .stat-icon { background: linear-gradient(135deg, #10B981, #059669); }
.stat-card.info .stat-icon { background: linear-gradient(135deg, #06B6D4, #0891B2); }
.stat-card.warning .stat-icon { background: linear-gradient(135deg, #F59E0B, #D97706); }
.stat-card.danger .stat-icon { background: linear-gradient(135deg, #EF4444, #DC2626); }

.stat-content {
    flex: 1;
}

.stat-content h3 {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0 0 5px 0;
    color: var(--dark);
}

.stat-content p {
    margin: 0;
    color: var(--secondary);
    font-weight: 500;
}

.stat-trend {
    text-align: right;
}

.trend-up, .trend-down, .trend-neutral {
    font-size: 0.875rem;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 20px;
}

.trend-up {
    color: #10B981;
    background: rgba(16, 185, 129, 0.1);
}

.trend-down {
    color: #EF4444;
    background: rgba(239, 68, 68, 0.1);
}

.trend-neutral {
    color: #6B7280;
    background: rgba(107, 114, 128, 0.1);
}

/* Main Grid */
.main-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    margin-bottom: 40px;
}

/* Section Headers */
.section-header {
    margin-bottom: 25px;
}

.section-header h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
}

.section-header p {
    color: var(--secondary);
    margin: 0;
}

/* Forms Section */
.forms-section {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.forms-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.form-card {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 25px;
    transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    position: relative;
    overflow: hidden;
}

.form-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    border-color: var(--primary);
}

.form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.form-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.form-badge {
    background: rgba(59, 130, 246, 0.1);
    color: var(--primary);
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.form-content h4 {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 10px;
}

.form-content p {
    color: var(--secondary);
    font-size: 0.9rem;
    margin-bottom: 15px;
    line-height: 1.5;
}

.form-stats {
    margin-bottom: 20px;
}

.form-stats .stat-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.875rem;
    color: var(--secondary);
    background: white;
    padding: 6px 12px;
    border-radius: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.form-actions {
    display: flex;
    gap: 10px;
}

.form-actions .btn {
    flex: 1;
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.3s ease;
}

/* Chart Section */
.chart-section {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.chart-container {
    position: relative;
    height: 300px;
    margin-top: 20px;
}

/* Recent Section */
.recent-section {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.recent-section .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.recent-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
}

.recent-card {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 20px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.recent-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    border-color: var(--primary);
}

.recent-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.inspection-number {
    font-weight: 700;
    color: var(--dark);
    font-size: 0.95rem;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-draft { background: #f3f4f6; color: #6b7280; }
.status-completed { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.status-approved { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.status-rejected { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

.recent-content h5 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 10px;
}

.inspection-meta {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-bottom: 15px;
}

.inspection-meta span {
    font-size: 0.875rem;
    color: var(--secondary);
    display: flex;
    align-items: center;
    gap: 8px;
}

.inspection-meta i {
    width: 14px;
    text-align: center;
}

.inspection-result {
    margin-bottom: 15px;
}

.result-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.result-pass { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.result-fail { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
.result-pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

.recent-actions {
    display: flex;
    gap: 8px;
}

.recent-actions .btn {
    border-radius: 8px;
    transition: all 0.3s ease;
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    grid-column: 1 / -1;
}

.empty-state-large {
    text-align: center;
    padding: 60px 20px;
}

.empty-state h5,
.empty-state-large h4 {
    color: var(--dark);
    margin-bottom: 10px;
}

/* Animations */
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-20px); }
}

@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.fade-in-up {
    animation: fade-in-up 0.6s ease-out forwards;
}

/* Mobile Responsive */
@media (max-width: 991.98px) {
    .main-grid {
        grid-template-columns: 1fr;
        gap: 25px;
    }
    
    .welcome-content {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
    
    .welcome-text h2 {
        font-size: 1.5rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .stat-card {
        padding: 20px;
    }
    
    .stat-content h3 {
        font-size: 2rem;
    }
    
    .forms-grid {
        grid-template-columns: 1fr;
    }
    
    .recent-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .dashboard-container {
        padding: 0 10px;
    }
    
    .welcome-section {
        padding: 25px 20px;
        margin-bottom: 20px;
    }
    
    .forms-section,
    .chart-section,
    .recent-section {
        padding: 20px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .stat-card {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .recent-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .inspection-meta {
        flex-direction: column;
    }
}

/* Enhanced hover effects */
.form-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.1), transparent);
    transition: left 0.6s ease;
}

.form-card:hover::before {
    left: 100%;
}

.recent-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 3px;
    height: 100%;
    background: var(--primary);
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.recent-card:hover::before {
    transform: scaleY(1);
}

/* Loading states */
.stat-card.loading {
    position: relative;
    overflow: hidden;
}

.stat-card.loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.8), transparent);
    animation: loading-shimmer 1.5s infinite;
}

@keyframes loading-shimmer {
    0% { left: -100%; }
    100% { left: 100%; }
}

/* Print styles */
@media print {
    .welcome-actions,
    .form-actions,
    .recent-actions,
    .chart-section {
        display: none !important;
    }
    
    .dashboard-container {
        margin: 0;
        padding: 0;
    }
    
    .stat-card,
    .form-card,
    .recent-card {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}
</style>

<?php include_once '../../templates/footer.php'; ?>