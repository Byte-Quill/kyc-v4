<?php
declare(strict_types=1);

$u = require_role(['CEO']);
header_html('Analytics');

// ---------------------------------------------------------------------------
// KPIs
// ---------------------------------------------------------------------------
$total     = (int) db()->query('SELECT COUNT(*) FROM applications')->fetchColumn();
$submitted = (int) db()->query("SELECT COUNT(*) FROM applications WHERE status = 'SUBMITTED'")->fetchColumn();
$underReview = (int) db()->query("SELECT COUNT(*) FROM applications WHERE status = 'UNDER_REVIEW'")->fetchColumn();
$approved  = (int) db()->query("SELECT COUNT(*) FROM applications WHERE status = 'APPROVED'")->fetchColumn();
$rejected  = (int) db()->query("SELECT COUNT(*) FROM applications WHERE status = 'REJECTED'")->fetchColumn();
$resubmits = (int) db()->query("SELECT COUNT(*) FROM applications WHERE status = 'RESUBMISSION_REQUESTED'")->fetchColumn();
$pending   = $submitted + $underReview;
$approvalRate = $total > 0 ? round($approved / $total * 100) : 0;
$applicants   = (int) db()->query("SELECT COUNT(*) FROM user_roles WHERE role = 'APPLICANT'")->fetchColumn();
$emailsSent   = (int) db()->query('SELECT COUNT(*) FROM email_logs')->fetchColumn();
$emailsFailed = (int) db()->query("SELECT COUNT(*) FROM email_logs WHERE status = 'FAILED'")->fetchColumn();
?>
<div class="hero compact">
    <div>
        <p class="eyebrow">CEO ANALYTICS</p>
        <h1>Company overview</h1>
        <p>High-level view of the verification pipeline and applicant activity.</p>
    </div>
</div>

<div class="stats">
    <div class="stat"><strong><?= $total ?></strong><span>Total applications</span></div>
    <div class="stat"><strong><?= $pending ?></strong><span>Awaiting review</span></div>
    <div class="stat"><strong><?= $approved ?></strong><span>Approved</span></div>
    <div class="stat"><strong><?= $rejected ?></strong><span>Rejected</span></div>
    <div class="stat"><strong><?= $approvalRate ?>%</strong><span>Approval rate</span></div>
</div>

<div class="stats">
    <div class="stat"><strong><?= $applicants ?></strong><span>Registered applicants</span></div>
    <div class="stat"><strong><?= $resubmits ?></strong><span>Awaiting resubmission</span></div>
    <div class="stat"><strong><?= $emailsSent ?></strong><span>Emails sent</span></div>
    <?php if ($emailsFailed > 0): ?>
        <div class="stat stat-bad"><strong><?= $emailsFailed ?></strong><span>Emails failed</span></div>
    <?php endif; ?>
</div>

<div class="grid-2">
    <div class="card">
        <h2>Pipeline by status</h2>
        <?php
        $rows = db()->query('SELECT status, COUNT(*) c FROM applications GROUP BY status ORDER BY c DESC')->fetchAll();
        $max  = max(array_column($rows, 'c') ?: [1]);
        if (!$rows): ?>
            <p class="empty">No applications yet.</p>
        <?php else: ?>
            <ul class="bars">
                <?php foreach ($rows as $row): ?>
                    <li>
                        <span class="bar-label"><?= e(format_status($row['status'])) ?></span>
                        <span class="bar-track"><span class="bar-fill badge-<?= status_class($row['status']) ?>" style="width: <?= round($row['c'] / $max * 100) ?>%"></span></span>
                        <span class="bar-value"><?= $row['c'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Recent submissions</h2>
        <?php
        $recent = db()->query('SELECT a.id, a.status, u.username applicant_name, a.created_at
                               FROM applications a JOIN users u ON u.id = a.applicant_id
                               ORDER BY a.created_at DESC LIMIT 8')->fetchAll();
        if (!$recent): ?>
            <p class="empty">Nothing submitted yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Applicant</th><th>Status</th><th>Submitted</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $a): ?>
                        <tr>
                            <td><a href="?page=application&id=<?= $a['id'] ?>"><?= e($a['applicant_name']) ?></a></td>
                            <td><?= badge($a['status']) ?></td>
                            <td><?= e(date('d M Y', strtotime($a['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
footer_html();
