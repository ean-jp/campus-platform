/* vendor/products.js: "My products" page (P4).
   Loads GET /api/v1/vendor/items with the search / tab / category filters,
   draws one card per product, and lets the vendor hide or show a product. */
(function () {
  'use strict';

  var grid = document.getElementById('product-grid');
  var form = document.getElementById('product-filters');
  var q = document.getElementById('q');
  var cat = document.getElementById('filter-category');
  var summary = document.getElementById('product-summary');
  var tabs = document.querySelectorAll('.v-tab[data-status]');

  // start from the URL, so a filtered view can be bookmarked / refreshed
  var params = new URLSearchParams(location.search);
  var state = { status: params.get('status') || '', q: params.get('q') || '', category_id: params.get('category_id') || '' };
  q.value = state.q;

  var LABELS = { '': 'All', active: 'On sale', sold_out: 'Sold out', hidden: 'Hidden' };

  function syncUrl() {
    var p = new URLSearchParams();
    Object.keys(state).forEach(function (k) { if (state[k]) p.set(k, state[k]); });
    history.replaceState(null, '', 'products.html' + (p.toString() ? '?' + p : ''));
  }

  function load() {
    syncUrl();
    tabs.forEach(function (t) { t.setAttribute('aria-current', String(t.dataset.status === state.status)); });
    UI.loading(grid);

    return API.get('/vendor/items', state, { full: true }).then(function (res) {
      var items = res.data, c = res.meta.counts;
      tabs.forEach(function (t) {
        var n = { '': c.all, active: c.active, sold_out: c.sold_out, hidden: c.hidden }[t.dataset.status];
        t.textContent = LABELS[t.dataset.status] + ' (' + n + ')';
      });
      summary.textContent = c.all + (c.all === 1 ? ' product' : ' products') + ' · ' + c.sold_out + ' sold out · ' + c.hidden + ' hidden';

      grid.textContent = '';
      if (!items.length) {
        var filtered = state.q || state.status || state.category_id;
        UI.empty(grid,
          filtered ? 'No products match' : 'No products yet',
          filtered ? 'Try another search or filter.' : 'Add your first product so buyers can find you.',
          filtered ? null : { label: 'Add product', href: 'product-form.html' });
        var btn = grid.querySelector('.btn'); if (btn) btn.className = 'v-btn v-btn-red';
        return;
      }
      items.forEach(function (item) {
        grid.appendChild(V.productCard(item, {
          onToggle: function (it, b) { V.toggleVisibility(it, b, load); }
        }));
      });
    }).catch(function (err) {
      UI.empty(grid, 'Could not load your products', err.message || 'Please refresh the page.');
    });
  }

  // ---- filters ----
  form.addEventListener('submit', function (e) { e.preventDefault(); state.q = q.value.trim(); load(); });
  var typing;
  q.addEventListener('input', function () {
    clearTimeout(typing);
    typing = setTimeout(function () { state.q = q.value.trim(); load(); }, 350);
  });
  tabs.forEach(function (t) {
    t.addEventListener('click', function (e) { e.preventDefault(); state.status = t.dataset.status; load(); });
  });
  cat.addEventListener('change', function () { state.category_id = cat.value; load(); });

  // ---- start ----
  V.boot().then(function () {
    return API.get('/vendor/categories');
  }).then(function (cats) {
    cats.forEach(function (c) { cat.appendChild(V.el('option', { value: c.id, text: c.name })); });
    cat.value = state.category_id;
    return load();
  }).catch(function () { /* V.boot already showed what to do */ });
})();
