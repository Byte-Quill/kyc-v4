<?php
declare(strict_types=1);

/**
 * Shared page chrome. Pages render their own content between header_html()
 * and footer_html(). Layout is role-aware so each staff role sees its own
 * navigation and dashboard entry points.
 */

function header_html(string $title): void
{
    $u = user();
    $nav = [];

    if ($u) {
        $nav[] = ['dashboard', 'Dashboard'];
        $nav[] = ['applications', 'Applications'];
        if (is_staff()) {
            $nav[] = ['review', 'Review queue'];
        }
        if ($u['role'] === 'SUPER_ADMIN') {
            $nav[] = ['users', 'Users'];
        }
        if ($u['role'] === 'CEO') {
            $nav[] = ['ceo', 'Analytics'];
        }
    }
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= APP_NAME ?></title>
    <?php
    // Cache-bust the stylesheet with its modification time so browsers can
    // cache it aggressively (see .htaccess) yet always pick up new styles.
    $cssFile = __DIR__ . '/assets/style.css';
    $cssVersion = is_file($cssFile) ? (string) filemtime($cssFile) : '1';
    ?>
    <link rel="stylesheet" href="assets/style.css?v=<?= e($cssVersion) ?>">
</head>
<body>
<header>
    <a class="brand" href="?page=dashboard">KYC<span>Verify</span></a>
    <?php if ($u): ?>
        <nav>
            <?php foreach ($nav as [$href, $label]): ?>
                <a href="?page=<?= $href ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            <span class="user">
                <?= e($u['username']) ?>
                <small><?= e(role_label($u['role'])) ?></small>
            </span>
            <form method="post" class="inline">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                <button class="link" name="action" value="logout">Sign out</button>
            </form>
        </nav>
    <?php endif; ?>
</header>
<main>
<?php foreach (flashes() as $f): ?>
    <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>
<?php
}

function footer_html(): void
{
    ?>
</main>
<footer><?= APP_NAME ?> · Secure identity verification</footer>
</body>
</html>
<?php
}

/**
 * Small helper to render a card with a title and content — keeps pages tidy.
 */
function card(string $title, string $contentHtml): string
{
    return '<section class="card"><h2>' . e($title) . '</h2>' . $contentHtml . '</section>';
}
