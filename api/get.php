<?php
/**
 * GET /api/get.php
 *
 * Retrieve borrow records with optional filtering, searching, and pagination.
 *
 * Query Parameters:
 *   filter  – All | Returned | Not Returned   (default: All)
 *   search  – keyword matched on item_name or borrower_name
 *   page    – page number, starting at 1           (default: 1)
 *   limit   – rows per page, max 100               (default: 20)
 *
 * Example:
 *   GET /api/get.php?filter=Not+Returned&search=cable&page=2&limit=10
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

set_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(false, 'Method not allowed. Use GET.', null, 405);
}

// ─── Inputs ───────────────────────────────────────────────────────────────────

$allowed_filters = ['All', 'Returned', 'Not Returned'];
$filter          = trim($_GET['filter'] ?? 'All');
$search          = trim($_GET['search'] ?? '');

if (!in_array($filter, $allowed_filters, true)) {
    send_response(false, 'Invalid filter. Allowed values: All, Returned, Not Returned.', null, 400);
}

['page' => $page, 'limit' => $limit, 'offset' => $offset] = get_pagination();

// ─── Build WHERE clause ───────────────────────────────────────────────────────

$where_parts = ['1 = 1'];
$params      = [];
$types       = '';

if ($filter === 'Returned') {
    $where_parts[] = "status = 'Returned'";
} elseif ($filter === 'Not Returned') {
    $where_parts[] = "status IN ('Borrowed', 'Overdue')";
}

if ($search !== '') {
    $where_parts[] = "(item_name LIKE ? OR borrower_name LIKE ?)";
    $like_term      = '%' . $search . '%';
    $params[]       = $like_term;
    $params[]       = $like_term;
    $types         .= 'ss';
}

$where_sql = implode(' AND ', $where_parts);

// ─── Count total matching rows (for pagination meta) ─────────────────────────

$count_sql  = "SELECT COUNT(*) AS total FROM borrow_records WHERE {$where_sql}";
$count_stmt = $conn->prepare($count_sql);

if ($types !== '') {
    $count_stmt->bind_param($types, ...$params);
}

$count_stmt->execute();
$total_rows = (int) $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = (int) ceil($total_rows / $limit);

// ─── Fetch page of records ───────────────────────────────────────────────────

$data_sql  = "SELECT * FROM borrow_records WHERE {$where_sql} ORDER BY borrow_date DESC LIMIT ? OFFSET ?";
$data_stmt = $conn->prepare($data_sql);

if ($types !== '') {
    $bound_types = $types . 'ii';
    $data_stmt->bind_param($bound_types, ...[...$params, $limit, $offset]);
} else {
    $data_stmt->bind_param('ii', $limit, $offset);
}

$data_stmt->execute();
$result = $data_stmt->get_result();

if ($result === false) {
    send_response(false, 'Query failed: ' . $conn->error, null, 500);
}

$records = [];
while ($row = $result->fetch_assoc()) {
    $row['display_status'] = get_display_status($row);
    $records[]             = $row;
}

$data_stmt->close();

// ─── Response ─────────────────────────────────────────────────────────────────

if (empty($records)) {
    send_response(true, 'No records found.', [], 200, [
        'total_records' => 0,
        'total_pages'   => 0,
        'current_page'  => $page,
        'limit'         => $limit,
    ]);
}

send_response(true, 'Records retrieved successfully.', $records, 200, [
    'total_records' => $total_rows,
    'total_pages'   => $total_pages,
    'current_page'  => $page,
    'limit'         => $limit,
]);
