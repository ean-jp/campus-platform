<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Every API endpoint answers through this class.
 *   success: { "success": true,  "data": ... }
 *   error:   { "success": false, "error": "..." }
 * Both send the HTTP status code and stop the script.
 */
final class Response
{
    public static function success(mixed $data = null, int $status = 200): void
    {
        self::send(['success' => true, 'data' => $data], $status);
    }

    public static function error(string $message, int $status = 400): void
    {
        self::send(['success' => false, 'error' => $message], $status);
    }

    private static function send(array $payload, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
