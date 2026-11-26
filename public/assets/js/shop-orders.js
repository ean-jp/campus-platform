/* My orders (FR5.7): history with status filter. */
Shop.boot('orders', function (app) {
  var el = Shop.el, p = Shop.params(), status = p.status || '';
  var ACTIVE = { received: 1, preparing: 1, ready: 1 };
  var isActiveTab = status === 'active';
  var query = { page: p.page || 1, per_page: isActiveTab ? 50 : 10, status: isActiveTab ? '' : status };

  return API.get('/orders', query, { full: true }).then(function (res) {
    var rows = res.data, meta = res.meta || {};
    if (isActiveTab) rows = rows.filter(function (o) { return ACTIVE[o.status]; });
    Shop.clear(app);
    app.appendChild(el('div', { class: 'page-head' }, el('div', null, el('span', { class: 'eyebrow', text: 'History' }), el('h1', { text: 'My orders' }))));
    var tabs = el('div', { class: 'tabs', style: 'margin-bottom:24px' });
    [['', 'All'], ['active', 'In progress'], ['completed', 'Completed'], ['cancelled', 'Cancelled']].forEach(function (t) {
      tabs.appendChild(el('a', { class: 'tab', href: Shop.url('orders.html', { status: t[0] }), 'aria-current': status === t[0] ? 'true' : false }, t[1]));
    });
    app.appendChild(tabs);
    if (!rows.length) { var b = el('div'); app.appendChild(b); UI.empty(b, 'No orders here yet', 'When you order something it will show up here.', { label: 'Browse the shop', href: Shop.url('browse.html') }); return; }
    rows.forEach(function (o) {
      app.appendChild(el('a', { class: 'card-box shop-order', href: Shop.url('order.html', { id: o.id }) },
        el('div', null, el('div', { class: 'title', text: o.vendor.shop_name }),
          el('div', { class: 'sub', text: (o.summary || 'Order') + (o.item_count > 1 ? ' + ' + (o.item_count - 1) + ' more' : '') }),
          el('div', { class: 'sub', text: '#' + o.id + ' · ' + Shop.date(o.created_at) + ' · ' + (o.fulfilment === 'delivery' ? 'Delivery' : 'Pickup') })),
        el('div', { class: 'end' }, Shop.statusBadge(o.status), el('strong', { text: Shop.money(o.total) }))));
    });
    if (!isActiveTab) app.appendChild(Shop.pager(meta, 'orders.html', { status: status }));
  });
});
