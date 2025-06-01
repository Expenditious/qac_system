<?php
// ===========================================
// core/Validator.php - Validation Class
// ===========================================

class Validator {
    private $errors = [];
    private $data = [];
    
    public function __construct($data = []) {
        if (!defined('QAC_SYSTEM')) {
            die('Direct access not allowed');
        }
        
        $this->data = $data;
        $this->errors = [];
    }
    
    /**
     * Set data to validate
     */
    public function setData($data) {
        $this->data = $data;
        $this->errors = [];
        return $this;
    }
    
    /**
     * Validate field with rules
     */
    public function validate($field, $rules, $customMessage = null) {
        if (!isset($this->data[$field])) {
            $this->data[$field] = null;
        }
        
        $value = $this->data[$field];
        
        // Parse rules
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }
        
        foreach ($rules as $rule) {
            $this->validateSingleRule($field, $value, $rule, $customMessage);
        }
        
        return $this;
    }
    
    /**
     * Validate multiple fields
     */
    public function validateAll($rules) {
        foreach ($rules as $field => $fieldRules) {
            $customMessage = null;
            
            // Check if custom message is provided
            if (is_array($fieldRules) && isset($fieldRules['message'])) {
                $customMessage = $fieldRules['message'];
                $fieldRules = $fieldRules['rules'];
            }
            
            $this->validate($field, $fieldRules, $customMessage);
        }
        
        return $this;
    }
    
    /**
     * Validate single rule
     */
    private function validateSingleRule($field, $value, $rule, $customMessage = null) {
        // Parse rule and parameters
        $ruleParts = explode(':', $rule);
        $ruleName = $ruleParts[0];
        $parameters = isset($ruleParts[1]) ? explode(',', $ruleParts[1]) : [];
        
        $isValid = true;
        $message = '';
        
        switch ($ruleName) {
            case 'required':
                $isValid = !$this->isEmpty($value);
                $message = $customMessage ?: "กรุณากรอก {$this->getFieldLabel($field)}";
                break;
                
            case 'required_if':
                if (count($parameters) >= 2) {
                    $dependentField = $parameters[0];
                    $dependentValue = $parameters[1];
                    if (isset($this->data[$dependentField]) && $this->data[$dependentField] == $dependentValue) {
                        $isValid = !$this->isEmpty($value);
                        $message = $customMessage ?: "กรุณากรอก {$this->getFieldLabel($field)}";
                    }
                }
                break;
                
            case 'min':
                if (!$this->isEmpty($value) && isset($parameters[0])) {
                    $min = (float) $parameters[0];
                    $isValid = is_numeric($value) && (float) $value >= $min;
                    $message = $customMessage ?: "{$this->getFieldLabel($field)} ต้องมีค่าอย่างน้อย {$min}";
                }
                break;
                
            case 'max':
                if (!$this->isEmpty($value) && isset($parameters[0])) {
                    $max = (float) $parameters[0];
                    $isValid = is_numeric($value) && (float) $value <= $max;
                    $message = $customMessage ?: "{$this->getFieldLabel($field)} ต้องมีค่าไม่เกิน {$max}";
                }
                break;
                
            case 'min_length':
                if (!$this->isEmpty($value) && isset($parameters[0])) {
                    $minLength = (int) $parameters[0];
                    $isValid = strlen($value) >= $minLength;
                    $message = $customMessage ?: "{$this->getFieldLabel($field)} ต้องมีความยาวอย่างน้อย {$minLength} ตัวอักษร";
                }
                break;
                
            case 'max_length':
                if (!$this->isEmpty($value) && isset($parameters[0])) {
                    $maxLength = (int) $parameters[0];
                    $isValid = strlen($value) <= $maxLength;
                    $message = $customMessage ?: "{$this->getFieldLabel($field)} ต้องมีความยาวไม่เกิน {$maxLength} ตัวอักษร";
                }
                break;
                
            case 'email':
                if (!$this->isEmpty($value)) {
                    $isValid = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                    $message = $customMessage ?: "{$this->getFieldLabel($field)} ต้องเป็นรูปแบบอีเมลที่ถูกต้อง";
                }
                break;
                
            case 'numeric':
                if (!$this->isEmpty($value)) {
                    $isValid = is_numeric($value);
                    $message = $customMessage ?: "{$this->getFieldLabel($field)} ต้องเป็นตัวเลข";
                }
                break;
                
            case 'integer':
                if (!$this->isEmpty($value)) {
                    $isValid = filter_var($value, FILTER_VALIDATE_INT) !== false;
                    $message = $customMessage ?: "{$this->getFieldLabel($field)} ต้องเป็นจำนวนเต็ม";
                }
                break;
                
            case 'decimal':
                if (!$this->isEmpty($value)) {
                    $pattern = '/^\d+(\.\d{1,3})?$/';
                    $isValid = preg_match($pattern, $value);
                    $message = $customMessage ?: "{$this->getFieldLabel($field)} ต้องเป็นทศนิยมที่ถูกต้อง";
                }
                break;
                
            case 'date':
                if (!$this->isEmpty($value)) {
                    $isValid = $this->validateDate($value);
                    $message = $customMessage ?: "{$this->getFieldLabel($field)} ต้องเป็นวันที่ที่ถูกต้อง";
                }
                break;
                
            case 'time':
                if (!$this->isEmpty($value)) {
                    $isValid = $this->validateTime($value);
                    $message = $customMessage ?: "{$this->getFieldLabel($field)} ต้องเป็นเวลาที่ถูกต้อง";
                }
                break;
                
            case 'date_before':
                if (!$this->isEmpty($value) && isset($parameters[0])) {
                    $beforeDate = $parameters[0];
                    $isValid = strtotime($value) < strtotime($beforeDate);
                    $message = $customMessage ?: "{$this->getFieldLabel($field)} ต้องเป็นวันที่ก่อน {$beforeDate}";
                }
                break;
                
            case 'date_after':
                if (!$this->isEmpty($value) && isset($parameters[0])) {
                    $afterDate = $parameters[0];
                    $isValid = strtotime($value) > strtotime($afterDate);
                    $message = $customMessage ?: "{$this->getFieldLabel($field)} ต้องเป็นวันที่หลัง {$afterDate}";
                }
                break;
                
            case 'in':
                if (!$this->isEmpty($value)) {
                    $isValid = in_array($value, $parameters);
                    $allowedValues = implode(', ', $parameters);
                    $message = $customMessage ?: "{$this->getFieldLabel($field)} ต้องเป็นค่าใดค่าหนึ่งใน: {$allowedValues}";
                }
                break;
                
            case 'not_in':
                if (!$this->isEmpty($value)) {
                    $isValid = !in_array($value, $parameters);
                    $forbiddenValues = implode(', ', $parameters);
                    $message = $customMessage ?: "{$this->getFieldLabel($field)} ไม่สามารถเป็นค่า: {$forbiddenValues}";
                }
                break;
                
            case 'unique':
                if (!$this->isEmpty($value) && isset($parameters[0])) {
                    $table = $parameters[0];
                    $column = $parameters[1] ?? $field;
                    $excludeId = $parameters[2] ?? null;
                    
                    $isValid = $this->validateUnique($table, $column, $value, $excludeId);
                    $message = $customMessage ?: "{$this->getFieldLabel($field)} นี้มีอยู่ในระบบแล้ว";
                }
                break;
                
            case 'regex':
                if (!$this->isEmpty($value) && isset($parameters[0])) {
                    $pattern = $parameters[0];
                    $isValid = preg_match($pattern, $value);
                    $message = $customMessage ?: "{$this->getFieldLabel($field)} รูปแบบไม่ถูกต้อง";
                }
                break;
                
            case 'confirmed':
                $confirmField = $field . '_confirmation';
                if (isset($this->data[$confirmField])) {
                    $isValid = $value === $this->data[$confirmField];
                    $message = $customMessage ?: "{$this->getFieldLabel($field)} ไม่ตรงกัน";
                }
                break;
                
            case 'file':
                if (isset($_FILES[$field])) {
                    $isValid = $_FILES[$field]['error'] === UPLOAD_ERR_OK;
                    $message = $customMessage ?: "เกิดข้อผิดพลาดในการอัปโหลดไฟล์";
                }
                break;
                
            case 'file_size':
                if (isset($_FILES[$field]) && isset($parameters[0])) {
                    $maxSize = $this->parseFileSize($parameters[0]);
                    $isValid = $_FILES[$field]['size'] <= $maxSize;
                    $message = $customMessage ?: "ขนาดไฟล์ต้องไม่เกิน {$parameters[0]}";
                }
                break;
                
            case 'file_extension':
                if (isset($_FILES[$field])) {
                    $allowedExtensions = $parameters;
                    $fileExtension = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                    $isValid = in_array($fileExtension, array_map('strtolower', $allowedExtensions));
                    $message = $customMessage ?: "ไฟล์ต้องเป็นนามสกุล: " . implode(', ', $allowedExtensions);
                }
                break;
        }
        
        if (!$isValid && $message) {
            $this->addError($field, $message);
        }
    }
    
    /**
     * Add custom validation rule
     */
    public function addRule($field, $callback, $message = null) {
        $value = $this->data[$field] ?? null;
        
        if (!call_user_func($callback, $value, $this->data)) {
            $this->addError($field, $message ?: "ข้อมูล {$this->getFieldLabel($field)} ไม่ถูกต้อง");
        }
        
        return $this;
    }
    
    /**
     * Check if validation passed
     */
    public function isValid() {
        return empty($this->errors);
    }
    
    /**
     * Get all errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get errors for specific field
     */
    public function getFieldErrors($field) {
        return $this->errors[$field] ?? [];
    }
    
    /**
     * Get first error message
     */
    public function getFirstError() {
        foreach ($this->errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }
        return null;
    }
    
    /**
     * Add error message
     */
    public function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * Clear all errors
     */
    public function clearErrors() {
        $this->errors = [];
        return $this;
    }
    
    /**
     * Get sanitized data
     */
    public function getSanitizedData() {
        $sanitized = [];
        
        foreach ($this->data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = trim($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Check if value is empty
     */
    private function isEmpty($value) {
        return $value === null || $value === '' || $value === [];
    }
    
    /**
     * Get field label for error messages
     */
    private function getFieldLabel($field) {
        $labels = [
            'username' => 'ชื่อผู้ใช้',
            'password' => 'รหัสผ่าน',
            'email' => 'อีเมล',
            'first_name' => 'ชื่อ',
            'last_name' => 'นามสกุล',
            'phone' => 'เบอร์โทรศัพท์',
            'department' => 'แผนก',
            'position' => 'ตำแหน่ง',
            'inspection_date' => 'วันที่ตรวจสอบ',
            'inspection_time' => 'เวลาตรวจสอบ',
            'inspector' => 'ผู้ตรวจสอบ',
            'temperature' => 'อุณหภูมิ',
            'humidity' => 'ความชื้น',
            'pressure' => 'ความดัน'
        ];
        
        return $labels[$field] ?? $field;
    }
    
    /**
     * Validate date format
     */
    private function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    /**
     * Validate time format
     */
    private function validateTime($time, $format = 'H:i') {
        $t = DateTime::createFromFormat($format, $time);
        return $t && $t->format($format) === $time;
    }
    
    /**
     * Validate unique value in database
     */
    private function validateUnique($table, $column, $value, $excludeId = null) {
        try {
            $db = new Database();
            
            $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = :value";
            $params = ['value' => $value];
            
            if ($excludeId) {
                $sql .= " AND id != :exclude_id";
                $params['exclude_id'] = $excludeId;
            }
            
            $count = $db->fetchColumn($sql, $params);
            return $count == 0;
            
        } catch (Exception $e) {
            error_log('Unique validation error: ' . $e->getMessage());
            return true; // Assume valid if database error
        }
    }
    
    /**
     * Parse file size string (e.g., "10MB" -> bytes)
     */
    private function parseFileSize($size) {
        $units = ['B' => 1, 'KB' => 1024, 'MB' => 1048576, 'GB' => 1073741824];
        
        $size = strtoupper($size);
        $unit = preg_replace('/[^A-Z]/', '', $size);
        $number = (float) preg_replace('/[^0-9.]/', '', $size);
        
        return $number * ($units[$unit] ?? 1);
    }
    
    /**
     * Validate inspection form data
     */
    public function validateInspectionForm($formData) {
        $rules = [
            'inspection_date' => 'required|date',
            'inspection_time' => 'required|time',
            'inspector' => 'required|min_length:2',
            'form_id' => 'required|integer'
        ];
        
        // Add parameter-specific validation
        foreach ($formData as $key => $value) {
            if (strpos($key, 'param_') === 0) {
                // Extract parameter info and add appropriate validation
                $paramType = $formData[$key . '_type'] ?? 'text';
                
                switch ($paramType) {
                    case 'numeric':
                        $rules[$key] = 'numeric';
                        break;
                    case 'boolean':
                        $rules[$key] = 'in:0,1,true,false';
                        break;
                    case 'date':
                        $rules[$key] = 'date';
                        break;
                    case 'time':
                        $rules[$key] = 'time';
                        break;
                    default:
                        $rules[$key] = 'max_length:255';
                }
            }
        }
        
        return $this->validateAll($rules);
    }
    
    /**
     * Validate user registration data
     */
    public function validateUserRegistration($userData) {
        $rules = [
            'username' => 'required|min_length:3|max_length:50|unique:users,username',
            'password' => 'required|min_length:6',
            'password_confirmation' => 'required|confirmed',
            'first_name' => 'required|min_length:2|max_length:100',
            'last_name' => 'required|min_length:2|max_length:100',
            'email' => 'email|unique:users,email',
            'role' => 'required|in:admin,supervisor,inspector,viewer'
        ];
        
        return $this->validateAll($rules);
    }
    
    /**
     * Get validation summary
     */
    public function getSummary() {
        return [
            'is_valid' => $this->isValid(),
            'error_count' => count($this->errors),
            'field_count' => count($this->data),
            'first_error' => $this->getFirstError(),
            'errors' => $this->errors
        ];
    }
}

?>