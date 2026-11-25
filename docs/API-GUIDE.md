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

## 5. Response shapes (Person 2's `response.php` must send exactly these)

```json
{ "success": true,  "data": { } }
{ "success": true,  "data": [ ], "meta": { "page": 1, "per_page": 20, "total": 57 } }
{ "success": false, "error": { "code": "OUT_OF_STOCK", "message": "Only 2 left." } }
{ "success": false, "error": { "code": "VALIDATION", "message": "Please check the highlighted fields.",
                               "fields": { "phone": "Enter a phone number." } } }
```
Status codes: 200, 201, 400, 401 (not logged in: `api.js` sends them to login), 403, 404, 409 (conflict: slot taken, out of stock), 422 (validation), 429.

## 6. Vendor sample data (Person 4)

Each endpoint below already has a sample file in `public/assets/mock/`. Use the same field names in PHP so nothing changes when the mock is switched off.

| Call | Mock file | Notes |
|---|---|---|
| `GET /vendor/orders?status=` | `vendor/orders.json` | order `status`: placed, received, preparing, ready, completed, cancelled. The buyer's pickup code is NOT included; the vendor types it. Guest phone is included only for that vendor's own order. |
| `PATCH /vendor/orders/{id}` | echoed | body `{ status, pickup_code? }` |
| `GET /vendor/items` , `GET /vendor/items/{id}` | `vendor/items.json` | `status`: active, sold_out, hidden |
| `POST /vendor/items` , `PATCH /vendor/items/{id}` , `DELETE` | echoed | form fields as named in `product-form.html` |
| `POST /vendor/items/{id}/images` | echoed | multipart, field `images[]` |
| `GET /vendor/stats` | `vendor/stats.json` | dashboard (`today`, `low_stock`, `best_sellers`) and sales page (`period`, `recent_completed`) |
| `GET/PATCH /vendor/profile` | `vendor/profile.json` | `hours.mon.open/close/closed` etc. |
| `GET /notifications` | `notifications.json` | `meta.unread_count` for the bell |
| `GET /categories` | `categories.json` | fills the category dropdowns |

In mock mode `?status=` and `?q=` filter lists; other filters are ignored. Writes are not saved: they just succeed so buttons and toasts can be built.

## 7. Add your own mock data (Person 5, Person 3)

Create `public/assets/mock/<same path as the endpoint>.json`, for example `services.json` for `GET /services`, or `providers/2/slots.json` for `GET /providers/2/slots`. For `GET /x/3` the helper also looks for id 3 inside `x.json`. A missing file shows a toast naming the file to create.

## 8. Switching to the real API

1. Person 2's router answers `/api/v1/...` and `response.php` uses the shapes in section 5.
2. Set `MOCK = false` in `api.js` (or test one tab with `?mock=0`).
3. Person 1's login page URL goes in `API.config.loginUrl`.

Not final until the group agrees: the error object (`code` + `message`), `fields` on 422, and the `meta` paging object. Person 2 needs to confirm these three.
