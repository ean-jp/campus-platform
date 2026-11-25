/* vendor/dashboard.js: vendor home page (P4).
   Uses GET /vendor/me, /vendor/stats, /vendor/orders?status=active and /vendor/items. */
(function () {
  'use strict';

  var el = V.el, icon = V.icon;
  function $(id) { return document.getElementById(id); }
  function set(id, text) { $(id).textContent = text; }

  function hello(first) {
    var h = new Date().getHours();
    return (h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening') + (first ? ', ' + first : '') + '.';
  }

  function plural(n, one, many) { return n + ' ' + (n === 1 ? one : many); }

  function showHero(me, stats) {
    var v = me.vendor;
    set('hero-pill', 'Approved ' + (v.vendor_type === 'off_campus' ? 'vendor' : 'student vendor') + ' · ' + v.shop_name);
    set('hero-hello', hello(me.user.first_name));
    set('hero-open', v.is_open ? 'Your shop is open.' : 'Your shop is closed now.');
    var low = stats.low_stock.filter(function (x) { return x.stock > 0; }).length;
    var parts = [];
    parts.push(stats.today.waiting ? plural(stats.today.waiting, 'order is', 'orders are') + ' waiting for you' : 'No orders are waiting right now');
    if (low) parts.push(plural(low, 'product is', 'products are') + ' running low');
    set('hero-lead', parts.join(' and ') + '.');
    set('trust-hours', v.opens_at && v.closes_at
      ? (v.is_open ? 'Open until ' + V.hhmm(v.closes_at) : 'Opens at ' + V.hhmm(v.opens_at))
      : 'Open any time');
    set('trust-place', v.location ? 'Pickup: ' + v.location : 'Add a pickup spot in Shop');
    set('trust-rating', stats.rating.average ? stats.rating.average + ' from ' + plural(stats.rating.reviews, 'review', 'reviews') : 'No reviews yet');
  }

  function showTiles(s) {
    set('t-sales', V.money(s.today.sales));
    var y = s.today.sales_yesterday, note = $('t-sales-note');
    if (y > 0) {
      var pct = Math.round((s.today.sales - y) / y * 100);
      note.textContent = (pct >= 0 ? '▲ ' : '▼ ') + Math.abs(pct) + '% vs yesterday';
      note.className = 'v-tile-note' + (pct >= 0 ? ' up' : '');
    } else {
      note.textContent = 'completed orders';
    }
    set('t-orders', s.today.orders);
    set('t-orders-note', s.today.waiting ? s.today.waiting + ' waiting for you' : 'all handled');
    set('t-products', s.products.listed);
    set('t-products-note', s.products.sold_out ? s.products.sold_out + ' sold out' : 'none sold out');
    set('t-rating', s.rating.average ? s.rating.average + ' ★' : '–');
    set('t-rating-note', s.rating.reviews ? 'from ' + plural(s.rating.reviews, 'review', 'reviews') : 'no reviews yet');
  }

  var BADGE = { received: ['New', 'v-badge new'], preparing: ['Preparing', 'v-badge preparing'], ready: ['Ready', 'v-badge ready'] };

  function showWaiting(orders) {
    var box = $('waiting-list');
    box.textContent = '';
    if (!orders.length) return UI.empty(box, 'All caught up', 'New orders will show up here.');
    var ul = el('ul', { className: 'v-rows' });
    orders.slice(0, 4).forEach(function (o) {
      var first = o.items[0] ? o.items[0].quantity + ' × ' + o.items[0].name : 'Order';
      var more = o.items.length > 1 ? ' + ' + (o.items.length - 1) + ' more' : '';
      var b = BADGE[o.status] || [o.status, 'v-badge'];
      ul.appendChild(el('li', { className: 'v-rowi' }, [
        el('span', { className: 'v-rowi-icon' }, [icon(o.fulfilment === 'delivery' ? 'bike' : 'bag')]),
        el('div', { className: 'v-rowi-body' }, [
          el('div', { className: 'v-rowi-title', text: '#' + o.id + ' · ' + first + more }),
          el('div', { className: 'v-rowi-sub', text: o.buyer.name + ' · ' + (o.fulfilment === 'delivery' ? 'Delivery' : 'Pickup') + ' · ' + V.timeAgo(o.created_at) })
        ]),
        el('span', { className: b[1], text: b[0] }),
        el('a', { className: 'v-btn v-btn-outline v-btn-sm', href: 'orders.html#order-' + o.id, text: 'Open' })
      ]));
    });
    box.appendChild(ul);
  }

  function showLow(items) {
    var box = $('low-list');
    box.textContent = '';
    if (!items.length) {
      box.appendChild(el('p', { className: 'muted', text: 'Nothing is running low. Nice!' }));
      return;
    }
    var ul = el('ul', { className: 'v-rows' });
    items.forEach(function (it) {
      var out = it.stock === 0;
      ul.appendChild(el('li', { className: 'v-rowi' }, [
        el('span', { className: 'v-rowi-icon ' + (out ? 'black' : 'red') }, [icon(out ? 'x-circle' : 'alert')]),
        el('div', { className: 'v-rowi-body' }, [el('div', { className: 'v-rowi-title', text: it.name }), el('div', { className: 'v-rowi-sub', text: it.category })]),
        el('a', { className: 'v-badge ' + (out ? 'out' : 'low'), href: 'product-form.html?id=' + it.id + '#stock', text: out ? 'Sold out' : it.stock + ' left' })
      ]));
    });
    box.appendChild(ul);
  }

  function showBest() {
    var grid = $('best-grid');
    UI.loading(grid);
    return API.get('/vendor/items', { status: 'active' }).then(function (items) {
      grid.textContent = '';
      items.sort(function (a, b) { return b.sold_week - a.sold_week; });
      if (!items.length) return UI.empty(grid, 'No products on sale', 'Add a product to start selling.');
      items.slice(0, 4).forEach(function (it) {
        grid.appendChild(V.productCard(it, { onToggle: function (x, b) { V.toggleVisibility(x, b, showBest); } }));
      });
    });
  }

  UI.loading($('waiting-list'));
  V.boot().then(function (me) {
    return Promise.all([API.get('/vendor/stats'), API.get('/vendor/orders', { status: 'active' })]).then(function (r) {
      showHero(me, r[0]);
      showTiles(r[0]);
      showWaiting(r[1]);
      showLow(r[0].low_stock);
      return showBest();
    });
  }).catch(function () { /* errors already shown */ });
})();
