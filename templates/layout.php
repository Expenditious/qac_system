<?php
// ===========================================
// templates/layout.php - Main Layout Template
// ===========================================

// Prevent direct access
if (!defined('QAC_SYSTEM')) {
    die('Direct access not allowed');
}

// Require authentication for all pages using this layout
requireLogin();

// Default values
$page_title = $page_title ?? 'QAC System';
$breadcrumbs = $breadcrumbs ?? [];
$show_sidebar = $show_sidebar ?? true;
$page_actions = $page_actions ?? '';

// Include header
include_once 'header.php';
include_once '../../templates/header.php';
include_once '../../templates/sidebar.php';
// Include sidebar if needed
if ($show_sidebar): 
    include_once 'sidebar.php';
endif;

// Start main content area
?>

<!-- Main Content -->
<main class="main-content-area">
    <?php
    // Include the actual page content
    if (isset($content_file) && file_exists($content_file)) {
        include $content_file;
    } else {
        // Default content or error
        echo '<div class="container-fluid">';
        echo '<div class="alert alert-warning">เนื้อหาไม่พบ</div>';
        echo '</div>';
    }
    ?>
</main>

<?php
// Include footer
include_once 'footer.php';
?>

<style>
.main-content-area {
    padding: 20px;
    min-height: calc(100vh - var(--header-height) - 100px);
}

.sidebar-enabled .main-content-area {
    margin-left: var(--sidebar-width);
}

@media (max-width: 991.98px) {
    .main-content-area {
        margin-left: 0;
        padding: 15px;
    }
}
</style>

<?php if ($show_sidebar): ?>
<script>document.body.classList.add('sidebar-enabled');</script>
<?php endif; ?>