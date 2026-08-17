<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// KYC Verify — JSON read API (GET)
// ---------------------------------------------------------------------------
// Same session bootstrap as index.php so the API shares the session cookie
// and CSRF token. Every response is { ok: true, data } or { ok: false, error }.
// ---------------------------------------------------------------------------

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    // -----------------------------------------------------------------------
    // Public
    // -----------------------------------------------------------------------
    case 'csrf':
        // Expose the per-session CSRF token so the SPA can send it on POSTs.
        json_ok(['csrf' => csrf()]);

    case 'me':
        $u = user();
        if (!$u) {
            json_error('Not signed in.', 401);
        }
        json_ok($u);

    // -----------------------------------------------------------------------
    // Authenticated
    // -----------------------------------------------------------------------
    case 'dashboard':
        $u = api_require_login();
        if (in_array($u['role'], STAFF_ROLES, true)) {
            $totalApps     = (int) db()->query('SELECT COUNT(*) FROM applications')->fetchColumn();
            $pendingApps   = (int) db()->query("SELECT COUNT(*) FROM applications WHERE status IN ('SUBMITTED','UNDER_REVIEW')")->fetchColumn();
            $approvedApps  = (int) db()->query("SELECT COUNT(*) FROM applications WHERE status = 'APPROVED'")->fetchColumn();
            $rejectedApps  = (int) db()->query("SELECT COUNT(*) FROM applications WHERE status = 'REJECTED'")->fetchColumn();
            $resubmits     = (int) db()->query("SELECT COUNT(*) FROM applications WHERE status = 'RESUBMISSION_REQUESTED'")->fetchColumn();
            $userCount     = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $applicantCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'APPLICANT'")->fetchColumn();
            $approvalRate  = $totalApps > 0 ? round($approvedApps / $totalApps * 100) : 0;

            $recent = db()->query('SELECT a.id, a.status, a.updated_at, u.username applicant_name
                                   FROM applications a JOIN users u ON u.id = a.applicant_id
                                   ORDER BY a.updated_at DESC LIMIT 8')->fetchAll();

            json_ok([
                'stats' => [
                    'total' => $totalApps,
                    'pending' => $pendingApps,
                    'approved' => $approvedApps,
                    'rejected' => $rejectedApps,
                    'resubmits' => $resubmits,
                    'approval_rate' => $approvalRate,
                    'applicants' => $applicantCount,
                    'users' => $userCount,
                ],
                'recent' => $recent,
            ]);
        }

        $s = db()->prepare("SELECT COUNT(*) FROM applications WHERE applicant_id = ? AND status = 'RESUBMISSION_REQUESTED'");
        $s->execute([$u['id']]);
        $resubmissions = (int) $s->fetchColumn();

        json_ok([
            'resubmissions' => $resubmissions,
            'recent' => db()->query('SELECT 1')->fetchAll() ?: [], // keep shape stable; applicant list lives in `applications`
        ]);

    case 'applications':
        $u = api_require_login();
        if (in_array($u['role'], STAFF_ROLES, true)) {
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
        json_ok($apps);

    case 'application':
        $u  = api_require_login();
        $id = (int) ($_GET['id'] ?? 0);
        $app = application_for($id);

        if (!$app || !can_access($app, $u)) {
            json_error('Application not found.', 404);
        }

        $isOwner   = $app['applicant_id'] == $u['id'];
        $isStaff   = in_array($u['role'], STAFF_ROLES, true);
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

        $docs = [];
        foreach (['education' => $education, 'additional_documents' => $additional] as $table => $row) {
            foreach (DOCUMENT_FIELDS[$table] as $col) {
                $docs[$table][$col] = document_url($row[$col] ?? null, (int) $app['applicant_id']);
            }
        }

        json_ok([
            'application' => $app,
            'permissions' => [
                'is_owner' => $isOwner,
                'is_staff' => $isStaff,
                'editable' => $editable,
                'reviewable' => $reviewable,
            ],
            'documents' => $docs,
            'audit' => $audit,
        ]);

    case 'review_queue':
        api_require_role(STAFF_ROLES);
        $apps = db()->query("SELECT a.*, u.username applicant_name
                             FROM applications a JOIN users u ON u.id = a.applicant_id
                             WHERE a.status IN ('SUBMITTED','UNDER_REVIEW')
                             ORDER BY a.created_at ASC")->fetchAll();
        json_ok($apps);

    case 'users':
        api_require_role(['SUPER_ADMIN']);
        $users  = db()->query('SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC')->fetchAll();
        $counts = db()->query("SELECT role, COUNT(*) c FROM users GROUP BY role")->fetchAll();
        json_ok([
            'users' => $users,
            'counts' => $counts,
            'roles' => ['APPLICANT', 'ADMIN', 'SUPER_ADMIN', 'CEO'],
        ]);

    case 'ceo':
        api_require_role(['CEO']);
        $total       = (int) db()->query('SELECT COUNT(*) FROM applications')->fetchColumn();
        $submitted   = (int) db()->query("SELECT COUNT(*) FROM applications WHERE status = 'SUBMITTED'")->fetchColumn();
        $underReview = (int) db()->query("SELECT COUNT(*) FROM applications WHERE status = 'UNDER_REVIEW'")->fetchColumn();
        $approved    = (int) db()->query("SELECT COUNT(*) FROM applications WHERE status = 'APPROVED'")->fetchColumn();
        $rejected    = (int) db()->query("SELECT COUNT(*) FROM applications WHERE status = 'REJECTED'")->fetchColumn();
        $resubmits   = (int) db()->query("SELECT COUNT(*) FROM applications WHERE status = 'RESUBMISSION_REQUESTED'")->fetchColumn();
        $pending     = $submitted + $underReview;
        $approvalRate = $total > 0 ? round($approved / $total * 100) : 0;
        $applicants  = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'APPLICANT'")->fetchColumn();
        $emailsSent  = (int) db()->query('SELECT COUNT(*) FROM email_logs')->fetchColumn();
        $emailsFailed = (int) db()->query("SELECT COUNT(*) FROM email_logs WHERE status = 'FAILED'")->fetchColumn();

        $pipeline = db()->query('SELECT status, COUNT(*) c FROM applications GROUP BY status ORDER BY c DESC')->fetchAll();
        $recent   = db()->query('SELECT a.id, a.status, u.username applicant_name, a.created_at
                                 FROM applications a JOIN users u ON u.id = a.applicant_id
                                 ORDER BY a.created_at DESC LIMIT 8')->fetchAll();

        json_ok([
            'stats' => [
                'total' => $total,
                'submitted' => $submitted,
                'under_review' => $underReview,
                'approved' => $approved,
                'rejected' => $rejected,
                'resubmits' => $resubmits,
                'pending' => $pending,
                'approval_rate' => $approvalRate,
                'applicants' => $applicants,
                'emails_sent' => $emailsSent,
                'emails_failed' => $emailsFailed,
            ],
            'pipeline' => $pipeline,
            'recent' => $recent,
        ]);

    default:
        json_error('Unknown API action.', 404);
}
