<?php
declare(strict_types=1);

namespace App\Services\Shop;

use App\Core\Response;

/**
 * Reads and checks request input for the marketplace endpoints (Person 3).
 * JSON body for POST/PATCH, query string for GET. Never trusts the client:
 * every value is cast and range-checked here, and prices/totals are never read from input.
 */
final class Input
{
    private static ?array $body = null;

    /** JSON body as an array (empty array if there is no body). */
    public static function body(): array
    {
        if (self::$body === null) {
            $raw = file_get_contents('php://input');
            if ($raw === false || trim($raw) === '') {
                self::$body = [];
            } else {
                $data = json_decode($raw, true);
                if (!is_array($data)) {
                    Response::error('Request body must be valid JSON', 400);
                }
                self::$body = $data;
            }
        }
        return self::$body;
    }

    /** Positive integer from an array (query or body), or $default. 422 if present but invalid. */
    public static function int(array $src, string $key, ?int $default = null, int $min = 1, int $max = 1000000): ?int
    {
        if (!array_key_exists($key, $src) || $src[$key] === '' || $src[$key] === null) {
            return $default;
        }
        $v = filter_var($src[$key], FILTER_VALIDATE_INT);
        if ($v === false || $v < $min || $v > $max) {
            Response::error("'$key' must be a whole number between $min and $max", 422);
        }
        return $v;
    }

    /** Price (GH₵) as a float >= 0, or $default. */
    public static function money(array $src, string $key, ?float $default = null): ?float
    {
        if (!array_key_exists($key, $src) || $src[$key] === '' || $src[$key] === null) {
            return $default;
        }
        if (!is_numeric($src[$key]) || (float)$src[$key] < 0) {
            Response::error("'$key' must be a number 0 or more", 422);
        }
        return (float)$src[$key];
    }

    /** Trimmed string, or $default. 422 if longer than $max. */
    public static function text(array $src, string $key, ?string $default = null, int $max = 255): ?string
    {
        if (!array_key_exists($key, $src) || $src[$key] === null) {
            return $default;
        }
        if (!is_string($src[$key])) {
            Response::error("'$key' must be text", 422);
        }
        $v = trim($src[$key]);
        if ($v === '') {
            return $default;
        }
        if (mb_strlen($v) > $max) {
            Response::error("'$key' is too long (max $max characters)", 422);
        }
        return $v;
    }

    /** Page / per_page from the query string. */
    public static function paging(array $query, int $defaultPerPage = 20, int $maxPerPage = 50): array
    {
        $page    = self::int($query, 'page', 1, 1, 100000);
        $perPage = self::int($query, 'per_page', $defaultPerPage, 1, $maxPerPage);
        return [$page, $perPage, ($page - 1) * $perPage];
    }
}
