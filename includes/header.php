<?php
// Ensure APP_SETTINGS is available, ideally loaded by the calling script (e.g., index.php or specific page.php)
// The primary responsibility for loading APP_SETTINGS is on the page including the header, via functions.php.
// global $APP_SETTINGS; // Should already be global from functions.php

// Helper function get_app_setting_value() should be available if functions.php was included before this header.
// It's good practice to ensure includes/functions.php is included by any script that includes this header.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php
              // Check if $APP_SETTINGS is set and is an array. It should be by now.
              // The get_app_setting_value function is defined in includes/settings_functions.php
              // which should be included by includes/functions.php
              $company_name_default = 'Email Platform';
              $page_title_suffix = 'Email Dashboard'; // Or make this dynamic per page later
              $company_name = $company_name_default; // Default

              if (isset($APP_SETTINGS) && is_array($APP_SETTINGS) && function_exists('get_app_setting_value')) {
                  $company_name = htmlspecialchars(get_app_setting_value($APP_SETTINGS, 'company_name', $company_name_default));
              } elseif (isset($APP_SETTINGS['company_name'])) { // Fallback if function not available but array key is
                  $company_name = htmlspecialchars($APP_SETTINGS['company_name']);
              }
              echo $company_name . ' - ' . $page_title_suffix;
          ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="public/css/styles.css" rel="stylesheet"> <!-- Path relative to including file (e.g. index.php in root) -->
</head>
<body>
    <div class="container-fluid">
        <div class="row">
