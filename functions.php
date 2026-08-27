<?php
/**
 * functions.php — Core helper library (loaded by every entry point).
 *
 * Contains the shared building blocks used across the whole app:
 *   - Output & escaping ....... e(), redirect(), flash(), flashes()
 *   - CSRF protection ......... csrf(), verify_csrf(), verify_csrf_api()
 *   - JSON API envelopes ...... json_response(), json_ok(), json_error(), json_input()
 *   - Audit trail ............. log_action()
 *   - Application queries ..... application_for(), can_access()
 *   - Status display .......... status_class(), format_status(), badge()
 *   - Document links .......... document_link(), document_url()
 *   - File uploads ............ store_upload(), save_user_document(), DOCUMENT_FIELDS
 */
declare(strict_types=1);

require_once __DIR__ . '/database.php';

// ---------------------------------------------------------------------------
// Output & session helpers
// ---------------------------------------------------------------------------

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $out = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $out;
}

function csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Invalid form token. Please go back and try again.');
    }
}

/**
 * Verify the CSRF token delivered via the X-CSRF-Token header, falling back
 * to the classic hidden form field (used by multipart uploads). Shared by
 * the JSON API endpoints.
 */
function verify_csrf_api(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) $token)) {
        json_error('Invalid form token. Refresh the page and try again.', 419);
    }
}

// ---------------------------------------------------------------------------
// JSON API helpers
// ---------------------------------------------------------------------------

/** Send a JSON response and terminate the request. */
function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload);
    exit;
}

/** Success envelope: { ok: true, data, redirect?, flash? } */
function json_ok(mixed $data = null, ?string $redirect = null, ?string $flash = null): never
{
    $payload = ['ok' => true, 'data' => $data];
    if ($redirect !== null) {
        $payload['redirect'] = $redirect;
    }
    if ($flash !== null) {
        $payload['flash'] = $flash;
    }
    json_response($payload);
}

/** Error envelope: { ok: false, error } */
function json_error(string $message, int $status = 400): never
{
    json_response(['ok' => false, 'error' => $message], $status);
}

/** Read a JSON request body into an associative array (falls back to $_POST). */
function json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return $_POST;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $_POST;
}

// ---------------------------------------------------------------------------
// Audit logging
// ---------------------------------------------------------------------------

function log_action(int $applicationId, ?int $actorId, string $action, string $detail = ''): void
{
    db()->prepare('INSERT INTO audit_logs (application_id, actor_id, action, detail) VALUES (?, ?, ?, ?)')
        ->execute([$applicationId, $actorId, $action, $detail]);
}

// ---------------------------------------------------------------------------
// Application queries & permissions
// ---------------------------------------------------------------------------

function application_for(int $id): ?array
{
    $sql = 'SELECT a.*,
                   u.username  AS applicant_name,
                   u.email     AS applicant_email,
                   r.username  AS reviewer_name,
                   ad.permanent_address,
                   ad.temporary_address
            FROM applications a
            JOIN users u        ON u.id = a.applicant_id
            LEFT JOIN users r   ON r.id = a.reviewer_id
            LEFT JOIN addresses ad ON ad.user_id = a.applicant_id
            WHERE a.id = ?';
    $s = db()->prepare($sql);
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function can_access(array $app, array $u): bool
{
    return $app['applicant_id'] == $u['id'] || in_array($u['role'], STAFF_ROLES, true);
}

/**
 * Count every application status in ONE query instead of issuing a separate
 * `SELECT COUNT(*) ... WHERE status = ?` per status. This cuts ~6 database
 * round-trips down to 1, which matters most on low-power devices and slow
 * disks. Returns ['total' => int, 'by_status' => [STATUS => int]] with every
 * known status present (defaulting to 0).
 */
function application_status_counts(): array
{
    $byStatus = [
        'DRAFT'                    => 0,
        'SUBMITTED'                => 0,
        'UNDER_REVIEW'             => 0,
        'APPROVED'                 => 0,
        'REJECTED'                 => 0,
        'RESUBMISSION_REQUESTED'   => 0,
    ];

    $rows  = db()->query('SELECT status, COUNT(*) c FROM applications GROUP BY status')->fetchAll();
    $total = 0;
    foreach ($rows as $row) {
        $count = (int) $row['c'];
        $byStatus[$row['status']] = $count;
        $total += $count;
    }

    return ['total' => $total, 'by_status' => $byStatus];
}

/**
 * Count sent/failed emails in ONE query (email_logs). Same rationale as
 * application_status_counts(): a single round-trip instead of two/three.
 * Returns ['sent' => int, 'failed' => int, 'total' => int].
 */
function email_status_counts(): array
{
    $sent = 0;
    $failed = 0;
    $rows = db()->query('SELECT status, COUNT(*) c FROM email_logs GROUP BY status')->fetchAll();
    foreach ($rows as $row) {
        if ($row['status'] === 'SENT') {
            $sent = (int) $row['c'];
        } elseif ($row['status'] === 'FAILED') {
            $failed = (int) $row['c'];
        }
    }
    return ['sent' => $sent, 'failed' => $failed, 'total' => $sent + $failed];
}

// ---------------------------------------------------------------------------
// Status display
// ---------------------------------------------------------------------------

function status_class(string $status): string
{
    return strtolower(str_replace('_', '-', $status));
}

function format_status(string $status): string
{
    return ucwords(strtolower(str_replace('_', ' ', $status)));
}

function badge(string $status): string
{
    return '<span class="badge badge-' . status_class($status) . '">' . e(format_status($status)) . '</span>';
}

function document_link(?string $file, int $userId): string
{
    if (!$file) {
        return '<span class="muted">Not uploaded</span>';
    }
    return '<a href="uploads/users/' . $userId . '/' . rawurlencode($file) . '" target="_blank" rel="noopener">View uploaded document</a>';
}

/** Absolute URL for a stored document — used by the JSON API. */
function document_url(?string $file, int $userId): ?string
{
    if (!$file) {
        return null;
    }
    return 'uploads/users/' . $userId . '/' . rawurlencode($file);
}

// ---------------------------------------------------------------------------
// File uploads
// ---------------------------------------------------------------------------

function store_upload(array $file, string $folder): string
{
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Choose a file to upload.');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('The file must be 5 MB or smaller.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, and PDF files are allowed.');
    }
    if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
        throw new RuntimeException('Unable to create upload folder.');
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $folder . '/' . $stored)) {
        throw new RuntimeException('Unable to save the document.');
    }
    return $stored;
}

const DOCUMENT_FIELDS = [
    'education'           => ['see_document', 'slc_document', 'graduate_document'],
    'additional_documents' => ['citizenship_document', 'passport_document', 'license_document'],
];

function save_user_document(int $userId, string $table, string $column, array $file): void
{
    if (!isset(DOCUMENT_FIELDS[$table]) || !in_array($column, DOCUMENT_FIELDS[$table], true)) {
        throw new RuntimeException('Invalid document field.');
    }
    $stored = store_upload($file, UPLOAD_DIR . '/users/' . $userId);
    db()->prepare("INSERT INTO `$table` (user_id, `$column`) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE `$column` = VALUES(`$column`)")
        ->execute([$userId, $stored]);
}
