<?php
declare(strict_types=1);

namespace App\Controllers\Vendor;

/**
 * Notifications for ANY logged-in user (P4, Stage 3). Powers the bell on every page.
 *
 *   GET   /api/v1/notifications              my latest 50 (meta.unread_count for the bell)
 *   PATCH /api/v1/notifications/{id}/read    mark one as read
 *   POST  /api/v1/notifications/read-all     mark all as read
 *
 * To SEND one from your own code (e.g. checkout), insert a row:
 *   INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)
 * "link" is a page path from the site root, e.g. vendor/orders.html#order-12
 */
final class NotificationController extends VendorBase
{
    public function index(): void
    {
        $user = $this->user();
        $stmt = $this->db->prepare(
            'SELECT id, type, message, link, is_read, created_at FROM notifications
              WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 50'
        );
        $stmt->execute([$user['id']]);
        $list = array_map(fn($n) => [
            'id'      => (int) $n['id'],
            'type'    => $n['type'],
            'message' => $n['message'],
            'link'    => $n['link'],
            'is_read' => (bool) $n['is_read'],
            'sent_at' => $n['created_at'],
        ], $stmt->fetchAll());

        $this->ok($list, 200, ['unread_count' => $this->unread((int) $user['id'])]);
    }

    public function markRead(array $p): void
    {
        $user = $this->user();
        $this->requireCsrf();
        // "AND user_id = ?" means you can only touch your own notifications
        $this->db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
                 ->execute([$this->idParam($p), $user['id']]);
        $this->ok(['unread_count' => $this->unread((int) $user['id'])]);
    }

    public function markAllRead(): void
    {
        $user = $this->user();
        $this->requireCsrf();
        $this->db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$user['id']]);
        $this->ok(['unread_count' => 0]);
    }

    private function unread(int $userId): int
    {
        $s = $this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $s->execute([$userId]);
        return (int) $s->fetchColumn();
    }
}
