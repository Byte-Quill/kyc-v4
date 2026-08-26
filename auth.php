<?php
/**
 * auth.php — Authentication & authorization helpers.
 *
 * The logged-in user is stored in $_SESSION['user'] (set on login in
 * actions.php / api_actions.php). These helpers read that session data and
 * enforce access rules:
 *   - user() ................. current user array or null
 *   - require_login() ........ page guard — redirects to login if signed out
 *   - require_role() ......... page guard — 403 unless user has one of the roles
 *   - api_require_login() .... JSON API guard — returns a 401 JSON error
 *   - api_require_role() ..... JSON API guard — returns a 403 JSON error
 *   - is_staff(), role_label()
 *
 * Roles: APPLICANT (default), ADMIN, SUPER_ADMIN, CEO.
 * STAFF_ROLES can review applications and see every application.
 */
declare(strict_types=1);

// Staff roles that can review applications and see every application.
const STAFF_ROLES = ['ADMIN', 'SUPER_ADMIN', 'CEO'];

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $u = user();
    if (!$u) {
        flash('error', 'Please sign in first.');
        redirect('?page=login');
    }
    return $u;
}

function require_role(array $roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
    return $u;
}

/** Login guard for the JSON API — returns a 401 JSON envelope instead of redirecting. */
function api_require_login(): array
{
    $u = user();
    if (!$u) {
        json_error('Please sign in first.', 401);
    }
    return $u;
}

/** Role guard for the JSON API — returns a 403 JSON envelope instead of an HTML page. */
function api_require_role(array $roles): array
{
    $u = api_require_login();
    if (!in_array($u['role'], $roles, true)) {
        json_error('You do not have permission to perform this action.', 403);
    }
    return $u;
}

function is_staff(): bool
{
    $u = user();
    return $u !== null && in_array($u['role'], STAFF_ROLES, true);
}

function role_label(string $role): string
{
    return ucwords(strtolower(str_replace('_', ' ', $role)));
}
