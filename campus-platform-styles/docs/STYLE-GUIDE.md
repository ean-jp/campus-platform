# Campus Platform: style guide (for Persons 4 and 5)

Owner: Person 3. The look comes from the approved sign-in page: Poppins, Ashesi red, pill buttons, soft rounded cards, automatic light/dark mode.

## 1. Files

```
public/assets/css/styles.css   shared kit: link on EVERY page (do not edit; ask Person 3)
public/assets/css/login.css    sign-in page only: do not link it anywhere else
public/assets/css/vendor.css   Person 4's page-specific styles (create it)
public/assets/css/resources.css Person 5's page-specific styles (create it)
public/assets/img/             ashesi-logo.png, login images
public/login.html              reference page that uses the kit
```

## 2. Page head (same order everywhere)

```html
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap">
<link rel="stylesheet" href="/assets/css/styles.css">
<link rel="stylesheet" href="/assets/css/vendor.css">   <!-- your own file, if you need one -->
```

## 3. Rules

1. Use the shared classes first. Only write your own CSS for things the kit does not have.
2. Put your own rules in your own file and prefix your class names (`vendor-...`, `res-...`) so nothing clashes.
3. Use the colour variables (`var(--red)`, `var(--ink)`, `var(--mut)`, `var(--line)`, `var(--card)`, `var(--soft)`), never fixed hex colours, so dark mode keeps working.
4. Need a new shared component? Ask Person 3 (or build it in your own file and hand it over later). Do not edit `styles.css` directly.
5. Show a loading, success and error state on every page: spinner / skeleton, toast, and `.empty`.

## 4. Page skeleton

```html
<body>
  <header class="site-header">
    <div class="bar">
      <a class="logo" href="/"><img alt="" src="/assets/img/ashesi-logo.png">Campus Platform</a>
      <nav class="nav-links">
        <a href="/shop" aria-current="page">Shop</a>
        <a href="/resources">Resources</a>
      </nav>
      <span class="nav-spacer"></span>
      <button class="icon-btn" aria-label="Notifications">🔔<span class="count">3</span></button>
    </div>
  </header>

  <main class="container page"> ... your page ... </main>

  <nav class="bottom-nav"><div class="items"> <a href="/shop" aria-current="page">Shop</a> <a href="/resources">Resources</a> </div></nav>
  <div class="toast-region" id="toasts"></div>
</body>
```

The shared header, bottom menu and footer will move into `layout.php` so you will not copy them by hand. Until then, use the skeleton above.

## 5. Class cheat sheet

| Need | Markup |
|---|---|
| Main button | `<button class="btn primary">Save item</button>` |
| Secondary button | `<button class="btn ghost">Cancel</button>` |
| Destructive button | `<button class="btn danger auto sm">Delete</button>` |
| Small / auto-width button | add `auto sm`: `class="btn primary auto sm"` |
| Text input | `<div class="field"><label>Price</label><input class="input"><span class="hint">In GH₵</span></div>` |
| Dropdown / textarea | `class="select"` / `class="textarea"` |
| Field with an error | `<div class="field invalid"> ... <span class="error">Enter a price.</span></div>` |
| Checkbox | `<label class="check"><input type="checkbox"> Sold out</label>` |
| Card panel | `<div class="card-box"> ... </div>` |
| Product / service card | `<a class="item-card" href="#"><img class="thumb" src="" alt=""><div class="body"><span class="title">Name</span><span class="meta">Vendor</span><span class="price">GH₵ 35.00</span></div></a>` |
| Order status badge | `<span class="badge status-preparing">Preparing</span>` (also `status-placed`, `-received`, `-ready`, `-completed`, `-cancelled`) |
| Other badges | `class="badge ok"` / `warn` / `info` / `danger` / plain `badge` |
| Notification / cart count | `<button class="icon-btn">🔔<span class="count">3</span></button>` |
| Grid of cards | `<div class="grid cols-3"> ... </div>` (also `cols-2`, `cols-4`, `auto`; collapses on phones) |
| Row of items | `<div class="row between wrap"> ... </div>` |
| Vertical spacing between children | `<div class="stack"> ... </div>` |
| Empty page / list | `<div class="empty"><h3>No orders yet</h3><p>New orders show up here.</p></div>` |
| Loading spinner | `<div class="loading-block"><span class="spinner lg"></span></div>` |
| Loading placeholder | `<span class="skeleton line" style="width:70%"></span>` / `class="skeleton thumb"` |
| Toast message | `<div class="toast success">Item saved.</div>` (`error`, `warn`) inside `#toasts` |
| Modal | `.modal-backdrop > .modal` with `.actions` for buttons |
| Quantity stepper | `<div class="qty"><button>−</button><span>2</span><button>+</button></div>` |
| Order timeline | `<ul class="timeline"><li class="done">Placed</li><li class="current">Preparing</li><li>Ready</li></ul>` |
| Muted / small text | `class="muted"` / `class="small"` |

Toasts and the modal are CSS only for now. Person 3 is writing `api.js` and `ui.js`, which will show and hide them for you (for example `showToast('Item saved', 'success')`).
