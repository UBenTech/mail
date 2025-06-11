<?php
// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define ROOT_PATH if not already defined.
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__ . '/');
}

// Ensure database connection is established
// This makes $conn available globally for subsequent includes.
require_once ROOT_PATH . 'config/database.php';

// Basic Routing
// Determine the current page, default to 'dashboard'
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Whitelist of allowed pages (can be expanded later)
// For now, all unknown pages will default to dashboard or a placeholder
$allowed_pages = ['dashboard', 'compose', 'templates', 'contacts', 'analytics', 'settings'];

// Include the main content based on the page
// For pages other than dashboard, we'll include a placeholder for now if they don't exist.
switch ($page) {
    case 'dashboard':
        include ROOT_PATH . 'dashboard.php';
        break;
    // Add cases for other pages as they are developed
    // For now, other valid pages can show a placeholder or also default to dashboard
    case 'compose':
    case 'templates':
    case 'contacts':
    case 'analytics':
    case 'settings':
        // Example: include a placeholder or the actual page if it exists
        $page_file = ROOT_PATH . $page . '.php';
        if (file_exists($page_file)) {
            include $page_file; // THIS IS THE KEY CHANGE
        } else {
            // This part can remain as is, for handling if a page file is accidentally deleted
            echo "<p>Page '{$page}' not found. Displaying dashboard.</p>";
            include ROOT_PATH . 'dashboard.php';
        }
        break;
    default:
        // Handle unknown pages - show 404 or redirect to dashboard
        // For now, just show a message and dashboard
        echo "<p>Unknown page requested. Displaying dashboard.</p>";
        include ROOT_PATH . 'dashboard.php';
        break;
}
?>
