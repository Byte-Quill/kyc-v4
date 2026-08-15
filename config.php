<?php
// XAMPP defaults. Change these if your MySQL account/database differs.
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'kyc_system');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'KYC Verify');
define('APP_URL', 'http://localhost/kyc-v4'); // Base URL used in email links.
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);

// ---------------------------------------------------------------------------
// Email (SMTP) settings — used by mailer.php via PHPMailer.
// Set MAIL_ENABLED to false to skip real delivery; every email is still
// recorded in the email_logs table so the flow can be verified offline.
// ---------------------------------------------------------------------------
define('MAIL_ENABLED', false);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'
define('MAIL_FROM', 'no-reply@kyc.local');
define('MAIL_FROM_NAME', 'KYC Verify');

date_default_timezone_set('Asia/Kathmandu');
