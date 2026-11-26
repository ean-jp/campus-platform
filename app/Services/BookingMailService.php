<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use Throwable;

final class BookingMailService
{
    // ==========================================
    // VERIFIED ASHESI EMAIL
    // ==========================================

    private static function getVerifiedAshesiEmail(
        int $userId
    ): ?string {

        $db = Database::connection();

        // Check the user's primary email address.
        $stmt = $db->prepare(
            "SELECT email
             FROM users
             WHERE id = ?
             AND account_type = 'ashesi'
             AND status = 'active'
             AND email_verified_at IS NOT NULL"
        );

        $stmt->execute([$userId]);

        $email = $stmt->fetchColumn();

        if ($email && self::isAshesiEmail($email)) {
            return $email;
        }

        // Check verified linked Ashesi email addresses.
        $stmt = $db->prepare(
            "SELECT ue.email
             FROM user_emails ue
             INNER JOIN users u ON ue.user_id = u.id
             WHERE ue.user_id = ?
             AND ue.is_ashesi = 1
             AND ue.verified_at IS NOT NULL
             AND u.status = 'active'
             AND u.account_type = 'ashesi'
             ORDER BY ue.verified_at DESC
             LIMIT 1"
        );

        $stmt->execute([$userId]);

        $email = $stmt->fetchColumn();

        if ($email && self::isAshesiEmail($email)) {
            return $email;
        }

        return null;
    }

    private static function isAshesiEmail(
        string $email
    ): bool {

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $domain = strtolower(
            substr(strrchr($email, '@'), 1)
        );

        $ashesiDomain = strtolower(
            (string) Env::get(
                'ASHESI_DOMAIN',
                'ashesi.edu.gh'
            )
        );

        return $domain === $ashesiDomain
            || str_ends_with($domain, '.' . $ashesiDomain);
    }

    // ==========================================
    // RETRIEVE BOOKING INFORMATION
    // ==========================================

    private static function getBookingDetails(
        int $bookingId
    ): ?array {

        $db = Database::connection();

        $stmt = $db->prepare(
            "SELECT
                b.id AS booking_id,
                b.user_id,
                b.status,
                u.full_name AS student_name,
                t.slot_date,
                t.start_time,
                t.end_time,
                p.name AS provider_name,
                s.name AS service_name
             FROM bookings b
             INNER JOIN users u
                ON b.user_id = u.id
             INNER JOIN time_slots t
                ON b.slot_id = t.id
             INNER JOIN providers p
                ON t.provider_id = p.id
             INNER JOIN resource_services s
                ON p.service_id = s.id
             WHERE b.id = ?
             AND b.status = 'confirmed'
             AND u.status = 'active'
             AND TIMESTAMP(t.slot_date, t.start_time) > NOW()
             LIMIT 1"
        );

        $stmt->execute([$bookingId]);

        return $stmt->fetch() ?: null;
    }

    // ==========================================
    // HTML ESCAPING
    // ==========================================

