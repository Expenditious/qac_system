<?php
// ===========================================
// modules/forms/qa/fm_qa_23/form.php - FM-QA-23 Complete Form
// ===========================================

define('QAC_SYSTEM', true);
require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../config/session_config.php';
require_once '../../../../core/Form.php';

// Check authentication
requireLogin();

// Initialize variables
$form = new Form('FM-QA-23', 'FM-QA-23-1');
$products = [];
$suppliers = [];
$error_message = '';
$success_message = '';

try {
    // Get products for dropdown
    $sql = "SELECT product_id, product_code, product_name FROM qac_products WHERE is_active = 1 ORDER BY product_code";
    $products = fetchAll($sql);
    
    // Get suppliers for dropdown
    $sql = "SELECT supplier_id, supplier_code, supplier_name FROM qac_suppliers WHERE is_active = 1 ORDER BY supplier_code";
    $suppliers = fetchAll($sql);
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาดในการโหลดข้อมูล: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!SessionManager::validateCSRF($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid security token');
        }
        
        // Validate basic form data
        $required_fields = ['inspection_date', 'inspection_time', 'product_id', 'supplier_id', 'preform_color', 'blowing_machine_temp', 'water_temp'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("กรุณากรอก {$field}");
            }
        }
        
        // Start transaction
        $db = getDB();
        $db->beginTransaction();
        
        // Generate inspection number
        $inspection_no = generateInspectionNumber();
        
        // Prepare master data
        $master_data = [
            'inspection_no' => $inspection_no,
            'form_id' => $form->getFormInfo()['form_id'],
            'type_id' => $form->getTypeInfo()['type_id'],
            'inspection_date' => $_POST['inspection_date'],
            'inspection_time' => $_POST['inspection_time'],
            'inspector' => getUsername(),
            'status' => 'completed',
            'created_by' => getUsername()
        ];
        
        // Insert master record
        $inspection_id = insertRecord('qac_inspection_master', $master_data);
        
        // Get or create header parameters
        $header_params = [
            'product_id' => ['name' => 'ชื่อสินค้า', 'type' => 'select'],
            'supplier_id' => ['name' => 'Preform Supplier', 'type' => 'select'],
            'preform_color' => ['name' => 'สีของ Preform', 'type' => 'text'],
            'blowing_machine_temp' => ['name' => 'อุณหภูมิเครื่องเป่า', 'type' => 'numeric'],
            'water_temp' => ['name' => 'อุณหภูมิน้ำ', 'type' => 'numeric']
        ];
        
        foreach ($header_params as $param_code => $param_info) {
            // Check if parameter exists
            $sql = "SELECT parameter_id FROM qac_parameters 
                    WHERE form_id = :form_id AND parameter_name = :param_name";
            $existing_param = fetchOne($sql, [
                'form_id' => $form->getFormInfo()['form_id'],
                'param_name' => $param_info['name']
            ]);
            
            if (!$existing_param) {
                // Create parameter if not exists
                $param_data = [
                    'form_id' => $form->getFormInfo()['form_id'],
                    'parameter_name' => $param_info['name'],
                    'parameter_type' => $param_info['type'],
                    'is_required' => 1,
                    'sort_order' => array_search($param_code, array_keys($header_params)) + 1
                ];
                $parameter_id = insertRecord('qac_parameters', $param_data);
            } else {
                $parameter_id = $existing_param['parameter_id'];
            }
            
            // Insert detail data
            $detail_data = [
                'inspection_id' => $inspection_id,
                'parameter_id' => $parameter_id,
                'parameter_name' => $param_info['name'],
                'parameter_type' => $param_info['type'],
                'sort_order' => array_search($param_code, array_keys($header_params)) + 1
            ];
            
            // Set value based on type
            if ($param_info['type'] === 'numeric') {
                $detail_data['value_numeric'] = $_POST[$param_code];
            } else {
                $detail_data['value_text'] = $_POST[$param_code];
            }
            
            insertRecord('qac_inspection_details', $detail_data);
        }
        
        // Process bottle inspections
        if (isset($_POST['bottles']) && is_array($_POST['bottles'])) {
            foreach ($_POST['bottles'] as $bottle_number => $bottle_data) {
                // Skip empty bottles
                if (empty(array_filter($bottle_data))) {
                    continue;
                }
                
                $bottle_inspection = [
                    'inspection_id' => $inspection_id,
                    'bottle_number' => $bottle_number,
                    'bottle_weight' => !empty($bottle_data['weight']) ? $bottle_data['weight'] : null,
                    'volume_at_fill_level' => !empty($bottle_data['volume']) ? $bottle_data['volume'] : null,
                    'shoulder_measurement' => !empty($bottle_data['shoulder']) ? $bottle_data['shoulder'] : null,
                    'body_measurement' => !empty($bottle_data['body']) ? $bottle_data['body'] : null,
                    'bottom_measurement' => !empty($bottle_data['bottom']) ? $bottle_data['bottom'] : null,
                    'inner_mouth_measurement' => !empty($bottle_data['inner_mouth']) ? $bottle_data['inner_mouth'] : null,
                    'thread_measurement' => !empty($bottle_data['thread']) ? $bottle_data['thread'] : null,
                    'mouth_to_ring_measurement' => !empty($bottle_data['mouth_to_ring']) ? $bottle_data['mouth_to_ring'] : null,
                    'neck_to_ring_measurement' => !empty($bottle_data['neck_to_ring']) ? $bottle_data['neck_to_ring'] : null,
                    'ring_gap_measurement' => !empty($bottle_data['ring_gap']) ? $bottle_data['ring_gap'] : null,
                    'neck_width_measurement' => !empty($bottle_data['neck_width']) ? $bottle_data['neck_width'] : null,
                    'tilt_measurement' => !empty($bottle_data['tilt']) ? $bottle_data['tilt'] : null,
                    'remarks' => !empty($bottle_data['remarks']) ? $bottle_data['remarks'] : null,
                    'result_status' => validateBottleMeasurements($bottle_data) ? 'pass' : 'fail'
                ];
                
                insertRecord('qac_bottle_inspections', $bottle_inspection);
            }
        }
        
        $db->commit();
        
        // Log activity
        logActivity('create_inspection', "Created FM-QA-23 inspection: {$inspection_no}", [
            'table' => 'qac_inspection_master',
            'id' => $inspection_id
        ]);
        
        $success_message = "บันทึกข้อมูลเรียบร้อย เลขที่ใบตรวจ: {$inspection_no}";
        
        // Redirect to view page
        header("Location: view.php?id={$inspection_id}&success=1");
        exit();
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollback();
        }
        $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        error_log('FM-QA-23 Form Error: ' . $e->getMessage());
    }
}

