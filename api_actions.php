<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// KYC Verify — JSON write API (POST)
// ---------------------------------------------------------------------------
// Mirrors the actions in the old actions.php, but returns JSON envelopes
// ({ ok: true, data, flash? } / { ok: false, error }) instead of redirecting.
// Accepts both JSON bodies and classic form posts (multipart uploads).
// CSRF must be verified via the X-CSRF-Token header (or the `csrf` field).
// ---------------------------------------------------------------------------

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';

verify_csrf_api();

$in = json_input();
$action = $in['action'] ?? '';

try {
    $result = handle_api_action($action, $in);
    if (is_array($result)) {
        json_ok($result['data'] ?? null, $result['redirect'] ?? null, $result['flash'] ?? null);
    }
    json_ok();
} catch (RuntimeException $e) {
    json_error($e->getMessage(), 422);
} catch (PDOException $e) {
    error_log('KYC Verify database error: ' . $e->getMessage());
    if ($e->getCode() === '23000') {
        json_error('That email address is already registered.', 422);
    } elseif ($e->getCode() === '42S02') {
        json_error('The database schema is incomplete. Import install.sql into the kyc_system database.', 500);
    } else {
        json_error('Unable to connect to the database. Start MySQL and verify the settings in config.php.', 500);
    }
} catch (Throwable $e) {
    json_error($e->getMessage(), 500);
}

/**
 * Dispatch a write action. Returns ['data' => mixed, 'redirect' => string, 'flash' => string].
 */
function handle_api_action(string $action, array $in): array
{
    switch ($action) {
        case 'register':
            $username = trim($in['username'] ?? '');
            $email    = strtolower(trim($in['email'] ?? ''));
            $password = $in['password'] ?? '';
            if (strlen($username) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
                throw new RuntimeException('Enter a username, valid email, and password of at least 8 characters.');
            }
            db()->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)')
                ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
            db()->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, 'APPLICANT')")
                ->execute([(int) db()->lastInsertId()]);
            return ['redirect' => '/login', 'flash' => 'Account created. Please sign in.'];

        case 'login':
            $s = db()->prepare('SELECT u.*, ur.role FROM users u JOIN user_roles ur ON ur.user_id = u.id WHERE u.email = ?');
            $s->execute([strtolower(trim($in['email'] ?? ''))]);
            $account = $s->fetch();
            if (!$account || !password_verify($in['password'] ?? '', $account['password_hash'])) {
                throw new RuntimeException('Incorrect email or password.');
            }
            session_regenerate_id(true);
            unset($account['password_hash']);
            $_SESSION['user'] = $account;
            return ['data' => $account, 'redirect' => '/dashboard', 'flash' => 'Welcome back, ' . $account['username'] . '.'];

        case 'logout':
            session_unset();
            session_destroy();
            session_start();
            return ['redirect' => '/login', 'flash' => 'You have signed out.'];

        default:
            $u = api_require_login();
            return match ($action) {
                'create_application'       => api_create_application($u),
                'save_application'         => api_save_application($u, $in),
                'submit'                   => api_submit_application($u, $in),
                'upload_profile_document'  => api_upload_profile_document($u, $in),
                'review'                   => api_review_application($u, $in),
                'create_user'              => api_create_user($u, $in),
                'update_user'              => api_update_user($u, $in),
                'reset_password'           => api_reset_password($u, $in),
                default                    => throw new RuntimeException('Unknown action.'),
            };
    }
}

// ---------------------------------------------------------------------------
// Shared application helpers (JSON variants)
// ---------------------------------------------------------------------------

function api_editable_application(array $u, array $in): array
{
    $id  = (int) ($in['id'] ?? 0);
    $app = application_for($id);
    if (!$app || $app['applicant_id'] != $u['id'] || !in_array($app['status'], ['DRAFT', 'RESUBMISSION_REQUESTED'], true)) {
        throw new RuntimeException('This application cannot be edited.');
    }
    return $app;
}

