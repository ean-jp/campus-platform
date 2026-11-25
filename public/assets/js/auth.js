/* ==========================================================================
   auth.js: the sign-up, verify-email and log-in pages (Person 1).
   Needs ui.js and api.js loaded first. One script, three pages: it looks at
   <body data-page="login|signup|verify"> to decide what to wire up.

   Notes for the team:
   - These pages always call the REAL API, even while api.js has MOCK = true.
   - api.js reads the CSRF token from <input name="csrf_token">. Nothing fills it
     in for static .html pages, so this file fetches GET /auth/csrf on load and
     puts the token into the hidden input (and refreshes it once if the server
     says the token is stale).
   - The server decides the account type (Ashesi or guest). The line shown under
     the email box while typing is only a preview.
   ========================================================================== */
(function () {
  'use strict';

  // Auth pages must hit the real backend (set before any request is made).
  API.config.mock = false;

  // api.js shows a "MOCK DATA" tag when it starts in mock mode; it is wrong here.
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('body > div').forEach(function (d) {
      if (d.textContent === 'MOCK DATA') d.remove();
    });
  });

  var ROOT = API.config.base.replace(/api\/v1$/, '');     // site root, e.g. /campus-platform/public/
  var OPTS = { silent: true, noRedirect: true };          // we show errors ourselves; a 401 here is normal
  var ASHESI_DOMAIN = 'ashesi.edu.gh';

  var page = document.body.getAttribute('data-page');
  var form = document.querySelector('form');
  function field(name) { return form.elements[name]; }   // form.elements['x'] works in every browser

  // sessionStorage can be blocked; never let that break the page
  var store = {
    get: function (k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { sessionStorage.setItem(k, v); } catch (e) { /* ignore */ } },
    del: function (k) { try { sessionStorage.removeItem(k); } catch (e) { /* ignore */ } }
  };

  // ---------------------------------------------------------------- helpers
  function go(path) { location.href = ROOT + path; }

  // Where to go after logging in: ?next=/some/path (same site only) or the homepage.
  function nextUrl() {
    var n = new URLSearchParams(location.search).get('next');
    if (n && /^\/(?![\/\\])/.test(n)) return n;           // must start with a single "/"
    return ROOT + 'index.html';
  }

  function refreshCsrf() {
    return fetch(API.config.base + '/auth/csrf', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (p) {
        var token = p && p.data && p.data.csrf_token;
        if (!token) throw new Error('No CSRF token');
        document.querySelectorAll('input[name="csrf_token"]').forEach(function (i) { i.value = token; });
        return token;
      });
  }
  var csrfReady = refreshCsrf().catch(function () { /* the first request will retry and report */ });

  // Run an API call once the token is ready; if the server says the token is stale, refresh and retry once.
  function call(fn) {
    return csrfReady.then(fn).catch(function (err) {
      if (err && err.code === 'CSRF') return refreshCsrf().then(fn);
      throw err;
    });
  }

  function fail(err) {
    if (err && err.fields) UI.fieldErrors(form, err.fields);
    UI.error((err && err.message) || 'Something went wrong. Please try again.');
  }

  // Already logged in? Skip the login/sign-up forms.
  function redirectIfLoggedIn() {
    API.get('/auth/me', null, OPTS).then(function () { location.replace(nextUrl()); }, function () { /* not logged in: stay */ });
  }

  function previewType(email) {
    var at = email.lastIndexOf('@');
    if (at < 1) return '';
    var d = email.slice(at + 1).toLowerCase();
    if (d === ASHESI_DOMAIN || d.slice(-(ASHESI_DOMAIN.length + 1)) === '.' + ASHESI_DOMAIN) return 'ashesi';
    return d.indexOf('.') > 0 ? 'guest' : '';
  }

  // ------------------------------------------------------------------ login
  function initLogin() {
    redirectIfLoggedIn();
    var btn = form.querySelector('button[type="submit"]');

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var email = field('email').value.trim().toLowerCase();
      var password = field('password').value;

      var errs = {};
      if (!/^\S+@\S+\.\S+$/.test(email)) errs.email = 'Enter your email address.';
      if (!password) errs.password = 'Enter your password.';
      UI.fieldErrors(form, errs);
      if (Object.keys(errs).length) return;

      UI.run(btn, function () {
        return call(function () { return API.post('/auth/login', { email: email, password: password }, OPTS); });
      }, 'Logging in…').then(function () {
        location.href = nextUrl();
      }, function (err) {
        if (err && err.code === 'EMAIL_NOT_VERIFIED') {
          store.set('pendingEmail', email);
          UI.warn('Please verify your email first. We are sending you a new code.');
          go('verify.html?email=' + encodeURIComponent(email) + '&resend=1');
          return;
        }
        fail(err);
      });
    });
  }

  // ----------------------------------------------------------------- signup
  function initSignup() {
    redirectIfLoggedIn();
    var btn = form.querySelector('button[type="submit"]');
    var hint = document.getElementById('type-hint');

    field('email').addEventListener('input', function () {
      var t = previewType(field('email').value.trim());
      hint.className = 'hint' + (t ? ' type-' + t : '');
      hint.textContent = t === 'ashesi' ? 'Ashesi email: you will get the marketplace and Campus Resources.'
                       : t === 'guest'  ? 'Guest account: marketplace only. You can link an Ashesi email later.'
                       : '';
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var name = field('name').value.trim();
      var email = field('email').value.trim().toLowerCase();
      var phone = field('phone').value.trim();
      var password = field('password').value;

      var errs = {};
      if (name.length < 2) errs.name = 'Enter your full name.';
      if (!/^\S+@\S+\.\S+$/.test(email)) errs.email = 'Enter a valid email address.';
      if (phone && !/^\+?[0-9 ]{9,15}$/.test(phone)) errs.phone = 'Enter a valid phone number.';
      if (password.length < 8 || password.length > 72) errs.password = 'Use a password of 8 to 72 characters.';
      if (field('password2').value !== password) errs.password2 = 'The passwords do not match.';
      UI.fieldErrors(form, errs);
      if (Object.keys(errs).length) return;

      UI.run(btn, function () {
        return call(function () {
          return API.post('/auth/signup', { name: name, email: email, phone: phone, password: password }, OPTS);
        });
      }, 'Creating account…').then(function (data) {
        store.set('pendingEmail', email);
        store.set('resendUntil', String(Date.now() + ((data && data.resend_in) || 60) * 1000));
        go('verify.html?email=' + encodeURIComponent(email));
      }, fail);
    });
  }

  // ----------------------------------------------------------------- verify
  function initVerify() {
    var params = new URLSearchParams(location.search);
    var email = (params.get('email') || store.get('pendingEmail') || '').trim().toLowerCase();
    if (!email) { go('signup.html'); return; }
    store.set('pendingEmail', email);

    document.getElementById('sent-to').textContent = email;
    var btn = form.querySelector('button[type="submit"]');
    var resendBtn = document.getElementById('resend');
    var codeInput = field('code');
    var timer = null;

    codeInput.focus();
    codeInput.addEventListener('input', function () {
      codeInput.value = codeInput.value.replace(/\D/g, '').slice(0, 6);
    });

    // "Send a new code (42s)": the server allows one new code per minute
    function startCooldown(seconds) {
      clearInterval(timer);
      var end = Date.now() + seconds * 1000;
      store.set('resendUntil', String(end));
      function tick() {
        var left = Math.ceil((end - Date.now()) / 1000);
        if (left <= 0) {
          clearInterval(timer);
          resendBtn.disabled = false;
          resendBtn.textContent = 'Send a new code';
          return;
        }
        resendBtn.disabled = true;
        resendBtn.textContent = 'Send a new code (' + left + 's)';
      }
      tick();
      timer = setInterval(tick, 500);
    }

    function resend() {
      resendBtn.disabled = true;
      return call(function () { return API.post('/auth/resend', { email: email }, OPTS); }).then(
        function (data) {
          UI.success('If this email is waiting for verification, a new code is on its way.');
          startCooldown((data && data.resend_in) || 60);
        },
        function (err) { fail(err); startCooldown(5); }
      );
    }

    resendBtn.addEventListener('click', resend);

    // Came from the login page with an unverified account: send a fresh code straight away
    if (params.get('resend') === '1') {
      if (window.history && history.replaceState) {
        params.delete('resend');
        history.replaceState(null, '', location.pathname + '?' + params.toString());
      }
      resend();
    } else {
      var left = Math.ceil((Number(store.get('resendUntil') || 0) - Date.now()) / 1000);
      if (left > 0) startCooldown(left);
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var code = codeInput.value.trim();
      var errs = {};
      if (!/^\d{6}$/.test(code)) errs.code = 'Enter the 6-digit code.';
      UI.fieldErrors(form, errs);
      if (Object.keys(errs).length) return;

      UI.run(btn, function () {
        return call(function () { return API.post('/auth/verify', { email: email, code: code }, OPTS); });
      }, 'Checking…').then(function () {
        store.del('pendingEmail');
        store.del('resendUntil');
        location.href = nextUrl();          // the server has logged the user in
      }, function (err) {
        fail(err);
        if (err && (err.code === 'CODE_EXPIRED' || err.code === 'TOO_MANY_ATTEMPTS')) codeInput.value = '';
      });
    });
  }

  // ------------------------------------------------------------------ start
  if (!form) return;
  if (page === 'login') initLogin();
  else if (page === 'signup') initSignup();
  else if (page === 'verify') initVerify();
})();
