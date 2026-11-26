/* Order tracking (FR5.7): status timeline, pickup code, cancel before "preparing". */
Shop.boot('orders', function (app) {
  var el = Shop.el, id = Shop.params().id;
  if (!id) { UI.empty(app, 'No order selected', '', { label: 'My orders', href: Shop.url('orders.html') }); return; }

  function render(o) {
    Shop.clear(app);
    document.title = 'Order #' + o.id + ' · Campus Platform';
    var cancelBtn = o.can_cancel ? el('button', { class: 'btn danger block', type: 'button', on: { click: askCancel } }, Shop.icon('x-circle'), 'Cancel order') : null;

    function askCancel() {
      Shop.modal({ title: 'Cancel this order?', danger: true, confirmLabel: 'Yes, cancel it', cancelLabel: 'Keep order',
        body: el('p', { class: 'muted', text: 'The vendor has not started yet. Your items go back on sale.' }),
        onConfirm: function (btn) {
          return UI.run(btn, function () { return API.post('/orders/' + o.id + '/cancel'); }, 'Cancelling…').then(function (updated) {
            UI.success('Order cancelled');
            if (updated && updated.timeline) render(updated); else API.get('/orders/' + o.id).then(render);
          });
        } });
    }

    app.appendChild(el('a', { class: 'link', href: Shop.url('orders.html'), style: 'margin-bottom:24px' }, Shop.icon('arrow-left'), 'My orders'));
    app.appendChild(el('div', { class: 'page-head' }, el('div', null, el('span', { class: 'eyebrow', text: 'Order #' + o.id }), el('h1', { text: o.vendor.shop_name }), el('p', { text: 'Placed ' + Shop.date(o.created_at) })), Shop.statusBadge(o.status)));

    var left = el('div', { class: 'stack' },
      el('section', { class: 'card-box' }, el('div', { class: 'card-head' }, el('h3', { text: 'Progress' })),
        el('ul', { class: 'timeline' }, o.timeline.map(function (s) { return el('li', { class: s.state === 'done' ? 'done' : (s.state === 'current' ? 'current' : null), text: s.label }); }))),
      el('section', { class: 'card-box' }, el('div', { class: 'card-head' }, el('h3', { text: 'Items' })),
        el('table', { class: 'shop-lines' }, el('tbody', null,
          o.items.map(function (i) { return el('tr', null, el('td', { text: i.quantity + ' × ' + i.item_name }), el('td', { text: Shop.money(i.line_total) })); }),
          el('tr', null, el('td', null, el('strong', { text: 'Total' })), el('td', { text: Shop.money(o.total) }))))));

    var right = el('aside', { class: 'stack' });
    if (o.pickup_code) right.appendChild(el('section', { class: 'card-box' }, el('div', { class: 'card-head' }, el('h3', { text: 'Your pickup code' })), el('div', { class: 'shop-code', text: o.pickup_code }), el('p', { class: 'muted small', style: 'margin:12px 0 0', text: 'Give this code to the vendor when you collect or receive your order.' })));
    right.appendChild(el('section', { class: 'card-box' }, el('div', { class: 'card-head' }, el('h3', { text: 'Details' })),
      el('div', { class: 'shop-facts', style: 'border:0;padding:0;margin:0' },
        el('div', null, Shop.icon('bag'), o.fulfilment === 'delivery' ? 'Delivery to ' + o.delivery_spot : 'Pickup from the vendor'),
        el('div', null, Shop.icon('wallet'), 'Pay on delivery'),
        o.vendor.phone ? el('div', null, Shop.icon('phone'), el('a', { href: 'tel:' + o.vendor.phone.replace(/[^\d+]/g, ''), text: o.vendor.phone })) : null)));
    if (cancelBtn) right.appendChild(cancelBtn);
    else if (o.status === 'preparing' || o.status === 'ready') right.appendChild(el('p', { class: 'muted small', text: 'The vendor has started this order, so it can no longer be cancelled. Please contact the vendor.' }));

    app.appendChild(el('div', { class: 'shop-layout' }, left, right));
  }
  return API.get('/orders/' + encodeURIComponent(id)).then(render);
});
