<?php
// No direct access
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    exit('No direct script access allowed');
}

// Note: config/database.php is included by includes/functions.php,
// which should be included by any page using these compose functions.
// So, $conn should be globally available.
require_once __DIR__ . '/templates_functions.php';
require_once __DIR__ . '/contacts_functions.php';   // Ensures get_contact_by_id is available

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

if (!function_exists('get_contact_details_for_recipient_log')) {
    function get_contact_details_for_recipient_log($conn, $contact_id) {
        // get_contact_by_id is from contacts_functions.php, which is included above.
        if (function_exists('get_contact_by_id')) {
             $contact = get_contact_by_id($conn, $contact_id);
             if ($contact) {
                 return ['email' => $contact['email'], 'id' => $contact['id']];
             }
        } else {
            // Fallback direct query if get_contact_by_id is somehow not available
            // This shouldn't happen if includes are correct.
            error_log("Warning: get_contact_by_id function not found in get_contact_details_for_recipient_log. Falling back to direct query.");
            $stmt = $conn->prepare("SELECT email FROM contacts WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $contact_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($contact_result = $result->fetch_assoc()) { // Renamed to avoid conflict with $contact var scope
                    $stmt->close();
                    return ['email' => $contact_result['email'], 'id' => $contact_id];
                }
                $stmt->close();
            }
        }
        return null;
    }
}

