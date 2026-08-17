<?php
declare(strict_types=1);

$u = require_login();

// ---------------------------------------------------------------------------
// Applicant dashboard
// ---------------------------------------------------------------------------
if ($u['role'] === 'APPLICANT') {
    header_html('Dashboard');
    $s = db()->prepare("SELECT COUNT(*) FROM applications WHERE applicant_id = ? AND status = 'RESUBMISSION_REQUESTED'");
    $s->execute([$u['id']]);
    $resubmissions = (int) $s->fetchColumn();
    ?>
    <div class="hero">
        <div>
            <p class="eyebrow">YOUR VERIFICATION</p>
            <h1>Hello, <?= e($u['username']) ?>.</h1>
            <p>Create, complete, and monitor your KYC applications. The review team is notified the moment you submit.</p>
        </div>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <button class="button button-primary" name="action" value="create_application">+ New application</button>
        </form>
    </div>

    <?php if ($resubmissions > 0): ?>
        <div class="callout callout-warn">
            <strong>Action needed:</strong> one of your applications needs changes.
            <a class="button button-small" href="?page=applications">Review and resubmit</a>
        </div>
    <?php endif; ?>
    <?php
    footer_html();
    exit;
}

// ---------------------------------------------------------------------------
// Staff dashboards (ADMIN / SUPER_ADMIN / CEO)
// ---------------------------------------------------------------------------
require_role(STAFF_ROLES);

header_html(ucfirst(strtolower($u['role'])) . ' Dashboard');

$totalApps     = (int) db()->query('SELECT COUNT(*) FROM applications')->fetchColumn();
$pendingApps   = (int) db()->query("SELECT COUNT(*) FROM applications WHERE status IN ('SUBMITTED','UNDER_REVIEW')")->fetchColumn();
$approvedApps  = (int) db()->query("SELECT COUNT(*) FROM applications WHERE status = 'APPROVED'")->fetchColumn();
$rejectedApps  = (int) db()->query("SELECT COUNT(*) FROM applications WHERE status = 'REJECTED'")->fetchColumn();
$resubmits     = (int) db()->query("SELECT COUNT(*) FROM applications WHERE status = 'RESUBMISSION_REQUESTED'")->fetchColumn();
$userCount     = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$applicantCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'APPLICANT'")->fetchColumn();
$approvalRate  = $totalApps > 0 ? round($approvedApps / $totalApps * 100) : 0;
?>
<div class="hero">
    <div>
        <p class="eyebrow"><?= e(role_label($u['role'])) ?> OVERVIEW</p>
        <h1><?= e($u['role'] === 'CEO' ? 'Company verification' : 'Review workspace') ?></h1>
        <p>
            <?= $u['role'] === 'CEO'
                ? 'See how the whole company is performing across every KYC submission.'
                : ($u['role'] === 'SUPER_ADMIN'
                    ? 'Review applications and manage every user account and role on the platform.'
                    : 'Review applications awaiting a decision and keep the queue moving.') ?>
        </p>
        <p class="muted">Signed in as <strong><?= e($u['username']) ?></strong> · <?= e($u['email']) ?></p>
    </div>
    <?php if ($pendingApps > 0): ?>
        <a class="button button-primary" href="?page=review">Review queue (<?= $pendingApps ?>)</a>
    <?php endif; ?>
</div>

<div class="stats">
    <div class="stat"><strong><?= $totalApps ?></strong><span>Total applications</span></div>
    <div class="stat"><strong><?= $pendingApps ?></strong><span>Awaiting review</span></div>
    <div class="stat"><strong><?= $approvedApps ?></strong><span>Approved</span></div>
    <div class="stat"><strong><?= $rejectedApps ?></strong><span>Rejected</span></div>
    <div class="stat"><strong><?= $resubmits ?></strong><span>Changes requested</span></div>
    <?php if ($u['role'] !== 'ADMIN'): ?>
        <div class="stat"><strong><?= $approvalRate ?>%</strong><span>Approval rate</span></div>
    <?php endif; ?>
</div>

<?php if ($u['role'] === 'CEO'): ?>
    <div class="stats">
        <div class="stat"><strong><?= $applicantCount ?></strong><span>Applicants</span></div>
        <div class="stat"><strong><?= $userCount ?></strong><span>Total users</span></div>
    </div>
<?php endif; ?>

<?php if ($u['role'] === 'SUPER_ADMIN'): ?>
    <div class="callout callout-info">
        <strong>User management:</strong> create accounts and change roles from the users page.
        <a class="button button-small" href="?page=users">Manage users</a>
    </div>
<?php endif; ?>

<section class="card">
    <h2>Latest applications</h2>
    <?php
    $s = db()->query('SELECT a.id, a.status, a.updated_at, u.username applicant_name
                      FROM applications a JOIN users u ON u.id = a.applicant_id
                      ORDER BY a.updated_at DESC LIMIT 8');
    $recent = $s->fetchAll();
    if (!$recent): ?>
        <p class="empty">No applications yet. They will appear here as soon as applicants submit.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Applicant</th><th>Status</th><th>Updated</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recent as $a): ?>
                    <tr>
                        <td>#<?= $a['id'] ?></td>
                        <td><?= e($a['applicant_name']) ?></td>
                        <td><?= badge($a['status']) ?></td>
                        <td><?= e(date('d M Y, H:i', strtotime($a['updated_at']))) ?></td>
                        <td><a href="?page=application&id=<?= $a['id'] ?>">Open →</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
footer_html();
