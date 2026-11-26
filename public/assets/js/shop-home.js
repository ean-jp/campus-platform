/* Homepage (FR2): changes by account type. Ashesi: Resources card. Guest: "link your Ashesi email" prompt. */
Shop.boot('home', function (app) {
  var el = Shop.el, user = Shop.user;
  var first = user && user.full_name ? user.full_name.split(' ')[0] : null;

  return Promise.all([
    API.get('/categories'),
    API.get('/items', { per_page: 8, sort: 'newest' }),
    user ? API.get('/orders', { per_page: 3 }, { silent: true, noRedirect: true }).catch(function () { return []; }) : [],
    Shop.isAshesi() ? API.get('/bookings', null, { silent: true, noRedirect: true }).catch(function () { return []; }) : []
  ]).then(function (r) {
    var cats = r[0], items = r[1], orders = r[2] || [], bookings = Array.isArray(r[3]) ? r[3] : [];
    Shop.clear(app);

    // hero
    app.appendChild(el('section', { class: 'hero shop-hero' },
      el('span', { class: 'pill' }, Shop.icon('shield'), user ? 'Welcome back' : 'Campus marketplace'),
      el('h1', { class: 'display' }, first ? 'Hi ' + first + '. ' : 'Everything on campus. ', el('span', { class: 'text-red', text: first ? 'What are you craving?' : 'One place.' })),
      el('p', { class: 'lead', text: 'Shop from student and local vendors, with pickup or delivery to your hostel.' }),
      el('div', { class: 'hero-actions' },
        el('a', { class: 'btn primary', href: Shop.url('browse.html') }, Shop.icon('bag'), 'Browse the shop'),
        Shop.isAshesi() ? el('a', { class: 'btn soft', href: Shop.resourcesUrl }, Shop.icon('clock'), 'Book a campus resource') : null,
        !user ? el('a', { class: 'btn ghost', href: Shop.root + 'login.html' }, 'Sign in') : null))
    );

    // guest prompt (FR2.2)
    if (Shop.isGuest()) {
      app.appendChild(el('div', { class: 'alert info', style: 'margin-top:32px' }, Shop.icon('alert'),
        el('span', null, 'Have an Ashesi email? ', el('a', { href: Shop.linkEmailUrl, text: 'Link it to unlock Campus Resources.' }))));
    }

    // Ashesi: resources card (FR2.1) + upcoming bookings (FR2.4)
    if (Shop.isAshesi()) {
      var next = bookings[0];
      app.appendChild(el('section', { class: 'section' },
        el('a', { class: 'tile', href: Shop.resourcesUrl },
          el('span', { class: 'tile-icon black' }, Shop.icon('clock')),
          el('span', { class: 'tile-body' },
            el('span', { class: 'tile-value', text: 'Campus Resources' }),
            el('span', { class: 'tile-label', text: next && next.start_at ? 'Next booking: ' + Shop.date(next.start_at) : 'Book advising, counselling, tutoring and more' })),
          Shop.icon('arrow-right'))));
    }

    // categories
    var iconFor = { 'food-meals': 'bag', 'snacks-drinks': 'tag', 'stationery-books': 'package', 'fashion-accessories': 'star', 'electronics-gadgets': 'phone' };
    app.appendChild(el('section', { class: 'section' },
      Shop.sectionHead('Categories', 'Shop by category'),
      el('div', { class: 'tiles', style: 'grid-template-columns:repeat(auto-fit,minmax(230px,1fr))' }, cats.map(function (c, i) {
        return el('a', { class: 'tile', href: Shop.url('browse.html', { category: c.slug }) },
          el('span', { class: 'tile-icon' + (i % 2 ? ' black' : '') }, Shop.icon(iconFor[c.slug] || 'tag')),
          el('span', { class: 'tile-body' }, el('span', { class: 'tile-value', style: 'font-size:18px', text: c.name })),
          Shop.icon('arrow-right'));
      }))));

    // newest items
    app.appendChild(el('section', { class: 'section' }, Shop.sectionHead('Marketplace', 'Fresh today', 'See all', Shop.url('browse.html')), Shop.grid(items)));

    // recent orders (FR2.4)
    if (orders.length) {
      app.appendChild(el('section', { class: 'section' }, Shop.sectionHead('Your orders', 'Recent orders', 'All orders', Shop.url('orders.html')),
        orders.map(function (o) {
          return el('a', { class: 'card-box shop-order', href: Shop.url('order.html', { id: o.id }) },
            el('div', null, el('div', { class: 'title', text: o.vendor.shop_name }), el('div', { class: 'sub', text: '#' + o.id + ' · ' + Shop.date(o.created_at) + ' · ' + o.item_count + ' item' + (o.item_count === 1 ? '' : 's') })),
            el('div', { class: 'end' }, Shop.statusBadge(o.status), el('strong', { text: Shop.money(o.total) })));
        })));
    }
  });
});
