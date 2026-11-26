# How to run the Campus Platform on your laptop (XAMPP, Windows)

## 1. One-time setup
1. Install **XAMPP** (PHP 8.2 or newer). Open the XAMPP Control Panel and **Start** both **Apache** and **MySQL**.
2. Put the project folder at `C:\xampp\htdocs\campus-platform` (so `C:\xampp\htdocs\campus-platform\public\index.php` exists).
3. In that folder, copy `.env.example` to a new file named `.env` (use VS Code or File Explorer with "file name extensions" turned on, so it is not `.env.txt`). The defaults already match XAMPP: user `root`, empty password, database `campus_platform`, `APP_ENV=development`.
4. Create the database: open <http://localhost/phpmyadmin> -> **Import** -> choose `database/schema.sql` -> **Go**. Then import `database/seed.sql` the same way. (The schema creates the `campus_platform` database itself. Re-importing both resets all data.)

## 2. Check it works
Open these in the browser:

| URL | You should see |
|---|---|
| <http://localhost/campus-platform/public/api/v1/ping> | `{"success":true,"data":{"pong":true}}` |
| <http://localhost/campus-platform/public/api/v1/categories> | the five categories as JSON |
| <http://localhost/campus-platform/public/api/v1/items?per_page=3> | three items, with a `meta` block |

If the pages open but `/api/v1/...` gives a 404, Apache is not reading `public/.htaccess`: in XAMPP Control Panel -> Apache -> Config -> `httpd.conf`, make sure `mod_rewrite` is not commented out and the `htdocs` Directory block has `AllowOverride All`, then restart Apache. If the API says "Something went wrong", look at the PHP error log (XAMPP -> Apache -> Logs) and check `.env`.

## 3. The pages

| Page | URL |
|---|---|
| Shop home | <http://localhost/campus-platform/public/shop/index.html> |
| Browse | <http://localhost/campus-platform/public/shop/browse.html> |
| Sign in | <http://localhost/campus-platform/public/login.html> |
| Vendor dashboard | <http://localhost/campus-platform/public/vendor/index.html> |
| Style guide | <http://localhost/campus-platform/public/style-guide.html> |

**Mock or real data?** `api.js` has `MOCK = true`, so the pages show sample data and nothing is saved. To use the real database for one browser tab, add `?mock=0` to the page address, for example
`http://localhost/campus-platform/public/shop/browse.html?mock=0&as=3`
`as=3` means "act as the guest user", `as=2` is the Ashesi student. This works only until the real login exists and only while `.env` says `APP_ENV=development`. The tag **MOCK DATA** at the bottom-left shows when mock mode is on.

## 4. Reset or inspect the data
- Reset everything: re-import `schema.sql` then `seed.sql` in phpMyAdmin.
- Look at orders and stock: phpMyAdmin -> `campus_platform` -> tables `orders`, `order_items`, `items`.

## 5. No XAMPP yet? (mock pages only)
In VS Code, install the **Live Server** extension, right-click `public/shop/index.html` -> **Open with Live Server**. The shop pages work on the sample data. The real API and database need XAMPP.
