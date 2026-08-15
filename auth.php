<?php
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

function is_staff(): bool
{
    $u = user();
    return $u !== null && in_array($u['role'], STAFF_ROLES, true);
}

function role_label(string $role): string
{
    return ucwords(strtolower(str_replace('_', ' ', $role)));
}
