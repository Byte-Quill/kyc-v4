<?php
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
