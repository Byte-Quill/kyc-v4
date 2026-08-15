<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';

/**
 * Handle a POST request. verify_csrf() has already been called by index.php.
 * On success it redirects; on failure it throws so index.php can show the error.
 */
function handle_action(string $action): void
{
    switch ($action) {
        // -------------------------------------------------------------------
        // Auth
        // -------------------------------------------------------------------
        case 'register':
            $username = trim($_POST['username'] ?? '');
            $email    = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';
            if (strlen($username) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
                throw new RuntimeException('Enter a username, valid email, and password of at least 8 characters.');
            }
            db()->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)')
                ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
            flash('success', 'Account created. Please sign in.');
            redirect('?page=login');

        case 'login':
            $s = db()->prepare('SELECT * FROM users WHERE email = ?');
            $s->execute([strtolower(trim($_POST['email'] ?? ''))]);
            $account = $s->fetch();
            if (!$account || !password_verify($_POST['password'] ?? '', $account['password_hash'])) {
                throw new RuntimeException('Incorrect email or password.');
            }
            session_regenerate_id(true);
            unset($account['password_hash']);
            $_SESSION['user'] = $account;
            redirect('?page=dashboard');

        case 'logout':
            session_unset();
            session_destroy();
            session_start();
            flash('success', 'You have signed out.');
            redirect('?page=login');

        default:
            $u = require_login();
            match ($action) {
                // -------------------------------------------------------------------
                // Applicant application workflow
                // -------------------------------------------------------------------
                'create_application' => create_application($u),
                'save_application'   => save_application($u),
                'upload_profile_document' => upload_profile_document($u),
                'submit'             => submit_application($u),

                // -------------------------------------------------------------------
                // Staff review workflow
                // -------------------------------------------------------------------
                'review'             => review_application($u),

                // -------------------------------------------------------------------
                // Super Admin user management
                // -------------------------------------------------------------------
                'create_user'        => create_user($u),
                'update_user'        => update_user($u),
                'reset_password'     => reset_password($u),

                default => throw new RuntimeException('Unknown action.'),
            };
    }
}

// ---------------------------------------------------------------------------
// Applicant actions
// ---------------------------------------------------------------------------

function editable_application(array $u): array
{
    $id   = (int) ($_POST['id'] ?? 0);
    $app  = application_for($id);
    if (!$app || $app['applicant_id'] != $u['id'] || !in_array($app['status'], ['DRAFT', 'RESUBMISSION_REQUESTED'], true)) {
        throw new RuntimeException('This application cannot be edited.');
    }
    return $app;
}

function create_application(array $u): void
{
    db()->prepare('INSERT INTO applications (applicant_id) VALUES (?)')->execute([$u['id']]);
    $id = (int) db()->lastInsertId();
    log_action($id, (int) $u['id'], 'CREATED', 'Application draft created.');
    flash('success', 'Application draft created.');
    redirect('?page=application&id=' . $id);
}

