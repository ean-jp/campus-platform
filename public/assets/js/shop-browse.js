/* Browse / category / search results (FR5.1-5.2). State lives in the URL: ?q=&category=&vendor=&min_price=&max_price=&sort=&page= */
Shop.boot('browse', function (app) {
  var el = Shop.el, p = Shop.params();
  var query = { q: p.q, category: p.category, vendor: p.vendor, min_price: p.min_price, max_price: p.max_price, sort: p.sort || 'newest', page: p.page || 1, per_page: 12 };

  return Promise.all([API.get('/categories'), API.get('/vendors'), API.get('/items', query, { full: true })]).then(function (r) {
    var cats = r[0], vendors = r[1], res = r[2], items = res.data, meta = res.meta || {};
    Shop.clear(app);

    var activeCat = cats.filter(function (c) { return c.slug === p.category || String(c.id) === p.category; })[0];
    var keep = function (extra) { var o = { q: p.q, vendor: p.vendor, min_price: p.min_price, max_price: p.max_price, sort: p.sort, category: p.category }; Object.keys(extra).forEach(function (k) { o[k] = extra[k]; }); return o; };

    app.appendChild(el('div', { class: 'page-head' }, el('div', null, el('span', { class: 'eyebrow', text: 'Marketplace' }), el('h1', { text: activeCat ? activeCat.name : (p.q ? 'Results for “' + p.q + '”' : 'Browse the shop') }))));

    // search + filters: a plain GET form, so every result page has a shareable URL
    var qInput = el('input', { name: 'q', value: p.q || '', placeholder: 'Search food, books, chargers…', 'aria-label': 'Search' });
    var catHidden = el('input', { type: 'hidden', name: 'category', value: p.category || '' });
    var sel = function (name, label, opts, val) {
      return el('div', { class: 'field' }, el('label', { for: 'f-' + name, text: label }),
        el('select', { class: 'select', id: 'f-' + name, name: name }, opts.map(function (o) { return el('option', { value: o[0], selected: String(o[0]) === String(val || '') }, o[1]); })));
    };
    var num = function (name, label, val) {
      return el('div', { class: 'field' }, el('label', { for: 'f-' + name, text: label }), el('input', { class: 'input', id: 'f-' + name, name: name, type: 'number', min: '0', step: '1', value: val || '', placeholder: 'GH₵' }));
    };
    app.appendChild(el('form', { method: 'get', action: Shop.url('browse.html') },
      el('div', { class: 'search' }, Shop.icon('search'), qInput, el('button', { class: 'btn primary', type: 'submit' }, 'Search')), catHidden,
      el('div', { class: 'shop-filterbar' },
        sel('vendor', 'Shop', [['', 'All shops']].concat(vendors.map(function (v) { return [v.id, v.shop_name]; })), p.vendor),
        num('min_price', 'Min price', p.min_price), num('max_price', 'Max price', p.max_price),
        sel('sort', 'Sort by', [['newest', 'Newest'], ['price_asc', 'Price: low to high'], ['price_desc', 'Price: high to low'], ['name', 'Name']], p.sort || 'newest'),
        el('button', { class: 'btn ghost', type: 'submit' }, 'Apply filters'))));

    // category tabs
    var tabs = el('div', { class: 'tabs', style: 'margin:14px 0 28px' }, el('a', { class: 'tab', href: Shop.url('browse.html', keep({ category: '' })), 'aria-current': !activeCat ? 'true' : false }, 'All'));
    cats.forEach(function (c) { tabs.appendChild(el('a', { class: 'tab', href: Shop.url('browse.html', keep({ category: c.slug })), 'aria-current': activeCat && activeCat.id === c.id ? 'true' : false }, c.name)); });
    app.appendChild(tabs);

    app.appendChild(el('p', { class: 'muted', style: 'margin-bottom:18px', text: (meta.total !== undefined ? meta.total : items.length) + ' item' + (meta.total === 1 ? '' : 's') }));
    if (!items.length) {
      var box = el('div'); app.appendChild(box);
      UI.empty(box, 'Nothing matches that', 'Try a different search or clear the filters.', { label: 'Clear filters', href: Shop.url('browse.html') });
    } else {
      app.appendChild(Shop.grid(items));
      app.appendChild(Shop.pager(meta, 'browse.html', keep({})));
    }
  });
});
