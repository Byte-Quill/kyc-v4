<?php
declare(strict_types=1);

header_html('Sign in');
?>
<section class="auth">
    <div class="auth-intro">
        <p class="eyebrow">APPLICATION VERIFICATION</p>
        <h1>Know your customer,<br>with clarity.</h1>
        <p>Submit identity, address, education, and government documents securely. Our review team — CEO, Super Admin, and Admin — verifies every submission.</p>
        <ul class="auth-points">
            <li>Role-based dashboards for staff</li>
            <li>Instant email alerts on submission</li>
            <li>Full audit trail on every decision</li>
        </ul>
    </div>
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
