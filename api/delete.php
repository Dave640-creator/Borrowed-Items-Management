<?php
/**
 * POST /api/delete.php
 *
 * Delete a borrow record by its ID.
 *
 * Request Body (JSON):
 * {
 *   "id": 5
 * }
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

set_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(false, 'Method not allowed. Use POST.', null, 405);
}

// ─── Read Body ────────────────────────────────────────────────────────────────

$data = get_request_body();

if (empty($data)) {
    send_response(false, 'Request body is empty or not valid JSON.', null, 400);
}

$id = isset($data['id']) ? (int) $data['id'] : 0;

if ($id <= 0) {
    send_response(false, 'A valid numeric id is required.', null, 400);
}

// ─── Check record exists ─────────────────────────────────────────────────────

$check = $conn->prepare('SELECT id FROM borrow_records WHERE id = ? LIMIT 1');
$check->bind_param('i', $id);
$check->execute();

if ($check->get_result()->num_rows === 0) {
    send_response(false, "No record found with id {$id}.", null, 404);
}
$check->close();

// ─── Delete ───────────────────────────────────────────────────────────────────

$stmt = $conn->prepare('DELETE FROM borrow_records WHERE id = ?');
$stmt->bind_param('i', $id);

if (!$stmt->execute()) {
    send_response(false, 'Failed to delete record: ' . $stmt->error, null, 500);
}
$stmt->close();

send_response(true, 'Record deleted successfully.');
