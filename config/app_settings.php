<?php
// Application Settings

// Prevent direct access if not already defined by a common entry point
if (!defined('ROOT_PATH')) {
    // Attempt to define ROOT_PATH relative to this file's location
    // This might need adjustment based on how your includes are structured.
    // For the purpose of this application, ROOT_PATH is typically defined
    // in index.php. If this file is included elsewhere without ROOT_PATH,
    // it might indicate an issue or direct access.
    // For now, we'll rely on other files defining ROOT_PATH if needed,
    // or handle direct access by checking SCRIPT_FILENAME if this were web-accessible.
    // Since it's a .php config, direct web access should be prevented by server config.
}

// Email Settings
define('APP_SETTING_DEFAULT_FROM_EMAIL', 'noreply@example.com');
define('APP_SETTING_REPLY_TO_EMAIL', 'support@example.com');

// Company Information
define('APP_SETTING_COMPANY_NAME', 'CtpaInstitute.org'); // Default company name

// Analytics Settings (Example placeholders)
// define('APP_SETTING_GOOGLE_ANALYTICS_ID', '');

// Pagination Settings (Example)
define('APP_SETTING_ITEMS_PER_PAGE', 20);


// Other settings can be added here as needed

// For files that primarily use `define`, returning a value isn't standard.
// If this were to return an array of settings instead of using define,
// then a return statement would be appropriate.
// For now, this file's purpose is to make constants globally available upon inclusion.

?>
