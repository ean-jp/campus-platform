<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * One shared PDO connection. Usage:
 *   $db   = Database::connection();
 *   $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
 *   $stmt->execute([$id]);
 * Always use prepared statements; never put user input into the SQL string.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Env::get('DB_HOST', '127.0.0.1'),
                Env::get('DB_PORT', '3306'),
                Env::get('DB_NAME', 'campus_platform')
            );
            try {
                self::$pdo = new PDO($dsn, Env::get('DB_USER', 'root'), Env::get('DB_PASS', ''), [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                error_log('DB connection failed: ' . $e->getMessage());
                Response::error('Database unavailable', 500); // generic: never leak details
            }
        }
        return self::$pdo;
    }
}
