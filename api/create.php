<?php
/**
 * POST /api/create.php
 *
 * Create a new borrow record.
 *
 * Request Body (JSON):
 * {
 *   "item_name"            : "HDMI Cable",     // max 50 chars
 *   "borrower_name"        : "Juan Dela Cruz", // max 50 chars
 *   "phone_number"         : "09171234567",    // max 20 chars, optional
 *   "borrow_date"          : "2026-05-24",
 *   "expected_return_date" : "2026-05-31"
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

// ─── Validate Fields ─────────────────────────────────────────────────────────

$errors = [];

// item_name – required, max 50 chars
$error = validate_string($data['item_name'] ?? '', 'Item name', 50);
if ($error) $errors['item_name'] = $error;

// borrower_name – required, max 50 chars
$error = validate_string($data['borrower_name'] ?? '', 'Borrower name', 50);
if ($error) $errors['borrower_name'] = $error;

// phone_number – optional, max 20 chars, digits/+/spaces only
$phone_number = trim($data['phone_number'] ?? '');
if ($phone_number !== '') {
    if (mb_strlen($phone_number) > 20) {
        $errors['phone_number'] = 'Phone number must not exceed 20 characters.';
    } elseif (!preg_match('/^[0-9+\-\s()]+$/', $phone_number)) {
        $errors['phone_number'] = 'Phone number contains invalid characters.';
    }
}

// borrow_date – required, valid date
$borrow_date = trim($data['borrow_date'] ?? '');
if ($borrow_date === '') {
    $errors['borrow_date'] = 'Borrow date is required.';
} elseif (!is_valid_date($borrow_date)) {
    $errors['borrow_date'] = 'Invalid date format. Use YYYY-MM-DD.';
}

// expected_return_date – required, valid date, must be after borrow_date
$expected_return_date = trim($data['expected_return_date'] ?? '');
if ($expected_return_date === '') {
    $errors['expected_return_date'] = 'Expected return date is required.';
} elseif (!is_valid_date($expected_return_date)) {
    $errors['expected_return_date'] = 'Invalid date format. Use YYYY-MM-DD.';
} elseif (!isset($errors['borrow_date']) && strtotime($expected_return_date) <= strtotime($borrow_date)) {
    $errors['expected_return_date'] = 'Expected return date must be after the borrow date.';
}

if (!empty($errors)) {
    send_response(false, 'Validation failed.', ['errors' => $errors], 422);
}

// ─── Sanitise & Insert ────────────────────────────────────────────────────────

$item_name            = sanitize($data['item_name']);
$borrower_name        = sanitize($data['borrower_name']);
$phone_number         = sanitize($phone_number);
$borrow_date          = sanitize($borrow_date);
$expected_return_date = sanitize($expected_return_date);

$stmt = $conn->prepare(
    'INSERT INTO borrow_records
        (item_name, borrower_name, phone_number, borrow_date, expected_return_date, status)
     VALUES (?, ?, ?, ?, ?, "Borrowed")'
);

$stmt->bind_param('sssss', $item_name, $borrower_name, $phone_number, $borrow_date, $expected_return_date);

if (!$stmt->execute()) {
    send_response(false, 'Failed to create record: ' . $stmt->error, null, 500);
}

$new_id = $conn->insert_id;
$stmt->close();

// ─── Response ─────────────────────────────────────────────────────────────────

send_response(true, 'Record created successfully.', [
    'id'                   => $new_id,
    'item_name'            => $item_name,
    'borrower_name'        => $borrower_name,
    'phone_number'         => $phone_number,
    'borrow_date'          => $borrow_date,
    'expected_return_date' => $expected_return_date,
    'status'               => 'Borrowed',
], 201);
