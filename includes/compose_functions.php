<?php
// No direct access
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    exit('No direct script access allowed');
}

// Note: config/database.php is included by includes/functions.php,
// which should be included by any page using these compose functions.
// So, $conn should be globally available.
require_once __DIR__ . '/templates_functions.php';
require_once __DIR__ . '/contacts_functions.php';

// Function to get all templates for a dropdown
if (!function_exists('get_all_templates_for_compose')) {
    function get_all_templates_for_compose($conn) {
        return get_all_templates($conn);
    }
}

// Function to get a specific template's content
if (!function_exists('get_template_content_for_compose')) {
    function get_template_content_for_compose($conn, $template_id) {
        $template = get_template_by_id($conn, $template_id);
        if ($template) {
            return ['subject' => $template['subject'], 'body_html' => $template['body_html']];
        }
        return null;
    }
}

// Function to save a new campaign or update an existing one
if (!function_exists('save_campaign_to_db')) {
    function save_campaign_to_db($conn, $campaign_name, $subject, $body_html, $status = 'draft', $scheduled_at_input = null, $campaign_id_for_update = null) {

        global $conn; // Ensure $conn is in scope

        // Basic validation for core content (applies to both insert and update)
        if (empty($campaign_name) || empty($subject) || empty($body_html)) {
            return "Campaign name, subject, and body are required.";
        }

        // Database connection check
        if (!isset($conn) || !$conn instanceof mysqli || !$conn->ping()) {
            error_log("save_campaign_to_db: Database connection is not valid.");
            return "Database connection error.";
        }

        $scheduled_at_db_val = null;
        $sent_at_sql_val = "NULL"; // Default SQL value for sent_at

        // Prepare scheduled_at and sent_at based on status
        if ($status === 'sent') {
            $sent_at_sql_val = "NOW()"; // Use SQL NOW() for sent_at
            $scheduled_at_db_val = null; // If sending now, it's not scheduled
        } elseif ($status === 'scheduled') {
            if (empty($scheduled_at_input)) {
                return "Scheduled date/time is required for scheduled campaigns.";
            }
            try {
                $date = new DateTime($scheduled_at_input);
                $scheduled_at_db_val = $date->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                error_log("Invalid scheduled date/time format: " . $scheduled_at_input . " Exception: " . $e->getMessage());
                return "Invalid scheduled date/time format. Please use a valid date and time.";
            }
            // sent_at_sql_val remains "NULL"
        } else { // 'draft' or other statuses
            $status = 'draft'; // Ensure status defaults to 'draft' if not 'sent' or 'scheduled'
            // scheduled_at_db_val remains null
            // sent_at_sql_val remains "NULL"
        }

        if ($campaign_id_for_update && is_numeric($campaign_id_for_update)) {
            // UPDATE Logic
            // Assumes 'updated_at' column in DB schema has ON UPDATE CURRENT_TIMESTAMP
            $sql = "UPDATE campaigns SET
                        name = ?,
                        subject = ?,
                        body_html = ?,
                        status = ?,
                        scheduled_at = ?,
                        sent_at = " . $sent_at_sql_val . "
                    WHERE id = ?";

            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                error_log("Prepare failed (UPDATE campaign): (" . $conn->errno . ") " . $conn->error . " SQL: " . $sql);
                return "Database prepare error (update campaign).";
            }
            $stmt->bind_param("sssssi", $campaign_name, $subject, $body_html, $status, $scheduled_at_db_val, $campaign_id_for_update);

            if ($stmt->execute()) {
                $affected_rows = $stmt->affected_rows; // Check if any row was actually updated
                $stmt->close();
                // Even if no rows were changed (e.g. same data submitted), consider it a success if query executed.
                // Or, you could return a specific message/code: return $affected_rows > 0 ? (int)$campaign_id_for_update : 'no_changes';
                return (int)$campaign_id_for_update;
            } else {
                error_log("Execute failed (UPDATE campaign): (" . $stmt->errno . ") " . $stmt->error);
                $error_detail = $stmt->error;
                $stmt->close();
                return "Failed to update campaign: " . $error_detail;
            }

        } else {
            // INSERT Logic
            // Assumes 'created_at' has DEFAULT CURRENT_TIMESTAMP or is set by NOW()
            // Assumes 'updated_at' (if exists) has DEFAULT CURRENT_TIMESTAMP or ON UPDATE CURRENT_TIMESTAMP
            $sql = "INSERT INTO campaigns (name, subject, body_html, status, scheduled_at, sent_at, created_at)
                    VALUES (?, ?, ?, ?, ?, " . $sent_at_sql_val . ", NOW())";

            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                error_log("Prepare failed (INSERT campaign): (" . $conn->errno . ") " . $conn->error . " SQL: " . $sql);
                return "Database prepare error (insert campaign).";
            }
            $stmt->bind_param("sssss", $campaign_name, $subject, $body_html, $status, $scheduled_at_db_val);

            if ($stmt->execute()) {
                $new_campaign_id = $conn->insert_id;
                $stmt->close();
                return (int)$new_campaign_id; // Return new ID on successful insert
            } else {
                error_log("Execute failed (INSERT campaign): (" . $stmt->errno . ") " . $stmt->error);
                $error_detail = $stmt->error;
                $stmt->close();
                return "Failed to save new campaign: " . $error_detail;
            }
        }
    }
}


if (!function_exists('get_campaign_by_id')) {
    /**
     * Fetches a specific campaign by its ID.
     *
     * @param mysqli $conn The database connection object.
     * @param int $campaign_id The ID of the campaign to fetch.
     * @return array|null An associative array of the campaign data if found, otherwise null.
     */
    function get_campaign_by_id($conn, $campaign_id) {
        global $conn; // Ensure $conn is in scope if not passed explicitly and relying on global from functions.php

        if (!isset($conn) || !$conn instanceof mysqli || !$conn->ping()) { // Added ping() for active connection check
            error_log("get_campaign_by_id: Database connection is not valid or not active.");
            return null;
        }
        if (empty($campaign_id) || !is_numeric($campaign_id)) {
            error_log("get_campaign_by_id: Invalid campaign_id provided: " . print_r($campaign_id, true));
            return null;
        }

        $sql = "SELECT id, name, subject, body_html, status, scheduled_at FROM campaigns WHERE id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            error_log("Prepare failed for get_campaign_by_id: (" . $conn->errno . ") " . $conn->error);
            return null;
        }

        $stmt->bind_param("i", $campaign_id);

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $campaign = $result->fetch_assoc();
                $stmt->close();
                return $campaign;
            } else {
                $stmt->close();
                return null; // Not found
            }
        } else {
            error_log("Execute failed for get_campaign_by_id: (" . $stmt->errno . ") " . $stmt->error);
            $stmt->close();
            return null;
        }
    }
}
?>
