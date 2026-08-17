<?php
declare(strict_types=1);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// ---------------------------------------------------------------------------
// API endpoints are separate entry points; this file only serves the SPA.
// In development the Vite dev server proxies /api and /uploads to this server,
// and serves its own index.html — so this shell is only used for production
// builds (dist/) and as a fallback for client-side routes.
// ---------------------------------------------------------------------------

$DIST = __DIR__ . '/dist';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = rtrim($path, '/') ?: '/';

// ---------------------------------------------------------------------------
// The PHP built-in server runs in router mode (php -S ... index.php), so every
// request arrives here — including real files like api.php, api_actions.php
// and uploads/... Let the built-in server serve those as-is. Under Apache the
// .htaccess rewrite already lets real files through, so this branch is a no-op
// there.
// ---------------------------------------------------------------------------
if (PHP_SAPI === 'cli-server') {
    $real = realpath(__DIR__ . $path);
    if ($real !== false && is_file($real) && str_starts_with($real, realpath(__DIR__))) {
        return false;
    }
}

// Serve real files from dist/ (JS, CSS, images, favicon, ...) so the built
// SPA can be deployed in production without Apache rewrite rules.
if ($path !== '/' && $path !== '/index.php') {
    $candidate = realpath($DIST . $path);
    if ($candidate !== false && str_starts_with($candidate, realpath($DIST))) {
        $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'js'   => 'application/javascript',
            'css'  => 'text/css',
            'html' => 'text/html',
            'svg'  => 'image/svg+xml',
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'ico'  => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'json' => 'application/json',
            default => 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        readfile($candidate);
        exit;
    }
}

// Serve the built SPA shell. In development the Vite server handles this.
$shell = $DIST . '/index.html';
if (is_file($shell)) {
    // Compute the base path for relative asset URLs so deep client-side
    // routes (e.g. /kyc-v4/application/5) still resolve ./assets/... correctly.
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    $base = ($base === '' || $base === '.') ? '/' : $base . '/';

    $html = file_get_contents($shell);
    if ($html !== false && $base !== '/') {
        $html = str_replace('<head>', '<head>' . "\n    " . '<base href="' . htmlspecialchars($base, ENT_QUOTES) . '">', $html);
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
}

// No production build yet — point the browser at the Vite dev server.
header('Location: http://localhost:5173/');
exit;
