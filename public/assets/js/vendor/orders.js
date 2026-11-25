/* vendor/orders.js: the vendor's Orders page (P4).
   GET   /api/v1/vendor/orders?status=...   list
   PATCH /api/v1/vendor/orders/{id}         { status, pickup_code }  move an order on
   Steps: received (shown as "New") -> preparing -> ready -> completed, or cancelled. */
(function () {
  'use strict';

  var list = document.getElementById('order-list');
  var tabs = document.querySelectorAll('.v-tab[data-status]');
  var status = new URLSearchParams(location.search).get('status') || 'active';

  var STEPS = ['received', 'preparing', 'ready', 'completed'];
  var STEP_LABEL = { received: 'Received', preparing: 'Preparing', ready: 'Ready', completed: 'Completed' };
  var BADGE = {
    received: ['New', 'v-badge new'], preparing: ['Preparing', 'v-badge preparing'], ready: ['Ready', 'v-badge ready'],
    completed: ['Completed', 'v-badge'], cancelled: ['Cancelled', 'v-badge low']
  };
  var TAB_LABEL = { active: 'Active', received: 'New', preparing: 'Preparing', ready: 'Ready', completed: 'Completed', cancelled: 'Cancelled' };
  var DONE_MSG = { preparing: 'is being prepared', ready: 'is ready. The buyer has been told.', completed: 'is complete', cancelled: 'was cancelled' };

  var el = V.el, icon = V.icon;

  function meta(iconName, children) {
    return el('span', { className: 'v-meta' }, [icon(iconName, 'v-ic-sm')].concat(children));
  }

  function steps(o) {
    if (o.status === 'cancelled') return null;
    var at = STEPS.indexOf(o.status);
    return el('ol', { className: 'v-steps', 'aria-label': 'Order progress' }, STEPS.map(function (s, i) {
      var cls = i < at ? 'done' : (i === at ? (s === 'completed' ? 'done' : 'current') : '');
      return el('li', { className: cls, text: STEP_LABEL[s], 'aria-current': i === at ? 'step' : null });
    }));
  }

  /** The buttons this order can use right now (the server says which steps are allowed). */
  function actions(o) {
    var box = el('form', { className: 'v-actions', novalidate: true });
    var next = o.next_steps || [];
    if (!next.length) return null;

    function stepButton(to, label, cls, iconName) {
      return el('button', { className: 'v-btn ' + cls, type: 'submit', name: 'status', value: to },
        iconName ? [icon(iconName), label] : [label]);
    }

    if (next.indexOf('preparing') >= 0) box.appendChild(stepButton('preparing', 'Accept & start preparing', 'v-btn-red', 'check'));
    if (next.indexOf('ready') >= 0) box.appendChild(stepButton('ready', 'Mark as ready', 'v-btn-black', 'check'));
    if (next.indexOf('completed') >= 0) {
      var field = el('div', { className: 'field', style: 'margin:0' }, [
        el('label', { className: 'sr-only', for: 'code-' + o.id, text: "Buyer's 4-digit pickup code" }),
        el('input', { className: 'input v-code', id: 'code-' + o.id, name: 'pickup_code', inputmode: 'numeric',
          pattern: '[0-9]{4}', maxlength: '4', placeholder: '0000', autocomplete: 'off', 'aria-describedby': 'code-help-' + o.id }),
        el('span', { className: 'error' })
      ]);
      box.appendChild(field);
      box.appendChild(stepButton('completed', 'Complete order', 'v-btn-red', 'check-circle'));
    }
    if (next.indexOf('cancelled') >= 0) {
      box.appendChild(stepButton('cancelled', o.status === 'received' ? 'Decline' : 'Cancel order', 'v-btn-outline'));
    }

    box.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = e.submitter || box.querySelector('button');
      var to = btn.value;
      var body = { status: to };
      if (to === 'completed') {
        var code = box.querySelector('[name="pickup_code"]');
        if (!/^\d{4}$/.test(code.value)) {
          UI.fieldErrors(box, { pickup_code: 'Type the 4-digit code the buyer shows you.' });
          code.focus();
          return;
        }
        body.pickup_code = code.value;
      }
      if (to === 'cancelled' && !confirm((o.status === 'received' ? 'Decline' : 'Cancel') + ' order #' + o.id + '? The items go back on sale and the buyer is told.')) return;

      UI.fieldErrors(box, null);
      UI.run(btn, function () { return API.patch('/vendor/orders/' + o.id, body); }, 'Saving…')
        .then(function () {
          UI.success('Order #' + o.id + ' ' + DONE_MSG[to]);
          load();
        })
        .catch(function (err) {
          if (err.fields) UI.fieldErrors(box, err.fields);
          if (err.status === 409) load();          // someone else changed it: show the latest
        });
    });
    return box;
  }

  function card(o) {
    var b = BADGE[o.status] || [o.status, 'v-badge'];
    var who = [el('strong', { text: o.buyer.name })];
    if (o.buyer.account_type === 'ashesi') who.push(' ', icon('shield', 'v-ic-sm v-red'), 'Ashesi');
    else who.push(' · Guest');

    var metas = el('div', { className: 'v-metas' }, [
      meta('clock', [V.timeAgo(o.created_at)]),
      meta('user', who),
      o.buyer.phone ? meta('phone', [el('a', { href: 'tel:' + o.buyer.phone.replace(/\s/g, ''), text: o.buyer.phone })]) : null,
      o.fulfilment === 'delivery'
        ? meta('bike', ['Delivery' + (o.delivery_location ? ' to ' + o.delivery_location : '')])
        : meta('bag', ['Pickup / meet-up']),
      meta('wallet', [o.fulfilment === 'delivery' ? 'Pay on delivery' : 'Pay on pickup'])
    ]);

    var items = el('ul', { className: 'v-items' }, o.items.map(function (it) {
      return el('li', {}, [el('span', { text: it.quantity + ' × ' + it.name }), el('span', { text: V.money(it.price * it.quantity) })]);
    }));

    var c = el('article', { className: 'v-card v-order', id: 'order-' + o.id }, [
      el('div', { className: 'v-order-top' }, [el('h3', { text: 'Order #' + o.id }), el('span', { className: b[1], text: b[0] })]),
      metas,
      steps(o),
      items,
      el('div', { className: 'v-total' }, [el('span', { text: 'Total' }), el('span', { className: 'v-red', text: V.money(o.total) })]),
      actions(o)
    ]);
    if (o.status === 'ready') {
      c.insertBefore(el('p', { className: 'muted', id: 'code-help-' + o.id, style: 'font-size:13.5px;margin:-6px 0 12px',
        text: 'When the buyer collects, ask for the 4-digit code on their order and type it here.' }), c.lastChild);
    }
    return c;
  }

  function load() {
    history.replaceState(null, '', 'orders.html' + (status !== 'active' ? '?status=' + status : '') + location.hash);
    tabs.forEach(function (t) { t.setAttribute('aria-current', String(t.dataset.status === status)); });
    if (!list.children.length) UI.loading(list);

    return API.get('/vendor/orders', { status: status }, { full: true }).then(function (res) {
      var counts = res.meta.counts;
      tabs.forEach(function (t) {
        var n = counts[t.dataset.status];
        t.textContent = TAB_LABEL[t.dataset.status] + (n ? ' (' + n + ')' : '');
      });
      document.querySelectorAll('.v-top-nav .v-count').forEach(function (c) { c.textContent = counts.active; c.hidden = !counts.active; });

      list.textContent = '';
      if (!res.data.length) {
        UI.empty(list, status === 'active' ? 'No orders to handle right now' : 'No ' + TAB_LABEL[status].toLowerCase() + ' orders',
          status === 'active' ? 'New orders show up here. Make sure your products are on sale.' : '');
        return;
      }
      res.data.forEach(function (o) { list.appendChild(card(o)); });

      var target = location.hash && document.querySelector(location.hash);
      if (target) { target.scrollIntoView({ block: 'center' }); target.style.boxShadow = '0 0 0 3px var(--v-red)'; }
    }).catch(function (err) {
      UI.empty(list, 'Could not load your orders', err.message || 'Please refresh the page.');
    });
  }

  tabs.forEach(function (t) {
    t.addEventListener('click', function (e) { e.preventDefault(); status = t.dataset.status; history.replaceState(null, '', location.pathname); load(); });
  });
  document.getElementById('refresh-btn').addEventListener('click', load);

  // check for new orders every minute, unless the vendor is typing a pickup code
  setInterval(function () {
    if (document.visibilityState === 'visible' && !(document.activeElement && document.activeElement.name === 'pickup_code')) load();
  }, 60000);

  V.boot().then(load).catch(function () { /* V.boot handled it */ });
})();