function save_application(array $u): void
{
    $app = editable_application($u);
    $id  = (int) $app['id'];

    $fields = ['full_name', 'date_of_birth', 'nationality', 'id_type', 'id_number', 'id_expiry', 'issuing_country'];
    $values = [];
    foreach ($fields as $f) {
        $values[] = trim($_POST[$f] ?? '') ?: null;
    }
    db()->prepare('UPDATE applications SET ' . implode(',', array_map(fn ($f) => "$f = ?", $fields)) . ' WHERE id = ?')
        ->execute([...$values, $id]);

    $permanent = trim($_POST['permanent_address'] ?? '');
    $temporary = trim($_POST['temporary_address'] ?? '');
    if (!$permanent) {
        throw new RuntimeException('Permanent address is required.');
    }
    db()->prepare('INSERT INTO addresses (user_id, permanent_address, temporary_address) VALUES (?, ?, ?)
                   ON DUPLICATE KEY UPDATE permanent_address = VALUES(permanent_address), temporary_address = VALUES(temporary_address)')
        ->execute([$u['id'], $permanent, $temporary ?: null]);

    log_action($id, (int) $u['id'], 'UPDATED', 'KYC information and addresses updated.');
    flash('success', 'Draft saved.');
    redirect('?page=application&id=' . $id);
}

function upload_profile_document(array $u): void
{
    $app = editable_application($u);
    $id  = (int) $app['id'];

    $table  = $_POST['table'] ?? '';
    $column = $_POST['column'] ?? '';
    save_user_document((int) $u['id'], $table, $column, $_FILES['document'] ?? []);

    log_action($id, (int) $u['id'], 'DOCUMENT_UPLOADED', str_replace('_', ' ', $column) . ' uploaded.');
    flash('success', 'Document uploaded.');
    redirect('?page=application&id=' . $id);
}

function submit_application(array $u): void
{
    $app = editable_application($u);
    $id  = (int) $app['id'];

    foreach (['full_name', 'date_of_birth', 'nationality', 'id_type', 'id_number', 'permanent_address'] as $f) {
        if (!$app[$f]) {
            throw new RuntimeException('Complete personal details and permanent address before submitting.');
        }
    }

    db()->prepare("UPDATE applications SET status = 'SUBMITTED' WHERE id = ?")->execute([$id]);
    log_action($id, (int) $u['id'], 'SUBMITTED', 'Application submitted for review.');

    // Notify every staff member (CEO, Super Admin, Admin) at once.
    notify_staff_of_submission($id, $app);

    flash('success', 'Application submitted for review. The review team has been notified.');
    redirect('?page=application&id=' . $id);
}

// ---------------------------------------------------------------------------
// Staff review actions
// ---------------------------------------------------------------------------

function review_application(array $u): void
{
    require_role(STAFF_ROLES);
    $id  = (int) ($_POST['id'] ?? 0);
    $app = application_for($id);
    if (!$app || !in_array($app['status'], ['SUBMITTED', 'UNDER_REVIEW'], true)) {
        throw new RuntimeException('This application is not awaiting review.');
    }

    $decision = $_POST['decision'] ?? '';
    $map      = ['approve' => 'APPROVED', 'reject' => 'REJECTED', 'resubmit' => 'RESUBMISSION_REQUESTED'];
    if (!isset($map[$decision])) {
        throw new RuntimeException('Choose a review decision.');
    }
    $notes = trim($_POST['review_notes'] ?? '');
    if ($decision !== 'approve' && !$notes) {
        throw new RuntimeException('Provide notes for rejection or resubmission.');
    }

    $newStatus = $map[$decision];
    db()->prepare('UPDATE applications SET status = ?, reviewer_id = ?, review_notes = ?, reviewed_at = NOW() WHERE id = ?')
        ->execute([$newStatus, $u['id'], $notes, $id]);
    log_action($id, (int) $u['id'], 'REVIEWED', format_status($newStatus) . ($notes ? ': ' . $notes : ''));

    // Notify the applicant about the outcome.
    $body = review_email_body($newStatus, $notes);
    send_email($app['applicant_email'], 'Your KYC application was ' . strtolower(format_status($newStatus)), $body);

    flash('success', 'Review decision saved and the applicant has been notified.');
    redirect('?page=application&id=' . $id);
}

// ---------------------------------------------------------------------------
// Super Admin user management
// ---------------------------------------------------------------------------

function create_user(array $u): void
{
    require_role(['SUPER_ADMIN']);
    $username = trim($_POST['username'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';
    $roles    = ['APPLICANT', 'ADMIN', 'SUPER_ADMIN', 'CEO'];

    if (strlen($username) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        throw new RuntimeException('Enter a username, valid email, and password of at least 8 characters.');
    }
    if (!in_array($role, $roles, true)) {
        throw new RuntimeException('Choose a valid role.');
    }
    db()->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)')
        ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
    flash('success', 'User "' . $username . '" created as ' . role_label($role) . '.');
    redirect('?page=users');
}

function update_user(array $u): void
{
    require_role(['SUPER_ADMIN']);
    $id      = (int) ($_POST['id'] ?? 0);
    $role    = $_POST['role'] ?? '';
    $roles   = ['APPLICANT', 'ADMIN', 'SUPER_ADMIN', 'CEO'];
    $target  = db()->prepare('SELECT * FROM users WHERE id = ?');
    $target->execute([$id]);
    $targetUser = $target->fetch();

    if (!$targetUser) {
        throw new RuntimeException('User not found.');
    }
    if (!in_array($role, $roles, true)) {
        throw new RuntimeException('Choose a valid role.');
    }
    if ($targetUser['id'] == $u['id']) {
        throw new RuntimeException('You cannot change your own role.');
    }

    db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]);
    flash('success', 'Role updated for "' . $targetUser['username'] . '".');
    redirect('?page=users');
}

