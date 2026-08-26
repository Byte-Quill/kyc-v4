<?php
declare(strict_types=1);

header_html('Create account');
?>
<section class="auth">
    <div class="auth-intro">
        <p class="eyebrow">START YOUR APPLICATION</p>
        <h1>Verification begins here.</h1>
        <p>Create your profile, complete the KYC form, and upload the documents required for verification.</p>
        <ul class="auth-points">
            <li>Your addresses are stored safely</li>
            <li>SEE, SLC, and graduate certificates</li>
            <li>Citizenship, passport, or license</li>
        </ul>
    </div>
    <form method="post" class="card auth-card">
        <h2>Create account</h2>
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="register">
        <label>Username
            <input name="username" required maxlength="120" autocomplete="username">
        </label>
        <label>Email
            <input type="email" name="email" required autocomplete="email">
        </label>
        <label>Password
            <input type="password" name="password" minlength="8" required autocomplete="new-password">
            <small class="hint">At least 8 characters.</small>
        </label>
        <button class="button button-block">Create account</button>
        <p class="muted">Already registered? <a href="?page=login">Sign in</a></p>
    </form>
</section>
<?php
footer_html();
