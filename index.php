<?php
declare(strict_types=1);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/actions.php';
require_once __DIR__ . '/layout.php';

// ---------------------------------------------------------------------------
// POST: verify CSRF and dispatch to the action handler.
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        handle_action($_POST['action'] ?? '');
    } catch (PDOException $e) {
        error_log('KYC Verify database error: ' . $e->getMessage());
        if ($e->getCode() === '23000') {
            $message = 'That email address is already registered.';
        } elseif ($e->getCode() === '42S02') {
            $message = 'The database schema is incomplete. Import install.sql into the kyc_system database.';
        } else {
            $message = 'Unable to connect to the database. Start MySQL and verify the settings in config.php.';
        }
        flash('error', $message);
        redirect('?page=' . ($_GET['page'] ?? 'dashboard'));
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect('?page=' . ($_GET['page'] ?? 'dashboard') . (!empty($_POST['id']) ? '&id=' . (int) $_POST['id'] : ''));
    }
}

// ---------------------------------------------------------------------------
// GET: route to a page. Pages render their own HTML using header_html().
// ---------------------------------------------------------------------------
$page = $_GET['page'] ?? (user() ? 'dashboard' : 'login');

$publicPages = ['login', 'register'];
if (in_array($page, $publicPages, true) && user()) {
    redirect('?page=dashboard');
}
if (!in_array($page, $publicPages, true) && !user()) {
    redirect('?page=login');
}

$pageFile = __DIR__ . '/pages/' . basename($page) . '.php';
if (!is_file($pageFile)) {
    http_response_code(404);
    header_html('Not found');
    echo '<section class="hero"><div><h1>Page not found</h1><p>That page does not exist.</p></div></section>';
    footer_html();
    exit;
}

require $pageFile;
