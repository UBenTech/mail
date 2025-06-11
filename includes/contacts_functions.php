<?php
// It's good practice to ensure this file isn't accessed directly
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    exit('No direct script access allowed');
}

// Functions for Contact Management

/**
 * Check if an email already exists in the contacts table, optionally excluding a specific contact ID.
 *
 * @param mysqli $conn The database connection object.
 * @param string $email The email address to check.
 * @param int $contact_id_to_exclude The ID of the contact to exclude from the check (0 if not excluding).
 * @return bool True if the email exists, false otherwise.
 */
function email_exists($conn, $email, $contact_id_to_exclude = 0) {
    $sql = "SELECT id FROM contacts WHERE email = ?";
    $params = ["s", $email];
    if ($contact_id_to_exclude > 0) {
        $sql .= " AND id != ?";
        $params = ["si", $email, $contact_id_to_exclude];
    }
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        // error_log("Prepare failed for email_exists: (" . $conn->errno . ") " . $conn->error);
        return true; // Assume exists on error to prevent duplicates
    }
    $stmt->bind_param(...$params);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $stmt->close();
        return $result->num_rows > 0;
    } else {
        // error_log("Execute failed for email_exists: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return true; // Assume exists on error
    }
}


/**
 * Get all contacts from the database.
 *
 * @param mysqli $conn The database connection object.
 * @return array An array of contacts or an empty array if none.
 */
function get_all_contacts($conn) {
    $sql = "SELECT id, email, first_name, last_name, status, created_at, updated_at FROM contacts ORDER BY created_at DESC";
    $result = $conn->query($sql);
    $contacts = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $contacts[] = $row;
        }
    }
    return $contacts;
}

/**
 * Get a single contact by its ID.
 *
 * @param mysqli $conn The database connection object.
 * @param int $contact_id The ID of the contact to retrieve.
 * @return array|null The contact as an associative array or null if not found.
 */
function get_contact_by_id($conn, $contact_id) {
    $sql = "SELECT id, email, first_name, last_name, status, created_at, updated_at FROM contacts WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        // error_log("Prepare failed for get_contact_by_id: (" . $conn->errno . ") " . $conn->error);
        return null;
    }
    $stmt->bind_param("i", $contact_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $contact = $result->fetch_assoc();
            $stmt->close();
            return $contact;
        } else {
            $stmt->close();
            return null;
        }
    } else {
        // error_log("Execute failed for get_contact_by_id: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return null;
    }
}

/**
 * Create a new contact.
 *
 * @param mysqli $conn The database connection object.
 * @param string $email The email of the contact.
 * @param string $first_name The first name of the contact.
 * @param string $last_name The last name of the contact.
 * @param string $status The status of the contact.
 * @return bool|string True on success, error message string on failure.
 */
function create_contact($conn, $email, $first_name, $last_name, $status) {
    if (email_exists($conn, $email)) {
        return "Error: Email address already exists.";
    }
    $sql = "INSERT INTO contacts (email, first_name, last_name, status) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        // error_log("Prepare failed for create_contact: (" . $conn->errno . ") " . $conn->error);
        return "Error: Could not prepare the statement.";
    }
    $stmt->bind_param("ssss", $email, $first_name, $last_name, $status);
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        // error_log("Execute failed for create_contact: (" . $stmt->errno . ") " . $stmt->error);
        $error_message = "Error: Could not create contact. " . $stmt->error;
        $stmt->close();
        return $error_message;
    }
}

/**
 * Update an existing contact.
 *
 * @param mysqli $conn The database connection object.
 * @param int $contact_id The ID of the contact to update.
 * @param string $email The new email of the contact.
 * @param string $first_name The new first name of the contact.
 * @param string $last_name The new last name of the contact.
 * @param string $status The new status of the contact.
 * @return bool|string True on success, error message string on failure.
 */
function update_contact($conn, $contact_id, $email, $first_name, $last_name, $status) {
    if (email_exists($conn, $email, $contact_id)) {
        return "Error: Email address already exists for another contact.";
    }
    $sql = "UPDATE contacts SET email = ?, first_name = ?, last_name = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        // error_log("Prepare failed for update_contact: (" . $conn->errno . ") " . $conn->error);
        return "Error: Could not prepare the statement.";
    }
    $stmt->bind_param("ssssi", $email, $first_name, $last_name, $status, $contact_id);
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        // error_log("Execute failed for update_contact: (" . $stmt->errno . ") " . $stmt->error);
        $error_message = "Error: Could not update contact. " . $stmt->error;
        $stmt->close();
        return $error_message;
    }
}

/**
 * Delete a contact.
 *
 * @param mysqli $conn The database connection object.
 * @param int $contact_id The ID of the contact to delete.
 * @return bool True on success, false on failure.
 */
function delete_contact($conn, $contact_id) {
    $sql = "DELETE FROM contacts WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        // error_log("Prepare failed for delete_contact: (" . $conn->errno . ") " . $conn->error);
        return false;
    }
    $stmt->bind_param("i", $contact_id);
    if ($stmt->execute()) {
        $stmt->close();
        return true;
    } else {
        // error_log("Execute failed for delete_contact: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return false;
    }
}

?>
