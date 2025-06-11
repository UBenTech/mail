<?php
// tools/run_scheduler_simulation.php
// This script simulates a scheduler picking up due campaigns and marking them as sent.

// Define ROOT_PATH if not already defined, to ensure includes work correctly.
// This script is intended to be run from the project root directory like: php tools/run_scheduler_simulation.php
if (!defined('ROOT_PATH')) {
    // If run from project root as `php tools/run_scheduler_simulation.php`, __DIR__ is `project_root/tools`
    // So ROOT_PATH should be dirname(__DIR__)
    define('ROOT_PATH', dirname(__DIR__) . '/');
}

// Ensure config/database.php is loaded. This file should define $conn.
if (file_exists(ROOT_PATH . 'config/database.php')) {
    require_once ROOT_PATH . 'config/database.php';
} else {
    echo "Error: Database configuration file not found at " . ROOT_PATH . "config/database.php
";
    exit(1);
}


if (!isset($conn) || !$conn instanceof mysqli || !$conn->ping()) {
    echo "Database connection error. Please check configuration (config/database.php).
";
    // Attempt to display mysqli connection error if $conn object exists but ping failed
    if (isset($conn) && $conn instanceof mysqli) {
        echo "MySQLi Connection Error: " . $conn->connect_error . "
";
    }
    exit(1);
}

echo "Scheduler Simulation Started: " . date("Y-m-d H:i:s") . "
";
$processed_count = 0;
$error_count = 0;

// 1. Find due scheduled campaigns
$sql_select = "SELECT id FROM campaigns WHERE status = 'scheduled' AND scheduled_at <= NOW()";
$result = $conn->query($sql_select);

if ($result === false) {
    echo "Error querying for due campaigns: " . $conn->error . "
";
    $conn->close();
    exit(1);
}

if ($result->num_rows === 0) {
    echo "No scheduled campaigns are due to be sent at this time.
";
    $conn->close();
    exit(0);
}

echo "Found " . $result->num_rows . " campaign(s) due for sending.
";

// 2. Prepare the update statement
// Also update scheduled_at to NULL as it's no longer scheduled to be sent in the future
$sql_update = "UPDATE campaigns SET status = 'sent', sent_at = NOW(), scheduled_at = NULL WHERE id = ?";
$stmt_update = $conn->prepare($sql_update);

if ($stmt_update === false) {
    echo "Error preparing update statement: " . $conn->error . "
";
    $conn->close();
    exit(1);
}

// 3. Loop and update
while ($campaign = $result->fetch_assoc()) {
    $campaign_id = $campaign['id'];

    $stmt_update->bind_param("i", $campaign_id);
    if ($stmt_update->execute()) {
        echo "Campaign ID " . $campaign_id . " successfully updated to 'sent'.
";
        $processed_count++;
    } else {
        echo "Error updating campaign ID " . $campaign_id . ": " . $stmt_update->error . "
";
        $error_count++;
    }
}

$stmt_update->close();
$result->free(); // Free the result set
$conn->close();

echo "
Scheduler Simulation Finished: " . date("Y-m-d H:i:s") . "
";
echo "Successfully processed: " . $processed_count . " campaign(s).
";
echo "Failed to process: " . $error_count . " campaign(s).
";

exit(0);
?>
