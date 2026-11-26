/* Checkout (FR5.5, FR5.6, FR8): food can be pickup or delivery, guests must give a phone, pay on delivery. */
Shop.boot('cart', function (app) {
  var el = Shop.el;
  var SPOTS = ['Hostel A', 'Hostel B', 'Hostel C', 'Hostel D', 'Library', 'Engineering block', 'Business block', 'Student centre', 'Main gate'];

  return API.get('/cart').then(function (cart) {
    Shop.clear(app);
    Shop.setCartCount(cart.item_count);
    if (!cart.groups.length) { UI.empty(app, 'Your cart is empty', 'Add something before checking out.', { label: 'Browse the shop', href: Shop.url('browse.html') }); return; }
    if (cart.has_problems) { UI.empty(app, 'Fix your cart first', 'Some items are sold out or unavailable.', { label: 'Back to cart', href: Shop.url('cart.html') }); return; }

    var guest = Shop.isGuest();
    var fulfilment = 'pickup';

    var spot = el('select', { class: 'select', id: 'spot', name: 'delivery_spot' }, el('option', { value: '' }, 'Choose a location'), SPOTS.map(function (s) { return el('option', { value: s }, s); }));
    var spotField = el('div', { class: 'field shop-spot', hidden: true }, el('label', { for: 'spot', text: 'Delivery location *' }), spot, el('span', { class: 'error' }));
    var phone = el('input', { class: 'input', id: 'phone', name: 'contact_phone', type: 'tel', autocomplete: 'tel', placeholder: '024 123 4567', value: (Shop.user && Shop.user.phone) || '' });
    var phoneField = el('div', { class: 'field' }, el('label', { for: 'phone', text: guest ? 'Phone number *' : 'Phone number (optional)' }), phone,
      el('span', { class: 'hint', text: guest ? 'Vendors use this to reach you about your order.' : 'Lets the vendor reach you if needed.' }), el('span', { class: 'error' }));

    function option(value, title, desc, checked, disabled) {
      var input = el('input', { type: 'radio', name: 'fulfilment', value: value, checked: checked, disabled: disabled, on: { change: function () { fulfilment = value; spotField.hidden = value !== 'delivery'; } } });
      return el('label', { class: 'option', style: disabled ? 'opacity:.55;cursor:not-allowed' : null }, input, el('div', null, el('strong', { text: title }), el('span', { text: desc })));
    }
    var fulfil = el('div', { class: 'options' },
      option('pickup', 'Pickup', 'Collect it from the vendor', true, false),
      option('delivery', 'Delivery to a campus location', cart.delivery_possible ? 'Food orders only' : 'Only available when your cart is all food', false, !cart.delivery_possible));

    var pay = el('div', { class: 'options' },
      el('label', { class: 'option' }, el('input', { type: 'radio', name: 'payment_method', value: 'pay_on_delivery', checked: true }), el('div', null, el('strong', { text: 'Pay on delivery' }), el('span', { text: 'Pay in cash or mobile money when you receive it' }))),
      el('label', { class: 'option', style: 'opacity:.55;cursor:not-allowed' }, el('input', { type: 'radio', disabled: true }), el('div', null, el('strong', { text: 'Mobile money or card' }), el('span', { text: 'Coming soon' }))));

    var submit = el('button', { class: 'btn primary block', type: 'submit' }, Shop.icon('check'), 'Place order');
    var form = el('form', { class: 'card-box', novalidate: true },
      el('h3', { text: 'How would you like it?' }), fulfil, el('div', { style: 'height:16px' }), spotField, phoneField,
      el('h3', { style: 'margin-top:8px', text: 'Payment' }), pay, el('div', { style: 'height:22px' }), submit);

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      UI.fieldErrors(form, null);
      var errors = {};
      if (fulfilment === 'delivery' && !spot.value) errors.delivery_spot = 'Choose where on campus we should deliver.';
      if (guest && !phone.value.trim()) errors.contact_phone = 'Guests must enter a phone number.';
      if (Object.keys(errors).length) { UI.fieldErrors(form, errors); return; }
      var body = { fulfilment: fulfilment, payment_method: 'pay_on_delivery' };
      if (fulfilment === 'delivery') body.delivery_spot = spot.value;
      if (phone.value.trim()) body.contact_phone = phone.value.trim();
      UI.run(submit, function () { return API.post('/checkout', body); }, 'Placing order…').then(function (res) {
        try { sessionStorage.setItem('last-checkout', JSON.stringify(res)); } catch (x) { /* ignore */ }
        Shop.setCartCount(0);
        location.href = Shop.url('confirmation.html');
      }, function (err) {
        if (err && err.status === 409) setTimeout(function () { location.href = Shop.url('cart.html'); }, 1800);   // stock changed: show the cart again
      });
    });
    // the inputs use "name" so UI.fieldErrors can find them
    var side = el('aside', { class: 'card-box shop-summary' }, el('h3', { text: 'Your order' }),
      cart.groups.map(function (g) {
        return el('div', { style: 'margin-bottom:14px' }, el('strong', { text: g.vendor.shop_name }),
          g.items.map(function (i) { return el('div', { class: 'shop-sumrow', style: 'font-weight:500;padding:3px 0' }, el('span', { text: i.quantity + ' × ' + i.name }), el('span', { text: Shop.money(i.line_total) })); }));
      }),
      el('div', { class: 'shop-sumrow total' }, el('span', { text: 'Total' }), el('span', { text: Shop.money(cart.total) })),
      cart.groups.length > 1 ? el('p', { class: 'muted small', text: 'You will get ' + cart.groups.length + ' orders, one per shop.' }) : null,
      el('a', { class: 'link', href: Shop.url('cart.html'), style: 'margin-top:10px' }, Shop.icon('arrow-left'), 'Edit cart'));

    app.appendChild(el('div', { class: 'page-head' }, el('div', null, el('span', { class: 'eyebrow', text: 'Almost there' }), el('h1', { text: 'Checkout' }))));
    app.appendChild(el('div', { class: 'shop-layout' }, form, side));
  });
});
