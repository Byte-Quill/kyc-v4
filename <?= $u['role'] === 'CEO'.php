<?= $u['role'] === 'CEO'
    ? 'See how the whole company is performing across every KYC submission.'
    : ($u['role'] === 'SUPER_ADMIN'
        ? 'Review applications and manage every user account and role on the platform.'
        : 'Review applications awaiting a decision and keep the queue moving.') ?>