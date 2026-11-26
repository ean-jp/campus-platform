# API guide: how pages talk to the backend (Person 3)

Works today with **no PHP written**: while `MOCK = true` in `api.js`, every call reads sample JSON from `public/assets/mock/`. When the real endpoint is live, set `MOCK = false` (one line in `api.js`). Your page code does not change.

## 1. Add to every page that needs data

From a page inside a sub-folder (`public/vendor/orders.html`):

```html
<script src="../assets/js/ui.js"></script>
<script src="../assets/js/api.js"></script>
<script src="../assets/js/vendor-orders.js"></script>   <!-- your own page script -->
```
(from `public/index.html` it would be `assets/js/...`). `api.js` finds the site root by itself, so it also works in a XAMPP sub-folder.

## 2. The calls

```js
const orders = await API.get('/vendor/orders', { status: 'placed' });   // -> array
await API.patch('/vendor/orders/1042', { status: 'received' });
await API.post('/vendor/items', API.formToJson(form));                   // form -> JSON
await API.post('/vendor/items/5/images', new FormData(form));            // file upload
await API.delete('/vendor/items/5');
const res = await API.get('/vendor/items', { page: 2 }, { full: true }); // res.data + res.meta
```
Paths are the ones in the team plan, without `/api/v1` (added for you). POST/PATCH/DELETE automatically send the CSRF token (`<meta name="csrf-token">`, or the hidden `csrf_token` input already in your forms).

## 3. Errors, loading, empty states (ui.js)

A failed call already shows a red toast and throws, so most code is just:

```js
const list = document.getElementById('orders');
UI.loading(list);                                   // spinner
try {
  const orders = await API.get('/vendor/orders');
  if (!orders.length) return UI.empty(list, 'No orders yet', 'New orders show up here.');
  list.textContent = '';
  orders.forEach(o => list.appendChild(renderOrder(o)));
} catch (err) {
  UI.empty(list, 'Could not load orders', err.message);   // toast was shown already
}
```
Buttons: `await UI.run(button, () => API.patch(...))` disables the button while it works. Success message: `UI.success('Order accepted')`. Validation errors on a form: `UI.fieldErrors(form, err.fields)` (fills the `.error` line under each input whose `name` matches).

**Rule:** put user text on the page with `textContent`, never `innerHTML` (the security checklist).

## 4. Wiring example: accept / decline on orders.html

```js
document.querySelectorAll('.v-actions button[name="status"]').forEach(btn => {
  btn.addEventListener('click', async e => {
    e.preventDefault();
    const id = btn.closest('.v-order').id.replace('order-', '');
    const body = { status: btn.value };
    const code = btn.form.querySelector('[name="pickup_code"]');
    if (code) body.pickup_code = code.value;
    try {
      await UI.run(btn, () => API.patch('/vendor/orders/' + id, body));
      UI.success('Order #' + id + ' is now ' + btn.value);
    } catch (err) { /* toast already shown; 409/422 explain why */ }
  });
});
```

## 5. Response shapes (what Person 2's `Response.php` really sends today)

```json
{ "success": true,  "data": { } }
{ "success": false, "error": "Only 2 left in stock." }
```
`Response::success($data, $status)` and `Response::error($message, $status)`. `api.js` shows the `error` string in a red toast and throws it, so this already works. Status codes: 200, 201, 400, 401 (`api.js` sends the user to login), 403, 404, 405, 409 (conflict: out of stock, slot taken), 422, 429, 500.

Lists now carry a top-level `meta` (`Response::success($data, 200, $meta)`), read with `API.get(..., { full: true })`.

Optional extras `api.js` understands if Person 2 adds them later (nothing breaks without them): `error` as an object `{ "code": "OUT_OF_STOCK", "message": "..." }`, `error.fields` for per-input messages on 422 (`UI.fieldErrors`), .

## 6. Sample data per endpoint (matches `database/schema.sql`)

Every endpoint below has a sample file in `public/assets/mock/`, using the real column names so nothing changes when the mock is switched off.

| Call | Mock file | Real columns / notes |
|---|---|---|
| `GET /vendor/orders?status=` | `vendor/orders.json` | `status`: received, preparing, ready, completed, cancelled (there is no "placed"; new orders arrive as `received`). Lines use `item_name`, `unit_price`, `quantity`. `delivery_spot`, `contact_phone`. The buyer's `pickup_code` is NOT sent to the vendor; the vendor types it. |
| `PATCH /vendor/orders/{id}` | echoed | body `{ status, pickup_code? }` |
| `GET /vendor/items` , `/vendor/items/{id}` | `vendor/items.json` | `status`: active, hidden (removed = admin). Sold out = `stock` 0. `images[].file_path`. |
| `POST /vendor/items` , `PATCH` , `DELETE` | echoed | form fields as in `product-form.html` |
| `POST /vendor/items/{id}/images` | echoed | multipart, field `images[]` |
| `GET /vendor/stats` | `vendor/stats.json` | computed in PHP: `today`, `low_stock`, `best_sellers`, `period`, `recent_completed` |
| `GET/PATCH /vendor/profile` | `vendor/profile.json` | `shop_name`, `description`, `phone`, `logo_path`, `opens_at`, `closes_at`, `status` |
| `GET /notifications` | `notifications.json` | `is_read`, `created_at`; `meta.unread_count` for the bell |
| `GET /categories` | `categories.json` | same five names as `seed.sql` |
| Marketplace (Person 3) | `items.json`, `items/{id}.json`, `vendors/{id}.json`, `cart.json`, `orders.json`, `orders/{id}.json` | captured from the REAL endpoints; see `docs/SHOP-API.md` |

In mock mode `?status=` and `?q=` filter lists; other filters are ignored. Writes are not saved: they just succeed so buttons and toasts can be built.

**Mismatches between the vendor pages and the schema (Person 4 and Person 2 to settle):**
- `profile.html` has opening hours per day (`hours[mon][open]` ...) but `vendors` has only one `opens_at` / `closes_at`.
- `product-form.html` has `allow_pickup` / `allow_delivery`, which no table stores.
- Pages show a "Placed" status; the database starts at `received`.
- `vendors` has no `location` or `vendor_type` columns, which the profile page shows.

## 7. Add your own mock data (Person 5, Person 3)

Create `public/assets/mock/<same path as the endpoint>.json`, for example `services.json` for `GET /services`, or `providers/2/slots.json` for `GET /providers/2/slots`. For `GET /x/3` the helper also looks for id 3 inside `x.json`. A missing file shows a toast naming the file to create.

## 8. Switching to the real API

1. Person 2's router answers `/api/v1/...` (it already does for `/api/v1/ping`; open it to check routing + database).
2. **Router has no PATCH.** `Router.php` only has `get/post/put/delete`, and the plan uses PATCH for updates. Person 2: add one line next to the others, `public function patch(string $path, callable|array $handler, array $middleware = []): void { $this->add('PATCH', $path, $handler, $middleware); }`. Until then, PATCH calls answer 405.
3. Set `MOCK = false` in `api.js` (or test one tab with `?mock=0`).
4. Person 1's login page URL goes in `API.config.loginUrl`.
5. CSRF: the shared layout needs `<meta name="csrf-token" content="...">` once Person 1 has the token helper. Until then the header is skipped.
