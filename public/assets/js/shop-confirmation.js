/* Order confirmation: the pickup code(s) the buyer gives the vendor. */
Shop.boot('orders', function (app) {
  var el = Shop.el, res = null;
  try { res = JSON.parse(sessionStorage.getItem('last-checkout') || 'null'); } catch (e) { res = null; }
  if (!res || !res.orders) { location.replace(Shop.url('orders.html')); return; }

  Shop.clear(app);
  app.appendChild(el('div', { class: 'shop-center' },
    el('div', { class: 'shop-ok' }, Shop.icon('check')),
    el('span', { class: 'eyebrow', text: 'Order placed' }),
    el('h1', { text: 'Thank you!' }),
    el('p', { class: 'lead', text: res.order_count > 1 ? 'We sent ' + res.order_count + ' orders, one to each shop. Show the code below when you collect or receive each one.' : 'Show this code when you collect or receive your order.' })));

  res.orders.forEach(function (o) {
    app.appendChild(el('section', { class: 'card-box', style: 'max-width:640px;margin:28px auto 0' },
      el('div', { class: 'card-head' }, el('h3', { text: o.vendor.shop_name + ' · #' + o.id }), Shop.statusBadge(o.status)),
      el('div', { class: 'shop-code', 'aria-label': 'Pickup code ' + o.pickup_code.split('').join(' '), text: o.pickup_code }),
      el('table', { class: 'shop-lines', style: 'margin-top:18px' }, el('tbody', null,
        o.items.map(function (i) { return el('tr', null, el('td', { text: i.quantity + ' × ' + i.item_name }), el('td', { text: Shop.money(i.line_total) })); }),
        el('tr', null, el('td', null, el('strong', { text: 'Total' })), el('td', { text: Shop.money(o.total) })))),
      el('p', { class: 'muted small', style: 'margin:14px 0' }, o.fulfilment === 'delivery' ? 'Delivery to ' + o.delivery_spot : 'Pickup from the vendor', ' · Pay on delivery'),
      el('a', { class: 'btn ghost block', href: Shop.url('order.html', { id: o.id }) }, 'Track this order')));
  });
  app.appendChild(el('div', { class: 'shop-center', style: 'margin-top:28px' }, el('a', { class: 'btn primary', href: Shop.url('browse.html') }, 'Keep shopping')));
});
