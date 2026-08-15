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

    <div class="callout callout-info demo-logins">
        <h3>Demo accounts</h3>
        <p class="callout-text">One-click sign-in for the seeded staff roles (password: <code>Password123</code>):</p>
        <div class="demo-logins-grid">
            <?php
            $demo = [
                'CEO'         => 'ceo@kyc.local',
                'Super Admin' => 'superadmin@kyc.local',
                'Admin'       => 'admin@kyc.local',
            ];
            foreach ($demo as $label => $email):
            ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= csrf() ?>">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="email" value="<?= e($email) ?>">
                    <input type="hidden" name="password" value="Password123">
                    <button class="button button-small"><?= e($label) ?></button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php
footer_html();
