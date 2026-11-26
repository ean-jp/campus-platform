<?php
declare(strict_types=1);

namespace App\Controllers\Marketplace;

use App\Core\Database;
use App\Core\Response;
use App\Services\Shop\CurrentUser;
use App\Services\Shop\Input;

/** POST /api/v1/reports   { item_id, reason }   a buyer reports a listing for the admin's moderation queue. */
final class ReportController
{
    public static function create(): void
    {
        $user = CurrentUser::require();
        $body = Input::body();
        $itemId = Input::int($body, 'item_id');
        $reason = Input::text($body, 'reason', null, 500);
        if ($itemId === null) {
            Response::error("'item_id' is required", 422);
        }
        if ($reason === null || mb_strlen($reason) < 5) {
            Response::error('Please tell us what is wrong with this listing (at least 5 characters)', 422);
        }

        $db = Database::connection();
        $s = $db->prepare('SELECT i.id, v.user_id AS owner FROM items i JOIN vendors v ON v.id = i.vendor_id WHERE i.id = ?');
        $s->execute([$itemId]);
        $item = $s->fetch();
        if (!$item) {
            Response::error('Item not found', 404);
        }
        if ((int)$item['owner'] === (int)$user['id']) {
            Response::error('You cannot report your own listing', 422);
        }
        $dup = $db->prepare("SELECT 1 FROM reports WHERE item_id = ? AND reporter_id = ? AND status = 'open'");
        $dup->execute([$itemId, $user['id']]);
        if ($dup->fetchColumn()) {
            Response::error('You have already reported this listing. The admin will review it', 409);
        }

        $db->prepare('INSERT INTO reports (item_id, reporter_id, reason) VALUES (?, ?, ?)')->execute([$itemId, $user['id'], $reason]);
        Response::success(['id' => (int)$db->lastInsertId(), 'message' => 'Thanks. The admin will review this listing'], 201);
    }
}
