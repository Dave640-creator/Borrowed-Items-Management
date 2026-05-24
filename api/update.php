<?php
/**
 * POST /api/update.php
 *
 * Edit a borrow record's details or mark it as returned.
 *
 * Request Body (JSON) – Edit mode:
 * {
 *   "id"                   : 5,
 *   "action"               : "edit",
 *   "borrower_name"        : "Maria Santos",  // max 50 chars
 *   "phone_number"         : "09171234567",   // optional
 *   "expected_return_date" : "2026-06-01",
 *   "status"               : "Borrowed"
 * }
 *
 * Request Body (JSON) – Mark returned:
 * {
 *   "id"     : 5,
 *   "action" : "mark_returned"
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

// ─── Validate id & action ─────────────────────────────────────────────────────

$id = isset($data['id']) ? (int) $data['id'] : 0;
if ($id <= 0) {
    send_response(false, 'A valid numeric id is required.', null, 400);
}

$allowed_actions = ['mark_returned', 'edit'];
$action          = trim($data['action'] ?? '');

if (!in_array($action, $allowed_actions, true)) {
    send_response(false, "Invalid action. Allowed values: mark_returned, edit.", null, 400);
}

// ─── Check record exists ─────────────────────────────────────────────────────

$check = $conn->prepare('SELECT id FROM borrow_records WHERE id = ? LIMIT 1');
$check->bind_param('i', $id);
$check->execute();
if ($check->get_result()->num_rows === 0) {
    send_response(false, "No record found with id {$id}.", null, 404);
}
$check->close();

// ─── Mark Returned ────────────────────────────────────────────────────────────

if ($action === 'mark_returned') {
    $today = date('Y-m-d');
    $stmt  = $conn->prepare(
        "UPDATE borrow_records SET status = 'Returned', actual_return_date = ? WHERE id = ?"
    );
    $stmt->bind_param('si', $today, $id);

    if (!$stmt->execute()) {
        send_response(false, 'Failed to update record: ' . $stmt->error, null, 500);
    }
    $stmt->close();

    send_response(true, 'Record marked as returned.');
}

// ─── Edit Record ─────────────────────────────────────────────────────────────

$errors = [];

// borrower_name – required, max 50 chars
$error = validate_string($data['borrower_name'] ?? '', 'Borrower name', 50);
if ($error) $errors['borrower_name'] = $error;

// phone_number – optional, max 20 chars
$phone_number = trim($data['phone_number'] ?? '');
if ($phone_number !== '') {
    if (mb_strlen($phone_number) > 20) {
        $errors['phone_number'] = 'Phone number must not exceed 20 characters.';
    } elseif (!preg_match('/^[0-9+\-\s()]+$/', $phone_number)) {
        $errors['phone_number'] = 'Phone number contains invalid characters.';
    }
}

// expected_return_date – required, valid date
$expected_return_date = trim($data['expected_return_date'] ?? '');
if ($expected_return_date === '') {
    $errors['expected_return_date'] = 'Expected return date is required.';
} elseif (!is_valid_date($expected_return_date)) {
    $errors['expected_return_date'] = 'Invalid date format. Use YYYY-MM-DD.';
}

// status – must be valid
$valid_statuses = ['Borrowed', 'Returned'];
$status         = trim($data['status'] ?? '');
if (!in_array($status, $valid_statuses, true)) {
    $errors['status'] = 'Invalid status. Allowed values: Borrowed, Returned.';
}

if (!empty($errors)) {
    send_response(false, 'Validation failed.', ['errors' => $errors], 422);
}

$borrower_name        = sanitize($data['borrower_name']);
$phone_number         = sanitize($phone_number);
$expected_return_date = sanitize($expected_return_date);
$status               = sanitize($status);

if ($status === 'Returned') {
    $today = date('Y-m-d');
    $stmt  = $conn->prepare(
        "UPDATE borrow_records
            SET borrower_name = ?, phone_number = ?, expected_return_date = ?, status = ?, actual_return_date = ?
          WHERE id = ?"
    );
    $stmt->bind_param('sssssi', $borrower_name, $phone_number, $expected_return_date, $status, $today, $id);
} else {
    $stmt = $conn->prepare(
        "UPDATE borrow_records
            SET borrower_name = ?, phone_number = ?, expected_return_date = ?, status = ?, actual_return_date = NULL
          WHERE id = ?"
    );
    $stmt->bind_param('ssssi', $borrower_name, $phone_number, $expected_return_date, $status, $id);
}

if (!$stmt->execute()) {
    send_response(false, 'Failed to update record: ' . $stmt->error, null, 500);
}
$stmt->close();

send_response(true, 'Record updated successfully.');
