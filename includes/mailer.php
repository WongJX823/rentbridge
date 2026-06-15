<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email via SMTP.
 * Returns ['ok' => bool, 'error' => string|null].
 */
function send_email(string $toEmail, string $toName, string $subject, string $htmlBody, string $plainBody = ''): array {
    $config = require __DIR__ . '/mail_config.php';

    $mail = new PHPMailer(true);

    try {
        // SMTP setup
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        if ($config['encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($config['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }
        $mail->Port = (int)$config['port'];
        $mail->CharSet = 'UTF-8';

        // From + to
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($toEmail, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainBody ?: strip_tags($htmlBody);

        $mail->send();
        return ['ok' => true, 'error' => null];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $mail->ErrorInfo];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Send a verification code email.
 */
function send_verification_code_email(string $toEmail, string $toName, string $code, string $purposeLabel = 'password change'): array {
    $subject = 'Your RentBridge verification code: ' . $code;
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; background:#F4F4EE; padding:24px; margin:0;">
    <div style="max-width:500px; margin:0 auto; background:white; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
        <div style="background:#0F2C52; color:white; padding:20px 24px;">
            <h2 style="margin:0; font-weight:700;">RentBridge</h2>
        </div>
        <div style="padding:32px 24px;">
            <h3 style="color:#0F2C52; margin-top:0;">Hi {$toName},</h3>
            <p style="color:#333; line-height:1.5;">
                You requested a verification code to authorize a {$purposeLabel}.
                Please use the code below to complete the action:
            </p>
            <div style="background:#F4F4EE; border:2px solid #2E8B57; border-radius:8px; padding:20px; text-align:center; margin:24px 0;">
                <div style="font-size:32px; font-weight:700; letter-spacing:6px; color:#0F2C52; font-family: monospace;">
                    {$code}
                </div>
            </div>
            <p style="color:#666; font-size:14px; line-height:1.5;">
                This code expires in <strong>10 minutes</strong>.
                If you didn't request this, please ignore this email and consider changing your password.
            </p>
        </div>
        <div style="padding:16px 24px; background:#FAF8F3; color:#666; font-size:12px; text-align:center;">
            RentBridge · Trusted student rental platform
        </div>
    </div>
</body>
</html>
HTML;

    $plain = "Hi {$toName},\n\nYour verification code is: {$code}\n\nThis code expires in 10 minutes.\n\nIf you didn't request this, please ignore this email.\n\nRentBridge";

    return send_email($toEmail, $toName, $subject, $html, $plain);
}