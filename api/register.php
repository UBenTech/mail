<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define root path if not defined
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

// Get and validate input
$username = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validate input
if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
    json_response(['error' => 'All fields are required'], 400);
}

if ($password !== $confirm_password) {
    json_response(['error' => 'Passwords do not match'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => 'Invalid email format'], 400);
}

// Attempt registration
$user_id = register_user($username, $email, $password);

if ($user_id) {
    // Auto login after registration
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    
    json_response([
        'success' => true,
        'message' => 'Registration successful',
        'user' => [
            'id' => $user_id,
            'username' => $username,
            'email' => $email
        ]
    ]);
} else {
    json_response(['error' => 'Registration failed. Email or username may already exist.'], 400);
}
