<?php
/**
 * pages/application.php — Single application detail page (?page=application&id=N).
 *
 * Shows everything about one KYC application, adapting to the viewer:
 *   - Owner + editable status (DRAFT / RESUBMISSION_REQUESTED):
 *       editable form, document upload forms, Save / Submit buttons
 *   - Otherwise: read-only summary
 *   - Staff + reviewable status (SUBMITTED / UNDER_REVIEW):
 *       the review decision panel (Approve / Reject / Request changes)
 *   - Final outcome card + full audit trail for everyone with access
 *
 * Access rule: only the applicant or staff may view (can_access()).
 */
declare(strict_types=1);

$u  = require_login();
$id = (int) ($_GET['id'] ?? 0);
$app = application_for($id);

if (!$app || !can_access($app, $u)) {
    http_response_code(404);
    header_html('Not found');
    echo '<section class="hero"><div><h1>Application not found</h1><p>It may have been removed, or you do not have access to it.</p></div></section>';
    footer_html();
    exit;
}

$isOwner   = $app['applicant_id'] == $u['id'];
$isStaff   = is_staff();
$editable  = $isOwner && in_array($app['status'], ['DRAFT', 'RESUBMISSION_REQUESTED'], true);
$reviewable = $isStaff && in_array($app['status'], ['SUBMITTED', 'UNDER_REVIEW'], true);

$s = db()->prepare('SELECT * FROM education WHERE user_id = ?');
$s->execute([$app['applicant_id']]);
$education = $s->fetch() ?: [];

$s = db()->prepare('SELECT * FROM additional_documents WHERE user_id = ?');
$s->execute([$app['applicant_id']]);
$additional = $s->fetch() ?: [];

