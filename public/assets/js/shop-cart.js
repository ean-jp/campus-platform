/* Cart (FR5.4): grouped by vendor, change amount, remove, totals worked out by the server. */
Shop.boot('cart', function (app) {
  var el = Shop.el;

  function render(cart) {
    Shop.clear(app);
    Shop.setCartCount(cart.item_count);
    app.appendChild(el('div', { class: 'page-head' }, el('div', null, el('span', { class: 'eyebrow', text: 'Your cart' }), el('h1', { text: 'Cart' }))));
    if (!cart.groups.length) {
      var box = el('div'); app.appendChild(box);
      UI.empty(box, 'Your cart is empty', 'Browse the marketplace to add something.', { label: 'Browse the shop', href: Shop.url('browse.html') });
      return;
    }

    var list = el('div');
    cart.groups.forEach(function (g) {
      list.appendChild(el('section', { class: 'card-box shop-vgroup' },
        el('header', null, el('h3', { style: 'margin:0' }, el('a', { href: Shop.url('vendor.html', { id: g.vendor.id }), text: g.vendor.shop_name })),
          el('span', { class: 'badge ' + (g.vendor.is_open ? 'ok' : 'warn'), text: g.vendor.is_open ? 'Open now' : 'Closed' })),
        g.items.map(line), el('div', { class: 'shop-sumrow' }, el('span', { class: 'muted', text: 'Subtotal' }), el('span', { text: Shop.money(g.subtotal) }))));
    });

    var checkout = el('a', { class: 'btn primary block', href: cart.has_problems ? null : Shop.url('checkout.html'), 'aria-disabled': cart.has_problems ? 'true' : false }, 'Go to checkout', Shop.icon('arrow-right'));
    if (cart.has_problems) checkout.addEventListener('click', function (e) { e.preventDefault(); UI.error('Fix the items marked in your cart first.'); });
    var side = el('aside', { class: 'card-box shop-summary' }, el('h3', { text: 'Order summary' }),
      el('div', { class: 'shop-sumrow' }, el('span', { class: 'muted', text: cart.item_count + ' item' + (cart.item_count === 1 ? '' : 's') }), el('span', { text: Shop.money(cart.total) })),
      cart.groups.length > 1 ? el('p', { class: 'muted small', text: 'You are ordering from ' + cart.groups.length + ' shops, so you will get ' + cart.groups.length + ' separate orders.' }) : null,
      el('div', { class: 'shop-sumrow total' }, el('span', { text: 'Total' }), el('span', { text: Shop.money(cart.total) })),
      el('div', { style: 'height:14px' }), checkout,
      el('p', { class: 'muted small', style: 'margin:14px 0 0', text: 'Pay on delivery. Delivery fees are agreed with the vendor.' }));
    app.appendChild(el('div', { class: 'shop-layout' }, list, side));
  }

  function line(i) {
    var busy = false;
    function change(q) {
      if (busy) return; busy = true;
      API.patch('/cart/items/' + i.id, { quantity: q }).then(function (c) { if (c && c.groups) render(c); else reload(); }, function () { busy = false; reload(); });
    }
    function remove(btn) {
      UI.run(btn, function () { return API.delete('/cart/items/' + i.id); }).then(function (c) { UI.success('Removed from cart'); if (c && c.groups) render(c); else reload(); }, function () {});
    }
    return el('div', { class: 'shop-line' },
      el('img', { src: Shop.imgSrc(i.image), alt: '' }),
      el('div', null, el('div', { class: 'name', text: i.name }), el('div', { class: 'sub', text: Shop.money(i.price) + ' each' }), i.problem ? el('span', { class: 'badge warn', style: 'margin-top:6px', text: i.problem }) : null),
      el('div', { class: 'right' }, el('strong', { text: Shop.money(i.line_total) }),
        el('div', { class: 'row' },
          el('div', { class: 'qty' },
            el('button', { type: 'button', 'aria-label': 'Less of ' + i.name, disabled: i.quantity <= 1, text: '−', on: { click: function () { change(i.quantity - 1); } } }),
            el('span', { text: String(i.quantity) }),
            el('button', { type: 'button', 'aria-label': 'More of ' + i.name, disabled: i.quantity >= Math.min(i.stock, 99), text: '+', on: { click: function () { change(i.quantity + 1); } } })),
          el('button', { class: 'icon-btn', type: 'button', 'aria-label': 'Remove ' + i.name, on: { click: function (e) { remove(e.currentTarget); } } }, Shop.icon('trash')))));
  }

  function reload() { return API.get('/cart').then(render); }
  return reload();
});
