/* vendor/profile.js: Shop profile page (P4, Stage 3).
   GET/PATCH /api/v1/vendor/profile, POST /api/v1/vendor/profile/logo */
(function () {
  'use strict';

  var form = document.getElementById('profile-form');
  var saveBtn = document.getElementById('save-btn');
  var logoInput = document.getElementById('logo');
  var logoImg = document.getElementById('logo-img');
  var badge = document.getElementById('open-badge');

  function fill(p) {
    form.elements.business_name.value = p.shop_name;
    form.elements.description.value = p.description;
    form.elements.phone.value = p.phone;
    form.elements.location.value = p.location;
    form.elements.opens_at.value = p.opens_at;
    form.elements.closes_at.value = p.closes_at;
    document.getElementById('shop-type').textContent = p.vendor_type === 'off_campus' ? 'Off-campus vendor' : 'Student vendor';
    logoImg.src = V.img(p.logo);
    badge.textContent = p.is_open ? 'Open now' : 'Closed now';
    badge.className = 'v-badge ' + (p.is_open ? 'ready' : 'out');
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    UI.fieldErrors(form, null);
    UI.run(saveBtn, function () { return API.patch('/vendor/profile', API.formToJson(form)); }, 'Saving…')
      .then(function (p) { fill(p); UI.success('Shop profile saved'); })
      .catch(function (err) { if (err.fields) UI.fieldErrors(form, err.fields); });
  });

  // The logo saves as soon as a picture is chosen
  logoInput.addEventListener('change', function () {
    var f = logoInput.files[0];
    if (!f) return;
    UI.fieldErrors(form, null);
    if (['image/jpeg', 'image/png', 'image/webp'].indexOf(f.type) < 0) { UI.fieldErrors(form, { logo: 'Use a JPG, PNG or WebP picture.' }); return; }
    if (f.size > 2 * 1024 * 1024) { UI.fieldErrors(form, { logo: 'The picture is bigger than 2 MB.' }); return; }
    var fd = new FormData();
    fd.append('logo', f, f.name);
    API.post('/vendor/profile/logo', fd)
      .then(function (p) { logoImg.src = V.img(p.logo); UI.success('Logo updated'); })
      .catch(function (err) { if (err.fields) UI.fieldErrors(form, err.fields); })
      .then(function () { logoInput.value = ''; });
  });

  V.boot().then(function () { return API.get('/vendor/profile'); }).then(fill).catch(function () { /* handled */ });
})();
