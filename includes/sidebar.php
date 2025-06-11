<?php
// Determine the current page for active link highlighting
// This assumes $page is set by index.php
global $page; // Make $page from index.php available here
$current_page = isset($page) ? $page : 'dashboard';
?>
<!-- Sidebar -->
<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block sidebar">
    <div class="sidebar-header">
        <img src="public/images/logo.png" alt="CtpaInstitute Logo" class="logo">
        <h3>CtpaInstitute.org</h3>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>" href="index.php?page=dashboard">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'compose') ? 'active' : ''; ?>" href="index.php?page=compose">
                <i class="fas fa-envelope"></i> Compose
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'templates') ? 'active' : ''; ?>" href="index.php?page=templates">
                <i class="fas fa-file-alt"></i> Templates
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'contacts') ? 'active' : ''; ?>" href="index.php?page=contacts">
                <i class="fas fa-address-book"></i> Contacts
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'analytics') ? 'active' : ''; ?>" href="index.php?page=analytics">
                <i class="fas fa-chart-bar"></i> Analytics
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'settings') ? 'active' : ''; ?>" href="index.php?page=settings">
                <i class="fas fa-cog"></i> Settings
            </a>
        </li>
    </ul>
</nav>
