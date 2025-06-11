<?php
// settings.php

// Include functions.php first, which should handle DB connection and load initial $APP_SETTINGS.
require_once __DIR__ . '/includes/functions.php';
// settings_functions.php is already included by functions.php if we followed the previous step correctly,
// but including it here again won't cause issues due to require_once and ensures it's available.
require_once __DIR__ . '/includes/settings_functions.php';

global $conn; // $conn should be globally available from functions.php -> config/database.php
global $APP_SETTINGS; // $APP_SETTINGS should be globally available from functions.php

$page_error_message = '';
$page_success_message = '';

// Define which settings are editable and their types/labels for the form
// This also acts as a whitelist for updates.
$editable_settings_config = [
    'default_from_email' => ['label' => 'Default From Email', 'type' => 'email', 'validation' => FILTER_VALIDATE_EMAIL, 'help_text' => 'Default email address for outgoing system emails.'],
    'reply_to_email' => ['label' => 'Reply-To Email', 'type' => 'email', 'validation' => FILTER_VALIDATE_EMAIL, 'help_text' => 'Email address for replies to system emails.'],
    'company_name' => ['label' => 'Company Name', 'type' => 'text', 'help_text' => 'The name of your company or organization.'],
    'items_per_page' => ['label' => 'Items Per Page', 'type' => 'number', 'validation' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 1, 'max_range' => 100], 'help_text' => 'Default number of items to display per page in lists (e.g., 1-100).'],
    // Example of a textarea setting:
    // 'site_description' => ['label' => 'Site Description', 'type' => 'textarea', 'help_text' => 'A short description of your site for meta tags.'],
];

// Handle POST request to update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!isset($conn) || !$conn instanceof mysqli || !$conn->ping()) {
        $page_error_message = "Database connection error. Cannot save settings.";
    } else {
        $all_updates_successful = true;
        $validation_errors = [];

        foreach ($editable_settings_config as $key => $config) {
            if (isset($_POST[$key])) { // Check if the form field was submitted
                $value = trim($_POST[$key]);

                // Validate if a validation filter is specified
                if (isset($config['validation'])) {
                    $filter_options = [];
                    if (isset($config['options'])) {
                         // For FILTER_VALIDATE_INT, options need to be structured correctly
                        if ($config['validation'] === FILTER_VALIDATE_INT || $config['validation'] === FILTER_VALIDATE_FLOAT) {
                            if(isset($config['options']['min_range']) || isset($config['options']['max_range'])) {
                                $filter_options['options'] = $config['options'];
                            }
                        } else {
                             $filter_options = ['options' => $config['options']]; // Generic options
                        }
                    }

                    if (!filter_var($value, $config['validation'], $filter_options)) {
                        $validation_errors[] = "Invalid value for " . htmlspecialchars($config['label']) . ". Please check the format or range.";
                        $all_updates_successful = false;
                        // We will still try to update other valid settings, but an error message will show.
                        // Alternatively, set a flag to prevent any DB updates if any validation fails.
                        continue;
                    }
                }

                // Proceed to update this specific setting
                if (!update_app_setting($conn, $key, $value)) {
                    $all_updates_successful = false; // Mark that at least one DB update failed
                    // Accumulate specific error messages if your update_app_setting function could return them
                    // For now, a general error message will be shown if $all_updates_successful is false later
                }
            }
        }

        if (!empty($validation_errors)) {
             $page_error_message = implode("<br>", $validation_errors);
        }

        if ($all_updates_successful && empty($validation_errors)) {
            $page_success_message = "Settings updated successfully!";
            // Reload settings from DB to reflect changes immediately in $APP_SETTINGS
            $APP_SETTINGS = load_db_app_settings($conn);
        } elseif (!$all_updates_successful && empty($validation_errors)) {
            // No validation errors, but database update failed for one or more settings
            $page_error_message = "Some settings could not be updated in the database. Please check logs or try again.";
        } elseif (!$all_updates_successful && !empty($validation_errors)) {
            // Both validation errors and potentially DB errors (though we skipped DB for invalid ones)
             $page_error_message .= "<br>Additionally, some valid settings might not have been saved due to database issues.";
        }
        // If there were validation errors, $APP_SETTINGS is NOT reloaded to keep submitted (but potentially invalid) values in form via $_POST
        // If no validation errors but DB errors, $APP_SETTINGS IS reloaded (might be slightly inconsistent if some saved and others not)
        // A better approach for mixed success/failure might be more granular feedback.
    }
}

// Fetch current settings to display in the form (uses the globally loaded $APP_SETTINGS)
// This ensures that if settings were just updated and reloaded, the latest values are shown.
$current_settings_values = $APP_SETTINGS;

?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Application Settings</h1>
    </div>

    <?php if (!empty($page_success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($page_success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($page_error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $page_error_message; // Allows <br> for multiple errors from validation
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!isset($conn) || !$conn instanceof mysqli || !$conn->ping()): ?>
        <div class="alert alert-danger">Database connection error. Settings cannot be displayed or modified.</div>
    <?php elseif (empty($editable_settings_config)): ?>
        <div class="alert alert-info">No editable settings are currently configured.</div>
    <?php else: ?>
    <form method="POST" action="settings.php">
        <div class="card">
            <div class="card-header">
                <h5>Edit Configuration</h5>
            </div>
            <div class="card-body">
                <?php foreach ($editable_settings_config as $key => $config): ?>
                    <?php
                        // Prioritize POSTed value if form submitted (especially if error), otherwise use current DB value
                        $value = isset($_POST['save_settings']) && isset($_POST[$key])
                                 ? htmlspecialchars($_POST[$key])
                                 : htmlspecialchars(get_app_setting_value($current_settings_values, $key, ''));
                    ?>
                    <div class="mb-3 row">
                        <label for="<?php echo htmlspecialchars($key); ?>" class="col-sm-4 col-form-label"><?php echo htmlspecialchars($config['label']); ?></label>
                        <div class="col-sm-8">
                            <?php if ($config['type'] === 'textarea'): ?>
                                <textarea class="form-control" id="<?php echo htmlspecialchars($key); ?>" name="<?php echo htmlspecialchars($key); ?>" rows="3"><?php echo $value; ?></textarea>
                            <?php elseif ($config['type'] === 'number'): ?>
                                 <input type="<?php echo htmlspecialchars($config['type']); ?>" class="form-control" id="<?php echo htmlspecialchars($key); ?>" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo $value; ?>" <?php echo isset($config['options']['min_range']) ? 'min="'.$config['options']['min_range'].'"' : ''; ?> <?php echo isset($config['options']['max_range']) ? 'max="'.$config['options']['max_range'].'"' : ''; ?>>
                            <?php else: // Default to text, email, etc. ?>
                                <input type="<?php echo htmlspecialchars($config['type']); ?>" class="form-control" id="<?php echo htmlspecialchars($key); ?>" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo $value; ?>">
                            <?php endif; ?>
                            <?php if (isset($config['help_text'])): ?>
                                <small class="form-text text-muted"><?php echo htmlspecialchars($config['help_text']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="card-footer text-end">
                <button type="submit" name="save_settings" class="btn btn-primary">Save Settings</button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</main>
        </div> <!-- .row -->
    </div> <!-- .container-fluid -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="public/js/script.js"></script> <!-- Or main.js if that's the primary script file -->
</body>
</html>
