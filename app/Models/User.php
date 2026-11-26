<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Env;

/**
 * User model (Person 1). Matches the shared schema.sql:
 *   users(full_name, email, phone, password_hash, account_type, role user|admin,
 *         status, email_verified_at, remember_token_hash)
 * "vendor" and "provider" are not stored in users.role. They come from the
 * vendors and providers tables, so we look them up here.
 * All queries use prepared statements.
 */
final class User
{
    /**
     * Account type comes ONLY from the email domain, decided on the server.
     * ashesi.edu.gh (or a subdomain of it) = 'ashesi', anything else = 'guest'.
     * The domain can be changed with ASHESI_DOMAIN in .env.
     */
    public static function accountTypeFor(string $email): string
    {
        $at = strrchr($email, '@');
        $domain = $at === false ? '' : strtolower(substr($at, 1));
        $ashesi = strtolower((string) Env::get('ASHESI_DOMAIN', 'ashesi.edu.gh'));

        if ($domain === $ashesi || str_ends_with($domain, '.' . $ashesi)) {
            return 'ashesi';
        }
        return 'guest';
    }

    public static function findByEmail(string $email): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        return $st->fetch() ?: null;
    }

    public static function findById(int $id): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** True if the email is already used by another account (sign-in email or linked email). */
    public static function emailTaken(string $email, int $exceptUserId = 0): bool
    {
        $st = Database::connection()->prepare(
            'SELECT 1 FROM users WHERE email = ? AND id <> ?
             UNION
             SELECT 1 FROM user_emails WHERE email = ? AND user_id <> ?
             LIMIT 1'
        );
        $st->execute([$email, $exceptUserId, $email, $exceptUserId]);
        return $st->fetchColumn() !== false;
    }

    /** New accounts always get the default role ('user') and are unverified. */
    public static function create(string $fullName, string $email, string $passwordHash, string $accountType, ?string $phone): int
    {
        $db = Database::connection();
        $st = $db->prepare(
            'INSERT INTO users (full_name, email, password_hash, account_type, phone)
             VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([$fullName, $email, $passwordHash, $accountType, $phone]);
        return (int) $db->lastInsertId();
    }

    /** Someone signs up again with an email that was never verified: refresh the pending account. */
    public static function updatePending(int $id, string $fullName, string $passwordHash, string $accountType, ?string $phone): void
    {
        $st = Database::connection()->prepare(
            'UPDATE users SET full_name = ?, password_hash = ?, account_type = ?, phone = ?
             WHERE id = ? AND email_verified_at IS NULL'
        );
        $st->execute([$fullName, $passwordHash, $accountType, $phone, $id]);
    }

    public static function markVerified(int $id): void
    {
        $st = Database::connection()->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?');
        $st->execute([$id]);
    }

    public static function updatePasswordHash(int $id, string $hash): void
    {
        $st = Database::connection()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $st->execute([$hash, $id]);
    }

    // ------------------------------------------------------------ roles

    /** True if this user owns a vendor shop that the admin has approved. */
    public static function isApprovedVendor(int $userId): bool
    {
        $st = Database::connection()->prepare(
            "SELECT 1 FROM vendors WHERE user_id = ? AND status = 'approved' LIMIT 1"
        );
        $st->execute([$userId]);
        return $st->fetchColumn() !== false;
    }

    /** True if this user is the login account of an active resource provider. */
    public static function isProvider(int $userId): bool
    {
        $st = Database::connection()->prepare(
            'SELECT 1 FROM providers WHERE user_id = ? AND is_active = 1 LIMIT 1'
        );
        $st->execute([$userId]);
        return $st->fetchColumn() !== false;
    }

    /**
     * The one role shown to the front end: admin, vendor, provider or user.
     * users.role gives 'admin'; vendor and provider come from their tables.
     */
    public static function effectiveRole(int $userId, string $storedRole): string
    {
        if ($storedRole === 'admin') {
            return 'admin';
        }
        if (self::isApprovedVendor($userId)) {
            return 'vendor';
        }
        if (self::isProvider($userId)) {
            return 'provider';
        }
        return 'user';
    }

    /** The array Session::login() expects. Values come from the database only. */
    public static function toSession(array $row): array
    {
        $id = (int) $row['id'];
        return [
            'id'           => $id,
            'name'         => $row['full_name'],
            'email'        => $row['email'],
            'role'         => self::effectiveRole($id, (string) $row['role']),
            'account_type' => $row['account_type'],
        ];
    }
}