/** Persist the personal and address fields from an input array. */
function api_save_application_fields(array $u, int $id, array $in): void
{
    $fields = ['full_name', 'date_of_birth', 'nationality', 'id_type', 'id_number', 'id_expiry', 'issuing_country'];
    $values = [];
    foreach ($fields as $f) {
        $values[] = trim($in[$f] ?? '') ?: null;
    }
    db()->prepare('UPDATE applications SET ' . implode(',', array_map(fn ($f) => "$f = ?", $fields)) . ' WHERE id = ?')
        ->execute([...$values, $id]);

    $permanent = trim($in['permanent_address'] ?? '');
    $temporary = trim($in['temporary_address'] ?? '');
    if (!$permanent) {
        throw new RuntimeException('Permanent address is required.');
    }
    db()->prepare('INSERT INTO addresses (user_id, permanent_address, temporary_address) VALUES (?, ?, ?)
                   ON DUPLICATE KEY UPDATE permanent_address = VALUES(permanent_address), temporary_address = VALUES(temporary_address)')
        ->execute([$u['id'], $permanent, $temporary ?: null]);
}

// ---------------------------------------------------------------------------
// Applicant actions
// ---------------------------------------------------------------------------

function api_create_application(array $u): array
{
    db()->prepare('INSERT INTO applications (applicant_id) VALUES (?)')->execute([$u['id']]);
    $id = (int) db()->lastInsertId();
    log_action($id, (int) $u['id'], 'CREATED', 'Application draft created.');
    return ['data' => ['id' => $id], 'redirect' => '/application/' . $id, 'flash' => 'Application draft created.'];
}

function api_save_application(array $u, array $in): array
{
    $app = api_editable_application($u, $in);
    $id  = (int) $app['id'];

    api_save_application_fields($u, $id, $in);

    log_action($id, (int) $u['id'], 'UPDATED', 'KYC information and addresses updated.');
    return ['redirect' => '/application/' . $id, 'flash' => 'Draft saved.'];
}

function api_upload_profile_document(array $u, array $in): array
{
    $app = api_editable_application($u, $in);
    $id  = (int) $app['id'];

    $table  = $in['table'] ?? '';
    $column = $in['column'] ?? '';
    save_user_document((int) $u['id'], $table, $column, $_FILES['document'] ?? []);

    log_action($id, (int) $u['id'], 'DOCUMENT_UPLOADED', str_replace('_', ' ', $column) . ' uploaded.');
    return ['redirect' => '/application/' . $id, 'flash' => 'Document uploaded.'];
}

function api_submit_application(array $u, array $in): array
{
    $app = api_editable_application($u, $in);
    $id  = (int) $app['id'];

    // Persist the fields from this request first, so a single "Submit" click
    // saves and submits — no need to hit "Save draft" beforehand.
    api_save_application_fields($u, $id, $in);

    // Re-read the freshly saved values and validate them.
    $app = application_for($id);
    foreach (['full_name', 'date_of_birth', 'nationality', 'id_type', 'id_number', 'permanent_address'] as $f) {
        if (!$app[$f]) {
            throw new RuntimeException('Complete personal details and permanent address before submitting.');
        }
    }

    db()->prepare("UPDATE applications SET status = 'SUBMITTED' WHERE id = ?")->execute([$id]);
    log_action($id, (int) $u['id'], 'SUBMITTED', 'Application submitted for review.');

    // Notify every staff member (CEO, Super Admin, Admin) at once.
    notify_staff_of_submission($id, $app);

    return ['redirect' => '/application/' . $id, 'flash' => 'Application submitted for review. The review team has been notified.'];
}

// ---------------------------------------------------------------------------
// Staff review actions
// ---------------------------------------------------------------------------

function api_review_application(array $u, array $in): array
{
    api_require_role(STAFF_ROLES);
    $id  = (int) ($in['id'] ?? 0);
    $app = application_for($id);
    if (!$app || !in_array($app['status'], ['SUBMITTED', 'UNDER_REVIEW'], true)) {
        throw new RuntimeException('This application is not awaiting review.');
    }

    $decision = $in['decision'] ?? '';
    $map      = ['approve' => 'APPROVED', 'reject' => 'REJECTED', 'resubmit' => 'RESUBMISSION_REQUESTED'];
    if (!isset($map[$decision])) {
        throw new RuntimeException('Choose a review decision.');
    }
    $notes = trim($in['review_notes'] ?? '');
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

    return ['redirect' => '/application/' . $id, 'flash' => 'Review decision saved and the applicant has been notified.'];
}

// ---------------------------------------------------------------------------
// Super Admin user management
// ---------------------------------------------------------------------------

function api_create_user(array $u, array $in): array
{
    api_require_role(['SUPER_ADMIN']);
    $username = trim($in['username'] ?? '');
    $email    = strtolower(trim($in['email'] ?? ''));
    $password = $in['password'] ?? '';
    $role     = $in['role'] ?? '';
    $roles    = ['APPLICANT', 'ADMIN', 'SUPER_ADMIN', 'CEO'];

    if (strlen($username) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        throw new RuntimeException('Enter a username, valid email, and password of at least 8 characters.');
    }
    if (!in_array($role, $roles, true)) {
        throw new RuntimeException('Choose a valid role.');
    }
    db()->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)')
        ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
    db()->prepare('INSERT INTO user_roles (user_id, role) VALUES (?, ?)')
        ->execute([(int) db()->lastInsertId(), $role]);
    return ['redirect' => '/users', 'flash' => 'User "' . $username . '" created as ' . role_label($role) . '.'];
}

