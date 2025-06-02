<?php
// ===========================================
// templates/footer.php - Modern Footer Template
// ===========================================

// Prevent direct access
if (!defined('QAC_SYSTEM')) {
    die('Direct access not allowed');
}
?>

</div> <!-- Close main-layout -->

<!-- Modern Footer -->
<footer class="main-footer">
    <div class="container-fluid">
        <div class="footer-content">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="footer-info">
                        <div class="d-flex align-items-center">
                            <div class="footer-logo">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div>
                                <strong><?php echo SITE_NAME; ?></strong> 
                                <span class="version">v<?php echo SITE_VERSION; ?></span>
                                <div class="footer-desc"><?php echo SITE_DESCRIPTION; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="footer-links">
                        <span class="copyright">© <?php echo date('Y'); ?> สงวนลิขสิทธิ์</span>
                        <div class="footer-menu">
                            <a href="#" class="footer-link">ช่วยเหลือ</a>
                            <a href="#" class="footer-link">เกี่ยวกับ</a>
                            <a href="#" class="footer-link">ติดต่อ</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- System Info (for debugging - only in development) -->
        <?php if (DEBUG_MODE): ?>
        <div class="debug-info">
            <div class="debug-content">
                <i class="fas fa-bug"></i>
                <small>
                    Page: <?php echo number_format((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2); ?>ms
                    <?php if (class_exists('Database')): ?>
                        <?php 
                        $db = new Database();
                        $queryCount = $db->getQueryCount();
                        if ($queryCount > 0): ?>
                            | DB: <?php echo $queryCount; ?> queries
                        <?php endif; ?>
                    <?php endif; ?>
                    | Memory: <?php echo number_format(memory_get_peak_usage(true) / 1024 / 1024, 2); ?>MB
                    | <?php echo date('H:i:s'); ?>
                </small>
            </div>
        </div>
        <?php endif; ?>
    </div>
</footer>

<!-- Back to Top Button -->
<button type="button" class="btn-back-to-top" id="backToTop" title="กลับขึ้นด้านบน">
    <i class="fas fa-chevron-up"></i>
</button>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-content">
        <div class="loading-spinner">
            <div class="spinner"></div>
        </div>
        <div class="loading-text">กำลังประมวลผล...</div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">
                    <i class="fas fa-question-circle text-warning me-2"></i>
                    ยืนยันการดำเนินการ
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="modal-message">คุณแน่ใจหรือไม่ที่ต้องการดำเนินการนี้?</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary" id="confirmAction">ยืนยัน</button>
            </div>
        </div>
    </div>
</div>

<!-- Core JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Page-specific JS -->
<?php 
$current_page = basename($_SERVER['PHP_SELF'], '.php');
if (file_exists(ASSETS_PATH . "/js/pages/{$current_page}.js")): ?>
<script src="<?php echo ASSETS_URL; ?>/js/pages/<?php echo $current_page; ?>.js"></script>
<?php endif; ?>

<!-- Global JavaScript -->
<script>
// Global configuration
window.QAC = {
    baseUrl: '<?php echo SITE_URL; ?>',
    assetsUrl: '<?php echo ASSETS_URL; ?>',
    csrfToken: '<?php echo getCSRFToken(); ?>',
    user: {
        id: <?php echo getUserId() ?: 'null'; ?>,
        username: '<?php echo getUsername(); ?>',
        fullName: '<?php echo getUserFullName(); ?>',
        role: '<?php echo getUserRole(); ?>'
    },
    settings: {
        dateFormat: '<?php echo DATE_FORMAT; ?>',
        timeFormat: '<?php echo TIME_FORMAT; ?>',
        timezone: '<?php echo DEFAULT_TIMEZONE; ?>'
    }
};

// Global utilities
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Back to top button
    const backToTopBtn = document.getElementById('backToTop');
    if (backToTopBtn) {
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.add('show');
            } else {
                backToTopBtn.classList.remove('show');
            }
        });
        
        backToTopBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    
    // Auto-hide alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        if (alert.classList.contains('alert-success')) {
            setTimeout(function() {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000); // 5 seconds
        }
    });
    
    // Form validation enhancement
    const forms = document.querySelectorAll('form[data-validate="true"]');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
    
    // Confirmation modal handler
    const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    
    confirmButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const message = this.getAttribute('data-confirm') || 'คุณแน่ใจหรือไม่ที่ต้องการดำเนินการนี้?';
            const action = this.getAttribute('href') || this.getAttribute('data-action');
            
            document.querySelector('#confirmModal .modal-message').textContent = message;
            
            document.getElementById('confirmAction').onclick = function() {
                if (action) {
                    if (action.startsWith('javascript:')) {
                        eval(action.substring(11));
                    } else {
                        window.location.href = action;
                    }
                }
                confirmModal.hide();
            };
            
            confirmModal.show();
        });
    });
    
    // Session timeout warning
    let sessionWarningShown = false;
    const sessionTimeout = <?php echo SESSION_TIMEOUT; ?> * 1000;
    const warningTime = sessionTimeout - (5 * 60 * 1000); // 5 minutes before timeout
    
    setTimeout(function() {
        if (!sessionWarningShown) {
            sessionWarningShown = true;
            if (confirm('เซสชันของคุณจะหมดอายุในอีก 5 นาที คุณต้องการต่ออายุเซสชันหรือไม่?')) {
                fetch(window.QAC.baseUrl + '/api/refresh_session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.QAC.csrfToken
                    }
                }).then(function(response) {
                    if (response.ok) {
                        sessionWarningShown = false;
                        showNotification('เซสชันได้รับการต่ออายุแล้ว', 'success');
                    }
                });
            }
        }
    }, warningTime);
});

