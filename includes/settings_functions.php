<?php
// includes/settings_functions.php

// Prevent direct script access
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    exit('No direct script access allowed');
}

/**
 * Fetches all application settings from the app_settings table.
 *
 * @param mysqli $conn The database connection object.
 * @return array An associative array of settings (setting_key => setting_value), or empty array on failure.
 */
if (!function_exists('get_all_app_settings')) {
    function get_all_app_settings($conn) {
        $settings = [];
        if (!$conn || $conn->connect_error) {
            error_log("Database connection error in get_all_app_settings.");
            return $settings; // Return empty array if DB connection is invalid
        }

        $sql = "SELECT setting_key, setting_value FROM app_settings";
        $result = $conn->query($sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } else {
            // Log error or handle - for now, returning empty array indicates potential issue
            error_log("Error fetching app settings: " . $conn->error);
        }
        return $settings;
    }
}

/**
 * Updates or inserts an application setting in the app_settings table.
 *
 * @param mysqli $conn The database connection object.
 * @param string $setting_key The key of the setting.
 * @param string $setting_value The value of the setting.
 * @return bool True on success, false on failure.
 */
if (!function_exists('update_app_setting')) {
    function update_app_setting($conn, $setting_key, $setting_value) {
        if (!$conn || $conn->connect_error) {
            error_log("Database connection error in update_app_setting.");
            return false;
        }

        // Using INSERT ... ON DUPLICATE KEY UPDATE for upsert behavior
        $sql = "INSERT INTO app_settings (setting_key, setting_value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("Prepare failed for update_app_setting: (" . $conn->errno . ") " . $conn->error);
            return false;
        }

        $stmt->bind_param("ss", $setting_key, $setting_value);

        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Execute failed for update_app_setting: (" . $stmt->errno . ") " . $stmt->error);
            $stmt->close();
            return false;
        }
    }
}

/**
 * Loads all application settings from the database.
 * This function is intended to be called early in the application's lifecycle.
 * The returned array should then be stored in a global variable or a configuration class.
 *
 * @param mysqli $conn The database connection object.
 * @return array The loaded settings.
 */
if (!function_exists('load_db_app_settings')) { // Renamed for clarity to avoid conflict with future global variable name
    function load_db_app_settings($conn) {
        // This function simply wraps get_all_app_settings for now.
        // It signifies the intent that its output is for global/application-wide use.
        return get_all_app_settings($conn);
    }
}

/**
 * Helper function to get a specific setting's value from a pre-loaded array of settings.
 *
 * @param array $app_settings_array The array of loaded settings (e.g., global $APP_SETTINGS).
 * @param string $key The key of the setting to retrieve.
 * @param mixed $default The default value to return if the key is not found.
 * @return mixed The setting value or the default.
 */
if (!function_exists('get_app_setting_value')) { // Renamed for clarity
    function get_app_setting_value($app_settings_array, $key, $default = null) {
        return isset($app_settings_array[$key]) ? $app_settings_array[$key] : $default;
    }
}

?>