$s = db()->prepare('SELECT l.*, u.username actor_name
                    FROM audit_logs l LEFT JOIN users u ON u.id = l.actor_id
                    WHERE application_id = ? ORDER BY l.created_at DESC');
$s->execute([$id]);
$audit = $s->fetchAll();

header_html('Application #' . $id);
?>

<div class="hero compact">
    <div>
        <p class="eyebrow">APPLICATION #<?= $id ?></p>
        <h1><?= e($app['full_name'] ?: 'New application') ?> <?= badge($app['status']) ?></h1>
        <p>Applicant: <?= e($app['applicant_name']) ?> · <?= e($app['applicant_email']) ?></p>
    </div>
</div>

<?php if ($app['status'] === 'RESUBMISSION_REQUESTED' && $isOwner): ?>
    <div class="callout callout-warn">
        <strong>Changes requested.</strong> The review team asked you to update this application.
        <?php if ($app['review_notes']): ?>
            <p class="callout-text">"<?= e($app['review_notes']) ?>"</p>
        <?php endif; ?>
        Make the corrections below and submit again.
    </div>
<?php endif; ?>

<?php if ($editable): ?>
    <!-- ===================== Editable form ===================== -->
    <form method="post" class="card form-grid" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="save_application">
        <input type="hidden" name="id" value="<?= $id ?>">

        <h2 class="grid-span">Personal and identity details</h2>
        <label>Full legal name *
            <input name="full_name" value="<?= e($app['full_name']) ?>" required>
        </label>
        <label>Date of birth *
            <input type="date" name="date_of_birth" value="<?= e($app['date_of_birth']) ?>" required>
        </label>
        <label>Nationality *
            <input name="nationality" value="<?= e($app['nationality']) ?>" required>
        </label>
        <label>ID type *
            <select name="id_type" required>
                <option value="">Select type</option>
                <?php foreach (['Citizenship', 'Passport', 'Driver License', 'National ID'] as $v): ?>
                    <option <?= $app['id_type'] === $v ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>ID number *
            <input name="id_number" value="<?= e($app['id_number']) ?>" required>
        </label>
        <label>ID expiry
            <input type="date" name="id_expiry" value="<?= e($app['id_expiry']) ?>">
        </label>
        <label>Issuing country
            <input name="issuing_country" value="<?= e($app['issuing_country']) ?>">
        </label>

        <h2 class="grid-span">Addresses</h2>
        <label class="grid-span">Permanent address *
            <textarea name="permanent_address" rows="2" required><?= e($app['permanent_address']) ?></textarea>
        </label>
        <label class="grid-span">Temporary address
            <textarea name="temporary_address" rows="2"><?= e($app['temporary_address']) ?></textarea>
        </label>

        <div class="grid-span form-actions">
            <button class="button button-primary" name="action" value="save_application">Save draft</button>
            <?php if (in_array($app['status'], ['DRAFT', 'RESUBMISSION_REQUESTED'], true)): ?>
                <button class="button button-success" name="action" value="submit"
                        onclick="return confirm('Submit this application for review? The review team will be notified.');">
                    Submit for review
                </button>
            <?php endif; ?>
        </div>
    </form>
<?php else: ?>
    <!-- ===================== Read-only summary ===================== -->
    <div class="card">
        <h2>Application summary</h2>
        <dl class="details">
            <div><dt>Full name</dt><dd><?= e($app['full_name'] ?: '—') ?></dd></div>
            <div><dt>Date of birth</dt><dd><?= e($app['date_of_birth'] ?: '—') ?></dd></div>
            <div><dt>Nationality</dt><dd><?= e($app['nationality'] ?: '—') ?></dd></div>
            <div><dt>ID type</dt><dd><?= e($app['id_type'] ?: '—') ?></dd></div>
            <div><dt>ID number</dt><dd><?= e($app['id_number'] ?: '—') ?></dd></div>
            <div><dt>ID expiry</dt><dd><?= e($app['id_expiry'] ?: '—') ?></dd></div>
            <div><dt>Issuing country</dt><dd><?= e($app['issuing_country'] ?: '—') ?></dd></div>
            <div><dt>Permanent address</dt><dd><?= nl2br(e($app['permanent_address'] ?: '—')) ?></dd></div>
            <div><dt>Temporary address</dt><dd><?= nl2br(e($app['temporary_address'] ?: '—')) ?></dd></div>
        </dl>
    </div>
<?php endif; ?>

<!-- ===================== Document uploads ===================== -->
<div class="card">
    <h2>Documents</h2>
    <div class="docs-grid">
        <div class="doc-group">
            <h3>Education</h3>
            <?php foreach (['see_document' => 'SEE certificate', 'slc_document' => 'SLC certificate', 'graduate_document' => 'Graduate certificate'] as $col => $label): ?>
                <div class="doc-item">
                    <strong><?= $label ?></strong>
                    <?= document_link($education[$col] ?? null, (int) $app['applicant_id']) ?>
                    <?php if ($editable): ?>
                        <form method="post" class="doc-upload" enctype="multipart/form-data">
                            <input type="hidden" name="csrf" value="<?= csrf() ?>">
                            <input type="hidden" name="action" value="upload_profile_document">
                            <input type="hidden" name="table" value="education">
                            <input type="hidden" name="column" value="<?= $col ?>">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="file" name="document" accept=".jpg,.jpeg,.png,.pdf" required>
                            <button class="button button-small">Upload</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="doc-group">
            <h3>Government ID</h3>
            <?php foreach (['citizenship_document' => 'Citizenship', 'passport_document' => 'Passport', 'license_document' => 'License'] as $col => $label): ?>
                <div class="doc-item">
                    <strong><?= $label ?></strong>
                    <?= document_link($additional[$col] ?? null, (int) $app['applicant_id']) ?>
                    <?php if ($editable): ?>
                        <form method="post" class="doc-upload" enctype="multipart/form-data">
                            <input type="hidden" name="csrf" value="<?= csrf() ?>">
                            <input type="hidden" name="action" value="upload_profile_document">
                            <input type="hidden" name="table" value="additional_documents">
                            <input type="hidden" name="column" value="<?= $col ?>">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="file" name="document" accept=".jpg,.jpeg,.png,.pdf" required>
                            <button class="button button-small">Upload</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ===================== Review panel (staff only) ===================== -->
<?php if ($reviewable): ?>
    <div class="card">
        <h2>Review decision</h2>
        <form method="post" class="review-form">
            <input type="hidden" name="csrf" value="<?= csrf() ?>">
            <input type="hidden" name="action" value="review">
            <input type="hidden" name="id" value="<?= $id ?>">
            <label>Notes (required for rejection or resubmission)
                <textarea name="review_notes" rows="3" placeholder="Explain what needs to change, or why this is being rejected…"></textarea>
            </label>
            <div class="review-actions">
                <button class="button button-success" name="decision" value="approve">Approve</button>
                <button class="button button-danger" name="decision" value="reject">Reject</button>
                <button class="button button-warn" name="decision" value="resubmit">Request changes</button>
            </div>
            <p class="hint">Choosing reject or request changes will email the applicant with your notes.</p>
        </form>
    </div>
<?php endif; ?>

<?php if ($app['status'] === 'APPROVED' || $app['status'] === 'REJECTED' || $app['status'] === 'RESUBMISSION_REQUESTED'): ?>
    <div class="card">
        <h2>Review outcome</h2>
        <dl class="details">
            <div><dt>Decision</dt><dd><?= badge($app['status']) ?></dd></div>
            <?php if ($app['reviewer_name']): ?><div><dt>Reviewed by</dt><dd><?= e($app['reviewer_name']) ?></dd></div><?php endif; ?>
            <?php if ($app['reviewed_at']): ?><div><dt>Reviewed at</dt><dd><?= e(date('d M Y, H:i', strtotime($app['reviewed_at']))) ?></dd></div><?php endif; ?>
            <?php if ($app['review_notes']): ?><div><dt>Notes</dt><dd><?= nl2br(e($app['review_notes'])) ?></dd></div><?php endif; ?>
        </dl>
    </div>
<?php endif; ?>

<!-- ===================== Audit trail ===================== -->
<div class="card">
    <h2>Audit trail</h2>
    <?php if (!$audit): ?>
        <p class="empty">No events recorded yet.</p>
    <?php else: ?>
        <ol class="timeline">
            <?php foreach ($audit as $entry): ?>
                <li>
                    <span class="tl-dot"></span>
                    <div>
                        <strong><?= e($entry['action']) ?></strong>
                        <?php if ($entry['detail']): ?><p><?= e($entry['detail']) ?></p><?php endif; ?>
                        <small class="muted"><?= e($entry['actor_name'] ?? 'System') ?> · <?= e(date('d M Y, H:i', strtotime($entry['created_at']))) ?></small>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</div>

<?php
footer_html();