    private static function escapeHtml(
        string $value
    ): string {

        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    // ==========================================
    // BOOKING CONFIRMATION EMAIL
    // ==========================================

    public static function sendConfirmation(
        int $bookingId
    ): bool {

        return self::sendBookingNotification(
            $bookingId,
            false
        );
    }

    // ==========================================
    // APPOINTMENT REMINDER EMAIL
    // ==========================================

    public static function sendReminder(
        int $bookingId
    ): bool {

        return self::sendBookingNotification(
            $bookingId,
            true
        );
    }

    // ==========================================
    // BUILD BOOKING NOTIFICATION
    // ==========================================

    private static function sendBookingNotification(
        int $bookingId,
        bool $isReminder
    ): bool {

        try {

            $booking = self::getBookingDetails(
                $bookingId
            );

            if (!$booking) {
                return false;
            }

            $email = self::getVerifiedAshesiEmail(
                (int) $booking['user_id']
            );

            if (!$email) {

                error_log(
                    'BookingMailService: No verified Ashesi '
                    . 'email for booking ' . $bookingId
                );

                return false;
            }

            $name = $booking['student_name'];

            $date = date(
                'l, F j, Y',
                strtotime($booking['slot_date'])
            );

            $start = date(
                'g:i A',
                strtotime($booking['start_time'])
            );

            $end = date(
                'g:i A',
                strtotime($booking['end_time'])
            );

            $provider = $booking['provider_name'];
            $service = $booking['service_name'];

            if ($isReminder) {

                $subject =
                    'Campus Platform - Appointment Reminder';

                $heading = 'Appointment Reminder';

                $message =
                    'This is a reminder of your upcoming '
                    . 'campus resource appointment.';

            } else {

                $subject =
                    'Campus Platform - Booking Confirmation';

                $heading = 'Appointment Confirmed';

                $message =
                    'Your campus resource appointment '
                    . 'has been successfully booked.';
            }

            // Plain-text email.
            $text =
                "Hi {$name},\n\n"
                . "{$message}\n\n"
                . "Booking ID: {$bookingId}\n"
                . "Service: {$service}\n"
                . "Provider: {$provider}\n"
                . "Date: {$date}\n"
                . "Time: {$start} - {$end}\n\n"
                . "Visit My Bookings on Campus Platform "
                . "to manage your appointment.\n\n"
                . "Regards,\n"
                . "Campus Platform";

            // Escape database values for HTML output.
            $safeName = self::escapeHtml($name);
            $safeService = self::escapeHtml($service);
            $safeProvider = self::escapeHtml($provider);
            $safeDate = self::escapeHtml($date);
            $safeStart = self::escapeHtml($start);
            $safeEnd = self::escapeHtml($end);

            // HTML email.
            $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<body style="font-family: Arial, sans-serif;
             background-color: #f4f4f4;
             padding: 24px;
             color: #222222;">

    <div style="max-width: 600px;
                margin: auto;
                background-color: #ffffff;
                border-radius: 12px;
                overflow: hidden;">

        <div style="background-color: #982b2b;
                    color: #ffffff;
                    padding: 24px;">

            <h2 style="margin: 0;">
                Campus Platform
            </h2>

            <p>Campus Resources</p>

        </div>

        <div style="padding: 24px;">

            <h2>{$heading}</h2>

            <p>Hi {$safeName},</p>

            <p>{$message}</p>

            <div style="background-color: #f5f5f5;
                        padding: 20px;
                        border-radius: 8px;">

                <p>
                    <strong>Booking ID:</strong>
                    {$bookingId}
                </p>

                <p>
                    <strong>Service:</strong>
                    {$safeService}
                </p>

                <p>
                    <strong>Provider:</strong>
                    {$safeProvider}
                </p>

                <p>
                    <strong>Date:</strong>
                    {$safeDate}
                </p>

                <p>
                    <strong>Time:</strong>
                    {$safeStart} - {$safeEnd}
                </p>

            </div>

            <p>
                Visit My Bookings on Campus Platform
                to manage your appointment.
            </p>

            <p>
                Regards,<br>
                <strong>Campus Platform</strong>
            </p>

        </div>

        <div style="background-color: #eeeeee;
                    padding: 16px;
                    text-align: center;
                    font-size: 12px;">

            Campus Platform | Campus Resources

        </div>

    </div>

</body>
</html>
HTML;

            return self::sendEmail(
                $email,
                $name,
                $subject,
                $html,
                $text
            );

        } catch (Throwable $e) {

            error_log(
                'BookingMailService error for booking '
                . $bookingId . ': ' . $e->getMessage()
            );

            return false;
        }
    }

    // ==========================================
    // SMTP EMAIL DELIVERY
    // ==========================================

    private static function sendEmail(
        string $to,
        string $name,
        string $subject,
        string $html,
        string $text
    ): bool {

        $host = trim(
            (string) Env::get('MAIL_HOST', '')
        );

        $user = trim(
            (string) Env::get('MAIL_USER', '')
        );

        $password = (string) Env::get(
            'MAIL_PASS',
            ''
        );

        $from = trim(
            (string) Env::get('MAIL_FROM', '')
        ) ?: $user;

        $fromName = (string) Env::get(
            'MAIL_FROM_NAME',
            'Campus Platform'
        );

        // Validate SMTP configuration.
        if (
            $host === ''
            || $user === ''
            || $password === ''
            || $from === ''
        ) {

            error_log(
                'BookingMailService: SMTP configuration '
                . 'is incomplete. Email was not sent.'
            );

            return false;
        }

        // Locate Composer's autoloader.
        $base = defined('BASE_PATH')
            ? BASE_PATH
            : dirname(__DIR__, 2);

        $autoload = $base . '/vendor/autoload.php';

        if (!is_file($autoload)) {

            error_log(
                'BookingMailService: PHPMailer '
                . 'is not installed.'
            );

            return false;
        }

        require_once $autoload;

        $encryption = strtolower(
            trim(
                (string) Env::get(
                    'MAIL_ENCRYPTION',
                    'tls'
                )
            )
        );

        try {

            $mail =
                new \PHPMailer\PHPMailer\PHPMailer(true);

            $mail->isSMTP();

            $mail->Host = $host;

            $mail->Port = (int) Env::get(
                'MAIL_PORT',
                '587'
            );

            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $password;
            $mail->Timeout = 15;
            $mail->CharSet = 'UTF-8';

            // Configure secure SMTP encryption.
            if (
                $encryption === 'ssl'
                || $encryption === 'smtps'
            ) {

                $mail->SMTPSecure =
                    \PHPMailer\PHPMailer\PHPMailer
                    ::ENCRYPTION_SMTPS;

            } elseif ($encryption === 'tls') {

                $mail->SMTPSecure =
                    \PHPMailer\PHPMailer\PHPMailer
                    ::ENCRYPTION_STARTTLS;

            } else {

                error_log(
                    'BookingMailService: Unsupported '
                    . 'SMTP encryption setting.'
                );

                return false;
            }

            $mail->setFrom($from, $fromName);

            $mail->addAddress($to, $name);

            $mail->isHTML(true);

            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text;

            $mail->send();

            return true;

        } catch (Throwable $e) {

            error_log(
                'BookingMailService SMTP error: '
                . $e->getMessage()
            );

            return false;
        }
    }
}