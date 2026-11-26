# Buyer pages (Person 3): public/shop/

Static pages that build themselves with JavaScript from the marketplace API (`docs/SHOP-API.md`), using the shared kit (`styles.css`) plus `shop.css`.

| Page | URL | What it does |
|---|---|---|
| Home | `shop/index.html` | Hero, category tiles, newest items, recent orders. **Ashesi:** Campus Resources card. **Guest:** "link your Ashesi email" prompt. |
| Browse / search | `shop/browse.html?q=&category=&vendor=&min_price=&max_price=&sort=&page=` | Search, category tabs, shop / price / sort filters, paging. Every state is in the URL. |
| Product | `shop/item.html?id=` | Photos, price, stock, shop + open/closed, reviews, quantity, add to cart, report listing. |
| Shop | `shop/vendor.html?id=` | A vendor's menu grouped by category, with hours. |
| Cart | `shop/cart.html` | Grouped by vendor, change amount, remove, problems flagged. |
| Checkout | `shop/checkout.html` | Pickup or delivery (food only), phone (required for guests), pay on delivery. |
| Confirmation | `shop/confirmation.html` | The pickup code for each order. |
| My orders | `shop/orders.html?status=` | History with status tabs. |
| Order | `shop/order.html?id=` | Status timeline, pickup code, details, cancel while "received". |

## Files
```
public/shop/*.html               page shells (header, bottom menu and toasts are added by shop-common.js)
public/assets/css/shop.css       page styles (prefix shop-)
public/assets/js/shop-common.js  shared helpers: Shop.boot, header, item card, modal, pager...
public/assets/js/shop-<page>.js  one script per page
```
Each page loads `ui.js`, `api.js`, `shop-common.js`, then its own script. All text goes in with `textContent`, never `innerHTML`.

## Try it
- **Mock mode (default, no PHP needed):** open any page. Data comes from `assets/mock/` (captured from the real API). The sample user is an Ashesi student; add `?type=guest` to any page to see the guest view.
- **Real API:** set `MOCK = false` in `api.js` (or add `?mock=0` to a page URL). Until the login page exists, add `?as=3` (guest) or `?as=2` (Ashesi) to act as a seed user. The server needs `APP_ENV=development` in `.env`.

## What other people's work plugs into
- **Person 1:** a real `GET /me` returning `{ full_name, phone, account_type }` makes the homepage, header avatar and checkout phone rule use the logged-in user (today the pages use `mock/me.json`, or no user on the real API). Set `Shop.linkEmailUrl` (in `shop-common.js`) to the link-email page and `API.config.loginUrl` (in `api.js`) to the login page.
- **Person 5:** `GET /bookings` should return a list with `start_at`; the homepage shows the next one. `Shop.resourcesUrl` points at `resources/index.html`.
- **Person 4:** items show `assets/images/placeholder.svg` until `item_images` rows exist (the image path is relative to `public/`).

Tested: all nine pages in mock mode at desktop and phone width with no errors, plus a real-browser run against the real API and database (browse, search, add to cart, change quantity, delivery checkout, pickup code, tracking, cancel with stock restored, report a listing, login redirect): 17 checks passed.
