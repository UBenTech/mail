<?php
/**
 * Database Configuration
 * 
 * This file connects to the MySQL database using the defined credentials.
 * IMPORTANT: Do not commit real credentials to version control.
 */

// Database settings
define('DB_HOST', 'localhost');
define('DB_USER', 'u662439561_mailbulk');
define('DB_PASS', 'BenTech#@5428#');
define('DB_NAME', 'u662439561_bulkmail');

// Connect to MySQL database
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Set character encoding to UTF-8
mysqli_set_charset($conn, "utf8");
?>
