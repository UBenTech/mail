<?php
// No direct access
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    exit('No direct script access allowed');
}

require_once __DIR__ . '/../config/database.php'; // For $conn, if used directly, though better to pass
require_once __DIR__ . '/templates_functions.php'; // For get_all_templates, get_template_by_id
require_once __DIR__ . '/contacts_functions.php';   // For get_all_contacts (maybe later for recipient selection)

// Function to get all templates for a dropdown
if (!function_exists('get_all_templates_for_compose')) {
    function get_all_templates_for_compose($conn) {
        return get_all_templates($conn); // Assumes get_all_templates returns id and name
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

// Function to save a new campaign (draft, scheduled, etc.)
// For now, recipient handling will be simplified (e.g., not linking to specific contacts table rows)
if (!function_exists('save_campaign_to_db')) {
    function save_campaign_to_db($conn, $campaign_name, $subject, $body_html, $status = 'draft', $scheduled_at = null) {
        // Basic validation
        if (empty($campaign_name) || empty($subject) || empty($body_html)) {
            return "Campaign name, subject, and body are required.";
        }

        $sql = "INSERT INTO campaigns (name, subject, body_html, status, scheduled_at, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
            return "Database prepare error.";
        }

        // For scheduled_at, if it's an empty string or not a valid date format, it should be NULL
        $scheduled_at_db = null; // Default to NULL
        if (!empty($scheduled_at)) {
           // Attempt to create a DateTime object to validate and format
           try {
               // Check if it's already in Y-m-d H:i:s or Y-m-d H:i format from datetime-local input
               if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $scheduled_at)) {
                    $date = new DateTime($scheduled_at); // Handles 'YYYY-MM-DDTHH:MM'
                    $scheduled_at_db = $date->format('Y-m-d H:i:s');
               } elseif (strtotime($scheduled_at) !== false) { // More general validation
                    $date = new DateTime($scheduled_at);
                    $scheduled_at_db = $date->format('Y-m-d H:i:s');
               } else {
                    return "Invalid scheduled date/time format. Please use YYYY-MM-DD HH:MM or a valid date string.";
               }
           } catch (Exception $e) {
               // Invalid date/time format provided
               error_log("Scheduled_at parsing error: " . $e->getMessage() . " for input: " . $scheduled_at);
               return "Invalid scheduled date/time format. Exception: " . $e->getMessage();
           }
        }

        $stmt->bind_param("sssss", $campaign_name, $subject, $body_html, $status, $scheduled_at_db);

        if ($stmt->execute()) {
            $stmt->close();
            return true; // Success
        } else {
            error_log("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
            $error_message = "Failed to save campaign: " . $stmt->error;
            $stmt->close();
            return $error_message;
        }
    }
}
?>
