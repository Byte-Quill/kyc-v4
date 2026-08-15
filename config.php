<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Configuration loader
// ---------------------------------------------------------------------------
// Values are read from the .env file when it exists (recommended), otherwise
// the defaults below are used. This keeps the app runnable out of the box.
//
// .env is ignored by git - keep real credentials there, not in this file.
// See .env.example for the full list of supported variables.
// ---------------------------------------------------------------------------

/**
 * Load .env values into $_ENV (without overwriting anything already set).
 * Lightweight parser - no composer package required.
 */
function load_env(): void
{
    $envFile = __DIR__ . '/.env';
    if (!is_file($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and lines without '='
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        if ($key === '' || array_key_exists($key, $_ENV)) {
            continue;
        }

        // Strip surrounding double quotes if present
        if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
    }
}

load_env();

/**
 * Return a config value from .env, or $default when missing.
 */
function env(string $key, mixed $default = null): mixed
{
    return array_key_exists($key, $_ENV) ? $_ENV[$key] : $default;
}

// ---------------------------------------------------------------------------
// Application
// ---------------------------------------------------------------------------
define('APP_NAME', (string) env('APP_NAME', 'KYC Verify'));
define('APP_URL', (string) env('APP_URL', 'http://localhost/kyc-v4')); // Base URL used in email links.
define('UPLOAD_DIR', __DIR__ . '/' . ltrim((string) env('UPLOAD_DIR', 'uploads'), '/'));
define('MAX_UPLOAD_BYTES', (int) env('MAX_UPLOAD_BYTES', 5 * 1024 * 1024));

// ---------------------------------------------------------------------------
// Database (MySQL / MariaDB)
// ---------------------------------------------------------------------------
define('DB_HOST', (string) env('DB_HOST', '127.0.0.1'));
define('DB_NAME', (string) env('DB_NAME', 'kyc_system'));
define('DB_USER', (string) env('DB_USER', 'root'));
define('DB_PASS', (string) env('DB_PASS', ''));

// ---------------------------------------------------------------------------
// Email (SMTP) settings — used by mailer.php via PHPMailer.
// Set MAIL_ENABLED to false to skip real delivery; every email is still
// recorded in the email_logs table so the flow can be verified offline.
// ---------------------------------------------------------------------------
define('MAIL_ENABLED', filter_var(env('MAIL_ENABLED', 'false'), FILTER_VALIDATE_BOOL));
define('SMTP_HOST', (string) env('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int) env('SMTP_PORT', 587));
define('SMTP_USER', (string) env('SMTP_USER', 'your-email@gmail.com'));
define('SMTP_PASS', (string) env('SMTP_PASS', 'your-app-password'));
define('SMTP_ENCRYPTION', (string) env('SMTP_ENCRYPTION', 'tls')); // 'tls' or 'ssl'
define('MAIL_FROM', (string) env('MAIL_FROM', 'no-reply@kyc.local'));
define('MAIL_FROM_NAME', (string) env('MAIL_FROM_NAME', 'KYC Verify'));

date_default_timezone_set((string) env('APP_TIMEZONE', 'Asia/Kathmandu'));
