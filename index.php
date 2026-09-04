<?php
declare(strict_types=1);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

// ---------------------------------------------------------------------------
// Front controller — pure PHP + MySQL, runs directly in XAMPP (no build step).
//
//   GET  ?page=<name>   renders pages/<name>.php
//   POST action=<name>  is dispatched through actions.php, then redirected
//
// The JSON API endpoints (api.php / api_actions.php) remain available as
// separate entry points. The React app in frontend/ is kept for reference.
// ---------------------------------------------------------------------------

// The PHP built-in server runs in router mode (php -S ... index.php), so every
// request arrives here — including real files like assets/style.css, api.php
// and uploads/... Under Apache the web server serves real files directly and
// applies .htaccess rules, so this branch is a no-op there.
if (PHP_SAPI === 'cli-server') {
    $assetPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($assetPath !== '/index.php' && $assetPath !== '/') {
        $real = realpath(__DIR__ . $assetPath);
        if ($real !== false && is_file($real) && str_starts_with($real, realpath(__DIR__))) {
            // Mirror the .htaccess deny rules: never serve config, docs,
            // scripts, VCS data or lock files from the dev server either.
            $banned = '/\.(sql|md|env|sh|bak|lock|json|phar)$|(^|\/)\.(git|env)/i';
            if (preg_match($banned, $assetPath)) {
                http_response_code(404);
                exit;
            }
            // Compress text assets and let browsers cache them for a week
            // (layout.php cache-busts the stylesheet via its mtime).
            $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
            if (in_array($ext, ['css', 'js', 'svg', 'json'], true)) {
                $content = (string) file_get_contents($real);
                $etag = '"' . md5($content . filesize($real)) . '"';
                header('ETag: ' . $etag);
                header('Cache-Control: public, max-age=604800');
                if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
                    http_response_code(304);
                    exit;
                }
                header('Content-Type: ' . ($ext === 'css' ? 'text/css' : ($ext === 'js' ? 'application/javascript' : ($ext === 'svg' ? 'image/svg+xml' : 'application/json'))));
                if (str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip')
                    && function_exists('gzencode')) {
                    header('Content-Encoding: gzip');
                    header('Vary: Accept-Encoding');
                    echo gzencode($content, 6);
                } else {
                    echo $content;
                }
                exit;
            }
            return false; // let the built-in server stream it (uploads etc.)
        }
    }
}

// --- Handle POST actions first (classic form submissions) -------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_once __DIR__ . '/actions.php';
    try {
        handle_action($_POST['action'] ?? '');
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    } catch (PDOException $e) {
        error_log('KYC Verify database error: ' . $e->getMessage());
        if ($e->getCode() === '23000') {
            flash('error', 'That email address is already registered.');
        } elseif ($e->getCode() === '42S02') {
            flash('error', 'The database schema is incomplete. Import install.sql into the kyc_system database.');
        } else {
            flash('error', 'Unable to connect to the database. Start MySQL and verify the settings in .env.');
        }
    }
}

// --- Route to a page ---------------------------------------------------------
$u    = user();
$page = $_GET['page'] ?? ($u ? 'dashboard' : 'login');

// Pages that require authentication redirect to the login page.
$publicPages = ['login', 'register'];
if (!in_array($page, $publicPages, true) && !$u) {
    redirect('?page=login');
}

$allowed = ['login', 'register', 'dashboard', 'applications', 'application', 'review', 'users', 'ceo'];
if (!in_array($page, $allowed, true)) {
    http_response_code(404);
    header_html('Not found');
    echo '<section class="hero"><div><h1>Page not found</h1><p>The page you are looking for does not exist.</p><p><a href="?page=dashboard">Go to the dashboard →</a></p></div></section>';
    footer_html();
    exit;
}

require __DIR__ . '/pages/' . $page . '.php';
