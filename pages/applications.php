<?php
/**
 * pages/applications.php — Applications list (?page=applications).
 *
 * Staff (ADMIN / SUPER_ADMIN / CEO) see every application in the company;
 * applicants see only their own. Applicants also get the "+ New application"
 * button, which POSTs action=create_application.
 */
declare(strict_types=1);

$u = require_login();
header_html('Applications');

$isStaff = is_staff();
if ($isStaff) {
    $apps = db()->query('SELECT a.*, u.username applicant_name
                         FROM applications a JOIN users u ON u.id = a.applicant_id
                         ORDER BY a.updated_at DESC')->fetchAll();
} else {
    $s = db()->prepare('SELECT a.*, u.username applicant_name
                        FROM applications a JOIN users u ON u.id = a.applicant_id
                        WHERE a.applicant_id = ? ORDER BY a.updated_at DESC');
    $s->execute([$u['id']]);
    $apps = $s->fetchAll();
}
?>
<div class="hero compact">
    <div>
        <p class="eyebrow"><?= $isStaff ? 'ALL APPLICATIONS' : 'MY APPLICATIONS' ?></p>
        <h1>Applications</h1>
        <p><?= $isStaff ? 'Every KYC submission across the company.' : 'Create a new verification or continue an existing draft.' ?></p>
    </div>
    <?php if (!$isStaff): ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <button class="button button-primary" name="action" value="create_application">+ New application</button>
        </form>
    <?php endif; ?>
</div>

<div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <?php if ($isStaff): ?><th>Applicant</th><?php endif; ?>
            <th>Status</th>
            <th>Updated</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($apps as $a): ?>
            <tr>
                <td>#<?= $a['id'] ?></td>
                <?php if ($isStaff): ?><td><?= e($a['applicant_name']) ?></td><?php endif; ?>
                <td><?= badge($a['status']) ?></td>
                <td><?= e(date('d M Y, H:i', strtotime($a['updated_at']))) ?></td>
                <td><a href="?page=application&id=<?= $a['id'] ?>">Open →</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$apps): ?>
            <tr><td colspan="<?= $isStaff ? 5 : 4 ?>" class="empty">No applications found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
footer_html();