function api_update_user(array $u, array $in): array
{
    api_require_role(['SUPER_ADMIN']);
    $id      = (int) ($in['id'] ?? 0);
    $role    = $in['role'] ?? '';
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

    db()->prepare('INSERT INTO user_roles (user_id, role) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE role = VALUES(role)')
        ->execute([$id, $role]);
    return ['redirect' => '/users', 'flash' => 'Role updated for "' . $targetUser['username'] . '".'];
}

function api_reset_password(array $u, array $in): array
{
    api_require_role(['SUPER_ADMIN']);
    $id       = (int) ($in['id'] ?? 0);
    $password = $in['password'] ?? '';
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
    return ['redirect' => '/users', 'flash' => 'Password reset for "' . $targetUser['username'] . '".'];
}

// ---------------------------------------------------------------------------
// Email notifications
// ---------------------------------------------------------------------------

/** Notify every staff account (CEO, Super Admin, Admin) that an application was submitted. */
function notify_staff_of_submission(int $applicationId, array $app): void
{
    $staff = db()->query("SELECT u.email, u.username, ur.role
                          FROM users u JOIN user_roles ur ON ur.user_id = u.id
                          WHERE ur.role IN ('CEO', 'SUPER_ADMIN', 'ADMIN')")
        ->fetchAll();

    $url  = APP_URL . '/application/' . $applicationId;
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
            . '<p><a href="' . htmlspecialchars(APP_URL . '/applications') . '" style="background:#126466;color:#fff;padding:10px 18px;border-radius:7px;text-decoration:none;font-weight:700">Open your applications</a></p>';
    } elseif ($newStatus === 'APPROVED') {
        $message = '<p>Congratulations — your KYC application has been <strong>approved</strong>.</p>'
            . ($notes ? '<p>Reviewer note: ' . nl2br(htmlspecialchars($notes)) . '</p>' : '');
    } else {
        $message = '<p>Unfortunately your KYC application was <strong>rejected</strong>.</p>'
            . ($notes ? '<p style="background:#fdf0f0;border:1px solid #f0cccc;padding:14px 16px;border-radius:8px"><strong>Reason:</strong><br>' . nl2br(htmlspecialchars($notes)) . '</p>' : '');
    }

    return email_layout('KYC application update', $message);
}
