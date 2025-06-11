<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include settings functions
require_once __DIR__ . '/settings_functions.php';

// Initialize global settings variable
global $APP_SETTINGS; // Make it available in the global scope
$APP_SETTINGS = [];   // Initialize as an array

// Ensure database connection is available for loading settings
// This assumes config/database.php defines $conn and connects.
if (file_exists(__DIR__ . '/../config/database.php')) {
    require_once __DIR__ . '/../config/database.php';
} else {
    // This is a critical error if database.php is missing
    error_log("CRITICAL ERROR: config/database.php not found. Cannot establish database connection or load settings.");
    // You might want to die here or set a flag that the application is in an error state.
    // For now, $conn will remain unset, and the subsequent check will handle it.
}

// Load application settings from the database if $conn is available and valid
if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
    $APP_SETTINGS = load_db_app_settings($conn);

    if (empty($APP_SETTINGS)) {
        error_log("Warning: Application settings could not be loaded from the database or are empty. Check 'app_settings' table and database connection.");
        // Consider setting some critical default settings here if loading fails,
        // or ensure the application handles missing settings gracefully.
        // e.g., $APP_SETTINGS['company_name'] = 'Default Company';
    }
} else {
    error_log("CRITICAL ERROR: Database connection (\$conn) is not available or not valid in includes/functions.php. Cannot load application settings.");
    // Handle critical error: application might not function correctly without DB-based settings.
    // If $conn was expected from database.php but that file is missing, this error will also trigger.
    // Consider die("Critical application error: Could not load settings.") or showing an error page.
}


// ---- Existing functions below ----

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function redirect($location) {
    header("Location: $location");
    exit;
}

function json_response($data, $status = 200) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function get_user_by_id($user_id) {
    global $conn; // $conn should be available now due to the include above
    if (!$conn || !($conn instanceof mysqli) || !$conn->ping()) {
        error_log("Error in get_user_by_id: Database connection not available or invalid.");
        return null;
    }
    $sql = "SELECT id, username, email FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log("Prepare statement failed in get_user_by_id: " . mysqli_error($conn));
        return null;
    }
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $user;
}

?>
