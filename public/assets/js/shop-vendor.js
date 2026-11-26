/* Vendor shop page (FR5.8): menu with availability and opening hours. */
Shop.boot('browse', function (app) {
  var el = Shop.el, id = Shop.params().id;
  if (!id) { UI.empty(app, 'No shop selected', '', { label: 'Browse the shop', href: Shop.url('browse.html') }); return; }
  return API.get('/vendors/' + encodeURIComponent(id)).then(function (v) {
    Shop.clear(app);
    document.title = v.shop_name + ' · Campus Platform';
    var hours = v.opens_at && v.closes_at ? v.opens_at + ' – ' + v.closes_at : null;
    app.appendChild(el('a', { class: 'link', href: Shop.url('browse.html'), style: 'margin-bottom:24px' }, Shop.icon('arrow-left'), 'Back to shop'));
    app.appendChild(el('div', { class: 'card-box shop-vendor' },
      el('span', { class: 'brand-tile' }, Shop.icon('store')),
      el('div', { class: 'info' },
        el('div', { class: 'row wrap' }, el('h1', { style: 'font-size:34px;margin:0', text: v.shop_name }), el('span', { class: 'badge ' + (v.is_open ? 'ok' : 'warn'), text: v.is_open ? 'Open now' : 'Closed' })),
        el('p', { class: 'muted', style: 'margin:8px 0 0', text: v.description || '' }),
        el('div', { class: 'shop-meta-row' }, hours ? el('span', null, Shop.icon('clock'), hours) : null, v.phone ? el('span', null, Shop.icon('phone'), v.phone) : null, el('span', null, Shop.icon('package'), v.items.length + ' item' + (v.items.length === 1 ? '' : 's'))))));

    if (!v.items.length) { var b = el('div', { style: 'margin-top:32px' }); app.appendChild(b); UI.empty(b, 'No items yet', 'This shop has nothing listed right now.'); return; }
    var groups = {}; v.items.forEach(function (it) { (groups[it.category.name] = groups[it.category.name] || []).push(it); });
    Object.keys(groups).forEach(function (name) {
      app.appendChild(el('section', { class: 'section' }, el('h2', { text: name }), el('div', { style: 'height:16px' }), Shop.grid(groups[name])));
    });
  });
});
