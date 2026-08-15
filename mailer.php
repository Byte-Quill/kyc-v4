<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

// PHPMailer lives in vendor/ after `composer install`. If it is missing the app
// still works: emails are logged to email_logs and only skipped when MAIL_ENABLED.
if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Send an email through SMTP (PHPMailer) and always record it in email_logs.
 * When MAIL_ENABLED is false the message is only logged, so the notification
 * flow can be verified without a mail server.
 */
function send_email(string $to, string $subject, string $body): bool
{
    $status = 'SENT';
    $error  = null;

    if (MAIL_ENABLED) {
        try {
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host       = SMTP_HOST;
            $mailer->SMTPAuth   = true;
            $mailer->Username   = SMTP_USER;
            $mailer->Password   = SMTP_PASS;
            $mailer->SMTPSecure = SMTP_ENCRYPTION;
            $mailer->Port       = SMTP_PORT;
            $mailer->CharSet    = 'UTF-8';

            $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mailer->addAddress($to);
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body    = $body;
            $mailer->AltBody = strip_tags($body);

            $mailer->send();
        } catch (Throwable $e) {
            $status = 'FAILED';
            $error  = $e->getMessage();
        }
    }

    db()->prepare('INSERT INTO email_logs (recipient, subject, body, status, error) VALUES (?, ?, ?, ?, ?)')
        ->execute([$to, $subject, $body, $status, $error]);

    return $status === 'SENT';
}

/** Simple HTML wrapper used for every notification so emails share a look. */
function email_layout(string $title, string $contentHtml): string
{
    return '<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:0 auto;color:#18212b">'
        . '<div style="background:#126466;color:#fff;padding:22px 28px;border-radius:10px 10px 0 0">'
        . '<strong style="font-size:18px">' . APP_NAME . '</strong></div>'
        . '<div style="background:#fff;border:1px solid #e3e8eb;border-top:0;padding:28px;border-radius:0 0 10px 10px">'
        . '<h2 style="margin:0 0 14px;font-size:20px">' . htmlspecialchars($title) . '</h2>'
        . $contentHtml
        . '<p style="margin:24px 0 0;color:#65717e;font-size:12px">This is an automated message from ' . APP_NAME . '. Please do not reply.</p>'
        . '</div></div>';
}
