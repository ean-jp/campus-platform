/* ==========================================================================
   vendor/common.js: shared code for every vendor page (P4).
   Load AFTER ui.js and api.js:
     <script src="../assets/js/ui.js"></script>
     <script src="../assets/js/api.js"></script>
     <script src="../assets/js/vendor/common.js"></script>
     <script src="../assets/js/vendor/<page>.js"></script>

   What it does:
     - vendor pages always use the real PHP API (not the mock JSON)
     - V.boot() asks the server who is logged in, saves the CSRF token for
       api.js, fills the header (initials, bell, order count) and sends people
       who are not approved vendors to the right place
     - small helpers to build the page safely (textContent only, never innerHTML)
   ========================================================================== */
(function (global) {
  'use strict';

  // ---- 1. Vendor pages talk to the real backend ----
  API.config.mock = false;
  document.addEventListener('DOMContentLoaded', function () {
    // api.js shows a "MOCK DATA" tag when the site default is mock mode; not true here
    Array.prototype.forEach.call(document.body.children, function (n) {
      if (n.textContent === 'MOCK DATA' && n.getAttribute('aria-hidden') === 'true') n.remove();
    });
  });

  var ROOT = API.config.base.replace(/api\/v1\/?$/, '');   // site root, e.g. http://localhost/campus-platform/public/

  // ---- 2. Building elements safely ----
  /** V.el('a', { className: 'v-btn', href: 'x.html', text: 'Open' }, [children]) */
  function el(tag, props, children) {
    var n = document.createElement(tag);
    Object.keys(props || {}).forEach(function (k) {
      var v = props[k];
      if (v === undefined || v === null || v === false) return;
      if (k === 'text') n.textContent = v;
      else if (k === 'className') n.className = v;
      else if (k === 'dataset') Object.keys(v).forEach(function (d) { n.dataset[d] = v[d]; });
      else if (k.indexOf('on') === 0 && typeof v === 'function') n.addEventListener(k.slice(2), v);
      else n.setAttribute(k, v === true ? '' : v);
    });
    (children || []).forEach(function (c) {
      if (c === null || c === undefined || c === false) return;
      n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
    });
    return n;
  }

  function icon(name, extra) {
    return el('i', { className: 'v-ic v-ic-' + name + (extra ? ' ' + extra : ''), 'aria-hidden': 'true' });
  }

  // ---- 3. Formatting ----
  function money(n) {
    n = Number(n) || 0;
    var whole = Math.round(n * 100) % 100 === 0;
    return 'GH₵ ' + n.toLocaleString('en-GB', { minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: 2 });
  }

  function img(path) {
    return path ? ROOT + path : ROOT + 'assets/images/placeholder.svg';
  }

  function toDate(s) { return s ? new Date(String(s).replace(' ', 'T')) : null; }

  function timeAgo(s) {
    var d = toDate(s);
    if (!d || isNaN(d)) return '';
    var mins = Math.round((Date.now() - d.getTime()) / 60000);
    var time = d.toLocaleTimeString('en-GB', { hour: 'numeric', minute: '2-digit', hour12: true });
    if (mins < 1) return 'Just now';
    if (mins < 60) return mins + ' min ago';
    var today = new Date(); today.setHours(0, 0, 0, 0);
    if (d >= today) return 'Today, ' + time;
    var y = new Date(today); y.setDate(y.getDate() - 1);
    if (d >= y) return 'Yesterday, ' + time;
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) + ', ' + time;
  }

  function hhmm(t) {   // "18:00:00" -> "6:00 pm"
    if (!t) return '';
    var p = t.split(':'), h = Number(p[0]), m = p[1];
    return ((h % 12) || 12) + ':' + m + (h < 12 ? ' am' : ' pm');
  }

  var CONDITION = { new: 'New', like_new: 'Like new', good: 'Good', fair: 'Fair' };

  // ---- 4. Messages that survive a page change (e.g. "Product saved" after redirect) ----
  function flash(message) { try { sessionStorage.setItem('v-flash', message); } catch (e) { /* ignore */ } }
  function showFlash() {
    try {
      var m = sessionStorage.getItem('v-flash');
      if (m) { sessionStorage.removeItem('v-flash'); UI.success(m); }
    } catch (e) { /* ignore */ }
  }

  // ---- 5. Who is logged in ----
  function setCsrf(token) {
    var m = document.querySelector('meta[name="csrf-token"]');
    if (!m) { m = el('meta', { name: 'csrf-token' }); document.head.appendChild(m); }
    m.setAttribute('content', token);
    document.querySelectorAll('input[name="csrf_token"]').forEach(function (i) { i.value = token; });
  }

  function fillHeader(me) {
    document.querySelectorAll('.v-avatar').forEach(function (a) {
      a.textContent = me.user.initials || '?';
      a.title = me.user.name;
    });
    document.querySelectorAll('.v-iconbtn .v-dot-count').forEach(function (d) {
      d.textContent = me.unread_notifications;
      d.hidden = !me.unread_notifications;
      d.parentNode.setAttribute('aria-label', 'Notifications, ' + me.unread_notifications + ' unread');
    });
    document.querySelectorAll('.v-top-nav .v-count').forEach(function (c) {
      c.textContent = me.waiting_orders;
      c.hidden = !me.waiting_orders;
    });
  }

  function blockPage(title, text, action) {
    var main = document.querySelector('main');
    main.textContent = '';
    var box = el('div', { className: 'empty', style: 'margin-top:40px' }, [
      el('h3', { text: title }), el('p', { text: text })
    ]);
    if (action) box.appendChild(el('a', { className: 'v-btn v-btn-red', href: action.href, text: action.label, style: 'margin-top:16px' }));
    main.appendChild(box);
  }

  /**
   * V.boot({ needApproved: true }) -> Promise of the "me" data.
   * Pages that need an approved shop stop here (and show a message) otherwise.
   */
  function boot(opts) {
    opts = opts || {};
    var need = opts.needApproved !== false;
    return API.get('/vendor/me').then(function (me) {
      setCsrf(me.csrf_token);
      fillHeader(me);
      showFlash();
      if (!need) return me;
      if (!me.vendor) {
        location.replace('apply.html');
        throw new Error('not a vendor');
      }
      if (me.vendor.status !== 'approved') {
        var msg = {
          pending:   ['Waiting for approval', 'The admin is checking your shop. You can add products as soon as it is approved.'],
          rejected:  ['Request not approved', 'Your vendor request was not approved. Contact the admin if you think this is a mistake.'],
          suspended: ['Shop suspended', 'Your shop has been suspended. Please contact the admin.']
        }[me.vendor.status] || ['Shop not active', ''];
        blockPage(msg[0], msg[1], { href: '../index.html', label: 'Back to the shop' });
        throw new Error('not approved');
      }
      return me;
    });
  }

  // ---- 6. Product card (used on the dashboard and the products page) ----
  /**
   * Builds the Figma-style product card. opts.onToggle(item, button) is called
   * for the hide/show button in the corner.
   */
  function productCard(item, opts) {
    opts = opts || {};
    var st = item.display_status;
    var tag = {
      active:   ['On sale', 'v-pcard-tag'],
      sold_out: ['Sold out', 'v-pcard-tag sold'],
      hidden:   ['Hidden', 'v-pcard-tag hidden'],
      removed:  ['Removed by admin', 'v-pcard-tag sold']
    }[st] || ['', 'v-pcard-tag'];
    var editUrl = 'product-form.html?id=' + item.id;

    var media = el('div', { className: 'v-pcard-media' }, [
      el('img', { src: img(item.image), alt: item.name, loading: 'lazy' }),
      el('span', { className: tag[1], text: tag[0] })
    ]);
    if (st !== 'removed' && opts.onToggle) {
      var hidden = item.status === 'hidden';
      var btn = el('button', {
        className: 'v-pcard-action', type: 'button',
        'aria-label': (hidden ? 'Show ' : 'Hide ') + item.name + (hidden ? ' to buyers' : ' from buyers'),
        title: hidden ? 'Show to buyers' : 'Hide from buyers'
      }, [icon(hidden ? 'eye' : 'eye-off')]);
      btn.addEventListener('click', function () { opts.onToggle(item, btn); });
      media.appendChild(btn);
    }

    var stockChip;
    if (st === 'removed') stockChip = el('span', { className: 'v-chip red', text: 'Removed by the admin' });
    else if (item.stock === 0) stockChip = el('span', { className: 'v-chip black', text: '0 in stock' });
    else if (item.low_stock) stockChip = el('span', { className: 'v-chip red', text: 'Only ' + item.stock + ' left' });
    else stockChip = el('span', { className: 'v-chip', text: item.stock + ' in stock' });

    var chips = el('div', { className: 'v-chips' }, [stockChip]);
    if (item.condition) chips.appendChild(el('span', { className: 'v-chip', text: CONDITION[item.condition] || item.condition }));

    var how = [];
    if (item.allow_pickup) how.push('Pickup');
    if (item.allow_delivery) how.push('Delivery');

    var body = el('div', { className: 'v-pcard-body' }, [
      el('div', { className: 'v-pcard-top' }, [
        el('span', { className: 'v-pcard-title', text: item.name }),
        el('span', { className: 'v-pcard-price', text: money(item.price) })
      ]),
      chips,
      el('span', { className: 'v-meta' }, [icon('tag', 'v-ic-sm'), item.category]),
      st === 'hidden'
        ? el('span', { className: 'v-meta' }, [icon('eye-off', 'v-ic-sm'), "Buyers can't see this"])
        : el('span', { className: 'v-meta' }, [icon('trend', 'v-ic-sm'), el('strong', { text: item.sold_week + ' sold' }), ' this week']),
      how.length ? el('span', { className: 'v-meta' }, [icon(item.allow_delivery ? 'bike' : 'bag', 'v-ic-sm'), how.join(' or ')]) : null
    ]);

    if (st === 'removed') {
      body.appendChild(el('span', { className: 'v-btn v-btn-outline v-btn-block', 'aria-disabled': 'true', text: 'Cannot be edited' }));
    } else if (item.stock === 0) {
      body.appendChild(el('a', { className: 'v-btn v-btn-red v-btn-block', href: editUrl + '#stock' }, [icon('plus'), 'Restock']));
    } else {
      body.appendChild(el('a', { className: 'v-btn v-btn-outline v-btn-block', href: editUrl }, [icon('edit'), 'Edit product']));
    }

    return el('article', { className: 'v-pcard' + (st === 'hidden' || st === 'sold_out' || st === 'removed' ? ' is-off' : ''), dataset: { id: item.id } }, [media, body]);
  }

  /** Hide/show a product from its card, then call done() to refresh. */
  function toggleVisibility(item, button, done) {
    var to = item.status === 'hidden' ? 'active' : 'hidden';
    button.disabled = true;
    API.patch('/vendor/items/' + item.id, { status: to }).then(function () {
      UI.success(to === 'hidden' ? item.name + ' is now hidden from buyers' : item.name + ' is back on sale');
      done();
    }).catch(function () { button.disabled = false; });
  }

  // Clear a field's red error as soon as the person changes it
  document.addEventListener('input', function (e) {
    var f = e.target.closest && e.target.closest('.field.invalid');
    if (!f) return;
    f.classList.remove('invalid');
    var er = f.querySelector('.error'); if (er) er.textContent = '';
  });
  document.addEventListener('change', function (e) {
    var f = e.target.closest && e.target.closest('.field.invalid');
    if (!f) return;
    f.classList.remove('invalid');
    var er = f.querySelector('.error'); if (er) er.textContent = '';
  });

  global.V = {
    root: ROOT, el: el, icon: icon, money: money, img: img, timeAgo: timeAgo, hhmm: hhmm,
    CONDITION: CONDITION, flash: flash, boot: boot, productCard: productCard, toggleVisibility: toggleVisibility
  };
})(window);
