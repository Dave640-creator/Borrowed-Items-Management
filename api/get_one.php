<?php
/**
 * GET /api/get_one.php
 *
 * Retrieve a single borrow record by its ID.
 *
 * Query Parameters:
 *   id – integer (required)
 *
 * Example:
 *   GET /api/get_one.php?id=5
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

set_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(false, 'Method not allowed. Use GET.', null, 405);
}

// ─── Input ────────────────────────────────────────────────────────────────────

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    send_response(false, 'A valid numeric id is required.', null, 400);
}

// ─── Query ────────────────────────────────────────────────────────────────────

$stmt = $conn->prepare('SELECT * FROM borrow_records WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result === false) {
    send_response(false, 'Query failed: ' . $conn->error, null, 500);
}

$record = $result->fetch_assoc();
$stmt->close();

// ─── Response ─────────────────────────────────────────────────────────────────

if (!$record) {
    send_response(false, "No record found with id {$id}.", null, 404);
}

$record['display_status'] = get_display_status($record);

send_response(true, 'Record retrieved successfully.', $record);
