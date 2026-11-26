/* ==========================================================================
   shop-common.js: shared helpers for the buyer pages (public/shop/*.html).
   Needs ui.js and api.js loaded first. Builds the page with DOM calls and
   textContent only (never innerHTML with data), per the security checklist.

     Shop.boot('home', async function (app) { ... });   // header + page body
   ========================================================================== */
(function (g) {
  'use strict';

  var ROOT = API.config.mockBase.replace(/assets\/mock$/, '');
  var Shop = { root: ROOT, user: null, cartCount: 0 };

  // Where the "link your Ashesi email" flow lives (Person 1). Change when their page exists.
  Shop.linkEmailUrl = ROOT + 'login.html#link';
  Shop.resourcesUrl = ROOT + 'resources';   // Person 5's PHP module

  // ---------------------------------------------------------------- tiny DOM helper
  function el(tag, props) {
    var n = document.createElement(tag);
    Object.keys(props || {}).forEach(function (k) {
      var v = props[k];
      if (v === undefined || v === null || v === false) return;
      if (k === 'class') n.className = v;
      else if (k === 'text') n.textContent = v;
      else if (k === 'on') Object.keys(v).forEach(function (e) { n.addEventListener(e, v[e]); });
      else n.setAttribute(k, v === true ? '' : v);
    });
    for (var i = 2; i < arguments.length; i++) add(n, arguments[i]);
    return n;
  }
  function add(parent, c) {
    if (c === undefined || c === null || c === false) return;
    if (Array.isArray(c)) c.forEach(function (x) { add(parent, x); });
    else parent.appendChild(c.nodeType ? c : document.createTextNode(String(c)));
  }
  Shop.el = el;
  Shop.icon = function (name, extra) { return el('i', { class: 'ic ic-' + name + (extra ? ' ' + extra : ''), 'aria-hidden': 'true' }); };
  Shop.clear = function (n) { while (n.firstChild) n.removeChild(n.firstChild); return n; };

  // ---------------------------------------------------------------- formatting
  Shop.money = function (n) { return 'GH₵ ' + Number(n).toFixed(2); };
  Shop.date = function (iso) {
    if (!iso) return '';
    var d = new Date(String(iso).replace(' ', 'T'));
    return isNaN(d) ? iso : d.toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
  };
  Shop.imgSrc = function (path) { return path ? ROOT + path.replace(/^\/+/, '') : ROOT + 'assets/images/placeholder.svg'; };
  Shop.params = function () {
    var out = {};
    new URLSearchParams(location.search).forEach(function (v, k) { out[k] = v; });
    return out;
  };
  Shop.url = function (page, params) {
    var qs = [];
    Object.keys(params || {}).forEach(function (k) {
      if (params[k] !== undefined && params[k] !== null && params[k] !== '') qs.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
    });
    return ROOT + 'shop/' + page + (qs.length ? '?' + qs.join('&') : '');
  };
  var STATUS = { received: 'Received', preparing: 'Preparing', ready: 'Ready', completed: 'Completed', cancelled: 'Cancelled' };
  Shop.statusBadge = function (s) { return el('span', { class: 'badge status-' + s, text: STATUS[s] || s }); };
  Shop.stars = function (n) { n = Math.round(n || 0); return '★★★★★'.slice(0, n) + '☆☆☆☆☆'.slice(0, 5 - n); };

  // ---------------------------------------------------------------- user + cart badge
  function loadUser() {
    return API.get('/me', null, { silent: true, noRedirect: true }).then(function (data) {
      // AuthController::me() wraps in { user: {...} } with 'name' instead of 'full_name'
      var u = data && data.user ? data.user : data;
      if (u && u.name && !u.full_name) u.full_name = u.name;
      // demo switch while mock data is on:  ?type=guest  or  ?type=ashesi
      try {
        var t = /[?&]type=(guest|ashesi)/.exec(location.search);
        if (t) sessionStorage.setItem('shop-type', t[1]);
        var st = sessionStorage.getItem('shop-type');
        if (API.config.mock && st && u) u.account_type = st;
      } catch (e) { /* ignore */ }
      Shop.user = u;
    }, function () { Shop.user = null; });
  }
  Shop.isGuest = function () { return !!Shop.user && Shop.user.account_type === 'guest'; };
  Shop.isAshesi = function () { return !!Shop.user && Shop.user.account_type === 'ashesi'; };

  var cartBadges = [];
  Shop.setCartCount = function (n) {
    Shop.cartCount = n || 0;
    cartBadges.forEach(function (b) { b.textContent = Shop.cartCount; b.hidden = !Shop.cartCount; });
  };
  Shop.refreshCart = function () {
    return API.get('/cart', null, { silent: true, noRedirect: true }).then(function (c) { Shop.setCartCount(c.item_count); return c; }, function () { return null; });
  };

  // ---------------------------------------------------------------- header + bottom menu
  Shop.chrome = function (active) {
    var links = [
      ['home', 'Home', 'home', 'index.html'], ['browse', 'Browse', 'bag', 'browse.html'], ['orders', 'My orders', 'receipt', 'orders.html']
    ];
    var nav = el('nav', { class: 'nav-links', 'aria-label': 'Main menu' });
    links.forEach(function (l) {
      nav.appendChild(el('a', { href: Shop.url(l[3]), 'aria-current': active === l[0] ? 'page' : false }, Shop.icon(l[2]), l[1]));
    });
    if (Shop.isAshesi()) nav.appendChild(el('a', { href: Shop.resourcesUrl }, Shop.icon('clock'), 'Resources'));

    var count = el('span', { class: 'count', hidden: true }, '0');
    cartBadges.push(count);
    var cartBtn = el('a', { class: 'btn ghost', href: Shop.url('cart.html'), 'aria-label': 'Cart' }, Shop.icon('bag'), el('span', { class: 'btn-label', text: 'Cart' }), count);
    count.style.cssText = 'margin-left:2px';

    var who = Shop.user
      ? [
          el('span', { class: 'avatar', title: Shop.user.full_name }, (Shop.user.full_name || '?').split(/\s+/).map(function (w) { return w[0]; }).slice(0, 2).join('').toUpperCase()),
          el('button', { class: 'icon-btn', type: 'button', title: 'Sign out', 'aria-label': 'Sign out', on: {
            click: function () {
              API.post('/auth/logout', {}, { silent: true, noRedirect: true }).finally(function () {
                location.href = ROOT + 'login.html';
              });
            }
          } }, Shop.icon('logout'))
        ]
      : el('a', { class: 'btn primary sm', href: ROOT + 'login.html' }, 'Sign in');

    var header = el('header', { class: 'site-header' },
      el('div', { class: 'bar' },
        el('a', { class: 'logo', href: Shop.url('index.html') }, el('span', { class: 'brand-tile' }, Shop.icon('store')), el('span', null, 'Campus ', el('b', { text: 'Platform' }))),
        nav,
        el('div', { class: 'nav-actions' }, cartBtn, who)));

    var items = el('div', { class: 'items' });
    [['home', 'Home', 'home', 'index.html'], ['browse', 'Browse', 'search', 'browse.html'], ['cart', 'Cart', 'bag', 'cart.html'], ['orders', 'Orders', 'receipt', 'orders.html']].forEach(function (l) {
      items.appendChild(el('a', { href: Shop.url(l[3]), 'aria-current': active === l[0] ? 'page' : false }, Shop.icon(l[2]), l[1]));
    });
    document.body.insertBefore(header, document.body.firstChild);
    document.body.appendChild(el('nav', { class: 'bottom-nav', 'aria-label': 'Main menu' }, items));
  };

  // ---------------------------------------------------------------- reusable pieces
  Shop.addToCart = function (itemId, btn, qty) {
    return UI.run(btn, function () { return API.post('/cart/items', { item_id: itemId, quantity: qty || 1 }); }, 'Adding…').then(function (cart) {
      UI.success('Added to cart');
      if (cart && typeof cart.item_count === 'number') Shop.setCartCount(cart.item_count); else Shop.refreshCart();
      return cart;
    }, function () { /* toast already shown by api.js */ });
  };

  Shop.itemCard = function (it) {
    var href = Shop.url('item.html', { id: it.id });
    var tag = !it.in_stock ? el('span', { class: 'tag sold', text: 'Sold out' }) : el('span', { class: 'tag', text: it.vendor.is_open ? 'Open now' : 'Closed' });
    var btn = el('button', { class: 'btn ' + (it.in_stock ? 'primary' : 'ghost') + ' block', type: 'button', disabled: !it.in_stock, on: {
      click: function (e) { Shop.addToCart(it.id, e.currentTarget); }
    } }, it.in_stock ? [Shop.icon('bag'), 'Add to cart'] : 'Sold out');
    return el('article', { class: 'item-card shop-card' + (it.in_stock ? '' : ' is-off') },
      el('div', { class: 'media' }, el('a', { href: href, 'aria-label': it.name }, el('img', { src: Shop.imgSrc(it.image), alt: '', loading: 'lazy' })), tag),
      el('div', { class: 'body' },
        el('div', { class: 'top' }, el('span', { class: 'title' }, el('a', { href: href, text: it.name })), el('span', { class: 'price', text: Shop.money(it.price) })),
        el('span', { class: 'meta', text: it.vendor.shop_name + ' · ' + it.category.name }),
        btn));
  };

  Shop.grid = function (items) {
    return el('div', { class: 'grid auto' }, items.map(Shop.itemCard));
  };

  Shop.sectionHead = function (eyebrow, title, linkText, linkHref) {
    return el('div', { class: 'section-head' },
      el('div', null, eyebrow ? el('span', { class: 'eyebrow', text: eyebrow }) : null, el('h2', { text: title })),
      linkText ? el('a', { class: 'link', href: linkHref }, linkText, Shop.icon('arrow-right')) : null);
  };

  // Simple confirm/prompt dialog built on the kit's .modal classes.
  Shop.modal = function (opts) {
    var prev = document.activeElement;
    var close = function () { back.remove(); if (prev && prev.focus) prev.focus(); };
    var confirm = el('button', { class: 'btn ' + (opts.danger ? 'danger' : 'primary'), type: 'button' }, opts.confirmLabel || 'OK');
    var cancel = el('button', { class: 'btn ghost', type: 'button', on: { click: close } }, opts.cancelLabel || 'Cancel');
    var back = el('div', { class: 'modal-backdrop', on: { click: function (e) { if (e.target === back) close(); } } },
      el('div', { class: 'modal', role: 'dialog', 'aria-modal': 'true', 'aria-label': opts.title },
        el('h3', { text: opts.title }), opts.body || null, el('div', { class: 'actions' }, cancel, confirm)));
    confirm.addEventListener('click', function () {
      var r = opts.onConfirm ? opts.onConfirm(confirm) : null;
      Promise.resolve(r).then(function (keepOpen) { if (keepOpen !== false) close(); }, function () { /* stay open; toast shown */ });
    });
    back.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    document.body.appendChild(back);
    (back.querySelector('textarea, input') || cancel).focus();
    return { close: close };
  };

  Shop.pager = function (meta, page, params) {
    if (!meta || meta.total_pages <= 1) return document.createDocumentFragment();   // nothing to show
    var box = el('nav', { class: 'shop-pager', 'aria-label': 'Pages' });
    var link = function (n, label) { var p = {}; Object.keys(params).forEach(function (k) { p[k] = params[k]; }); p.page = n; return el('a', { href: Shop.url(page, p) }, label || String(n)); };
    if (meta.page > 1) box.appendChild(link(meta.page - 1, '←'));
    for (var n = 1; n <= meta.total_pages; n++) {
      if (n === meta.page) box.appendChild(el('span', { 'aria-current': 'page', text: String(n) }));
      else if (n === 1 || n === meta.total_pages || Math.abs(n - meta.page) <= 1) box.appendChild(link(n));
      else if (Math.abs(n - meta.page) === 2) box.appendChild(el('span', { text: '…' }));
    }
    if (meta.page < meta.total_pages) box.appendChild(link(meta.page + 1, '→'));
    return box;
  };

  function ensureCsrf() {
    if (API.config.mock || !Shop.user) return Promise.resolve();
    return API.get('/auth/csrf', null, { silent: true, noRedirect: true }).then(function (d) {
      if (!d || !d.csrf_token) return;
      var m = document.querySelector('meta[name="' + API.config.csrfMeta + '"]');
      if (!m) {
        m = document.createElement('meta');
        m.setAttribute('name', API.config.csrfMeta);
        document.head.appendChild(m);
      }
      m.setAttribute('content', d.csrf_token);
    }, function () { /* ignore */ });
  }

  // ---------------------------------------------------------------- page boot
  Shop.boot = function (active, main) {
    var app = document.getElementById('app');
    return loadUser().then(function () {
      Shop.chrome(active);
      if (!document.getElementById('toasts')) document.body.appendChild(el('div', { id: 'toasts', class: 'toast-region' }));
      ensureCsrf();
      if (Shop.user || API.config.mock) Shop.refreshCart(); else Shop.setCartCount(0);
      UI.loading(app);
      return Promise.resolve(main(app)).catch(function (err) {
        if (err && err.name === 'ApiError') UI.empty(app, 'Could not load this page', err.message, { label: 'Back to shop', href: Shop.url('index.html') });
        else { if (g.console) console.error(err); UI.empty(app, 'Something went wrong', 'Please refresh the page.'); }
      });
    });
  };

  g.Shop = Shop;
})(window);
