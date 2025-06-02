<?php
// ===========================================
// modules/forms/qa/fm_qa_23/history.php - FM-QA-23 History List
// ===========================================

define('QAC_SYSTEM', true);
require_once '../../../../config/config.php';
require_once '../../../../config/database.php';
require_once '../../../../config/session_config.php';

// Check authentication
requireLogin();

// Initialize variables
$inspections = [];
$error_message = '';
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;

try {
    // Build WHERE clause
    $where = ["f.form_code = 'FM-QA-23'"];
    $params = [];
    
    if (!empty($search)) {
        $where[] = "(m.inspection_no LIKE :search OR m.inspector LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }
    
    if (!empty($status_filter)) {
        $where[] = "m.status = :status";
        $params['status'] = $status_filter;
    }
    
    if (!empty($date_from)) {
        $where[] = "m.inspection_date >= :date_from";
        $params['date_from'] = $date_from;
    }
    
    if (!empty($date_to)) {
        $where[] = "m.inspection_date <= :date_to";
        $params['date_to'] = $date_to;
    }
    
    $where_clause = implode(' AND ', $where);
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total 
                  FROM qac_inspection_master m
                  LEFT JOIN qac_forms f ON m.form_id = f.form_id
                  WHERE {$where_clause}";
    
    $total_result = fetchOne($count_sql, $params);
    $total_records = $total_result['total'];
    $total_pages = ceil($total_records / $per_page);
    
    // Get inspections with pagination
    $offset = ($page - 1) * $per_page;
    
    $sql = "SELECT m.*, f.form_name, t.type_name,
                   (SELECT COUNT(*) FROM qac_bottle_inspections bi WHERE bi.inspection_id = m.inspection_id) as bottle_count
            FROM qac_inspection_master m
            LEFT JOIN qac_forms f ON m.form_id = f.form_id
            LEFT JOIN qac_inspection_types t ON m.type_id = t.type_id
            WHERE {$where_clause}
            ORDER BY m.inspection_date DESC, m.inspection_time DESC
            LIMIT {$offset}, {$per_page}";
    
    $inspections = fetchAll($sql, $params);
    
} catch (Exception $e) {
    $error_message = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    $classes = [
        'completed' => 'bg-success',
        'draft' => 'bg-secondary',
        'approved' => 'bg-primary',
        'rejected' => 'bg-danger',
        'cancelled' => 'bg-warning'
    ];
    return $classes[$status] ?? 'bg-secondary';
}

// Function to get status text
function getStatusText($status) {
    $texts = [
        'completed' => 'เสร็จสิ้น',
        'draft' => 'ร่าง',
        'approved' => 'อนุมัติแล้ว',
        'rejected' => 'ไม่อนุมัติ',
        'cancelled' => 'ยกเลิก'
    ];
    return $texts[$status] ?? $status;
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
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin-bottom: 0;
        }
        
        .navbar-brand, .nav-link {
            color: white !important;
        }
        
        .page-header {
            background: white;
            padding: 20px 0;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin: 0;
        }
        
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .data-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .table-responsive {
            border-radius: 8px;
        }
        
        .table th {
            background: #f8f9fa;
            border: none;
            font-weight: 600;
            color: #555;
            padding: 15px 10px;
        }
        
        .table td {
            border: none;
            border-bottom: 1px solid #f1f1f1;
            padding: 15px 10px;
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .btn-action {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            margin: 0 2px;
            text-decoration: none;
        }
        
        .btn-view {
            background: #17a2b8;
            color: white;
        }
        
        .btn-edit {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-new {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .pagination .page-link {
            border: none;
            color: #667eea;
        }
        
        .pagination .page-item.active .page-link {
            background: #667eea;
            border-color: #667eea;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
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

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="page-title">
                        <i class="fas fa-flask me-2"></i>FM-QA-23 - การตรวจสอบคุณภาพขวด
                    </h1>
                </div>
                <div class="col-md-6 text-end">
                    <a href="form.php" class="btn-new">
                        <i class="fas fa-plus me-2"></i>สร้างการตรวจสอบใหม่
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Filter Section -->
        <div class="filter-card">
            <form method="GET" action="">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">ค้นหา</label>
                        <input type="text" class="form-control" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="เลขที่ใบตรวจ, ผู้ตรวจ...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">สถานะ</label>
                        <select class="form-control" name="status">
                            <option value="">ทั้งหมด</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>เสร็จสิ้น</option>
                            <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>ร่าง</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>อนุมัติแล้ว</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>ไม่อนุมัติ</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">วันที่เริ่มต้น</label>
                        <input type="date" class="form-control" name="date_from" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">วันที่สิ้นสุด</label>
                        <input type="date" class="form-control" name="date_to" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>ค้นหา
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-refresh me-1"></i>รีเซ็ต
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results -->
        <div class="data-card">
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger m-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php elseif (empty($inspections)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h5>ไม่พบข้อมูลการตรวจสอบ</h5>
                    <p class="text-muted">ยังไม่มีการตรวจสอบที่ตรงกับเงื่อนไขที่ค้นหา</p>
                    <a href="form.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>สร้างการตรวจสอบแรก
                    </a>
                </div>
            <?php else: ?>
                <!-- Summary -->
                <div class="p-3 bg-light border-bottom">
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted">
                                แสดงผลลัพธ์ <?php echo (($page - 1) * $per_page) + 1; ?> - 
                                <?php echo min($page * $per_page, $total_records); ?> 
                                จากทั้งหมด <?php echo number_format($total_records); ?> รายการ
                            </small>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>" 
                               class="btn btn-outline-success btn-sm">
                                <i class="fas fa-file-excel me-1"></i>ส่งออก Excel
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 150px;">เลขที่ใบตรวจ</th>
                                <th style="width: 120px;">วันที่ตรวจ</th>
                                <th style="width: 80px;">เวลา</th>
                                <th style="width: 120px;">ผู้ตรวจสอบ</th>
                                <th style="width: 80px;">จำนวนขวด</th>
                                <th style="width: 100px;">สถานะ</th>
                                <th style="width: 120px;">วันที่บันทึก</th>
                                <th style="width: 150px;" class="text-center">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inspections as $inspection): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($inspection['inspection_no']); ?></strong>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($inspection['inspection_date'])); ?>
                                </td>
                                <td>
                                    <?php echo date('H:i', strtotime($inspection['inspection_time'])); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($inspection['inspector']); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?php echo $inspection['bottle_count']; ?></span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo getStatusBadgeClass($inspection['status']); ?>">
                                        <?php echo getStatusText($inspection['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($inspection['created_at'])); ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <a href="view.php?id=<?php echo $inspection['inspection_id']; ?>" 
                                       class="btn-action btn-view" title="ดูรายละเอียด">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if (canEdit($inspection['created_by'])): ?>
                                        <a href="edit.php?id=<?php echo $inspection['inspection_id']; ?>" 
                                           class="btn-action btn-edit" title="แก้ไข">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (isAdmin()): ?>
                                        <button onclick="deleteInspection(<?php echo $inspection['inspection_id']; ?>)" 
                                                class="btn-action btn-delete" title="ลบ">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="p-3 border-top">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <!-- Previous -->
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <!-- Page Numbers -->
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <!-- Next -->
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteInspection(id) {
            if (confirm('คุณแน่ใจหรือไม่ที่จะลบการตรวจสอบนี้?\n\nการลบจะไม่สามารถกู้คืนได้')) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete.php';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'inspection_id';
                input.value = id;
                
                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = 'csrf_token';
                csrf.value = '<?php echo getCSRFToken(); ?>';
                
                form.appendChild(input);
                form.appendChild(csrf);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Auto-submit search form on filter change
        document.querySelectorAll('select[name="status"]').forEach(function(select) {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>