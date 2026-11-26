# Campus Platform: style guide (official look)

Owner: Person 3. The look is the team's Figma marketplace design: Ashesi red + pure black on a warm off-white page, Manrope type, rounded-square buttons, 20-24px cards, automatic light/dark mode.

**See it live:** open `public/style-guide.html` in the browser. Every component below is on that page; copy its markup.

## 1. Files

```
public/assets/css/styles.css      shared kit: link on EVERY page, first. Do not edit; ask Person 3.
public/assets/css/login.css       sign-in page only
public/assets/css/vendor.css      Person 4 (scoped to body.v-vendor)
public/assets/css/<yours>.css     page-specific styles, prefix your classes (res-..., shop-...)
public/assets/images/             logos and placeholders
public/style-guide.html           living reference of the kit
```

## 2. Page head (same order everywhere)

```html
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Poppins:wght@400;500;600&display=swap">
<link rel="stylesheet" href="../assets/css/styles.css">
<link rel="stylesheet" href="../assets/css/<yours>.css">
<script src="../assets/js/ui.js"></script>
<script src="../assets/js/api.js"></script>
```

## 3. Rules

1. Use the kit's classes first. Write your own CSS only for what the kit does not have, in your own file with your own prefix.
2. Colours only through the variables (`var(--red)`, `--ink`, `--mut`, `--bg`, `--card`, `--line`, `--soft`, `--red-soft`), never fixed hex, so dark mode keeps working.
3. Do not edit `styles.css`. Need something new? Ask Person 3, or build it in your own file and hand it over.
4. Every page shows loading, success and error states (`UI.loading`, `UI.success`, `UI.empty`; see `docs/API-GUIDE.md`).

## 4. Page skeleton

The shared header and bottom menu are in `public/style-guide.html`: copy the `<header class="site-header">` and `<nav class="bottom-nav">` blocks, change the links, and set `aria-current="page"` on the current one. They move into `layout.php` later.

```html
<body>
  <header class="site-header"> ... </header>
  <main class="container page"> ... your page ... </main>
  <nav class="bottom-nav"> ... </nav>
</body>
```

## 5. Cheat sheet

| Need | Markup |
|---|---|
| Main button (red) | `<button class="btn primary">Place order</button>` |
| Full-width button | add `block`: `class="btn primary block"` |
| Other buttons | `btn black`, `btn ghost` (outline), `btn soft` (pale red), `btn danger` (red outline) |
| Small button | add `sm` |
| Icon in a button | `<button class="btn primary"><i class="ic ic-bag" aria-hidden="true"></i>Add to cart</button>` |
| Text field | `<div class="field"><label for="p">Phone</label><input id="p" class="input"><span class="hint">Help text</span></div>` |
| Dropdown / textarea | `class="select"` / `class="textarea"` |
| Field with error | `<div class="field invalid">...<span class="error">Message</span></div>` |
| Checkbox | `<label class="check"><input type="checkbox"> Label</label>` |
| Choice cards | `<div class="options"><label class="option"><input type="radio" name="f"><div><strong>Pickup</strong><span>Collect it</span></div></label></div>` |
| Search bar | `<div class="search"><i class="ic ic-search"></i><input placeholder="Search"><button class="btn primary">Search</button></div>` |
| Filter tabs | `<div class="tabs"><a class="tab" aria-current="true">All</a><a class="tab">Food</a></div>` |
| Card panel | `<div class="card-box"><div class="card-head"><h3>Title</h3></div> ... </div>` |
| Product card | `<a class="item-card" href="#"><div class="media"><img alt="..." src="..."><span class="tag">Open now</span></div><div class="body"><div class="top"><span class="title">Name</span><span class="price">GH₵ 35</span></div><span class="meta">Vendor</span></div></a>` (add `is-off` and `tag sold` for sold out) |
| Category / stat tile | `<a class="tile"><span class="tile-icon"><i class="ic ic-bag"></i></span><span class="tile-body"><span class="tile-value">Food</span><span class="tile-label">24 items</span></span></a>` |
| Grid of cards | `<div class="grid auto">` (or `cols-2`, `cols-3`, `cols-4`; collapses on phones) |
| Hero | `<section class="hero"><h1 class="display">Headline <span class="text-red">accent.</span></h1><p class="lead">...</p></section>` |
| Section heading | `<span class="eyebrow">Label</span><h2>Title</h2>` |
| Order status badge | `<span class="badge status-preparing">Preparing</span>` (`status-received`, `-ready`, `-completed`, `-cancelled`) |
| Other badges / chips | `badge ok`, `warn`, `info`, `danger`, `dark`; `<span class="chip red">...</span>` |
| Notice | `<div class="alert info"><i class="ic ic-alert"></i>Message</div>` (`ok`, `warn`, `info`) |
| Notification / cart count | `<a class="icon-btn"><i class="ic ic-bell"></i><span class="count">3</span></a>` |
| Quantity stepper | `<div class="qty"><button>−</button><span>2</span><button>+</button></div>` |
| Order timeline | `<ul class="timeline"><li class="done">Received</li><li class="current">Preparing</li><li>Ready</li></ul>` |
| Empty / loading | `UI.empty(el, "No orders yet", "...")`, `UI.loading(el)`, or `class="skeleton line"` |
| Toast | `UI.success("Saved")`, `UI.error("Not enough stock")` |
| Muted / small text | `class="muted"` / `class="small"` |

### Icons
`<i class="ic ic-NAME" aria-hidden="true"></i>` takes the text colour. Names: home receipt package plus chart store bell search shield pin tag arrow-right arrow-left clock user bike bag wallet edit eye-off eye star alert x-circle check-circle check phone image upload trend trash shop-bag logout 

## 6. What changed from the first kit (if you already used it)
- Buttons are auto-width now (use `block` for full width) and `.primary` is solid red, not a gradient.
- `.tile` is a row (icon + text); product cards use `.media` + `.tag`.
- Header classes: `.site-header .bar`, `.logo` + `.brand-tile`, `.nav-links`, `.nav-actions`, `.avatar`, `.count`.
- Font is Manrope (with Poppins as backup): add the new font link to the page head.
