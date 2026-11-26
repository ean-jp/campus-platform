# Person 4 – Stage 2: vendor pages connected to the database

The vendor pages now save and load real data through PHP and MySQL.
Tested end to end with the team's own `schema.sql`, `seed.sql`, Router, Session and api.js.

## What works now

| Page | What you can do |
| --- | --- |
| `vendor/apply.html` | Send a "Become a vendor" request. It is saved as **pending** and the admin gets a notification. |
| `vendor/products.html` | See your real products. Search, filter by status and category, hide or show a product. |
| `vendor/product-form.html` | Add or edit a product: name, category, **condition**, description, price, stock, visibility, pickup and/or delivery, up to 4 photos. Delete a product. |
| `vendor/orders.html` | See your orders. Accept → Mark as ready → Complete (needs the buyer's 4-digit pickup code), or cancel/decline. The buyer gets a notification at each step. |
| `vendor/index.html` | Dashboard with real numbers, waiting orders, low stock and best sellers. |

Still sample data (Stage 3): `sales.html`, `profile.html`, `notifications.html`.
They already check the login and show the right header.

## Security built in

- **Only your own shop:** every database query includes "this vendor's id", so typing another
  product or order number gives "not found". Tested.
- **Vendor check from the database**, not from the browser: the user must have an **approved**
  row in `vendors`. Pending, rejected and suspended shops are blocked with a clear message.
- **CSRF token** on every save, change and delete (`app/Services/Csrf.php`).
- **SQL injection:** prepared statements only. Tested with `' OR 1=1 --`.
- **XSS:** pages use `textContent` only, never `innerHTML`.
- **Photo uploads:** the real file type is checked from the file's contents (JPG, PNG or WebP only),
  max 2 MB, max 4 per product, files get random names, and `public/uploads/.htaccess` stops any
  script from running there. Tested with a PHP file renamed to `.jpg`: it was refused.
- **Orders can't skip steps or be changed twice:** the row is locked while it is updated.
  Completing needs the buyer's pickup code. Cancelling puts the stock back.

## How to install on your laptop

1. Copy everything from this zip into the repo, keeping the same folders. Choose **Replace** when asked.
2. Database (phpMyAdmin → SQL tab, or the `mysql` command):
   - **Fresh setup:** import `database/schema.sql`, then `database/seed.sql`, then
     `database/seed_p4_vendor_demo.sql`.
   - **Already imported the old schema?** Run `database/migrations/2026_11_24_p4_vendor_products.sql` once,
     then `database/seed_p4_vendor_demo.sql`.
3. Copy `.env.example` to `.env` (keep `APP_ENV=development`). Never commit `.env`.
4. Open `http://localhost/campus-platform/public/dev-login.php`, choose
   **Kente Corner Owner (vendor)** and click **Log in**.
5. Open `http://localhost/campus-platform/public/vendor/index.html`.

To test as a buyer applying to sell: log in as **Test Ashesi Buyer**, then open `vendor/apply.html`.

## The API (all under `/api/v1`)

| Method | Path | What it does |
| --- | --- | --- |
| GET | `/vendor/me` | who is logged in, their shop (or null), unread count, waiting orders, CSRF token |
| POST | `/vendor/apply` | "Become a vendor" request |
| GET | `/vendor/stats` | dashboard numbers |
| GET | `/vendor/categories` | categories for the product form |
| GET | `/vendor/items?q=&status=&category_id=` | my products (`meta.counts` for the tabs) |
| POST | `/vendor/items` | add a product |
| GET / PATCH / DELETE | `/vendor/items/{id}` | one product |
| POST | `/vendor/items/{id}/images` | upload photos (multipart `images[]`) |
| DELETE | `/vendor/items/{id}/images/{imageId}` | remove a photo |
| GET | `/vendor/orders?status=` | my orders (`active`, `received`, `preparing`, `ready`, `completed`, `cancelled`, `all`) |
| PATCH | `/vendor/orders/{id}` | `{ "status": "...", "pickup_code": "1234" }` |

Replies use the shapes in `docs/API-GUIDE.md` section 5, including `error.code` and `error.fields`.

## Changes outside my own files (tell the team before merging)

| File | Owner | Change | Why |
| --- | --- | --- | --- |
| `app/Core/Router.php` | P2 | added one line: `patch()` | api.js sends PATCH for updates, and the Router had no PATCH, so every update failed with "Method not allowed". |
| `database/schema.sql` → `vendors` | P2 | added `vendor_type` and `location` | the "Become a vendor" form asks for them, and the requirements list vendor type. |
| `public/dev-login.php` | P1 | reads `APP_ENV` from `.env`; test users now use real ids (buyer 2, guest 3, vendor 8, admin 1) | Before, it always said "Not available" on XAMPP, and its fake ids weren't in the database, so nothing could be saved. |
| `database/schema.sql` → `items` | P4 (mine) | `item_condition`, `allow_pickup`, `allow_delivery`, status `deleted` | condition chip, pickup/delivery for every seller, deleting a product that has old orders. |

## Notes for teammates

- **P1:** there is no `login.html` yet, so when nobody is logged in, api.js sends people to a page that
  doesn't exist. Set `API.config.loginUrl` when your login page is ready. My `Csrf.php` can move into
  `app/Core` for everyone to use.
- **P1:** `users.role` can only be `user` or `admin`. When someone logs in, set the session role to
  `vendor` if they have an approved row in `vendors`. My pages already check the `vendors` table
  directly, so they work either way.
- **P2:** `Response::error()` sends the error as plain text, but `docs/API-GUIDE.md` says
  `{ code, message, fields }`. My vendor API already uses the guide's shape.
- **P3:** orders start as `received` in the schema (there is no `placed`). The vendor mock data
  in `assets/mock/vendor/orders.json` uses `placed`.
- **P3:** "Sold out" is not saved as a status. It means `stock = 0`. The API returns `display_status: "sold_out"`.
- **P3:** checkout should check `items.status = 'active'` and `stock > 0`, and treat `allow_pickup` /
  `allow_delivery` as the choices the buyer can pick. Items with status `deleted` must never show.
- **Vendor pages always use the real API**, even while api.js is in mock mode for other pages.
