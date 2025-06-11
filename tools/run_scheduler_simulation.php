<?php
// tools/run_scheduler_simulation.php
// This script simulates a scheduler picking up due campaigns and marking them as sent.

// Define ROOT_PATH if not already defined, to ensure includes work correctly.
if (!defined('ROOT_PATH')) {
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

// Prepare main campaign update statement (status to 'sent', set sent_at, nullify scheduled_at)
$stmt_update_campaign = $conn->prepare("UPDATE campaigns SET status = 'sent', sent_at = NOW(), scheduled_at = NULL WHERE id = ?");
if ($stmt_update_campaign === false) {
    echo "Error preparing main campaign update statement: " . $conn->error . "
";
    $result->free();
    $conn->close();
    exit(1);
}

// Loop through each due campaign and process it within a transaction
while ($campaign_row = $result->fetch_assoc()) {
    $campaign_id = $campaign_row['id'];
    $campaign_successfully_processed_flag = false;

    $conn->begin_transaction();

    try {
        // 1. Update campaign to 'sent'
        $stmt_update_campaign->bind_param("i", $campaign_id);
        if (!$stmt_update_campaign->execute()) {
            throw new Exception("Failed to update campaign status/timestamps: " . $stmt_update_campaign->error);
        }
        if ($stmt_update_campaign->affected_rows === 0) {
            echo "Campaign ID " . $campaign_id . ": No rows affected by status update (already processed or ID issue?). Skipping further updates.
";
            $conn->commit(); // Commit as no error, but nothing to do further for this campaign.
            continue;
        }
        echo "Campaign ID " . $campaign_id . ": Status updated to 'sent', sent_at to NOW(), scheduled_at to NULL.
";

        // 2. Fetch total_recipients for this campaign (already set when recipients were added)
        $total_recipients = 0;
        $stmt_select_rec_count = $conn->prepare("SELECT total_recipients FROM campaigns WHERE id = ?");
        if (!$stmt_select_rec_count) throw new Exception("Prepare failed (SELECT total_recipients): " . $conn->error);
        $stmt_select_rec_count->bind_param("i", $campaign_id);
        if (!$stmt_select_rec_count->execute()) throw new Exception("Execute failed (SELECT total_recipients): " . $stmt_select_rec_count->error);
        $res_rec_count = $stmt_select_rec_count->get_result();
        if ($rec_data = $res_rec_count->fetch_assoc()) {
            $total_recipients = (int)$rec_data['total_recipients'];
        }
        $stmt_select_rec_count->close();
        echo "Campaign ID " . $campaign_id . ": Fetched total_recipients = " . $total_recipients . ".
";

        // 3. Update successfully_sent in campaigns table
        // For this simulation, we assume all targeted recipients are successfully sent.
        $stmt_update_success_count = $conn->prepare("UPDATE campaigns SET successfully_sent = ? WHERE id = ?");
        if (!$stmt_update_success_count) throw new Exception("Prepare failed (UPDATE successfully_sent): " . $conn->error);
        $stmt_update_success_count->bind_param("ii", $total_recipients, $campaign_id);
        if (!$stmt_update_success_count->execute()) throw new Exception("Execute failed (UPDATE successfully_sent): " . $stmt_update_success_count->error);
        $stmt_update_success_count->close();
        echo "Campaign ID " . $campaign_id . ": successfully_sent updated to " . $total_recipients . ".
";

        // 4. Update status in campaign_recipients table from 'targeted' to 'sim_sent'
        if ($total_recipients > 0) {
            $stmt_update_cr_status = $conn->prepare("UPDATE campaign_recipients SET status = 'sim_sent', processed_at = NOW() WHERE campaign_id = ? AND status = 'targeted'");
            if (!$stmt_update_cr_status) throw new Exception("Prepare failed (UPDATE campaign_recipients status): " . $conn->error);
            $stmt_update_cr_status->bind_param("i", $campaign_id);
            if (!$stmt_update_cr_status->execute()) throw new Exception("Execute failed (UPDATE campaign_recipients status): " . $stmt_update_cr_status->error);
            $updated_recipient_rows = $stmt_update_cr_status->affected_rows;
            $stmt_update_cr_status->close();
            echo "Campaign ID " . $campaign_id . ": campaign_recipients statuses updated to 'sim_sent' for " . $updated_recipient_rows . " recipients.
";
        } else {
            echo "Campaign ID " . $campaign_id . ": No recipients to update in campaign_recipients (total_recipients is 0).
";
        }

        $conn->commit();
        $campaign_successfully_processed_flag = true;

    } catch (Exception $e) {
        $conn->rollback();
        echo "Error processing campaign ID " . $campaign_id . ": " . $e->getMessage() . ". Transaction rolled back.
";
    }

    if ($campaign_successfully_processed_flag) {
        $processed_count++;
    } else {
        $error_count++;
    }
    echo "-----------------------------------------------------
"; // Separator for each campaign's log
}

$stmt_update_campaign->close();
$result->free();
$conn->close();

echo "
Scheduler Simulation Finished: " . date("Y-m-d H:i:s") . "
";
echo "Successfully processed: " . $processed_count . " campaign(s).
";
// Note: error_count here reflects campaigns that had an issue *during* the transaction block.
// Campaigns skipped due to initial update affecting 0 rows are not counted in error_count here.
echo "Failed to process due to errors (after initial fetch): " . $error_count . " campaign(s).
";

exit(0);
?>