// Loading overlay functions
function showLoading(message = 'กำลังประมวลผล...') {
    const overlay = document.getElementById('loadingOverlay');
    const text = overlay.querySelector('.loading-text');
    if (text) text.textContent = message;
    overlay.style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
}

// Notification function
function showNotification(message, type = 'info', duration = 5000) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show notification-toast`;
    
    const icons = {
        success: 'check-circle',
        danger: 'exclamation-triangle',
        warning: 'exclamation-circle',
        info: 'info-circle'
    };
    
    alertDiv.innerHTML = `
        <i class="fas fa-${icons[type] || 'info-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    setTimeout(function() {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, duration);
}

// Format functions
function formatDate(date, format = '<?php echo DATE_FORMAT; ?>') {
    if (!date) return '';
    
    const d = new Date(date);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    
    return format.replace('d', day).replace('m', month).replace('Y', year);
}

function formatTime(time, format = '<?php echo TIME_FORMAT; ?>') {
    if (!time) return '';
    
    const t = new Date('1970-01-01 ' + time);
    const hours = String(t.getHours()).padStart(2, '0');
    const minutes = String(t.getMinutes()).padStart(2, '0');
    
    return format.replace('H', hours).replace('i', minutes);
}
</script>

<style>
/* Modern Footer Styles */
.main-footer {
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border-top: 1px solid var(--border-color);
    margin-top: 50px;
    padding: 30px 0 20px;
    box-shadow: 0 -2px 20px rgba(0,0,0,0.05);

    transition: all 0.3s ease;
}

.main-footer.sidebar-collapsed {
    margin-left: var(--sidebar-collapsed-width);
}

.footer-content {
    padding: 0;
}

.footer-logo {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    margin-right: 15px;
}

.footer-info strong {
    font-size: 1.1rem;
    color: var(--dark);
    font-weight: 600;
}

.version {
    color: var(--secondary);
    font-size: 0.875rem;
    margin-left: 8px;
    background: rgba(59, 130, 246, 0.1);
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: 500;
}

.footer-desc {
    color: var(--secondary);
    font-size: 0.875rem;
    margin-top: 2px;
}

.footer-links {
    text-align: right;
}

.copyright {
    color: var(--secondary);
    font-size: 0.875rem;
    display: block;
    margin-bottom: 8px;
}

.footer-menu {
    display: flex;
    gap: 20px;
    justify-content: flex-end;
}

.footer-link {
    color: var(--secondary);
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.3s ease;
    position: relative;
}

.footer-link:hover {
    color: var(--primary);
    transform: translateY(-1px);
}

.footer-link::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--primary);
    transition: width 0.3s ease;
}

.footer-link:hover::after {
    width: 100%;
}

.debug-info {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid var(--border-color);
    text-align: center;
}

.debug-content {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(239, 68, 68, 0.1);
    padding: 8px 16px;
    border-radius: 20px;
    color: var(--danger);
    font-size: 0.8rem;
}

/* Back to top button */
.btn-back-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1020;
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    border: none;
    border-radius: 50%;
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
    transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px);
}

.btn-back-to-top.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.btn-back-to-top:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 35px rgba(59, 130, 246, 0.4);
    background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
}

/* Loading overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-content {
    text-align: center;
}

.loading-spinner {
    margin-bottom: 20px;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 4px solid #f3f4f6;
    border-top: 4px solid var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.loading-text {
    font-weight: 500;
    color: var(--secondary);
    font-size: 1.1rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Notification toast */
.notification-toast {
    position: fixed;
    top: 100px;
    right: 30px;
    z-index: 9999;
    min-width: 350px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    border: none;
    border-radius: 12px;
    backdrop-filter: blur(20px);
}

/* Modal enhancements */
.modal-content {
    border: none;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    backdrop-filter: blur(20px);
}

.modal-header {
    border-radius: 16px 16px 0 0;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

.modal-footer {
    border-radius: 0 0 16px 16px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}

/* Mobile responsive adjustments */
@media (max-width: 991.98px) {
    .main-footer {
        margin-left: 0;
        padding: 25px 0 15px;
    }
    
    .footer-menu {
        justify-content: center;
        margin-top: 15px;
    }
    
    .footer-links {
        text-align: center;
    }
    
    .copyright {
        text-align: center;
        margin-bottom: 15px;
    }
    
    .btn-back-to-top {
        bottom: 20px;
        right: 20px;
        width: 45px;
        height: 45px;
    }
    
    .notification-toast {
        right: 15px;
        left: 15px;
        min-width: auto;
    }
}

@media (max-width: 576px) {
    .footer-info {
        text-align: center;
        margin-bottom: 20px;
    }
    
    .footer-menu {
        flex-direction: column;
        gap: 10px;
    }
}

/* Print styles */
@media print {
    .main-header,
    .sidebar,
    .main-footer,
    .btn-back-to-top,
    .loading-overlay,
    .notification-toast {
        display: none !important;
    }
    
    .content-wrapper {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    
    body {
        background: white !important;
    }
}

/* Accessibility improvements */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* Dark mode support (future enhancement) */
@media (prefers-color-scheme: dark) {
    /* Will be implemented later if needed */
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .footer-link::after {
        height: 3px;
    }
    
    .btn-back-to-top {
        border: 2px solid white;
    }
}
</style>

</body>
</html>