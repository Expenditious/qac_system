<?php
// ===========================================
// core/Form.php - QAC Form Handler Class
// ===========================================

// Prevent direct access
if (!defined('QAC_SYSTEM')) {
    die('Direct access not allowed');
}

class Form {
    
    private $db;
    private $formId;
    private $typeId;
    private $formData;
    private $typeData;
    private $parameters;
    private $validator;
    private $errors = [];
    
    public function __construct($formCode = null, $typeCode = null) {
        $this->db = getDB();
        $this->validator = new Validator();
        
        if ($formCode) {
            $this->loadForm($formCode, $typeCode);
        }
    }
    
    /**
     * Load form and type data
     */
    public function loadForm($formCode, $typeCode = null) {
        try {
            // Load form data
            $sql = "SELECT * FROM qac_forms WHERE form_code = :form_code AND is_active = 1";
            $this->formData = fetchOne($sql, ['form_code' => $formCode]);
            
            if (!$this->formData) {
                throw new Exception("Form '{$formCode}' not found or inactive");
            }
            
            $this->formId = $this->formData['form_id'];
            
            // Load type data if specified
            if ($typeCode) {
                $sql = "SELECT * FROM qac_inspection_types 
                        WHERE type_code = :type_code AND form_id = :form_id AND is_active = 1";
                $this->typeData = fetchOne($sql, [
                    'type_code' => $typeCode,
                    'form_id' => $this->formId
                ]);
                
                if (!$this->typeData) {
                    throw new Exception("Type '{$typeCode}' not found for form '{$formCode}'");
                }
                
                $this->typeId = $this->typeData['type_id'];
            }
            
            // Load parameters
            $this->loadParameters();
            
        } catch (Exception $e) {
            error_log('Form loading error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Load form parameters
     */
    private function loadParameters() {
        $sql = "SELECT * FROM qac_parameters 
                WHERE form_id = :form_id 
                AND (type_id IS NULL OR type_id = :type_id)
                AND is_active = 1 
                ORDER BY sort_order ASC, parameter_id ASC";
        
        $params = [
            'form_id' => $this->formId,
            'type_id' => $this->typeId
        ];
        
        $this->parameters = fetchAll($sql, $params);
        
        // Decode JSON options for select parameters
        foreach ($this->parameters as &$param) {
            if ($param['options']) {
                $param['options'] = json_decode($param['options'], true);
            }
            if ($param['validation_rules']) {
                $param['validation_rules'] = json_decode($param['validation_rules'], true);
            }
        }
    }
    
    /**
     * Get form information
     */
    public function getFormInfo() {
        return $this->formData;
    }
    
    /**
     * Get type information
     */
    public function getTypeInfo() {
        return $this->typeData;
    }
    
    /**
     * Get form parameters
     */
    public function getParameters() {
        return $this->parameters;
    }
    
    /**
     * Get available types for current form
     */
    public function getAvailableTypes() {
        if (!$this->formId) {
            return [];
        }
        
        $sql = "SELECT * FROM qac_inspection_types 
                WHERE form_id = :form_id AND is_active = 1 
                ORDER BY sort_order ASC, type_id ASC";
        
        return fetchAll($sql, ['form_id' => $this->formId]);
    }
    
    /**
     * Generate inspection number
     */
    public function generateInspectionNo() {
        $prefix = INSPECTION_NO_PREFIX;
        $date = date('Ymd');
        $time = date('His');
        
        // Get next sequence number for today
        $sql = "SELECT COUNT(*) + 1 as next_seq 
                FROM qac_inspection_master 
                WHERE DATE(created_at) = CURDATE()";
        
        $result = fetchOne($sql);
        $sequence = str_pad($result['next_seq'], 3, '0', STR_PAD_LEFT);
        
        return "{$prefix}-{$date}-{$time}-{$sequence}";
    }
    
    /**
     * Validate form data
     */
    public function validate($data) {
        $this->errors = [];
        
        if (!$this->parameters) {
            $this->errors[] = 'No parameters defined for this form';
            return false;
        }
        
        foreach ($this->parameters as $param) {
            $paramName = 'param_' . $param['parameter_id'];
            $value = $data[$paramName] ?? null;
            
            // Check required fields
            if ($param['is_required'] && ($value === null || $value === '')) {
                $this->errors[] = "กรุณากรอก {$param['parameter_name']}";
                continue;
            }
            
            // Skip validation if not required and empty
            if (!$param['is_required'] && ($value === null || $value === '')) {
                continue;
            }
            
            // Type-specific validation
            switch ($param['parameter_type']) {
                case 'numeric':
                    if (!is_numeric($value)) {
                        $this->errors[] = "{$param['parameter_name']} ต้องเป็นตัวเลข";
                    } else {
                        // Check min/max values
                        if ($param['min_value'] !== null && $value < $param['min_value']) {
                            $this->errors[] = "{$param['parameter_name']} ต้องมีค่าไม่น้อยกว่า {$param['min_value']}";
                        }
                        if ($param['max_value'] !== null && $value > $param['max_value']) {
                            $this->errors[] = "{$param['parameter_name']} ต้องมีค่าไม่เกิน {$param['max_value']}";
                        }
                    }
                    break;
                    
                case 'boolean':
                    if (!in_array($value, ['0', '1', 'true', 'false', true, false, 0, 1])) {
                        $this->errors[] = "{$param['parameter_name']} ต้องเป็น true หรือ false";
                    }
                    break;
                    
                case 'select':
                    if ($param['options'] && !in_array($value, $param['options'])) {
                        $this->errors[] = "{$param['parameter_name']} มีค่าที่ไม่ถูกต้อง";
                    }
                    break;
                    
                case 'date':
                    if (!$this->validateDate($value)) {
                        $this->errors[] = "{$param['parameter_name']} รูปแบบวันที่ไม่ถูกต้อง";
                    }
                    break;
                    
                case 'time':
                    if (!$this->validateTime($value)) {
                        $this->errors[] = "{$param['parameter_name']} รูปแบบเวลาไม่ถูกต้อง";
                    }
                    break;
            }
            
            // Custom validation rules
            if ($param['validation_rules']) {
                $this->validateCustomRules($param, $value);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Save inspection data
     */
    public function saveInspection($data) {
        if (!$this->validate($data)) {
            throw new Exception('Validation failed: ' . implode(', ', $this->errors));
        }
        
        try {
            $this->db->beginTransaction();
            
            // Prepare master data
            $masterData = [
                'inspection_no' => $data['inspection_no'] ?? $this->generateInspectionNo(),
                'form_id' => $this->formId,
                'type_id' => $this->typeId,
                'inspection_date' => $data['inspection_date'] ?? date('Y-m-d'),
                'inspection_time' => $data['inspection_time'] ?? date('H:i:s'),
                'shift' => $data['shift'] ?? null,
                'department' => $data['department'] ?? null,
                'location' => $data['location'] ?? null,
                'inspector' => $data['inspector'] ?? getUsername(),
                'supervisor' => $data['supervisor'] ?? null,
                'status' => $data['status'] ?? 'completed',
                'overall_result' => $data['overall_result'] ?? 'pass',
                'remarks' => $data['remarks'] ?? null,
                'created_by' => getUsername()
            ];
            
            // Insert master record
            $inspectionId = insertRecord('qac_inspection_master', $masterData);
            
            // Save parameter details
            foreach ($this->parameters as $param) {
                $paramName = 'param_' . $param['parameter_id'];
                $value = $data[$paramName] ?? null;
                
                if ($value !== null && $value !== '') {
                    $detailData = [
                        'inspection_id' => $inspectionId,
                        'parameter_id' => $param['parameter_id'],
                        'parameter_name' => $param['parameter_name'],
                        'parameter_type' => $param['parameter_type'],
                        'sort_order' => $param['sort_order']
                    ];
                    
                    // Set value based on type
                    switch ($param['parameter_type']) {
                        case 'numeric':
                            $detailData['value_numeric'] = floatval($value);
                            break;
                        case 'boolean':
                            $detailData['value_boolean'] = $this->convertToBoolean($value);
                            break;
                        case 'date':
                            $detailData['value_date'] = $value;
                            break;
                        case 'time':
                            $detailData['value_time'] = $value;
                            break;
                        case 'datetime':
                            $detailData['value_datetime'] = $value;
                            break;
                        default:
                            $detailData['value_text'] = $value;
                    }
                    
                    // Check if value meets standards
                    $detailData['is_standard'] = $this->checkStandards($param, $value);
                    
                    // Add remarks if provided
                    $remarkKey = 'remark_' . $param['parameter_id'];
                    if (isset($data[$remarkKey]) && !empty($data[$remarkKey])) {
                        $detailData['remarks'] = $data[$remarkKey];
                    }
                    
                    insertRecord('qac_inspection_details', $detailData);
                }
            }
            
            $this->db->commit();
            
            // Log activity
            logActivity('create_inspection', "Created inspection: {$masterData['inspection_no']}", [
                'table' => 'qac_inspection_master',
                'id' => $inspectionId
            ]);
            
            return $inspectionId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Failed to save inspection: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update inspection data
     */
    public function updateInspection($inspectionId, $data, $editReason = '') {
        if (!$this->validate($data)) {
            throw new Exception('Validation failed: ' . implode(', ', $this->errors));
        }
        
        try {
            $this->db->beginTransaction();
            
            // Get old data for history
            $oldData = $this->getInspectionData($inspectionId);
            
            // Update master record
            $masterData = [
                'inspection_date' => $data['inspection_date'] ?? $oldData['inspection_date'],
                'inspection_time' => $data['inspection_time'] ?? $oldData['inspection_time'],
                'shift' => $data['shift'] ?? $oldData['shift'],
                'department' => $data['department'] ?? $oldData['department'],
                'location' => $data['location'] ?? $oldData['location'],
                'supervisor' => $data['supervisor'] ?? $oldData['supervisor'],
                'overall_result' => $data['overall_result'] ?? $oldData['overall_result'],
                'remarks' => $data['remarks'] ?? $oldData['remarks'],
                'updated_by' => getUsername()
            ];
            
            updateRecord('qac_inspection_master', $masterData, 'inspection_id = :id', ['id' => $inspectionId]);
            
            // Delete old details
            deleteRecord('qac_inspection_details', 'inspection_id = :id', ['id' => $inspectionId]);
            
            // Insert new details
            foreach ($this->parameters as $param) {
                $paramName = 'param_' . $param['parameter_id'];
                $value = $data[$paramName] ?? null;
                
                if ($value !== null && $value !== '') {
                    $detailData = [
                        'inspection_id' => $inspectionId,
                        'parameter_id' => $param['parameter_id'],
                        'parameter_name' => $param['parameter_name'],
                        'parameter_type' => $param['parameter_type'],
                        'sort_order' => $param['sort_order']
                    ];
                    
                    // Set value based on type
                    switch ($param['parameter_type']) {
                        case 'numeric':
                            $detailData['value_numeric'] = floatval($value);
                            break;
                        case 'boolean':
                            $detailData['value_boolean'] = $this->convertToBoolean($value);
                            break;
                        case 'date':
                            $detailData['value_date'] = $value;
                            break;
                        case 'time':
                            $detailData['value_time'] = $value;
                            break;
                        case 'datetime':
                            $detailData['value_datetime'] = $value;
                            break;
                        default:
                            $detailData['value_text'] = $value;
                    }
                    
                    $detailData['is_standard'] = $this->checkStandards($param, $value);
                    
                    $remarkKey = 'remark_' . $param['parameter_id'];
                    if (isset($data[$remarkKey]) && !empty($data[$remarkKey])) {
                        $detailData['remarks'] = $data[$remarkKey];
                    }
                    
                    insertRecord('qac_inspection_details', $detailData);
                }
            }
            
            // Save edit history
            $historyData = [
                'inspection_id' => $inspectionId,
                'edit_by' => getUsername(),
                'edit_reason' => $editReason,
                'old_values' => json_encode($oldData),
                'new_values' => json_encode($data)
            ];
            
            insertRecord('qac_edit_history', $historyData);
            
            $this->db->commit();
            
            // Log activity
            logActivity('update_inspection', "Updated inspection ID: {$inspectionId}", [
                'table' => 'qac_inspection_master',
                'id' => $inspectionId
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Failed to update inspection: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get inspection data with details
     */
    public function getInspectionData($inspectionId) {
        $sql = "SELECT m.*, f.form_name, t.type_name
                FROM qac_inspection_master m
                LEFT JOIN qac_forms f ON m.form_id = f.form_id
                LEFT JOIN qac_inspection_types t ON m.type_id = t.type_id
                WHERE m.inspection_id = :id";
        
        $master = fetchOne($sql, ['id' => $inspectionId]);
        
        if (!$master) {
            return null;
        }
        
        // Get details
        $sql = "SELECT * FROM qac_inspection_details 
                WHERE inspection_id = :id 
                ORDER BY sort_order ASC, detail_id ASC";
        
        $details = fetchAll($sql, ['id' => $inspectionId]);
        
        $master['details'] = $details;
        
        return $master;
    }
    
    /**
     * Get inspection history
     */
    public function getInspectionHistory($filters = []) {
        $where = ["m.form_id = :form_id"];
        $params = ['form_id' => $this->formId];
        
        if ($this->typeId) {
            $where[] = "m.type_id = :type_id";
            $params['type_id'] = $this->typeId;
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "m.inspection_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "m.inspection_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['inspector'])) {
            $where[] = "m.inspector = :inspector";
            $params['inspector'] = $filters['inspector'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = "m.status = :status";
            $params['status'] = $filters['status'];
        }
        
        $sql = "SELECT m.*, f.form_name, t.type_name
                FROM qac_inspection_master m
                LEFT JOIN qac_forms f ON m.form_id = f.form_id
                LEFT JOIN qac_inspection_types t ON m.type_id = t.type_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY m.inspection_date DESC, m.inspection_time DESC";
        
        return fetchAll($sql, $params);
    }
    
    /**
     * Get validation errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Check if value meets standards
     */
    private function checkStandards($param, $value) {
        // Basic standard checking
        if ($param['parameter_type'] === 'numeric') {
            if ($param['min_value'] !== null && $value < $param['min_value']) {
                return false;
            }
            if ($param['max_value'] !== null && $value > $param['max_value']) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Convert value to boolean
     */
    private function convertToBoolean($value) {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['true', '1', 'yes', 'on']);
        }
        
        return (bool) $value;
    }
    
    /**
     * Validate date format
     */
    private function validateDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Validate time format
     */
    private function validateTime($time) {
        $t = DateTime::createFromFormat('H:i', $time);
        return $t && $t->format('H:i') === $time;
    }
    
    /**
     * Validate custom rules
     */
    private function validateCustomRules($param, $value) {
        if (!$param['validation_rules']) {
            return;
        }
        
        $rules = $param['validation_rules'];
        
        foreach ($rules as $rule => $ruleValue) {
            switch ($rule) {
                case 'max_length':
                    if (strlen($value) > $ruleValue) {
                        $this->errors[] = "{$param['parameter_name']} ต้องมีความยาวไม่เกิน {$ruleValue} ตัวอักษร";
                    }
                    break;
                    
                case 'min_length':
                    if (strlen($value) < $ruleValue) {
                        $this->errors[] = "{$param['parameter_name']} ต้องมีความยาวอย่างน้อย {$ruleValue} ตัวอักษร";
                    }
                    break;
                    
                case 'pattern':
                    if (!preg_match($ruleValue, $value)) {
                        $this->errors[] = "{$param['parameter_name']} รูปแบบไม่ถูกต้อง";
                    }
                    break;
            }
        }
    }
    
    /**
     * Generate form HTML
     */
    public function renderForm($data = [], $action = '', $method = 'POST') {
        if (!$this->parameters) {
            return '<p class="text-danger">ไม่พบพารามิเตอร์สำหรับฟอร์มนี้</p>';
        }
        
        $html = "<form method='{$method}' action='{$action}' class='qac-form'>";
        $html .= "<input type='hidden' name='csrf_token' value='" . getCSRFToken() . "'>";
        
        foreach ($this->parameters as $param) {
            $html .= $this->renderParameter($param, $data);
        }
        
        $html .= "</form>";
        
        return $html;
    }
    
    /**
     * Render individual parameter
     */
    private function renderParameter($param, $data = []) {
        $paramName = 'param_' . $param['parameter_id'];
        $value = $data[$paramName] ?? $param['default_value'] ?? '';
        $required = $param['is_required'] ? 'required' : '';
        $requiredMark = $param['is_required'] ? '<span class="text-danger">*</span>' : '';
        
        $html = "<div class='form-group mb-3'>";
        $html .= "<label for='{$paramName}' class='form-label'>";
        $html .= $param['parameter_name'];
        if ($param['unit']) {
            $html .= " ({$param['unit']})";
        }
        $html .= " {$requiredMark}</label>";
        
        switch ($param['parameter_type']) {
            case 'text':
                $html .= "<input type='text' class='form-control' id='{$paramName}' name='{$paramName}' value='{$value}' {$required}>";
                break;
                
            case 'numeric':
                $step = 'step="0.001"';
                $min = $param['min_value'] !== null ? "min='{$param['min_value']}'" : '';
                $max = $param['max_value'] !== null ? "max='{$param['max_value']}'" : '';
                $html .= "<input type='number' class='form-control' id='{$paramName}' name='{$paramName}' value='{$value}' {$step} {$min} {$max} {$required}>";
                break;
                
            case 'boolean':
                $checked = $this->convertToBoolean($value) ? 'checked' : '';
                $html .= "<div class='form-check'>";
                $html .= "<input type='checkbox' class='form-check-input' id='{$paramName}' name='{$paramName}' value='1' {$checked}>";
                $html .= "<label class='form-check-label' for='{$paramName}'>ผ่าน</label>";
                $html .= "</div>";
                break;
                
            case 'select':
                $html .= "<select class='form-select' id='{$paramName}' name='{$paramName}' {$required}>";
                $html .= "<option value=''>-- เลือก --</option>";
                if ($param['options']) {
                    foreach ($param['options'] as $option) {
                        $selected = ($value == $option) ? 'selected' : '';
                        $html .= "<option value='{$option}' {$selected}>{$option}</option>";
                    }
                }
                $html .= "</select>";
                break;
                
            case 'textarea':
                $html .= "<textarea class='form-control' id='{$paramName}' name='{$paramName}' rows='3' {$required}>{$value}</textarea>";
                break;
                
            case 'date':
                $html .= "<input type='date' class='form-control' id='{$paramName}' name='{$paramName}' value='{$value}' {$required}>";
                break;
                
            case 'time':
                $html .= "<input type='time' class='form-control' id='{$paramName}' name='{$paramName}' value='{$value}' {$required}>";
                break;
                
            case 'datetime':
                $html .= "<input type='datetime-local' class='form-control' id='{$paramName}' name='{$paramName}' value='{$value}' {$required}>";
                break;
        }
        
        $html .= "</div>";
        
        return $html;
    }
}

?>