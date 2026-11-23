/* ==========================================================================
   ui.js: small helpers for the shared look (toasts, loading, empty states).
   Pairs with styles.css. Never puts user data into innerHTML: only textContent.

     UI.success('Item saved');          UI.error('Not enough stock');
     UI.toast('Heads up', 'warn');      (types: success, error, warn, info)

     UI.loading(container);             spinner while a page section loads
     UI.empty(container, 'No orders yet', 'New orders show up here.',
              { label: 'Browse shop', href: '/shop' });
     UI.setBusy(button, true);          disable + "Please wait…" while saving
     await UI.run(button, () => API.post('/checkout', data));   busy + always re-enable
     UI.fieldErrors(formEl, err.fields) show 422 messages under each input
   ========================================================================== */
(function (global) {
  'use strict';

  var DURATION = { success: 3500, info: 4000, warn: 5500, error: 6000 };

  function region() {
    var r = document.getElementById('toasts') || document.querySelector('.toast-region');
    if (!r) {
      r = document.createElement('div');
      r.id = 'toasts';
      r.className = 'toast-region';
      document.body.appendChild(r);
    }
    return r;
  }

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
  }

  function toast(message, type) {
    type = type || 'info';
    var t = el('div', 'toast ' + type);
    t.setAttribute('role', type === 'error' ? 'alert' : 'status');
    t.appendChild(el('span', '', message));

    var close = el('button', 'close', '×');
    close.type = 'button';
    close.setAttribute('aria-label', 'Dismiss message');
    close.addEventListener('click', function () { t.remove(); });
    t.appendChild(close);

    var r = region();
    r.appendChild(t);
    while (r.children.length > 3) r.removeChild(r.firstChild);   // keep the stack short
    setTimeout(function () { if (t.parentNode) t.remove(); }, DURATION[type] || 4000);
    return t;
  }

  function loading(container) {
    container.textContent = '';
    var wrap = el('div', 'loading-block');
    var s = el('span', 'spinner lg');
    s.setAttribute('role', 'status');
    s.setAttribute('aria-label', 'Loading');
    wrap.appendChild(s);
    container.appendChild(wrap);
  }

  function empty(container, title, text, action) {
    container.textContent = '';
    var box = el('div', 'empty');
    box.appendChild(el('h3', '', title));
    if (text) box.appendChild(el('p', '', text));
    if (action && action.href) {
      var a = el('a', 'btn primary auto', action.label || 'Go back');
      a.href = action.href;
      box.appendChild(a);
    }
    container.appendChild(box);
  }

  function setBusy(button, busy, label) {
    if (!button) return;
    if (busy) {
      button.dataset.label = button.textContent;
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      button.textContent = label || 'Please wait…';
    } else {
      button.disabled = false;
      button.removeAttribute('aria-busy');
      if (button.dataset.label !== undefined) button.textContent = button.dataset.label;
    }
  }

  // Run an async action with the button disabled; always re-enable it afterwards.
  function run(button, action, label) {
    setBusy(button, true, label);
    return Promise.resolve().then(action).then(
      function (v) { setBusy(button, false); return v; },
      function (e) { setBusy(button, false); throw e; }
    );
  }

  // Show { fieldName: "message" } under matching inputs (input name="fieldName").
  function fieldErrors(form, fields) {
    form.querySelectorAll('.field.invalid').forEach(function (f) {
      f.classList.remove('invalid');
      var e = f.querySelector('.error'); if (e) e.textContent = '';
    });
    if (!fields) return;
    Object.keys(fields).forEach(function (name) {
      var input = form.querySelector('[name="' + name + '"]');
      var field = input && input.closest('.field');
      if (!field) return;
      field.classList.add('invalid');
      var e = field.querySelector('.error');
      if (!e) { e = el('span', 'error'); field.appendChild(e); }
      e.textContent = fields[name];
    });
  }

  global.UI = {
    toast: toast,
    success: function (m) { return toast(m, 'success'); },
    error:   function (m) { return toast(m, 'error'); },
    warn:    function (m) { return toast(m, 'warn'); },
    loading: loading,
    empty: empty,
    setBusy: setBusy,
    run: run,
    fieldErrors: fieldErrors
  };
})(window);
