<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * 6-digit email codes. Rules from the plan:
 *  - the code is stored HASHED (never in plain text)
 *  - it expires after 10 minutes
 *  - 5 wrong tries max, then the code is dead and a new one must be requested
 *  - a new code cancels the old ones; a new code can be requested once per minute
 *
 * Time is always measured by MySQL (NOW()) so PHP and MySQL clocks cannot disagree.
 */
final class VerificationService
{
    public const TTL_MINUTES    = 10;
    public const MAX_ATTEMPTS   = 5;
    public const RESEND_SECONDS = 60;

    /** Seconds the user still has to wait before a new code may be sent (0 = go ahead). */
    public static function secondsUntilResend(int $userId, string $purpose): int
    {
        $st = Database::connection()->prepare(
            'SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) FROM verification_codes
             WHERE user_id = ? AND purpose = ? ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$userId, $purpose]);
        $age = $st->fetchColumn();
        if ($age === false) {
            return 0;
        }
        return max(0, self::RESEND_SECONDS - (int) $age);
    }

    /** Create a new code and return it in plain text (to be emailed, never stored). */
    public static function issue(int $userId, string $email, string $purpose): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $db = Database::connection();

        $db->beginTransaction();
        try {
            $st = $db->prepare(
                'UPDATE verification_codes SET used_at = NOW()
                 WHERE user_id = ? AND purpose = ? AND used_at IS NULL'
            );
            $st->execute([$userId, $purpose]);

            $st = $db->prepare(
                'INSERT INTO verification_codes (user_id, email, code_hash, purpose, expires_at)
                 VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ' . self::TTL_MINUTES . ' MINUTE))'
            );
            $st->execute([$userId, $email, password_hash($code, PASSWORD_DEFAULT), $purpose]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        return $code;
    }

    /**
     * Check a code.
     * Returns ['status' => 'ok'|'invalid'|'expired'|'locked', 'remaining' => tries left].
     * The try counter goes up BEFORE the check and is saved even when the code is wrong.
     */
    public static function verify(int $userId, string $email, string $purpose, string $code): array
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $st = $db->prepare(
                'SELECT id, code_hash, attempts, (expires_at < NOW()) AS is_expired
                 FROM verification_codes
                 WHERE user_id = ? AND email = ? AND purpose = ? AND used_at IS NULL
                 ORDER BY id DESC LIMIT 1 FOR UPDATE'
            );
            $st->execute([$userId, $email, $purpose]);
            $row = $st->fetch();

            if (!$row || (int) $row['is_expired'] === 1) {
                $db->commit();
                return ['status' => 'expired', 'remaining' => 0];
            }
            if ((int) $row['attempts'] >= self::MAX_ATTEMPTS) {
                $db->commit();
                return ['status' => 'locked', 'remaining' => 0];
            }

            $attempts = (int) $row['attempts'] + 1;
            $db->prepare('UPDATE verification_codes SET attempts = ? WHERE id = ?')
               ->execute([$attempts, $row['id']]);

            if (!password_verify($code, $row['code_hash'])) {
                $db->commit();
                return ['status' => 'invalid', 'remaining' => self::MAX_ATTEMPTS - $attempts];
            }

            $db->prepare('UPDATE verification_codes SET used_at = NOW() WHERE id = ?')
               ->execute([$row['id']]);
            $db->commit();
            return ['status' => 'ok', 'remaining' => self::MAX_ATTEMPTS - $attempts];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
