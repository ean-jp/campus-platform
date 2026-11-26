<?php
declare(strict_types=1);

namespace App\Controllers\Vendor;

/**
 * Vendor products (P4).
 *
 *   GET    /api/v1/vendor/items               list my products (?q= &status= &category_id=)
 *   GET    /api/v1/vendor/items/{id}          one of my products
 *   POST   /api/v1/vendor/items               add a product
 *   PATCH  /api/v1/vendor/items/{id}          change a product (any of the fields)
 *   DELETE /api/v1/vendor/items/{id}          delete a product
 *   POST   /api/v1/vendor/items/{id}/images   upload photos (multipart, field "images[]")
 *   DELETE /api/v1/vendor/items/{id}/images/{imageId}
 *
 * Every query includes "vendor_id = <my vendor id>", so a vendor can never see
 * or change another vendor's products, even by typing a different id.
 */
final class ItemController extends VendorBase
{
    public const MAX_IMAGES = 4;
    public const MAX_IMAGE_BYTES = 2 * 1024 * 1024;   // 2 MB
    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const CONDITIONS = ['new', 'like_new', 'good', 'fair'];
    private const LOW_STOCK = 5;

    // ================================================================ list

    public function index(): void
    {
        $vendor = $this->vendor();
        $where = ['i.vendor_id = ?', "i.status <> 'deleted'"];
        $args = [$vendor['id']];

        $q = self::str($_GET['q'] ?? '');
        if ($q !== '') {
            $where[] = 'i.name LIKE ?';
            $args[] = '%' . addcslashes(mb_substr($q, 0, 80), '%_\\') . '%';
        }
        $cat = $_GET['category_id'] ?? '';
        if (ctype_digit((string) $cat)) {
            $where[] = 'i.category_id = ?';
            $args[] = (int) $cat;
        }
        $statusSql = [
            'active'   => "i.status = 'active' AND i.stock > 0",
            'sold_out' => "i.status = 'active' AND i.stock = 0",
            'hidden'   => "i.status = 'hidden'",
            'removed'  => "i.status = 'removed'",
        ];
        $status = $_GET['status'] ?? '';
        if (isset($statusSql[$status])) {
            $where[] = $statusSql[$status];
        }

        $rows = $this->select('WHERE ' . implode(' AND ', $where) . ' ORDER BY i.created_at DESC, i.id DESC', $args);

        // counts for the filter tabs (ignore the status filter itself)
        $c = $this->db->prepare(
            "SELECT COUNT(*) AS all_items,
                    SUM(status = 'active' AND stock > 0) AS active,
                    SUM(status = 'active' AND stock = 0) AS sold_out,
                    SUM(status = 'hidden') AS hidden,
                    SUM(status = 'removed') AS removed
               FROM items WHERE vendor_id = ? AND status <> 'deleted'"
        );
        $c->execute([$vendor['id']]);
        $counts = array_map('intval', $c->fetch());

        $this->ok(array_map([$this, 'shape'], $rows), 200, ['counts' => [
            'all' => $counts['all_items'], 'active' => $counts['active'], 'sold_out' => $counts['sold_out'],
            'hidden' => $counts['hidden'], 'removed' => $counts['removed'],
        ]]);
    }

    public function show(array $p): void
    {
        $vendor = $this->vendor();
        $this->ok($this->shape($this->mine($this->idParam($p), (int) $vendor['id']), true));
    }

    // ================================================================ add / change

