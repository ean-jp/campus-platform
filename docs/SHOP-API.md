# Marketplace API (Person 3): catalogue, cart, checkout, orders, reports

All paths start with `/api/v1`. Responses use the team shape: `{ "success": true, "data": ... }` or `{ "success": false, "error": "message" }`. Lists add `"meta": { page, per_page, total, total_pages }`.
Code: `app/Controllers/Marketplace/*`, `app/Services/Shop/*`, routes in `config/routes/shop.php`. Tested against the real schema + seed (62 checks, including two buyers racing for the last item).

## Public (no login)

| Call | What it does |
|---|---|
| `GET /categories` | `[{ id, name, slug }]` |
| `GET /items` | Active items of approved vendors. Query: `q`, `category` (id or slug), `vendor`, `min_price`, `max_price`, `sort` = `newest` (default) / `price_asc` / `price_desc` / `name`, `page`, `per_page` (max 50). |
| `GET /items/{id}` | One item: `images[]`, `vendor{ shop_name, phone, opens_at, closes_at, is_open }`, `rating{ avg, count }`, last 10 `reviews[]`. |
| `GET /vendors` | Approved shops: `[{ id, shop_name, description, logo, is_open, item_count }]` (feeds the shop filter). |
| `GET /vendors/{id}` | A shop: details, `is_open`, and its active `items[]`. |

An item looks like: `{ id, name, description, price, stock, in_stock, image, category{id,name,slug}, vendor{id,shop_name,is_open}, rating{avg,count}, created_at }`.
`image` is `null` until Person 4's uploads exist: show `assets/images/placeholder.svg` when it is null. Pending/suspended vendors, hidden items and inactive categories never appear (404).

## Needs login (cart, checkout, orders, reports)

| Call | Body | Notes |
|---|---|---|
| `GET /cart` | | Grouped by vendor: `{ groups:[{ vendor, items:[{ id, item_id, name, price, quantity, line_total, stock, image, problem }], subtotal }], item_count, total, delivery_possible, has_problems }`. `problem` is e.g. "Only 2 left" or "Sold out". `delivery_possible` is true only when every item is food. |
| `POST /cart/items` | `{ item_id, quantity? }` | Adds (or raises the quantity). 201 + the new cart. 409 if not enough stock. |
| `PATCH /cart/items/{id}` | `{ quantity }` | `{id}` is the cart line `id`, not the item id. Max 99. |
| `DELETE /cart/items/{id}` | | Removes the line. |
| `POST /checkout` | `{ fulfilment: "pickup"|"delivery", delivery_spot?, contact_phone?, payment_method?: "pay_on_delivery" }` | One order **per vendor**, all-or-nothing in a transaction. Returns `{ orders:[{ id, vendor, status, total, pickup_code, items... }], order_count, total }`. |
| `GET /orders` | | `?page`, `per_page`, `status`. Each row has `can_cancel`. |
| `GET /orders/{id}` | | Own orders only. Has `items[]`, `timeline[]` (`done`/`current`/`todo`), `pickup_code`, `can_cancel`. |
| `POST /orders/{id}/cancel` | | Only while status is `received`; gives the stock back. 409 afterwards. |
| `POST /reports` | `{ item_id, reason }` | Reason 5-500 chars. One open report per user per item. 409 if repeated. |

### Checkout rules (enforced on the server)
- Prices, totals, the pickup code and the account type are worked out in PHP; anything the client sends for them is ignored.
- `delivery` needs `delivery_spot` and is only allowed when every item is in the Food category (`Support::FOOD_CATEGORY_SLUGS`, one line to change). Otherwise 422.
- **Guests must give a phone number** (from the body or their profile). Ashesi users: optional. Format is checked.
- Stock is taken with `UPDATE items SET stock = stock - ? WHERE id = ? AND stock >= ?`; if someone got there first the whole checkout rolls back with 409 and the cart is kept.
- Each vendor gets a `new_order` row in `notifications` (until Person 4's Notifier exists).
- Only pay on delivery is accepted for now (the `orders.payment_method` column only allows that).

### Error codes you will see
401 not logged in · 403 suspended or bad CSRF token · 404 not found / not yours · 409 out of stock, already cancelled · 422 invalid input · 400 empty cart or bad JSON.

## Testing without the login page

Set `APP_ENV=development` in `.env`, then send a header to act as a seed user (2 = Ashesi student Ama, 3 = guest Kofi):

```
curl -H "X-Dev-User: 3" http://localhost/campus-platform/public/api/v1/cart
curl -H "X-Dev-User: 3" -H "Content-Type: application/json" -d "{\"item_id\":1,\"quantity\":2}" http://localhost/campus-platform/public/api/v1/cart/items
```
(or put `DEV_USER_ID=3` in `.env`). This only works while `APP_ENV=development`. Never deploy with it.

## Changes I made to other people's files (small, backward-compatible; please review)
- **Person 2:** `Router.php` gets a `patch()` method (it only had get/post/put/delete). `Response::success()` gets an optional third argument `$meta` for paging. Nothing else changed.
- **Person 1:** the API reads the logged-in user from `$_SESSION['user_id']`. Please set that in the login code. If the session also holds `csrf_token`, every POST/PATCH/DELETE must send it in the `X-CSRF-Token` header (`api.js` does this). When your `requireLogin()` is ready, swap the body of `CurrentUser::require()` in `app/Services/Shop/CurrentUser.php` to call it.
- **Person 4:** new-order and cancelled-order notifications are inserted straight into `notifications` with link `orders.html`. Switch to `Notifier::send()` when it exists. Vendor `orders.status` starts at `received`.
