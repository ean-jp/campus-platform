/* vendor/product-form.js: add or edit a product (P4).
     product-form.html            -> new product  (POST /vendor/items)
     product-form.html?id=12      -> edit product (PATCH /vendor/items/12)
   Photos are uploaded after the product is saved (POST /vendor/items/{id}/images),
   because a photo needs a product id to belong to. */
(function () {
  'use strict';

  var MAX = 4, MAX_BYTES = 2 * 1024 * 1024, TYPES = ['image/jpeg', 'image/png', 'image/webp'];

  var form = document.getElementById('product-form');
  var fileInput = document.getElementById('images');
  var previews = document.getElementById('previews');
  var photoCount = document.getElementById('photo-count');
  var saveBtn = document.getElementById('save-btn');
  var desc = document.getElementById('description');
  var descCount = document.getElementById('desc-count');

  var id = new URLSearchParams(location.search).get('id');
  var saved = [];     // photos already on the server: [{id, path}]
  var waiting = [];   // File objects chosen but not uploaded yet

  // ---------------------------------------------------------------- photos
  function drawPhotos() {
    previews.textContent = '';
    saved.forEach(function (p, i) {
      previews.appendChild(V.el('figure', {}, [
        V.el('img', { src: V.img(p.path), alt: 'Saved photo ' + (i + 1) }),
        V.el('figcaption', { text: i === 0 ? 'Cover' : 'Photo ' + (i + 1) }),
        V.el('button', { type: 'button', className: 'v-prev-del', 'aria-label': 'Remove saved photo ' + (i + 1),
          onclick: function () { removeSaved(p); } }, [V.icon('x-circle')])
      ]));
    });
    waiting.forEach(function (f, i) {
      var url = URL.createObjectURL(f);
      previews.appendChild(V.el('figure', { className: 'is-new' }, [
        V.el('img', { src: url, alt: 'New photo: ' + f.name, onload: function () { URL.revokeObjectURL(url); } }),
        V.el('figcaption', { text: saved.length + i === 0 ? 'Cover (new)' : 'New' }),
        V.el('button', { type: 'button', className: 'v-prev-del', 'aria-label': 'Remove ' + f.name,
          onclick: function () { waiting.splice(i, 1); drawPhotos(); } }, [V.icon('x-circle')])
      ]));
    });
    photoCount.textContent = (saved.length + waiting.length) + ' / ' + MAX;
    fileInput.disabled = saved.length + waiting.length >= MAX;
  }

  fileInput.addEventListener('change', function () {
    var problems = [];
    Array.prototype.forEach.call(fileInput.files, function (f) {
      if (TYPES.indexOf(f.type) < 0) problems.push(f.name + ' is not a JPG, PNG or WebP photo.');
      else if (f.size > MAX_BYTES) problems.push(f.name + ' is bigger than 2 MB.');
      else if (saved.length + waiting.length >= MAX) problems.push('Only ' + MAX + ' photos are allowed.');
      else waiting.push(f);
    });
    fileInput.value = '';            // so the same file can be picked again later
    problems.slice(0, 2).forEach(function (m) { UI.warn(m); });
    drawPhotos();
  });

  function removeSaved(p) {
    if (!confirm('Remove this photo from the product?')) return;
    API.delete('/vendor/items/' + id + '/images/' + p.id).then(function (item) {
      saved = item.images;
      drawPhotos();
      UI.success('Photo removed');
    });
  }

  function uploadWaiting(itemId) {
    if (!waiting.length) return Promise.resolve();
    var fd = new FormData();
    waiting.forEach(function (f) { fd.append('images[]', f, f.name); });
    return API.post('/vendor/items/' + itemId + '/images', fd).then(function (item) {
      waiting = [];
      saved = item.images;
    });
  }

  // ---------------------------------------------------------------- form
  desc.addEventListener('input', function () { descCount.textContent = desc.value.length; });

  function fill(item) {
    form.elements.name.value = item.name;
    form.elements.category_id.value = item.category_id;
    form.elements.condition.value = item.condition || '';
    form.elements.description.value = item.description;
    form.elements.price.value = item.price.toFixed(2);
    form.elements.stock.value = item.stock;
    form.querySelector('input[name="status"][value="' + (item.status === 'hidden' ? 'hidden' : 'active') + '"]').checked = true;
    form.elements.allow_pickup.checked = item.allow_pickup;
    form.elements.allow_delivery.checked = item.allow_delivery;
    descCount.textContent = item.description.length;
    saved = item.images || [];
    drawPhotos();
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    UI.fieldErrors(form, null);
    var body = API.formToJson(form);   // { name, category_id, condition, description, price, stock, status, allow_pickup, allow_delivery }
    var isNew = !id;
    var savedItemId;

    UI.run(saveBtn, function () {
      return (isNew ? API.post('/vendor/items', body) : API.patch('/vendor/items/' + id, body))
        .then(function (item) {
          savedItemId = item.id;
          if (isNew) {                   // from now on, this page edits the new product
            id = String(item.id);
            history.replaceState(null, '', 'product-form.html?id=' + id);
          }
          return uploadWaiting(item.id);
        });
    }, 'Saving…').then(function () {
      V.flash(isNew ? 'Product added' : 'Changes saved');
      location.href = 'products.html';
    }).catch(function (err) {
      if (err && err.fields) {
        UI.fieldErrors(form, err.fields);
        var first = form.querySelector('.field.invalid input, .field.invalid select, .field.invalid textarea');
        if (first) first.focus();
      }
      if (savedItemId && waiting.length) {
        // the product was saved but a photo was refused: stay here so they can fix it
        UI.warn('The product was saved, but some photos were not. Fix them and save again.');
        startEditing();
      }
    });
  });

  // ---------------------------------------------------------------- delete
  document.getElementById('delete-btn').addEventListener('click', function (e) {
    if (!confirm('Delete this product? Buyers will no longer see it.')) return;
    UI.run(e.currentTarget, function () { return API.delete('/vendor/items/' + id); }, 'Deleting…').then(function () {
      V.flash('Product deleted');
      location.href = 'products.html';
    });
  });

  function startEditing() {
    document.getElementById('form-title').textContent = 'Edit product';
    document.getElementById('form-eyebrow').textContent = 'Your listing';
    document.title = 'Edit Product · Campus Platform';
    document.getElementById('delete-card').hidden = false;
  }

  // ---------------------------------------------------------------- start
  drawPhotos();
  V.boot().then(function () {
    return API.get('/vendor/categories');
  }).then(function (cats) {
    var sel = form.elements.category_id;
    cats.forEach(function (c) { sel.appendChild(V.el('option', { value: c.id, text: c.name })); });
    if (!id) { form.elements.name.focus(); return; }
    startEditing();
    return API.get('/vendor/items/' + encodeURIComponent(id)).then(function (item) {
      if (item.status === 'removed') {
        UI.warn('The admin removed this product, so it cannot be changed.');
        Array.prototype.forEach.call(form.elements, function (el) { el.disabled = true; });
      }
      fill(item);
      if (location.hash === '#stock') { form.elements.stock.focus(); form.elements.stock.select(); }
    }).catch(function () {
      location.replace('products.html');
    });
  }).catch(function () { /* V.boot handled it */ });
})();
