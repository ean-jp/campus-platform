<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Env;

/**
 * User model (Person 1). All queries use prepared statements.
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

    /** New accounts are ALWAYS role 'buyer' and unverified. */
    public static function create(string $name, string $email, string $passwordHash, string $accountType, ?string $phone): int
    {
        $db = Database::connection();
        $st = $db->prepare(
            "INSERT INTO users (name, email, password_hash, account_type, role, phone)
             VALUES (?, ?, ?, ?, 'buyer', ?)"
        );
        $st->execute([$name, $email, $passwordHash, $accountType, $phone]);
        return (int) $db->lastInsertId();
    }

    /** Someone signs up again with an email that was never verified: refresh the pending account. */
    public static function updatePending(int $id, string $name, string $passwordHash, string $accountType, ?string $phone): void
    {
        $st = Database::connection()->prepare(
            'UPDATE users SET name = ?, password_hash = ?, account_type = ?, phone = ?
             WHERE id = ? AND email_verified = 0'
        );
        $st->execute([$name, $passwordHash, $accountType, $phone, $id]);
    }

    public static function markVerified(int $id): void
    {
        $st = Database::connection()->prepare('UPDATE users SET email_verified = 1 WHERE id = ?');
        $st->execute([$id]);
    }

    public static function updatePasswordHash(int $id, string $hash): void
    {
        $st = Database::connection()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $st->execute([$hash, $id]);
    }

    /** The array Session::login() expects. Values come from the database only. */
    public static function toSession(array $row): array
    {
        return [
            'id'           => (int) $row['id'],
            'name'         => $row['name'],
            'email'        => $row['email'],
            'role'         => $row['role'],
            'account_type' => $row['account_type'],
        ];
    }
}
