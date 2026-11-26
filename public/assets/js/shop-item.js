/* Product page (FR5.3): photos, price, stock, vendor, open/closed, reviews, add to cart, report. */
Shop.boot('browse', function (app) {
  var el = Shop.el, id = Shop.params().id;
  if (!id) { UI.empty(app, 'No item selected', 'Pick something from the shop.', { label: 'Browse the shop', href: Shop.url('browse.html') }); return; }

  return API.get('/items/' + encodeURIComponent(id)).then(function (it) {
    Shop.clear(app);
    document.title = it.name + ' · Campus Platform';

    // gallery
    var images = it.images && it.images.length ? it.images.map(function (i) { return { path: i.path, alt: i.alt || it.name }; }) : [{ path: null, alt: it.name }];
    var main = el('img', { src: Shop.imgSrc(images[0].path), alt: images[0].alt });
    var gallery = el('div', { class: 'shop-gallery' }, el('div', { class: 'main' }, main));
    if (images.length > 1) {
      var thumbs = el('div', { class: 'shop-thumbs' });
      images.forEach(function (im, i) {
        var b = el('button', { type: 'button', 'aria-label': 'Photo ' + (i + 1), 'aria-current': i === 0 ? 'true' : false, on: { click: function () {
          main.src = Shop.imgSrc(im.path); thumbs.querySelectorAll('button').forEach(function (x) { x.removeAttribute('aria-current'); }); b.setAttribute('aria-current', 'true');
        } } }, el('img', { src: Shop.imgSrc(im.path), alt: '' }));
        thumbs.appendChild(b);
      });
      gallery.appendChild(thumbs);
    }

    // buy row
    var qty = 1, max = Math.min(it.stock, 99);
    var qtyNum = el('span', { text: '1' });
    var dec = el('button', { type: 'button', 'aria-label': 'Less', text: '−', on: { click: function () { if (qty > 1) { qty--; sync(); } } } });
    var inc = el('button', { type: 'button', 'aria-label': 'More', text: '+', on: { click: function () { if (qty < max) { qty++; sync(); } } } });
    function sync() { qtyNum.textContent = qty; dec.disabled = qty <= 1; inc.disabled = qty >= max; }
    sync();
    var addBtn = el('button', { class: 'btn primary', type: 'button', disabled: !it.in_stock, on: { click: function (e) { Shop.addToCart(it.id, e.currentTarget, qty); } } }, it.in_stock ? [Shop.icon('bag'), 'Add to cart'] : 'Sold out');
    var buy = el('div', { class: 'shop-buyrow' }, it.in_stock ? el('div', { class: 'qty' }, dec, qtyNum, inc) : null, addBtn);

    var v = it.vendor;
    var hours = v.opens_at && v.closes_at ? v.opens_at + ' – ' + v.closes_at : 'Hours not set';
    var rating = it.rating && it.rating.count ? el('div', { class: 'row', style: 'margin-bottom:6px' }, el('span', { class: 'shop-stars', text: Shop.stars(it.rating.avg) }), el('span', { class: 'muted small', text: it.rating.avg + ' · ' + it.rating.count + ' review' + (it.rating.count === 1 ? '' : 's') })) : null;

    var info = el('div', null,
      el('a', { class: 'chip red', href: Shop.url('browse.html', { category: it.category.slug }), text: it.category.name }),
      el('h1', { style: 'margin-top:14px', text: it.name }), rating,
      el('div', { class: 'shop-price-big', text: Shop.money(it.price) }),
      el('p', { class: 'lead', style: 'font-size:16px', text: it.description || '' }),
      buy,
      el('div', { class: 'shop-facts' },
        el('div', null, Shop.icon('package'), it.in_stock ? (it.stock <= 5 ? 'Only ' + it.stock + ' left' : 'In stock') : 'Sold out right now'),
        el('div', null, Shop.icon('store'), el('a', { href: Shop.url('vendor.html', { id: v.id }), text: v.shop_name }), el('span', { class: 'badge ' + (v.is_open ? 'ok' : ''), text: v.is_open ? 'Open now' : 'Closed' })),
        el('div', null, Shop.icon('clock'), hours),
        el('div', null, Shop.icon('wallet'), 'Pay on delivery')),
      el('button', { class: 'btn ghost sm', type: 'button', on: { click: function () { reportDialog(it); } } }, Shop.icon('alert'), 'Report this listing'));

    app.appendChild(el('a', { class: 'link', href: Shop.url('browse.html'), style: 'margin-bottom:24px' }, Shop.icon('arrow-left'), 'Back to shop'));
    app.appendChild(el('div', { class: 'shop-split' }, gallery, info));

    // reviews
    var writeBtn = it.can_review ? el('button', { class: 'btn ghost sm', type: 'button', on: { click: function () { reviewDialog(it); } } }, Shop.icon('star'), 'Write a review') : null;
    app.appendChild(el('section', { class: 'section' },
      el('div', { class: 'section-head' }, el('h2', { text: 'Reviews' }), writeBtn),
      it.reviews && it.reviews.length
        ? el('div', { class: 'card-box' }, it.reviews.map(function (r) {
            return el('div', { class: 'shop-review' }, el('div', { class: 'row' }, el('span', { class: 'shop-stars', text: Shop.stars(r.rating) }), el('strong', { text: r.reviewer }), el('span', { class: 'muted small', text: Shop.date(r.created_at) })), r.comment ? el('p', { style: 'margin:6px 0 0', text: r.comment }) : null);
          }))
        : el('p', { class: 'muted', text: 'No reviews yet. Buyers can review items after a completed order.' })));
  });

  function reviewDialog(it) {
    var chosen = 5;
    var starBtns = [];
    var picker = el('div', { class: 'row', style: 'gap:6px;margin:10px 0 14px', role: 'radiogroup', 'aria-label': 'Rating' });
    function paint() {
      starBtns.forEach(function (b, idx) {
        var active = idx < chosen;
        b.textContent = active ? '★' : '☆';
        b.style.color = active ? 'var(--red)' : 'var(--mut)';
        b.setAttribute('aria-checked', idx + 1 === chosen ? 'true' : 'false');
      });
    }
    for (var s = 1; s <= 5; s++) {
      (function (val) {
        var b = el('button', { type: 'button', class: 'icon-btn', style: 'font-size:24px;width:40px;height:40px', role: 'radio', 'aria-label': val + ' star' + (val > 1 ? 's' : ''), on: { click: function () { chosen = val; paint(); } } });
        starBtns.push(b);
        picker.appendChild(b);
      })(s);
    }
    paint();
    var ta = el('textarea', { class: 'textarea', maxlength: '500', placeholder: 'How was it? (optional)', 'aria-label': 'Comment' });
    Shop.modal({ title: 'Review ' + it.name, confirmLabel: 'Submit review', body: el('div', null, el('p', { class: 'muted small', text: 'Tap a star to rate (1 to 5):' }), picker, ta), onConfirm: function (btn) {
      var payload = { item_id: it.id, rating: chosen };
      if (ta.value.trim()) payload.comment = ta.value.trim();
      return UI.run(btn, function () { return API.post('/reviews', payload); }, 'Sending…').then(function () {
        UI.success('Thanks for your review!');
        location.reload();
      });
    } });
  }

  function reportDialog(it) {
    var ta = el('textarea', { class: 'textarea', maxlength: '500', placeholder: 'What is wrong with this listing?', 'aria-label': 'Reason' });
    Shop.modal({ title: 'Report this listing', confirmLabel: 'Send report', body: el('div', null, el('p', { class: 'muted', text: 'The admin will review ' + it.name + '.' }), ta), onConfirm: function (btn) {
      if (ta.value.trim().length < 5) { UI.error('Please write at least 5 characters.'); return false; }
      return UI.run(btn, function () { return API.post('/reports', { item_id: it.id, reason: ta.value.trim() }); }, 'Sending…').then(function () { UI.success('Thanks. The admin will review this listing.'); });
    } });
  }
});
