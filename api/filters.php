<?php
/**
 * GET /api/filters.php
 *
 * Return the available filter options for the borrow records list.
 *
 * Example:
 *   GET /api/filters.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

set_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(false, 'Method not allowed. Use GET.', null, 405);
}

send_response(true, 'Filter options retrieved successfully.', [
    'filters' => ['All', 'Returned', 'Not Returned'],
]);
