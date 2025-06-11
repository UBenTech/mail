<?php
// Load application specific settings
// ROOT_PATH is typically defined in index.php. If settings.php is accessed directly
// and ROOT_PATH is not defined, file paths might be an issue.
// However, for including 'config/app_settings.php', a relative path from settings.php (root) is fine.

if (file_exists('config/app_settings.php')) {
    require_once 'config/app_settings.php';
} else {
    // Handle error: Settings file missing. This is crucial.
    define('APP_SETTINGS_FILE_ERROR', 'Configuration file config/app_settings.php is missing.');
}

// For consistency, include database.php, though not directly used for these defines
// require_once 'config/database.php'; // If $conn is needed on the page for other things

// Prepare an array of settings to display, checking if they are defined
$app_settings_to_display = [];
$error_message_settings = ''; // Use a variable for error messages

if (defined('APP_SETTINGS_FILE_ERROR')) {
    $error_message_settings = APP_SETTINGS_FILE_ERROR;
} else {
    $app_settings_to_display = [
        'Default From Email' => defined('APP_SETTING_DEFAULT_FROM_EMAIL') ? APP_SETTING_DEFAULT_FROM_EMAIL : 'Not set',
        'Reply-To Email' => defined('APP_SETTING_REPLY_TO_EMAIL') ? APP_SETTING_REPLY_TO_EMAIL : 'Not set',
        'Company Name' => defined('APP_SETTING_COMPANY_NAME') ? APP_SETTING_COMPANY_NAME : 'Not set',
        'Items Per Page' => defined('APP_SETTING_ITEMS_PER_PAGE') ? APP_SETTING_ITEMS_PER_PAGE : 'Not set',
        // Add more settings here as they are defined in app_settings.php
    ];
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Application Settings</h1>
    </div>

    <?php if (!empty($error_message_settings)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error_message_settings); ?>
        </div>
    <?php elseif (empty($app_settings_to_display) && !defined('APP_SETTINGS_FILE_ERROR')): ?>
        <div class="alert alert-info" role="alert">
            No application settings are currently defined or available.
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h5>Current Configuration</h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <?php foreach ($app_settings_to_display as $setting_name => $setting_value): ?>
                        <dt class="col-sm-4"><?php echo htmlspecialchars($setting_name); ?>:</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($setting_value); ?></dd>
                    <?php endforeach; ?>
                </dl>
                <p class="mt-3 text-muted">
                    <small>These settings are currently read-only and are defined in the application's configuration files. To modify them, system administrator intervention is required.</small>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Placeholder for future editable settings form -->
    <!--
    <div class="card mt-4">
        <div class="card-header">
            <h5>Edit Settings (Future Implementation)</h5>
        </div>
        <div class="card-body">
            <p>Editing settings directly through this interface will be available in a future update.</p>
        </div>
    </div>
    -->
</main>
        </div> <!-- .row -->
    </div> <!-- .container-fluid -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="public/js/script.js"></script> <!-- Or main.js if that's the primary script file -->
</body>
</html>
