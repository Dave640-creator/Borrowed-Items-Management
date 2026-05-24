<?php
/**
 * Database Configuration
 * XAMPP: Make sure MySQL is running in XAMPP Control Panel
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // Default XAMPP password is empty
define('DB_NAME', 'borrow_management');

// ─── Connect ─────────────────────────────────────────────────────────────────

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error,
        'data'    => null
    ]);
    exit;
}

$conn->set_charset('utf8');
