<?php
// ===========================================
// modules/forms/qa/fm_qa_23/view.php - View FM-QA-23 Inspection
// ===========================================

define('QAC_SYSTEM', true);
require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../config/session_config.php';

// Check authentication
requireLogin();

// Get inspection ID
$inspection_id = $_GET['id'] ?? 0;
$success = $_GET['success'] ?? 0;

if (!$inspection_id) {
    header('Location: index.php');
    exit();
}

try {
    // Get inspection master data
    $sql = "SELECT m.*, f.form_name, t.type_name
            FROM qac_inspection_master m
            LEFT JOIN qac_forms f ON m.form_id = f.form_id
            LEFT JOIN qac_inspection_types t ON m.type_id = t.type_id
            WHERE m.inspection_id = :id";
    
    $inspection = fetchOne($sql, ['id' => $inspection_id]);
    
    if (!$inspection) {
        throw new Exception('ไม่พบข้อมูลการตรวจสอบ');
    }
    
    // Get header details
    $sql = "SELECT * FROM qac_inspection_details 
            WHERE inspection_id = :id 
            ORDER BY sort_order ASC";
    
    $details = fetchAll($sql, ['id' => $inspection_id]);
    
    // Get bottle inspections
    $sql = "SELECT * FROM qac_bottle_inspections 
            WHERE inspection_id = :id 
            ORDER BY bottle_number ASC";
    
    $bottles = fetchAll($sql, ['id' => $inspection_id]);
    
    // Get product and supplier names
    $product_name = '';
    $supplier_name = '';
    
    foreach ($details as $detail) {
        if ($detail['parameter_name'] === 'ชื่อสินค้า') {
            $product_sql = "SELECT product_code FROM qac_products WHERE product_id = :id";
            $product = fetchOne($product_sql, ['id' => $detail['value_text']]);
            $product_name = $product ? $product['product_code'] : 'N/A';
        }
        if ($detail['parameter_name'] === 'Preform Supplier') {
            $supplier_sql = "SELECT supplier_code FROM qac_suppliers WHERE supplier_id = :id";
            $supplier = fetchOne($supplier_sql, ['id' => $detail['value_text']]);
            $supplier_name = $supplier ? $supplier['supplier_code'] : 'N/A';
        }
    }
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}

