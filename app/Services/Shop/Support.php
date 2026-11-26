<?php
declare(strict_types=1);

namespace App\Services\Shop;

use DateTimeImmutable;
use DateTimeZone;

/** Small shared helpers for the marketplace controllers (Person 3). */
final class Support
{
    /** Categories whose orders may be delivered (FR5.5: pickup or delivery is for Food). Edit here to change. */
    public const FOOD_CATEGORY_SLUGS = ['food-meals'];

    public const MAX_QTY = 99;

    /** "08:00:00" - "18:00:00" -> is the shop open right now (Ghana time)? No hours set = treated as open. */
    public static function isOpen(?string $opensAt, ?string $closesAt): bool
    {
        if ($opensAt === null || $closesAt === null) {
            return true;
        }
        $now = (new DateTimeImmutable('now', new DateTimeZone('Africa/Accra')))->format('H:i:s');
        if ($opensAt <= $closesAt) {
            return $now >= $opensAt && $now < $closesAt;
        }
        return $now >= $opensAt || $now < $closesAt;      // opens late, closes after midnight
    }

    /** DECIMAL string -> integer pesewas, so totals are exact. */
    public static function cents(string|int|float $amount): int
    {
        return (int)round(((float)$amount) * 100);
    }

    /** Integer pesewas -> float with 2 decimals for JSON. */
    public static function money(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /** "07:30:00" -> "07:30" for display. */
    public static function hhmm(?string $t): ?string
    {
        return $t === null ? null : substr($t, 0, 5);
    }

    /** Escape % and _ so a search term is matched literally in LIKE. */
    public static function likeEscape(string $s): string
    {
        return addcslashes($s, '\\%_');
    }

    /** Phone like "024 123 4567" or "+233241234567": 7-15 digits, spaces/dashes allowed. */
    public static function validPhone(string $p): bool
    {
        return preg_match('/^\+?[0-9 \-]{7,20}$/', $p) === 1
            && strlen(preg_replace('/\D/', '', $p)) >= 7
            && strlen(preg_replace('/\D/', '', $p)) <= 15;
    }

    /** Four random digits (000-9999 padded), from a secure source. */
    public static function pickupCode(): string
    {
        return str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
