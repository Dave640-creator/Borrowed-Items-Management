<?php
/**
 * Shared Helper Functions
 * Used across all API endpoints.
 */

// ─── CORS & JSON Headers ──────────────────────────────────────────────────────

function set_headers(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}


// ─── JSON Response ────────────────────────────────────────────────────────────

/**
 * Send a JSON response and stop execution.
 *
 * @param bool        $success
 * @param string      $message  Human-readable message
 * @param mixed       $data     Payload (null → key is omitted)
 * @param int         $code     HTTP status code
 * @param array|null  $meta     Optional pagination/extra meta
 */
function send_response(
    bool   $success,
    string $message,
    mixed  $data    = null,
    int    $code    = 200,
    ?array $meta    = null
): void {
    http_response_code($code);

    $response = [
        'success' => $success,
        'message' => $message,
    ];

    // Always include a data key so clients don't have to null-check
    $response['data'] = $data ?? [];

    if ($meta !== null) {
        $response['meta'] = $meta;
    }

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}


// ─── Input Sanitise ───────────────────────────────────────────────────────────

/**
 * Sanitise a raw string value.
 * Trims whitespace and escapes for MySQL.
 */
function sanitize(string $value): string
{
    global $conn;
    return $conn->real_escape_string(trim($value));
}

/**
 * Read and decode the JSON request body.
 * Returns empty array on failure.
 */
function get_request_body(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return [];
    }
    return json_decode($raw, true) ?? [];
}


// ─── Validation ───────────────────────────────────────────────────────────────

/**
 * Validate a date string against the given format (default YYYY-MM-DD).
 */
function is_valid_date(string $date, string $format = 'Y-m-d'): bool
{
    $d = DateTime::createFromFormat($format, $date);
    return $d !== false && $d->format($format) === $date;
}

/**
 * Validate that a string is non-empty and within the max character limit.
 * Returns an error string, or null when valid.
 */
function validate_string(string $value, string $field_label, int $max_len = 50): ?string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return "{$field_label} is required.";
    }
    if (mb_strlen($trimmed) > $max_len) {
        return "{$field_label} must not exceed {$max_len} characters.";
    }
    return null;
}


// ─── Status Helper ────────────────────────────────────────────────────────────

/**
 * Derive the real-time display status from a borrow record row.
 */
function get_display_status(array $record): string
{
    if ($record['status'] === 'Returned') {
        return 'Returned';
    }

    $today = date('Y-m-d');
    if ($today > $record['expected_return_date']) {
        return 'Overdue';
    }

    return 'Borrowed';
}


// ─── Pagination ───────────────────────────────────────────────────────────────

/**
 * Parse pagination params from $_GET.
 *
 * @return array{page: int, limit: int, offset: int}
 */
function get_pagination(): array
{
    $page  = max(1, (int) ($_GET['page']  ?? 1));
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20))); // cap at 100 rows/page
    $offset = ($page - 1) * $limit;

    return compact('page', 'limit', 'offset');
}
