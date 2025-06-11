<?php
// It's good practice to ensure this file isn't accessed directly
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    exit('No direct script access allowed');
}

// Functions for Email Template Management

/**
 * Get all email templates from the database.
 *
 * @param mysqli $conn The database connection object.
 * @return array An array of templates or an empty array if none.
 */
function get_all_templates($conn) {
    $sql = "SELECT id, name, subject, body_html, created_at, updated_at FROM email_templates ORDER BY created_at DESC";
    $result = $conn->query($sql);
    $templates = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $templates[] = $row;
        }
    }
    return $templates;
}

/**
 * Get a single template by its ID.
 *
 * @param mysqli $conn The database connection object.
 * @param int $template_id The ID of the template to retrieve.
 * @return array|null The template as an associative array or null if not found.
 */
function get_template_by_id($conn, $template_id) {
    $sql = "SELECT id, name, subject, body_html, created_at, updated_at FROM email_templates WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        // error_log("Prepare failed for get_template_by_id: (" . $conn->errno . ") " . $conn->error);
        return null;
    }
    $stmt->bind_param("i", $template_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            return $result->fetch_assoc();
        } else {
            return null;
        }
    } else {
        // error_log("Execute failed for get_template_by_id: (" . $stmt->errno . ") " . $stmt->error);
        return null;
    }
    $stmt->close();
}

/**
 * Create a new email template.
 *
 * @param mysqli $conn The database connection object.
 * @param string $name The name of the template.
 * @param string $subject The subject of the template.
 * @param string $body_html The HTML body of the template.
 * @return bool True on success, false on failure.
 */
function create_template($conn, $name, $subject, $body_html) {
    $sql = "INSERT INTO email_templates (name, subject, body_html) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        // error_log("Prepare failed for create_template: (" . $conn->errno . ") " . $conn->error);
        return false;
    }
    $stmt->bind_param("sss", $name, $subject, $body_html);
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        // error_log("Execute failed for create_template: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return false;
    }
}

/**
 * Update an existing email template.
 *
 * @param mysqli $conn The database connection object.
 * @param int $template_id The ID of the template to update.
 * @param string $name The new name of the template.
 * @param string $subject The new subject of the template.
 * @param string $body_html The new HTML body of the template.
 * @return bool True on success, false on failure.
 */
function update_template($conn, $template_id, $name, $subject, $body_html) {
    $sql = "UPDATE email_templates SET name = ?, subject = ?, body_html = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        // error_log("Prepare failed for update_template: (" . $conn->errno . ") " . $conn->error);
        return false;
    }
    $stmt->bind_param("sssi", $name, $subject, $body_html, $template_id);
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        // error_log("Execute failed for update_template: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return false;
    }
}

/**
 * Delete an email template.
 *
 * @param mysqli $conn The database connection object.
 * @param int $template_id The ID of the template to delete.
 * @return bool True on success, false on failure.
 */
function delete_template($conn, $template_id) {
    $sql = "DELETE FROM email_templates WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        // error_log("Prepare failed for delete_template: (" . $conn->errno . ") " . $conn->error);
        return false;
    }
    $stmt->bind_param("i", $template_id);
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        // error_log("Execute failed for delete_template: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return false;
    }
}

?>
