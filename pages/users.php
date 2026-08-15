<?php
declare(strict_types=1);

$u = require_role(['SUPER_ADMIN']);
header_html('User management');

$users = db()->query('SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC')->fetchAll();
$roles = ['APPLICANT', 'ADMIN', 'SUPER_ADMIN', 'CEO'];
?>
<div class="hero compact">
    <div>
        <p class="eyebrow">SUPER ADMIN</p>
        <h1>User management</h1>
        <p>Create accounts and control roles across the platform.</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h2>Create user</h2>
        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="create_user">
            <label>Username
                <input name="username" required maxlength="120">
            </label>
            <label>Email
                <input type="email" name="email" required>
            </label>
            <label>Password
                <input type="password" name="password" minlength="8" required>
                <small class="hint">At least 8 characters.</small>
            </label>
            <label>Role
                <select name="role" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role ?>"><?= e(role_label($role)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="button button-primary">Create user</button>
        </form>
    </div>

    <div class="card">
        <h2>Platform summary</h2>
        <div class="stats stats-inline">
            <div class="stat"><strong><?= count($users) ?></strong><span>Total users</span></div>
            <?php
            $counts = db()->query("SELECT role, COUNT(*) c FROM users GROUP BY role")->fetchAll();
            foreach ($counts as $row): ?>
                <div class="stat"><strong><?= $row['c'] ?></strong><span><?= e(role_label($row['role'])) ?>s</span></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card">
    <h2>All users</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $member): ?>
                <tr>
                    <td>#<?= $member['id'] ?></td>
                    <td><?= e($member['username']) ?></td>
                    <td><?= e($member['email']) ?></td>
                    <td><?= e(role_label($member['role'])) ?></td>
                    <td><?= e(date('d M Y', strtotime($member['created_at']))) ?></td>
                    <td>
                        <?php if ($member['id'] != $u['id']): ?>
                            <details class="inline-edit">
                                <summary>Manage</summary>
                                <form method="post" class="stack inline-stack">
                                    <input type="hidden" name="csrf" value="<?= csrf() ?>">
                                    <input type="hidden" name="id" value="<?= $member['id'] ?>">
                                    <label>Role
                                        <select name="role">
                                            <?php foreach ($roles as $role): ?>
                                                <option value="<?= $role ?>" <?= $member['role'] === $role ? 'selected' : '' ?>><?= e(role_label($role)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <button class="button button-small" name="action" value="update_user">Update role</button>
                                </form>
                                <form method="post" class="stack inline-stack">
                                    <input type="hidden" name="csrf" value="<?= csrf() ?>">
                                    <input type="hidden" name="id" value="<?= $member['id'] ?>">
                                    <label>New password
                                        <input type="password" name="password" minlength="8" placeholder="At least 8 characters" required>
                                    </label>
                                    <button class="button button-small button-warn" name="action" value="reset_password">Reset password</button>
                                </form>
                            </details>
                        <?php else: ?>
                            <span class="muted">You</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
footer_html();
