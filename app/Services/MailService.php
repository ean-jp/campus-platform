<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;

/**
 * Sends the verification emails with PHPMailer.
 *
 * .env keys (point them at your Mailtrap inbox while testing):
 *   MAIL_HOST=sandbox.smtp.mailtrap.io
 *   MAIL_PORT=2525
 *   MAIL_USER=...
 *   MAIL_PASS=...
 *   MAIL_FROM=no-reply@campus-platform.test
 *   MAIL_FROM_NAME=Campus Platform
 *
 * Install once:  composer require phpmailer/phpmailer
 */
final class MailService
{
    public static function sendCode(string $to, string $name, string $code, string $purpose): bool
    {
        $subjects = [
            'signup' => 'Your Campus Platform verification code',
            'link'   => 'Confirm your Ashesi email',
            'reset'  => 'Reset your Campus Platform password',
        ];
        $subject = $subjects[$purpose] ?? 'Your verification code';
        $minutes = VerificationService::TTL_MINUTES;

        $text = "Hi {$name},\n\nYour code is: {$code}\n\n"
              . "It expires in {$minutes} minutes. If you did not ask for it, ignore this email.\n";
        $html = '<p>Hi ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>'
              . '<p>Your code is: <strong style="font-size:20px;letter-spacing:3px">' . $code . '</strong></p>'
              . "<p>It expires in {$minutes} minutes. If you did not ask for it, ignore this email.</p>";

        $host = (string) Env::get('MAIL_HOST', '');
        $autoload = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/vendor/autoload.php';

        // Development fallback: no mail server configured, so show the code in the PHP error log.
        if ($host === '' || !is_file($autoload)) {
            if (Env::get('APP_ENV') === 'development') {
                error_log("[DEV MAIL] to={$to} purpose={$purpose} code={$code}");
                return true;
            }
            error_log('MailService: mail is not configured');
            return false;
        }

        require_once $autoload;

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->Port       = (int) Env::get('MAIL_PORT', '2525');
            $mail->SMTPAuth   = true;
            $mail->Username   = (string) Env::get('MAIL_USER', '');
            $mail->Password   = (string) Env::get('MAIL_PASS', '');
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom((string) Env::get('MAIL_FROM', 'no-reply@campus-platform.test'),
                           (string) Env::get('MAIL_FROM_NAME', 'Campus Platform'));
            $mail->addAddress($to, $name);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = $text;
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            error_log('MailService error: ' . $e->getMessage());
            return false;
        }
    }
}