// Function to save a new campaign or update an existing one
if (!function_exists('save_campaign_to_db')) {
    function save_campaign_to_db($conn, $campaign_name, $subject, $body_html, $status = 'draft',
                                 $scheduled_at_input = null, $campaign_id_for_update = null,
                                 $selected_contact_ids = []) // New parameter
    {
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
            $sent_at_sql_val = "NOW()";
            $scheduled_at_db_val = null;
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
        } else {
            $status = 'draft';
        }

        $current_campaign_id = null;
        $is_new_campaign = true;

        $conn->begin_transaction();

        try {
            if ($campaign_id_for_update && is_numeric($campaign_id_for_update)) {
                $is_new_campaign = false;
                $current_campaign_id = (int)$campaign_id_for_update;

                $sql_update_campaign = "UPDATE campaigns SET name = ?, subject = ?, body_html = ?, status = ?, scheduled_at = ?, sent_at = " . $sent_at_sql_val . " WHERE id = ?";
                $stmt_campaign = $conn->prepare($sql_update_campaign);
                if (!$stmt_campaign) throw new Exception("Prepare failed (UPDATE campaign): " . $conn->error);
                $stmt_campaign->bind_param("sssssi", $campaign_name, $subject, $body_html, $status, $scheduled_at_db_val, $current_campaign_id);
                if (!$stmt_campaign->execute()) throw new Exception("Execute failed (UPDATE campaign): " . $stmt_campaign->error);
                $stmt_campaign->close();
            } else {
                $sql_insert_campaign = "INSERT INTO campaigns (name, subject, body_html, status, scheduled_at, sent_at, created_at) VALUES (?, ?, ?, ?, ?, " . $sent_at_sql_val . ", NOW())";
                $stmt_campaign = $conn->prepare($sql_insert_campaign);
                if (!$stmt_campaign) throw new Exception("Prepare failed (INSERT campaign): " . $conn->error);
                $stmt_campaign->bind_param("sssss", $campaign_name, $subject, $body_html, $status, $scheduled_at_db_val);
                if (!$stmt_campaign->execute()) throw new Exception("Execute failed (INSERT campaign): " . $stmt_campaign->error);
                $current_campaign_id = $conn->insert_id;
                $stmt_campaign->close();
            }

            if (!$current_campaign_id) { // Should not happen if execute was successful for insert
                throw new Exception("Failed to get campaign ID after insert/update.");
            }

            if (!$is_new_campaign) {
                $stmt_delete_recipients = $conn->prepare("DELETE FROM campaign_recipients WHERE campaign_id = ?");
                if (!$stmt_delete_recipients) throw new Exception("Prepare failed (DELETE recipients): " . $conn->error);
                $stmt_delete_recipients->bind_param("i", $current_campaign_id);
                if (!$stmt_delete_recipients->execute()) throw new Exception("Execute failed (DELETE recipients): " . $stmt_delete_recipients->error);
                $stmt_delete_recipients->close();
            }

            $actual_recipients_processed_count = 0;
            if (!empty($selected_contact_ids)) {
                // Default recipient status is 'targeted' as per schema
                $stmt_insert_recipient = $conn->prepare("INSERT INTO campaign_recipients (campaign_id, contact_id, email_address, created_at) VALUES (?, ?, ?, NOW())");
                if (!$stmt_insert_recipient) throw new Exception("Prepare failed (INSERT recipient): " . $conn->error);

                foreach ($selected_contact_ids as $contact_id) {
                    $contact_details = get_contact_details_for_recipient_log($conn, (int)$contact_id);
                    if ($contact_details && !empty($contact_details['email'])) {
                        $stmt_insert_recipient->bind_param("iis", $current_campaign_id, $contact_details['id'], $contact_details['email']);
                        if ($stmt_insert_recipient->execute()) {
                            $actual_recipients_processed_count++;
                        } else {
                            error_log("Failed to insert recipient contact_id {$contact_id} for campaign_id {$current_campaign_id}: " . $stmt_insert_recipient->error);
                        }
                    } else {
                         error_log("Could not find email for contact_id {$contact_id} for campaign_id {$current_campaign_id}. Skipping.");
                    }
                }
                $stmt_insert_recipient->close();
            }

            $final_total_recipients = $actual_recipients_processed_count;
            $final_successfully_sent = ($status === 'sent') ? $actual_recipients_processed_count : 0;

            $stmt_update_counts = $conn->prepare("UPDATE campaigns SET total_recipients = ?, successfully_sent = ? WHERE id = ?");
            if (!$stmt_update_counts) throw new Exception("Prepare failed (UPDATE counts): " . $conn->error);
            $stmt_update_counts->bind_param("iii", $final_total_recipients, $final_successfully_sent, $current_campaign_id);
            if (!$stmt_update_counts->execute()) throw new Exception("Execute failed (UPDATE counts): " . $stmt_update_counts->error);
            $stmt_update_counts->close();

            if ($status === 'sent' && $actual_recipients_processed_count > 0) {
                $stmt_update_recipient_status = $conn->prepare("UPDATE campaign_recipients SET status = 'sim_sent', processed_at = NOW() WHERE campaign_id = ? AND status = 'targeted'");
                if (!$stmt_update_recipient_status) throw new Exception("Prepare failed (UPDATE recipient status): " . $conn->error);
                $stmt_update_recipient_status->bind_param("i", $current_campaign_id);
                if (!$stmt_update_recipient_status->execute()) throw new Exception("Execute failed (UPDATE recipient status): " . $stmt_update_recipient_status->error);
                $stmt_update_recipient_status->close();
            }

            $conn->commit();
            return (int)$current_campaign_id;

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Transaction failed in save_campaign_to_db for campaign ID {$current_campaign_id}: " . $e->getMessage());
            return "Failed to save campaign due to a transaction error. Details: " . $e->getMessage();
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
        global $conn;

        if (!isset($conn) || !$conn instanceof mysqli || !$conn->ping()) {
            error_log("get_campaign_by_id: Database connection is not valid or not active.");
            return null;
        }
        if (empty($campaign_id) || !is_numeric($campaign_id)) {
            error_log("get_campaign_by_id: Invalid campaign_id provided: " . print_r($campaign_id, true));
            return null;
        }

        $sql = "SELECT id, name, subject, body_html, status, scheduled_at, created_at, sent_at FROM campaigns WHERE id = ?";
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

if (!function_exists('get_recipients_for_campaign')) {
    /**
     * Fetches all recipient records for a specific campaign.
     *
     * @param mysqli $conn The database connection object.
     * @param int $campaign_id The ID of the campaign.
     * @return array An array of recipient data, or empty array on failure/no recipients.
     */
    function get_recipients_for_campaign($conn, $campaign_id) {
        $recipients = [];
        global $conn; // Ensure $conn is in scope

        if (!isset($conn) || !$conn instanceof mysqli || !$conn->ping()) {
            error_log("get_recipients_for_campaign: Database connection is not valid.");
            return $recipients; // Return empty array on DB error
        }
        if (empty($campaign_id) || !is_numeric($campaign_id)) {
            error_log("get_recipients_for_campaign: Invalid campaign_id provided.");
            return $recipients; // Return empty array for invalid ID
        }

        $sql = "SELECT cr.email_address, cr.status, cr.processed_at, c.first_name, c.last_name
                FROM campaign_recipients cr
                LEFT JOIN contacts c ON cr.contact_id = c.id
                WHERE cr.campaign_id = ?
                ORDER BY cr.email_address ASC";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("Prepare failed for get_recipients_for_campaign: (" . $conn->errno . ") " . $conn->error);
            return $recipients;
        }

        $stmt->bind_param("i", $campaign_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $recipients[] = $row;
            }
            $stmt->close();
        } else {
            error_log("Execute failed for get_recipients_for_campaign: (" . $stmt->errno . ") " . $stmt->error);
            $stmt->close();
        }
        return $recipients;
    }
}
?>
