<?php
require_once __DIR__ . '/database.php';

function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): never { header('Location: ' . $url); exit; }
function flash(string $type, string $message): void { $_SESSION['flash'][] = compact('type', 'message'); }
function flashes(): array { $out = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $out; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Invalid form token. Please go back and try again.'); } }
function user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): array { if (!user()) { flash('error', 'Please sign in first.'); redirect('?page=login'); } return user(); }
function require_role(array $roles): array { $u = require_login(); if (!in_array($u['role'], $roles, true)) { http_response_code(403); exit('You do not have permission to access this page.'); } return $u; }
function is_reviewer(): bool { return user() && in_array(user()['role'], ['REVIEWER', 'ADMIN'], true); }
function log_action(int $applicationId, int $actorId, string $action, string $detail = ''): void {
    db()->prepare('INSERT INTO audit_logs (application_id, actor_id, action, detail) VALUES (?, ?, ?, ?)')->execute([$applicationId, $actorId, $action, $detail]);
}
function application_for(int $id): ?array { $s = db()->prepare('SELECT a.*, u.username applicant_name, u.email applicant_email, r.username reviewer_name, ad.permanent_address, ad.temporary_address FROM applications a JOIN users u ON u.id=a.applicant_id LEFT JOIN users r ON r.id=a.reviewer_id LEFT JOIN addresses ad ON ad.user_id=a.applicant_id WHERE a.id=?'); $s->execute([$id]); return $s->fetch() ?: null; }
function can_access(array $app, array $u): bool { return $app['applicant_id'] == $u['id'] || in_array($u['role'], ['REVIEWER','ADMIN'], true); }
function status_class(string $status): string { return strtolower(str_replace('_', '-', $status)); }
function format_status(string $status): string { return ucwords(strtolower(str_replace('_', ' ', $status))); }
function store_upload(array $file, string $folder): string {
    $allowed = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'application/pdf'=>'pdf'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Choose a file to upload.');
    if ($file['size'] > MAX_UPLOAD_BYTES) throw new RuntimeException('The file must be 5 MB or smaller.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime])) throw new RuntimeException('Only JPG, PNG, and PDF files are allowed.');
    if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) throw new RuntimeException('Unable to create upload folder.');
    $stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $folder . '/' . $stored)) throw new RuntimeException('Unable to save the document.');
    return $stored;
}
function save_user_document(int $userId, string $table, string $column, array $file): void {
    $allowed = ['education'=>['see_document','slc_document','graduate_document'], 'additional_documents'=>['citizenship_document','passport_document','license_document']];
    if (!isset($allowed[$table]) || !in_array($column, $allowed[$table], true)) throw new RuntimeException('Invalid document field.');
    $stored = store_upload($file, UPLOAD_DIR . '/users/' . $userId);
    db()->prepare("INSERT INTO `$table` (user_id, `$column`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `$column`=VALUES(`$column`)")->execute([$userId, $stored]);
}
