<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;

/**
 * Sends the verification emails with PHPMailer over SMTP.
 *
 * Install once, from the project root:   composer require phpmailer/phpmailer
 *
 * .env keys:
 *   MAIL_HOST=smtp.gmail.com
 *   MAIL_PORT=587
 *   MAIL_ENCRYPTION=tls          tls = STARTTLS (port 587), ssl = SMTPS (port 465), none = no encryption
 *   MAIL_USER=yourname@gmail.com
 *   MAIL_PASS=abcdefghijklmnop   Gmail "app password", NOT your normal password
 *   MAIL_FROM=yourname@gmail.com Gmail only sends as the account it logs in with
 *   MAIL_FROM_NAME=Campus Platform
 *
 * With MAIL_HOST empty and APP_ENV=development, the code is written to the PHP
 * error log instead of being emailed (handy when offline). Once MAIL_HOST is set,
 * mail is always really sent, and any failure is written to the error log.
 */
final class MailService
{
    public static function sendCode(string $to, string $name, string $code, string $purpose): bool
    {
        $subjects = [
            'signup'         => 'Your Campus Platform verification code',
            'link_email'     => 'Confirm your Ashesi email',
            'password_reset' => 'Reset your Campus Platform password',
        ];
        $subject = $subjects[$purpose] ?? 'Your verification code';
        $minutes = VerificationService::TTL_MINUTES;

        $text = "Hi {$name},\n\nYour code is: {$code}\n\n"
              . "It expires in {$minutes} minutes. If you did not ask for it, ignore this email.\n";
        $html = '<p>Hi ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>'
              . '<p>Your code is: <strong style="font-size:20px;letter-spacing:3px">' . $code . '</strong></p>'
              . "<p>It expires in {$minutes} minutes. If you did not ask for it, ignore this email.</p>";

        $host = trim((string) Env::get('MAIL_HOST', ''));

        // No mail server configured: development shortcut, code goes to the error log.
        if ($host === '') {
            if (Env::get('APP_ENV') === 'development') {
                error_log("[DEV MAIL] to={$to} purpose={$purpose} code={$code}");
                return true;
            }
            error_log('MailService: MAIL_HOST is not set, so no email was sent.');
            return false;
        }

        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $autoload = $base . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            error_log('MailService: PHPMailer is not installed. Run "composer require phpmailer/phpmailer" in ' . $base);
            return false;
        }
        require_once $autoload;

        $encryption = strtolower(trim((string) Env::get('MAIL_ENCRYPTION', 'tls')));
        $user = (string) Env::get('MAIL_USER', '');
        $from = (string) Env::get('MAIL_FROM', '') ?: $user;
        if ($from === '') {
            $from = 'no-reply@campus-platform.test';
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host    = $host;
            $mail->Port    = (int) Env::get('MAIL_PORT', '587');
            $mail->Timeout = 15;                         // seconds; do not hang the sign-up request
            $mail->CharSet = 'UTF-8';

            if ($encryption === 'ssl' || $encryption === 'smtps') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'none' || $encryption === '') {
                $mail->SMTPSecure  = '';
                $mail->SMTPAutoTLS = false;
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->SMTPAuth = $user !== '';
            if ($mail->SMTPAuth) {
                $mail->Username = $user;
                $mail->Password = (string) Env::get('MAIL_PASS', '');
            }

            $mail->setFrom($from, (string) Env::get('MAIL_FROM_NAME', 'Campus Platform'));
            $mail->addAddress($to, $name);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = $text;
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            // The real reason (wrong password, blocked port, certificate problem...) is here.
            error_log('MailService error sending to ' . $to . ': ' . $e->getMessage());
            return false;
        }
    }
}
