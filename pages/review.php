<?php
declare(strict_types=1);

$u = require_role(STAFF_ROLES);
header_html('Review queue');

$apps = db()->query("SELECT a.*, u.username applicant_name
                     FROM applications a JOIN users u ON u.id = a.applicant_id
                     WHERE a.status IN ('SUBMITTED','UNDER_REVIEW')
                     ORDER BY a.created_at ASC")->fetchAll();
?>
<div class="hero compact">
    <div>
        <p class="eyebrow">REVIEW WORKSPACE</p>
        <h1>Review queue</h1>
        <p>Applications submitted by users, waiting for a decision.</p>
    </div>
</div>

<?php if ($apps): ?>
    <div class="callout callout-info"><?= count($apps) ?> application<?= count($apps) > 1 ? 's' : '' ?> awaiting a decision.</div>
<?php endif; ?>

<div class="table-wrap">
    <table>
        <thead>
        <tr><th>ID</th><th>Applicant</th><th>Submitted</th><th>Details</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($apps as $a): ?>
            <tr>
                <td>#<?= $a['id'] ?></td>
                <td><?= e($a['applicant_name']) ?></td>
                <td><?= e(date('d M Y, H:i', strtotime($a['updated_at']))) ?></td>
                <td><?= e($a['full_name'] ?: '—') ?></td>
                <td><a class="button button-small" href="?page=application&id=<?= $a['id'] ?>">Review</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$apps): ?>
            <tr><td colspan="5" class="empty">The queue is clear. Nothing to review right now.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
footer_html();
