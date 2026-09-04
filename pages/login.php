<?php
/**
 * pages/login.php — Sign-in page (public).
 *
 * Renders the login form. Submitting it POSTs action=login to index.php,
 * which dispatches to actions.php. On success the user lands on the
 * role-based dashboard; on failure an error flash is shown.
 */
declare(strict_types=1);

header_html('Sign in');
?>
<section class="auth auth-center">
    <form method="post" class="card auth-card">
        <h2>Welcome back</h2>
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="login">
        <label>Email
            <input type="email" name="email" required autofocus autocomplete="email">
        </label>
        <label>Password
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="button button-block">Sign in</button>
        <p class="muted">New here? <a href="?page=register">Create an account</a></p>
    </form>
</section>
<?php
footer_html();
