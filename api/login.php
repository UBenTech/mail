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
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// Validate input
if (empty($email) || empty($password)) {
    json_response(['error' => 'Email and password are required'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => 'Invalid email format'], 400);
}

// Attempt login
if (login_user($email, $password)) {
    $user = get_user_by_id($_SESSION['user_id']);
    json_response([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email']
        ]
    ]);
} else {
    json_response(['error' => 'Invalid email or password'], 401);
}
