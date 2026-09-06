<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// document.php — Authenticated document serving.
//
// Uploaded KYC documents are stored under uploads/users/{userId}/{filename}.
// Direct access to that folder is blocked by uploads/.htaccess (PHP disabled,
// downloads forced). This endpoint is the ONLY way to view a document:
//   - the viewer must be signed in
//   - the viewer must be the applicant OR staff (can_access())
//   - the file must exist and be inside the uploads directory
// ---------------------------------------------------------------------------

// Session hardening: httponly, SameSite=Lax, secure when HTTPS, 30-min idle timeout.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $isHttps,
]);
session_start();

// Idle session timeout: 30 minutes of inactivity.
$idleTimeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $idleTimeout) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$u = user();
if (!$u) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Sign in required.');
}

$userId = (int) ($_GET['user'] ?? 0);
$file   = $_GET['file'] ?? '';

if ($userId <= 0 || $file === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Invalid request.');
}

// The viewer must be the owner of the document OR staff.
if ($userId !== (int) $u['id'] && !is_staff()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('You do not have permission to view this document.');
}

// Resolve the real path and ensure it stays inside the uploads directory.
$uploadsRoot = realpath(UPLOAD_DIR);
$fullPath    = realpath(UPLOAD_DIR . '/users/' . $userId . '/' . $file);

if ($fullPath === false || $uploadsRoot === false || !str_starts_with($fullPath, $uploadsRoot . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Document not found.');
}

if (!is_file($fullPath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Document not found.');
}

// Only serve the allowed document types.
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($fullPath);
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
if (!isset($allowed[$mime])) {
    http_response_code(415);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Unsupported document type.');
}

// Stream the file with no-store caching (sensitive data).
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($fullPath));
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
readfile($fullPath);
exit;