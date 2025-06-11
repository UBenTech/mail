<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define root path if not defined
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// Include required files using absolute paths
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function register_user($username, $email, $password) {
    global $conn;
    
    $username = sanitize_input($username);
    $email = sanitize_input($email);
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sss", $username, $email, $hashed_password);
    
    if (mysqli_stmt_execute($stmt)) {
        return mysqli_insert_id($conn);
    }
    return false;
}

function login_user($email, $password) {
    global $conn;
    
    $email = sanitize_input($email);
    
    $sql = "SELECT id, username, password FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($user = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            return true;
        }
    }
    return false;
}

function logout_user() {
    session_unset();
    session_destroy();
    return true;
}