function reset_password(array $u): void
{
    require_role(['SUPER_ADMIN']);
    $id       = (int) ($_POST['id'] ?? 0);
    $password = $_POST['password'] ?? '';
    if (strlen($password) < 8) {
        throw new RuntimeException('The new password must be at least 8 characters.');
    }
    $target = db()->prepare('SELECT * FROM users WHERE id = ?');
    $target->execute([$id]);
    $targetUser = $target->fetch();
    if (!$targetUser) {
        throw new RuntimeException('User not found.');
    }

    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    flash('success', 'Password reset for "' . $targetUser['username'] . '".');
    redirect('?page=users');
}

// ---------------------------------------------------------------------------
// Email notifications
// ---------------------------------------------------------------------------

/** Notify every staff account (CEO, Super Admin, Admin) that an application was submitted. */
function notify_staff_of_submission(int $applicationId, array $app): void
{
    $staff = db()->query("SELECT email, username, role FROM users WHERE role IN ('CEO', 'SUPER_ADMIN', 'ADMIN')")
        ->fetchAll();

    $url  = APP_URL . '/index.php?page=application&id=' . $applicationId;
    $name = $app['full_name'] ?: $app['applicant_name'];

    $body = email_layout(
        'New KYC application submitted',
        '<p><strong>' . htmlspecialchars($name) . '</strong> has submitted KYC application <strong>#' . $applicationId . '</strong> and it is awaiting review.</p>'
        . '<p><a href="' . htmlspecialchars($url) . '" style="background:#126466;color:#fff;padding:10px 18px;border-radius:7px;text-decoration:none;font-weight:700">Review application</a></p>'
        . '<p style="color:#65717e;font-size:13px">Submitted: ' . htmlspecialchars(date('d M Y, H:i')) . '</p>'
    );

    foreach ($staff as $member) {
        send_email($member['email'], 'New KYC application #' . $applicationId . ' submitted', $body);
    }
}

/** Build the applicant-facing email body for a review outcome. */
function review_email_body(string $newStatus, string $notes): string
{
    if ($newStatus === 'RESUBMISSION_REQUESTED') {
        $message = '<p>Your KYC application needs changes before it can be approved.</p>'
            . '<p style="background:#fdf6e7;border:1px solid #f0dfb5;padding:14px 16px;border-radius:8px">'
            . '<strong>Reason from the review team:</strong><br>' . nl2br(htmlspecialchars($notes)) . '</p>'
            . '<p>Please sign in, correct the highlighted details, re-upload any documents, and submit the application again.</p>'
            . '<p><a href="' . htmlspecialchars(APP_URL . '/index.php?page=applications') . '" style="background:#126466;color:#fff;padding:10px 18px;border-radius:7px;text-decoration:none;font-weight:700">Open your applications</a></p>';
    } elseif ($newStatus === 'APPROVED') {
        $message = '<p>Congratulations — your KYC application has been <strong>approved</strong>.</p>'
            . ($notes ? '<p>Reviewer note: ' . nl2br(htmlspecialchars($notes)) . '</p>' : '');
    } else {
        $message = '<p>Unfortunately your KYC application was <strong>rejected</strong>.</p>'
            . ($notes ? '<p style="background:#fdf0f0;border:1px solid #f0cccc;padding:14px 16px;border-radius:8px"><strong>Reason:</strong><br>' . nl2br(htmlspecialchars($notes)) . '</p>' : '');
    }

    return email_layout('KYC application update', $message);
}