    public function store(): void
    {
        $vendor = $this->vendor();
        $this->requireCsrf();
        $data = $this->validate($this->input(), true);

        $stmt = $this->db->prepare(
            'INSERT INTO items (vendor_id, category_id, name, description, price, stock, item_condition, allow_pickup, allow_delivery, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $vendor['id'], $data['category_id'], $data['name'], $data['description'], $data['price'], $data['stock'],
            $data['item_condition'], (int) $data['allow_pickup'], (int) $data['allow_delivery'], $data['status'],
        ]);
        $id = (int) $this->db->lastInsertId();
        $this->ok($this->shape($this->mine($id, (int) $vendor['id']), true), 201);
    }

    public function update(array $p): void
    {
        $vendor = $this->vendor();
        $this->requireCsrf();
        $item = $this->mine($this->idParam($p), (int) $vendor['id']);
        if ($item['status'] === 'removed') {
            $this->fail(403, 'REMOVED_BY_ADMIN', 'The admin removed this product, so it cannot be changed.');
        }
        $data = $this->validate($this->input(), false);   // PATCH: only the fields that were sent
        if (!$data) {
            $this->invalid(['name' => 'Nothing to update.']);
        }
        $pickup   = $data['allow_pickup']   ?? (bool) $item['allow_pickup'];
        $delivery = $data['allow_delivery'] ?? (bool) $item['allow_delivery'];
        if (!$pickup && !$delivery) {
            $this->invalid(['allow_pickup' => 'Buyers need at least one way to get the product.']);
        }
        $set = [];
        $args = [];
        foreach ($data as $col => $val) {               // column names come from validate(), never from the user
            $set[] = "$col = ?";
            $args[] = is_bool($val) ? (int) $val : $val;
        }
        $args[] = $item['id'];
        $args[] = $vendor['id'];
        $this->db->prepare('UPDATE items SET ' . implode(', ', $set) . ' WHERE id = ? AND vendor_id = ?')->execute($args);
        $this->ok($this->shape($this->mine((int) $item['id'], (int) $vendor['id']), true));
    }

    public function destroy(array $p): void
    {
        $vendor = $this->vendor();
        $this->requireCsrf();
        $item = $this->mine($this->idParam($p), (int) $vendor['id']);

        $used = $this->db->prepare('SELECT COUNT(*) FROM order_items WHERE item_id = ?');
        $used->execute([$item['id']]);

        if ((int) $used->fetchColumn() > 0) {
            // Old orders still point at this product, so keep the row but hide it everywhere.
            $this->db->prepare("UPDATE items SET status = 'deleted' WHERE id = ? AND vendor_id = ?")->execute([$item['id'], $vendor['id']]);
            $this->db->prepare('DELETE FROM cart_items WHERE item_id = ?')->execute([$item['id']]);
        } else {
            $files = $this->imagesOf((int) $item['id']);
            $this->db->prepare('DELETE FROM items WHERE id = ? AND vendor_id = ?')->execute([$item['id'], $vendor['id']]);
            foreach ($files as $img) {
                $this->deleteFile($img['file_path']);
            }
        }
        $this->ok(['id' => (int) $item['id'], 'deleted' => true]);
    }

    // ================================================================ photos

    public function uploadImages(array $p): void
    {
        $vendor = $this->vendor();
        $this->requireCsrf();
        $item = $this->mine($this->idParam($p), (int) $vendor['id']);

        $files = $this->uploadedFiles('images');
        if (!$files) {
            $this->invalid(['images[]' => 'Choose at least one photo.']);
        }
        $existing = count($this->imagesOf((int) $item['id']));
        if ($existing + count($files) > self::MAX_IMAGES) {
            $left = max(0, self::MAX_IMAGES - $existing);
            $this->invalid(['images[]' => "A product can have up to " . self::MAX_IMAGES . " photos. You can add $left more."]);
        }

        // Check every file BEFORE saving any of them.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $checked = [];
        foreach ($files as $f) {
            $label = '"' . mb_substr(basename((string) $f['name']), 0, 60) . '"';
            if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > self::MAX_IMAGE_BYTES) {
                $this->invalid(['images[]' => "$label is bigger than 2 MB."]);
            }
            if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
                $this->invalid(['images[]' => "$label could not be uploaded. Please try again."]);
            }
            $mime = $finfo->file($f['tmp_name']);                   // real type from the file's bytes, not its name
            if (!isset(self::IMAGE_TYPES[$mime]) || @getimagesize($f['tmp_name']) === false) {
                $this->invalid(['images[]' => "$label is not a JPG, PNG or WebP photo."]);
            }
            $checked[] = ['tmp' => $f['tmp_name'], 'ext' => self::IMAGE_TYPES[$mime]];
        }

        $dir = self::uploadDir();
        $order = $existing;
        $stmt = $this->db->prepare('INSERT INTO item_images (item_id, file_path, alt_text, sort_order) VALUES (?, ?, ?, ?)');
        foreach ($checked as $c) {
            $name = bin2hex(random_bytes(16)) . '.' . $c['ext'];   // random name: never the user's file name
            if (!move_uploaded_file($c['tmp'], $dir . '/' . $name)) {
                $this->fail(500, 'UPLOAD_FAILED', 'The photo could not be saved. Please try again.');
            }
            @chmod($dir . '/' . $name, 0644);
            $stmt->execute([$item['id'], 'uploads/items/' . $name, mb_substr($item['name'], 0, 150), $order++]);
        }
        $this->ok($this->shape($this->mine((int) $item['id'], (int) $vendor['id']), true), 201);
    }

    public function deleteImage(array $p): void
    {
        $vendor = $this->vendor();
        $this->requireCsrf();
        $item = $this->mine($this->idParam($p), (int) $vendor['id']);
        $imageId = $this->idParam($p, 'imageId');

        $stmt = $this->db->prepare('SELECT id, file_path FROM item_images WHERE id = ? AND item_id = ?');
        $stmt->execute([$imageId, $item['id']]);
        $img = $stmt->fetch();
        if (!$img) {
            $this->fail(404, 'NOT_FOUND', 'That photo was not found.');
        }
        $this->db->prepare('DELETE FROM item_images WHERE id = ?')->execute([$img['id']]);
        $this->deleteFile($img['file_path']);
        $this->ok($this->shape($this->mine((int) $item['id'], (int) $vendor['id']), true));
    }

    // ================================================================ checks

    /**
     * Check the form. $creating = true means every required field must be there.
     * Returns only safe, cleaned values, keyed by real column names.
     */
    private function validate(array $in, bool $creating): array
    {
        $out = [];
        $err = [];
        $has = fn(string $k) => $creating || array_key_exists($k, $in);

        if ($has('name')) {
            $name = self::str($in['name'] ?? '');
            if (mb_strlen($name) < 2) $err['name'] = 'Enter a product name (at least 2 characters).';
            elseif (mb_strlen($name) > 150) $err['name'] = 'Keep the name under 150 characters.';
            else $out['name'] = $name;
        }
        if ($has('category_id')) {
            $cat = self::str($in['category_id'] ?? '');
            $stmt = $this->db->prepare('SELECT id FROM categories WHERE id = ? AND is_active = 1');
            $stmt->execute([ctype_digit($cat) ? (int) $cat : 0]);
            if (!$stmt->fetch()) $err['category_id'] = 'Choose a category.';
            else $out['category_id'] = (int) $cat;
        }
        if ($has('description')) {
            $d = self::str($in['description'] ?? '');
            if (mb_strlen($d) < 10) $err['description'] = 'Describe the product in at least 10 characters.';
            elseif (mb_strlen($d) > 1000) $err['description'] = 'Keep the description under 1,000 characters.';
            else $out['description'] = $d;
        }
        if ($has('price')) {
            $price = self::str($in['price'] ?? '');
            if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $price) || (float) $price < 0.5) {
                $err['price'] = 'Enter a price of at least GH₵ 0.50 (e.g. 45 or 45.50).';
            } else {
                $out['price'] = number_format((float) $price, 2, '.', '');
            }
        }
        if ($has('stock')) {
            $stock = self::str($in['stock'] ?? '');
            if (!ctype_digit($stock) || (int) $stock > 100000) $err['stock'] = 'Enter a whole number from 0 up.';
            else $out['stock'] = (int) $stock;
        }
        if ($has('status')) {
            $s = self::str($in['status'] ?? 'active');
            if (!in_array($s, ['active', 'hidden'], true)) $err['status'] = 'Choose "On sale" or "Hidden".';
            else $out['status'] = $s;
        }
        if ($has('condition')) {
            $c = self::str($in['condition'] ?? '');
            if ($c !== '' && !in_array($c, self::CONDITIONS, true)) $err['condition'] = 'Choose a condition from the list.';
            else $out['item_condition'] = $c === '' ? null : $c;
        }
        if ($has('allow_pickup'))   $out['allow_pickup']   = self::bool($in['allow_pickup'] ?? ($creating ? true : false));
        if ($has('allow_delivery')) $out['allow_delivery'] = self::bool($in['allow_delivery'] ?? false);

        $pickup   = $out['allow_pickup']   ?? null;
        $delivery = $out['allow_delivery'] ?? null;
        if ($pickup === false && $delivery === false) {
            $err['allow_pickup'] = 'Buyers need at least one way to get the product.';
        }

        if ($err) {
            $this->invalid($err);
        }
        return $out;
    }

    // ================================================================ data helpers

    /** Load one product that belongs to this vendor, or 404 (also for other vendors' products). */
    private function mine(int $id, int $vendorId): array
    {
        $rows = $this->select("WHERE i.id = ? AND i.vendor_id = ? AND i.status <> 'deleted'", [$id, $vendorId]);
        if (!$rows) {
            $this->fail(404, 'NOT_FOUND', 'That product was not found in your shop.');
        }
        return $rows[0];
    }

    private function select(string $tail, array $args): array
    {
        $stmt = $this->db->prepare(
            "SELECT i.id, i.vendor_id, i.category_id, c.name AS category, i.name, i.description, i.price, i.stock,
                    i.item_condition, i.allow_pickup, i.allow_delivery, i.status, i.created_at, i.updated_at,
                    (SELECT file_path FROM item_images im WHERE im.item_id = i.id ORDER BY im.sort_order, im.id LIMIT 1) AS image,
                    (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi JOIN orders o ON o.id = oi.order_id
                      WHERE oi.item_id = i.id AND o.status <> 'cancelled' AND o.created_at >= NOW() - INTERVAL 7 DAY) AS sold_week
               FROM items i JOIN categories c ON c.id = i.category_id $tail"
        );
        $stmt->execute($args);
        return $stmt->fetchAll();
    }

    private function imagesOf(int $itemId): array
    {
        $stmt = $this->db->prepare('SELECT id, file_path, alt_text, sort_order FROM item_images WHERE item_id = ? ORDER BY sort_order, id');
        $stmt->execute([$itemId]);
        return $stmt->fetchAll();
    }

    /** The JSON a page receives for one product. */
    private function shape(array $r, bool $withImages = false): array
    {
        $stock = (int) $r['stock'];
        $display = $r['status'];                       // what the label on the card says
        if ($r['status'] === 'active' && $stock === 0) $display = 'sold_out';

        $out = [
            'id'             => (int) $r['id'],
            'name'           => $r['name'],
            'category_id'    => (int) $r['category_id'],
            'category'       => $r['category'],
            'description'    => $r['description'] ?? '',
            'price'          => (float) $r['price'],
            'stock'          => $stock,
            'low_stock'      => $stock > 0 && $stock <= self::LOW_STOCK,
            'condition'      => $r['item_condition'],
            'allow_pickup'   => (bool) $r['allow_pickup'],
            'allow_delivery' => (bool) $r['allow_delivery'],
            'status'         => $r['status'],          // saved value: active | hidden | removed
            'display_status' => $display,              // active | sold_out | hidden | removed
            'image'          => $r['image'],           // path from the site root, e.g. uploads/items/ab12.jpg (or null)
            'sold_week'      => (int) $r['sold_week'],
            'created_at'     => $r['created_at'],
        ];
        if ($withImages) {
            $out['images'] = array_map(fn($i) => ['id' => (int) $i['id'], 'path' => $i['file_path']], $this->imagesOf((int) $r['id']));
            $out['max_images'] = self::MAX_IMAGES;
        }
        return $out;
    }

    /** $_FILES['images'] arrives as separate lists; turn it into one array per file. */
    private function uploadedFiles(string $field): array
    {
        $f = $_FILES[$field] ?? null;
        if (!$f || !isset($f['name'])) return [];
        if (!is_array($f['name'])) return $f['error'] === UPLOAD_ERR_NO_FILE ? [] : [$f];
        $list = [];
        foreach ($f['name'] as $i => $name) {
            if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            $list[] = ['name' => $name, 'tmp_name' => $f['tmp_name'][$i], 'size' => $f['size'][$i], 'error' => $f['error'][$i]];
        }
        return $list;
    }

    private static function uploadDir(): string
    {
        $dir = BASE_PATH . '/public/uploads/items';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create upload folder');
        }
        return $dir;
    }

    /** Delete a photo file, but only inside public/uploads/items (never anywhere else). */
    private function deleteFile(string $relative): void
    {
        $base = realpath(self::uploadDir());
        $path = realpath(BASE_PATH . '/public/' . $relative);
        if ($base && $path && str_starts_with($path, $base . DIRECTORY_SEPARATOR) && is_file($path)) {
            @unlink($path);
        }
    }
}