/**
 * Generate inspection number
 */
function generateInspectionNumber() {
    $prefix = 'QA';
    $form_code = '23';
    $type_code = '1';
    $date = date('Ymd');
    
    // Get next sequence number for today
    $sql = "SELECT COUNT(*) + 1 as next_seq 
            FROM qac_inspection_master 
            WHERE DATE(created_at) = CURDATE() 
            AND inspection_no LIKE 'QA-23-1-{$date}-%'";
    
    $result = fetchOne($sql);
    $sequence = str_pad($result['next_seq'], 4, '0', STR_PAD_LEFT);
    
    return "{$prefix}-{$form_code}-{$type_code}-{$date}-{$sequence}";
}

/**
 * Validate bottle measurements against specifications
 */
function validateBottleMeasurements($bottle_data) {
    $specs = [
        'weight' => ['min' => 24.97, 'max' => 25.20],
        'volume' => ['min' => 169.00, 'max' => 171.00],
        'shoulder' => ['min' => 59.50, 'max' => 60.50],
        'body' => ['min' => 59.10, 'max' => 60.10],
        'bottom' => ['min' => 59.50, 'max' => 60.50],
        'inner_mouth' => ['min' => 27.47, 'max' => 27.80],
        'thread' => ['min' => 29.27, 'max' => 29.60],
        'mouth_to_ring' => ['min' => 12.60, 'max' => 13.10],
        'neck_to_ring' => ['min' => 5.00, 'max' => 6.50],
        'ring_gap' => ['min' => 2.60, 'max' => 3.00],
        'neck_width' => ['min' => 27.50, 'max' => 28.50],
        'tilt' => ['min' => 0, 'max' => 0.35]
    ];
    
    foreach ($specs as $measurement => $spec) {
        if (!empty($bottle_data[$measurement])) {
            $value = floatval($bottle_data[$measurement]);
            if ($value < $spec['min'] || $value > $spec['max']) {
                return false;
            }
        }
    }
    
    return true;
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
            background-color: #f8f9fa;
        }
        
        .form-container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .form-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            text-align: center;
        }
        
        .form-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .form-subtitle {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .form-body {
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
        
        .bottle-inspection-item {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 20px;
            background: #fafafa;
            transition: all 0.3s ease;
        }
        
        .bottle-inspection-item:hover {
            border-color: #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
        }
        
        .bottle-header {
            background: #667eea;
            color: white;
            padding: 10px 15px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .bottle-body {
            padding: 20px;
            background: white;
        }
        
        .remove-bottle-btn {
            background: #dc3545;
            border: none;
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .remove-bottle-btn:hover {
            background: #c82333;
            transform: scale(1.1);
        }
        
        .add-bottle-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 20px 0;
        }
        
        .add-bottle-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        .preview-btn {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            margin-right: 10px;
        }
        
        .save-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .measurement-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .preview-modal .modal-dialog {
            max-width: 90%;
        }
        
        .preview-content {
            font-size: 14px;
        }
        
        .preview-table {
            font-size: 12px;
        }
        
        .spec-badge {
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            margin-left: 5px;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin-bottom: 0;
        }
        
        .navbar-brand, .nav-link {
            color: white !important;
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
        <div class="form-container">
            <!-- Form Header -->
            <div class="form-header">
                <h1 class="form-title">FM-QA-23</h1>
                <p class="form-subtitle">การตรวจสอบคุณภาพขวด Inline/Offline</p>
            </div>
            
            <!-- Form Body -->
            <div class="form-body">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <form id="qa23Form" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                    <input type="hidden" name="form_code" value="FM-QA-23">
                    
                    <!-- Header Information -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">เลขที่ใบตรวจ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="inspection_no" name="inspection_no" 
                                   value="<?php echo generateInspectionNumber(); ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">วันที่ตรวจ <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="inspection_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">เวลาที่ตรวจ <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="inspection_time" 
                                   value="<?php echo date('H:i'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">ชื่อสินค้า <span class="text-danger">*</span></label>
                            <select class="form-control" name="product_id" required>
                                <option value="">-- เลือกสินค้า --</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['product_id']; ?>">
                                        <?php echo htmlspecialchars($product['product_code']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Preform Supplier <span class="text-danger">*</span></label>
                            <select class="form-control" name="supplier_id" required>
                                <option value="">-- เลือก Supplier --</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['supplier_id']; ?>">
                                        <?php echo htmlspecialchars($supplier['supplier_code']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">สีของ Preform <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="preform_color" 
                                   placeholder="เช่น ใส, ฟ้า, เขียว" required>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">อุณหภูมิของเครื่องเป่า (°C) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="blowing_machine_temp" 
                                   step="0.1" min="0" max="200" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">อุณหภูมิน้ำ (°C) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="water_temp" 
                                   step="0.1" min="0" max="100" required>
                        </div>
                    </div>
                    
                    <!-- Bottle Inspections Section -->
                    <div class="section-title">
                        <i class="fas fa-flask me-2"></i>การตรวจสอบขวด
                    </div>
                    
                    <div id="bottleInspections">
                        <!-- Bottle 1 (Default) -->
                        <div class="bottle-inspection-item" data-bottle="1">
                            <div class="bottle-header">
                                <span><i class="fas fa-wine-bottle me-2"></i>ขวดที่ 1</span>
                                <button type="button" class="remove-bottle-btn" onclick="removeBottle(1)" style="display: none;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="bottle-body">
                                <div class="measurement-grid">
                                    <div>
                                        <label class="form-label">น้ำหนักขวด (กรัม)</label>
                                        <input type="number" class="form-control" name="bottles[1][weight]" 
                                               step="0.001" min="0">
                                        <small class="text-muted spec-badge">Spec: 24.97-25.20</small>
                                    </div>
                                    <div>
                                        <label class="form-label">ปริมาตรที่ระดับบรรจุน้ำ (มล.)</label>
                                        <input type="number" class="form-control" name="bottles[1][volume]" 
                                               step="0.001" min="0">
                                        <small class="text-muted spec-badge">Spec: 169.00-171.00</small>
                                    </div>
                                    <div>
                                        <label class="form-label">ไหล่ขวด (มม.)</label>
                                        <input type="number" class="form-control" name="bottles[1][shoulder]" 
                                               step="0.001" min="0">
                                        <small class="text-muted spec-badge">Spec: 59.50-60.50</small>
                                    </div>
                                    <div>
                                        <label class="form-label">ตัวขวด (มม.)</label>
                                        <input type="number" class="form-control" name="bottles[1][body]" 
                                               step="0.001" min="0">
                                        <small class="text-muted spec-badge">Spec: 59.10-60.10</small>
                                    </div>
                                    <div>
                                        <label class="form-label">ก้นขวด (มม.)</label>
                                        <input type="number" class="form-control" name="bottles[1][bottom]" 
                                               step="0.001" min="0">
                                        <small class="text-muted spec-badge">Spec: 59.50-60.50</small>
                                    </div>
                                    <div>
                                        <label class="form-label">ปากใน (มม.)</label>
                                        <input type="number" class="form-control" name="bottles[1][inner_mouth]" 
                                               step="0.001" min="0">
                                        <small class="text-muted spec-badge">Spec: 27.47-27.80</small>
                                    </div>
                                    <div>
                                        <label class="form-label">สันเกลียวขวด (มม.)</label>
                                        <input type="number" class="form-control" name="bottles[1][thread]" 
                                               step="0.001" min="0">
                                        <small class="text-muted spec-badge">Spec: 29.27-29.60</small>
                                    </div>
                                    <div>
                                        <label class="form-label">ปากขวดถึงแหวน (มม.)</label>
                                        <input type="number" class="form-control" name="bottles[1][mouth_to_ring]" 
                                               step="0.001" min="0">
                                        <small class="text-muted spec-badge">Spec: 12.60-13.10</small>
                                    </div>
                                    <div>
                                        <label class="form-label">คอขวดถึงแหวน (มม.)</label>
                                        <input type="number" class="form-control" name="bottles[1][neck_to_ring]" 
                                               step="0.001" min="0">
                                        <small class="text-muted spec-badge">Spec: 5.00-6.50</small>
                                    </div>
                                    <div>
                                        <label class="form-label">ช่องว่างระหว่างแหวน (มม.)</label>
                                        <input type="number" class="form-control" name="bottles[1][ring_gap]" 
                                               step="0.001" min="0">
                                        <small class="text-muted spec-badge">Spec: 2.60-3.00</small>
                                    </div>
                                    <div>
                                        <label class="form-label">ความกว้างคอขวด (มม.)</label>
                                        <input type="number" class="form-control" name="bottles[1][neck_width]" 
                                               step="0.001" min="0">
                                        <small class="text-muted spec-badge">Spec: 27.50-28.50</small>
                                    </div>
                                    <div>
                                        <label class="form-label">ความเอียง (มม.)</label>
                                        <input type="number" class="form-control" name="bottles[1][tilt]" 
                                               step="0.001" min="0">
                                        <small class="text-muted spec-badge">Max: ±0.35</small>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <label class="form-label">หมายเหตุ</label>
                                    <textarea class="form-control" name="bottles[1][remarks]" rows="2" 
                                              placeholder="หมายเหตุเพิ่มเติม..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Add Bottle Button -->
                    <div class="text-center">
                        <button type="button" class="add-bottle-btn" onclick="addBottle()">
                            <i class="fas fa-plus me-2"></i>เพิ่มขวดที่จะตรวจ
                        </button>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="text-center mt-4">
                        <button type="button" class="preview-btn" onclick="previewForm()">
                            <i class="fas fa-eye me-2"></i>Preview
                        </button>
                        <button type="submit" class="save-btn">
                            <i class="fas fa-save me-2"></i>บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-eye me-2"></i>ตรวจสอบข้อมูลก่อนบันทึก
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="previewContent" class="preview-content"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                    <button type="button" class="btn btn-primary" onclick="submitForm()">
                        <i class="fas fa-save me-2"></i>ยืนยันบันทึก
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let bottleCount = 1;
        
        function addBottle() {
            bottleCount++;
            const bottleTemplate = createBottleTemplate(bottleCount);
            document.getElementById('bottleInspections').insertAdjacentHTML('beforeend', bottleTemplate);
            updateRemoveButtons();
        }
        
        function removeBottle(bottleNumber) {
            const bottleElement = document.querySelector(`[data-bottle="${bottleNumber}"]`);
            if (bottleElement) {
                bottleElement.remove();
                updateRemoveButtons();
                renumberBottles();
            }
        }
        
        function updateRemoveButtons() {
            const bottles = document.querySelectorAll('.bottle-inspection-item');
            bottles.forEach((bottle, index) => {
                const removeBtn = bottle.querySelector('.remove-bottle-btn');
                if (bottles.length > 1) {
                    removeBtn.style.display = 'flex';
                } else {
                    removeBtn.style.display = 'none';
                }
            });
        }
        
        function renumberBottles() {
            const bottles = document.querySelectorAll('.bottle-inspection-item');
            bottles.forEach((bottle, index) => {
                const newNumber = index + 1;
                bottle.setAttribute('data-bottle', newNumber);
                bottle.querySelector('.bottle-header span').innerHTML = 
                    `<i class="fas fa-wine-bottle me-2"></i>ขวดที่ ${newNumber}`;
                
                // Update input names
                const inputs = bottle.querySelectorAll('input, textarea');
                inputs.forEach(input => {
                    const name = input.name;
                    if (name && name.includes('bottles[')) {
                        const newName = name.replace(/bottles\[\d+\]/, `bottles[${newNumber}]`);
                        input.name = newName;
                    }
                });
                
                // Update remove button onclick
                const removeBtn = bottle.querySelector('.remove-bottle-btn');
                removeBtn.setAttribute('onclick', `removeBottle(${newNumber})`);
            });
            
            bottleCount = bottles.length;
        }
        
        function createBottleTemplate(number) {
            return `
                <div class="bottle-inspection-item" data-bottle="${number}">
                    <div class="bottle-header">
                        <span><i class="fas fa-wine-bottle me-2"></i>ขวดที่ ${number}</span>
                        <button type="button" class="remove-bottle-btn" onclick="removeBottle(${number})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="bottle-body">
                        <div class="measurement-grid">
                            <div>
                                <label class="form-label">น้ำหนักขวด (กรัม)</label>
                                <input type="number" class="form-control" name="bottles[${number}][weight]" 
                                       step="0.001" min="0">
                                <small class="text-muted spec-badge">Spec: 24.97-25.20</small>
                            </div>
                            <div>
                                <label class="form-label">ปริมาตรที่ระดับบรรจุน้ำ (มล.)</label>
                                <input type="number" class="form-control" name="bottles[${number}][volume]" 
                                       step="0.001" min="0">
                                <small class="text-muted spec-badge">Spec: 169.00-171.00</small>
                            </div>
                            <div>
                                <label class="form-label">ไหล่ขวด (มม.)</label>
                                <input type="number" class="form-control" name="bottles[${number}][shoulder]" 
                                       step="0.001" min="0">
                                <small class="text-muted spec-badge">Spec: 59.50-60.50</small>
                            </div>
                            <div>
                                <label class="form-label">ตัวขวด (มม.)</label>
                                <input type="number" class="form-control" name="bottles[${number}][body]" 
                                       step="0.001" min="0">
                                <small class="text-muted spec-badge">Spec: 59.10-60.10</small>
                            </div>
                            <div>
                                <label class="form-label">ก้นขวด (มม.)</label>
                                <input type="number" class="form-control" name="bottles[${number}][bottom]" 
                                       step="0.001" min="0">
                                <small class="text-muted spec-badge">Spec: 59.50-60.50</small>
                            </div>
                            <div>
                                <label class="form-label">ปากใน (มม.)</label>
                                <input type="number" class="form-control" name="bottles[${number}][inner_mouth]" 
                                       step="0.001" min="0">
                                <small class="text-muted spec-badge">Spec: 27.47-27.80</small>
                            </div>
                            <div>
                                <label class="form-label">สันเกลียวขวด (มม.)</label>
                                <input type="number" class="form-control" name="bottles[${number}][thread]" 
                                       step="0.001" min="0">
                                <small class="text-muted spec-badge">Spec: 29.27-29.60</small>
                            </div>
                            <div>
                                <label class="form-label">ปากขวดถึงแหวน (มม.)</label>
                                <input type="number" class="form-control" name="bottles[${number}][mouth_to_ring]" 
                                       step="0.001" min="0">
                                <small class="text-muted spec-badge">Spec: 12.60-13.10</small>
                            </div>
                            <div>
                                <label class="form-label">คอขวดถึงแหวน (มม.)</label>
                                <input type="number" class="form-control" name="bottles[${number}][neck_to_ring]" 
                                       step="0.001" min="0">
                                <small class="text-muted spec-badge">Spec: 5.00-6.50</small>
                            </div>
                            <div>
                                <label class="form-label">ช่องว่างระหว่างแหวน (มม.)</label>
                                <input type="number" class="form-control" name="bottles[${number}][ring_gap]" 
                                       step="0.001" min="0">
                                <small class="text-muted spec-badge">Spec: 2.60-3.00</small>
                            </div>
                            <div>
                                <label class="form-label">ความกว้างคอขวด (มม.)</label>
                                <input type="number" class="form-control" name="bottles[${number}][neck_width]" 
                                       step="0.001" min="0">
                                <small class="text-muted spec-badge">Spec: 27.50-28.50</small>
                            </div>
                            <div>
                                <label class="form-label">ความเอียง (มม.)</label>
                                <input type="number" class="form-control" name="bottles[${number}][tilt]" 
                                       step="0.001" min="0">
                                <small class="text-muted spec-badge">Max: ±0.35</small>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">หมายเหตุ</label>
                            <textarea class="form-control" name="bottles[${number}][remarks]" rows="2" 
                                      placeholder="หมายเหตุเพิ่มเติม..."></textarea>
                        </div>
                    </div>
                </div>
            `;
        }
        
        function previewForm() {
            const formData = new FormData(document.getElementById('qa23Form'));
            let previewHTML = generatePreviewHTML(formData);
            document.getElementById('previewContent').innerHTML = previewHTML;
            
            const modal = new bootstrap.Modal(document.getElementById('previewModal'));
            modal.show();
        }
        
        function generatePreviewHTML(formData) {
            let html = `
                <div class="row mb-4">
                    <div class="col-12">
                        <h4 class="text-center mb-4">FM-QA-23 - การตรวจสอบคุณภาพขวด Inline/Offline</h4>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>เลขที่ใบตรวจ:</strong> ${formData.get('inspection_no') || 'N/A'}
                    </div>
                    <div class="col-md-3">
                        <strong>วันที่ตรวจ:</strong> ${formatDate(formData.get('inspection_date'))}
                    </div>
                    <div class="col-md-3">
                        <strong>เวลาที่ตรวจ:</strong> ${formData.get('inspection_time') || 'N/A'}
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>สินค้า:</strong> ${getSelectedText('product_id', formData.get('product_id'))}
                    </div>
                    <div class="col-md-4">
                        <strong>Preform Supplier:</strong> ${getSelectedText('supplier_id', formData.get('supplier_id'))}
                    </div>
                    <div class="col-md-4">
                        <strong>สีของ Preform:</strong> ${formData.get('preform_color') || 'N/A'}
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <strong>อุณหภูมิเครื่องเป่า:</strong> ${formData.get('blowing_machine_temp') || 'N/A'} °C
                    </div>
                    <div class="col-md-6">
                        <strong>อุณหภูมิน้ำ:</strong> ${formData.get('water_temp') || 'N/A'} °C
                    </div>
                </div>
                
                <h5 class="mb-3">รายละเอียดการตรวจสอบขวด</h5>
            `;
            
            // Get bottle data
            const bottleData = extractBottleData(formData);
            
            if (bottleData.length > 0) {
                html += `
                    <div class="table-responsive">
                        <table class="table table-bordered preview-table">
                            <thead class="table-light">
                                <tr>
                                    <th>ขวดที่</th>
                                    <th>น้ำหนัก<br>(กรัม)</th>
                                    <th>ปริมาตร<br>(มล.)</th>
                                    <th>ไหล่<br>(มม.)</th>
                                    <th>ตัวขวด<br>(มม.)</th>
                                    <th>ก้นขวด<br>(มม.)</th>
                                    <th>ปากใน<br>(มม.)</th>
                                    <th>สันเกลียว<br>(มม.)</th>
                                    <th>ปาก-แหวน<br>(มม.)</th>
                                    <th>คอ-แหวน<br>(มม.)</th>
                                    <th>ช่องว่าง<br>(มม.)</th>
                                    <th>ความกว้าง<br>(มม.)</th>
                                    <th>ความเอียง<br>(มม.)</th>
                                    <th>หมายเหตุ</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                bottleData.forEach((bottle, index) => {
                    html += `
                        <tr>
                            <td class="text-center">${index + 1}</td>
                            <td class="text-center">${bottle.weight || '-'}</td>
                            <td class="text-center">${bottle.volume || '-'}</td>
                            <td class="text-center">${bottle.shoulder || '-'}</td>
                            <td class="text-center">${bottle.body || '-'}</td>
                            <td class="text-center">${bottle.bottom || '-'}</td>
                            <td class="text-center">${bottle.inner_mouth || '-'}</td>
                            <td class="text-center">${bottle.thread || '-'}</td>
                            <td class="text-center">${bottle.mouth_to_ring || '-'}</td>
                            <td class="text-center">${bottle.neck_to_ring || '-'}</td>
                            <td class="text-center">${bottle.ring_gap || '-'}</td>
                            <td class="text-center">${bottle.neck_width || '-'}</td>
                            <td class="text-center">${bottle.tilt || '-'}</td>
                            <td>${bottle.remarks || '-'}</td>
                        </tr>
                    `;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                html += '<p class="text-muted">ไม่มีข้อมูลการตรวจสอบขวด</p>';
            }
            
            return html;
        }
        
        function extractBottleData(formData) {
            const bottles = [];
            const formDataObj = {};
            
            // Convert FormData to object
            for (let [key, value] of formData.entries()) {
                formDataObj[key] = value;
            }
            
            // Extract bottle data
            for (let key in formDataObj) {
                const match = key.match(/bottles\[(\d+)\]\[(\w+)\]/);
                if (match) {
                    const bottleIndex = parseInt(match[1]) - 1;
                    const field = match[2];
                    
                    if (!bottles[bottleIndex]) {
                        bottles[bottleIndex] = {};
                    }
                    
                    bottles[bottleIndex][field] = formDataObj[key];
                }
            }
            
            return bottles.filter(bottle => bottle && Object.keys(bottle).length > 0);
        }
        
        function getSelectedText(selectName, value) {
            const select = document.querySelector(`[name="${selectName}"]`);
            if (select && value) {
                const option = select.querySelector(`option[value="${value}"]`);
                return option ? option.textContent : value;
            }
            return 'N/A';
        }
        
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('th-TH');
        }
        
        function submitForm() {
            document.getElementById('qa23Form').submit();
        }
        
        // Form validation
        document.getElementById('qa23Form').addEventListener('submit', function(e) {
            // Validate required fields
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('.save-btn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>กำลังบันทึก...';
            submitBtn.disabled = true;
        });
        
        // Auto-save draft (optional feature)
        let autoSaveTimer;
        document.getElementById('qa23Form').addEventListener('input', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(() => {
                console.log('Auto-saving draft...');
            }, 5000);
        });
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateRemoveButtons();
        });
    </script>
</body>
</html>