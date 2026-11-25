/* vendor/apply.js: "Become a vendor" page (P4).  POST /api/v1/vendor/apply */
(function () {
  'use strict';

  var form = document.getElementById('apply-form');
  var box = document.getElementById('apply-status');
  var text = document.getElementById('apply-status-text');
  var btn = document.getElementById('apply-btn');

  function showStatus(status) {
    var msg = {
      pending:   'Request sent. The admin will review your shop soon. You will get a notification when it is approved.',
      approved:  'Your shop is approved. Go to your dashboard to add products.',
      rejected:  'Your request was not approved. Contact the admin if you think this is a mistake.',
      suspended: 'Your shop is suspended. Please contact the admin.'
    }[status];
    text.textContent = msg || '';
    box.hidden = false;
    form.hidden = true;
    if (status === 'approved') {
      box.appendChild(V.el('a', { className: 'v-btn v-btn-red v-btn-sm', href: 'index.html', text: 'Open my dashboard', style: 'margin-left:auto' }));
    }
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    UI.fieldErrors(form, null);
    UI.run(btn, function () { return API.post('/vendor/apply', API.formToJson(form)); }, 'Sending…')
      .then(function () {
        showStatus('pending');
        window.scrollTo({ top: box.getBoundingClientRect().top + window.scrollY - 120, behavior: 'smooth' });
      })
      .catch(function (err) {
        if (err.fields) UI.fieldErrors(form, err.fields);
        if (err.code === 'ALREADY_APPLIED') V.boot({ needApproved: false }).then(function (me) { showStatus(me.vendor.status); });
      });
  });

  V.boot({ needApproved: false }).then(function (me) {
    if (me.vendor) showStatus(me.vendor.status);
  }).catch(function () { /* not logged in: api.js sends them to the login page */ });
})();
