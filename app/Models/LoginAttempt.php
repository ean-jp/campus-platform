<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Failed-login tracking. 5 wrong passwords for one email in 15 minutes
 * (or 20 from one IP) locks further attempts until the window passes.
 */
final class LoginAttempt
{
    public const MAX_FAILURES_PER_EMAIL = 5;
    public const MAX_FAILURES_PER_IP    = 20;
    public const WINDOW_MINUTES         = 15;

    public static function isLocked(string $email, string $ip): bool
    {
        $db = Database::connection();

        $st = $db->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = ? AND success = 0
               AND attempted_at > (NOW() - INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE)'
        );
        $st->execute([$email]);
        if ((int) $st->fetchColumn() >= self::MAX_FAILURES_PER_EMAIL) {
            return true;
        }

        $st = $db->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = ? AND success = 0
               AND attempted_at > (NOW() - INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE)'
        );
        $st->execute([$ip]);
        return (int) $st->fetchColumn() >= self::MAX_FAILURES_PER_IP;
    }

    public static function record(string $email, string $ip, bool $success): void
    {
        $st = Database::connection()->prepare(
            'INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, ?)'
        );
        $st->execute([$email, $ip, $success ? 1 : 0]);
    }

    /** After a successful login the failure counter starts again. */
    public static function clearFailures(string $email): void
    {
        $st = Database::connection()->prepare('DELETE FROM login_attempts WHERE email = ? AND success = 0');
        $st->execute([$email]);
    }
}
