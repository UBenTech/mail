<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define root path if not defined
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/includes/auth.php';

if (logout_user()) {
    redirect('../public/index.php');
} else {
    echo "Logout failed";
}
?>