// Function to get detail value by parameter name
function getDetailValue($details, $param_name) {
    foreach ($details as $detail) {
        if ($detail['parameter_name'] === $param_name) {
            return $detail['value_text'] ?: $detail['value_numeric'] ?: $detail['value_boolean'];
        }
    }
    return 'N/A';
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดการตรวจสอบ <?php echo htmlspecialchars($inspection['inspection_no'] ?? ''); ?> | <?php echo SITE_NAME; ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin-bottom: 0;
        }
        
        .navbar-brand, .nav-link {
            color: white !important;
        }
        
        .view-container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .view-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .view-title {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }
        
        .view-body {
            padding: 30px;
        }
        
        .section-title {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px 20px;
            margin: 25px 0 20px 0;
            font-weight: 600;
            font-size: 18px;
            color: #333;
        }
        
        .info-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: 600;
            min-width: 200px;
            color: #555;
        }
        
        .info-value {
            color: #333;
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pass {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .bottle-card {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .bottle-header {
            background: #667eea;
            color: white;
            padding: 10px 15px;
            font-weight: 600;
        }
        
        .bottle-body {
            padding: 20px;
            background: white;
        }
        
        .measurement-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .measurement-item {
            padding: 10px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            background: #fafafa;
        }
        
        .measurement-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .measurement-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
        }
        
        .btn-print {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .btn-back {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
        }
        
        @media print {
            .navbar, .action-buttons {
                display: none !important;
            }
            
            .view-container {
                box-shadow: none;
                border-radius: 0;
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

    <div class="container-fluid py-4">
        <div class="view-container">
            <!-- View Header -->
            <div class="view-header">
                <div>
                    <h1 class="view-title">FM-QA-23 - การตรวจสอบคุณภาพขวด</h1>
                    <p class="mb-0">เลขที่ใบตรวจ: <?php echo htmlspecialchars($inspection['inspection_no'] ?? ''); ?></p>
                </div>
                <div class="action-buttons">
                    <?php if (canEdit($inspection['created_by'] ?? null)): ?>
                        <a href="edit.php?id=<?php echo $inspection_id; ?>" class="btn-edit">
                            <i class="fas fa-edit me-2"></i>แก้ไข
                        </a>
                    <?php endif; ?>
                    <button onclick="window.print()" class="btn-print">
                        <i class="fas fa-print me-2"></i>พิมพ์
                    </button>
                    <a href="index.php" class="btn-back">
                        <i class="fas fa-arrow-left me-2"></i>กลับ
                    </a>
                </div>
            </div>
            
            <!-- View Body -->
            <div class="view-body">
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        บันทึกข้อมูลเรียบร้อยแล้ว
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php else: ?>
                
                <!-- Basic Information -->
                <div class="info-card">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-row">
                                <span class="info-label">เลขที่ใบตรวจ:</span>
                                <span class="info-value"><?php echo htmlspecialchars($inspection['inspection_no']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">วันที่ตรวจ:</span>
                                <span class="info-value"><?php echo date('d/m/Y', strtotime($inspection['inspection_date'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">เวลาที่ตรวจ:</span>
                                <span class="info-value"><?php echo date('H:i', strtotime($inspection['inspection_time'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">ผู้ตรวจสอบ:</span>
                                <span class="info-value"><?php echo htmlspecialchars($inspection['inspector']); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-row">
                                <span class="info-label">สถานะ:</span>
                                <span class="status-badge status-<?php echo $inspection['status']; ?>">
                                    <?php 
                                    $status_text = [
                                        'completed' => 'เสร็จสิ้น',
                                        'draft' => 'ร่าง',
                                        'approved' => 'อนุมัติแล้ว',
                                        'rejected' => 'ไม่อนุมัติ'
                                    ];
                                    echo $status_text[$inspection['status']] ?? $inspection['status'];
                                    ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">ผลการตรวจโดยรวม:</span>
                                <span class="status-badge status-<?php echo $inspection['overall_result'] ?? 'pass'; ?>">
                                    <?php 
                                    $result_text = [
                                        'pass' => 'ผ่าน',
                                        'fail' => 'ไม่ผ่าน',
                                        'conditional' => 'มีเงื่อนไข'
                                    ];
                                    echo $result_text[$inspection['overall_result']] ?? 'ผ่าน';
                                    ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">วันที่บันทึก:</span>
                                <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($inspection['created_at'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">ผู้บันทึก:</span>
                                <span class="info-value"><?php echo htmlspecialchars($inspection['created_by']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Header Details -->
                <div class="section-title">
                    <i class="fas fa-info-circle me-2"></i>ข้อมูลการตรวจสอบ
                </div>
                
                <div class="info-card">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-row">
                                <span class="info-label">ชื่อสินค้า:</span>
                                <span class="info-value"><?php echo htmlspecialchars($product_name); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Preform Supplier:</span>
                                <span class="info-value"><?php echo htmlspecialchars($supplier_name); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">สีของ Preform:</span>
                                <span class="info-value"><?php echo htmlspecialchars(getDetailValue($details, 'สีของ Preform')); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-row">
                                <span class="info-label">อุณหภูมิเครื่องเป่า:</span>
                                <span class="info-value"><?php echo htmlspecialchars(getDetailValue($details, 'อุณหภูมิเครื่องเป่า')); ?> °C</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">อุณหภูมิน้ำ:</span>
                                <span class="info-value"><?php echo htmlspecialchars(getDetailValue($details, 'อุณหภูมิน้ำ')); ?> °C</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bottle Inspections -->
                <?php if (!empty($bottles)): ?>
                <div class="section-title">
                    <i class="fas fa-flask me-2"></i>รายละเอียดการตรวจสอบขวด (<?php echo count($bottles); ?> ขวด)
                </div>
                
                <?php foreach ($bottles as $bottle): ?>
                <div class="bottle-card">
                    <div class="bottle-header">
                        <i class="fas fa-wine-bottle me-2"></i>ขวดที่ <?php echo $bottle['bottle_number']; ?>
                        <?php if ($bottle['result_status']): ?>
                            <span class="float-end status-badge status-<?php echo $bottle['result_status']; ?>">
                                <?php 
                                $status = [
                                    'pass' => 'ผ่าน',
                                    'fail' => 'ไม่ผ่าน', 
                                    'conditional' => 'มีเงื่อนไข'
                                ];
                                echo $status[$bottle['result_status']] ?? $bottle['result_status'];
                                ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="bottle-body">
                        <div class="measurement-grid">
                            <div class="measurement-item">
                                <div class="measurement-label">น้ำหนักขวด (กรัม)</div>
                                <div class="measurement-value"><?php echo $bottle['bottle_weight'] ?: '-'; ?></div>
                                <small class="text-muted">Spec: 24.97-25.20</small>
                            </div>
                            <div class="measurement-item">
                                <div class="measurement-label">ปริมาตร (มล.)</div>
                                <div class="measurement-value"><?php echo $bottle['volume_at_fill_level'] ?: '-'; ?></div>
                                <small class="text-muted">Spec: 169.00-171.00</small>
                            </div>
                            <div class="measurement-item">
                                <div class="measurement-label">ไหล่ขวด (มม.)</div>
                                <div class="measurement-value"><?php echo $bottle['shoulder_measurement'] ?: '-'; ?></div>
                                <small class="text-muted">Spec: 59.50-60.50</small>
                            </div>
                            <div class="measurement-item">
                                <div class="measurement-label">ตัวขวด (มม.)</div>
                                <div class="measurement-value"><?php echo $bottle['body_measurement'] ?: '-'; ?></div>
                                <small class="text-muted">Spec: 59.10-60.10</small>
                            </div>
                            <div class="measurement-item">
                                <div class="measurement-label">ก้นขวด (มม.)</div>
                                <div class="measurement-value"><?php echo $bottle['bottom_measurement'] ?: '-'; ?></div>
                                <small class="text-muted">Spec: 59.50-60.50</small>
                            </div>
                            <div class="measurement-item">
                                <div class="measurement-label">ปากใน (มม.)</div>
                                <div class="measurement-value"><?php echo $bottle['inner_mouth_measurement'] ?: '-'; ?></div>
                                <small class="text-muted">Spec: 27.47-27.80</small>
                            </div>
                            <div class="measurement-item">
                                <div class="measurement-label">สันเกลียว (มม.)</div>
                                <div class="measurement-value"><?php echo $bottle['thread_measurement'] ?: '-'; ?></div>
                                <small class="text-muted">Spec: 29.27-29.60</small>
                            </div>
                            <div class="measurement-item">
                                <div class="measurement-label">ปาก-แหวน (มม.)</div>
                                <div class="measurement-value"><?php echo $bottle['mouth_to_ring_measurement'] ?: '-'; ?></div>
                                <small class="text-muted">Spec: 12.60-13.10</small>
                            </div>
                            <div class="measurement-item">
                                <div class="measurement-label">คอ-แหวน (มม.)</div>
                                <div class="measurement-value"><?php echo $bottle['neck_to_ring_measurement'] ?: '-'; ?></div>
                                <small class="text-muted">Spec: 5.00-6.50</small>
                            </div>
                            <div class="measurement-item">
                                <div class="measurement-label">ช่องว่าง (มม.)</div>
                                <div class="measurement-value"><?php echo $bottle['ring_gap_measurement'] ?: '-'; ?></div>
                                <small class="text-muted">Spec: 2.60-3.00</small>
                            </div>
                            <div class="measurement-item">
                                <div class="measurement-label">ความกว้าง (มม.)</div>
                                <div class="measurement-value"><?php echo $bottle['neck_width_measurement'] ?: '-'; ?></div>
                                <small class="text-muted">Spec: 27.50-28.50</small>
                            </div>
                            <div class="measurement-item">
                                <div class="measurement-label">ความเอียง (มม.)</div>
                                <div class="measurement-value"><?php echo $bottle['tilt_measurement'] ?: '-'; ?></div>
                                <small class="text-muted">Max: ±0.35</small>
                            </div>
                        </div>
                        
                        <?php if ($bottle['remarks']): ?>
                        <div class="mt-3">
                            <strong>หมายเหตุ:</strong>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($bottle['remarks'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if ($inspection['remarks']): ?>
                <div class="section-title">
                    <i class="fas fa-comment me-2"></i>หมายเหตุ
                </div>
                <div class="info-card">
                    <?php echo nl2br(htmlspecialchars($inspection['remarks'])); ?>
                </div>
                <?php endif; ?>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>