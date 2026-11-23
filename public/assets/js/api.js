/* ==========================================================================
   api.js  v2: the ONE helper every page uses to talk to the PHP API.
   Plain script, no build step. Works from any folder depth (public/vendor/x.html,
   public/resources/y.html ...) and from a XAMPP sub-folder, because it works out
   the site root from its own <script src>.

   LOAD (from a page inside a sub-folder such as public/vendor/):
     <script src="../assets/js/ui.js"></script>
     <script src="../assets/js/api.js"></script>

   MOCK MODE  (the backend is not built yet)
     While MOCK is true (see the line below), GETs read sample JSON from
     assets/mock/<path>.json and POST/PATCH/DELETE pretend to succeed. Pages can
     be built and demoed today. When the real PHP endpoint exists, set MOCK = false
     (one line, one file). A small "MOCK DATA" tag shows in the corner while on.
     Override per tab for testing:  add ?mock=0 or ?mock=1 to any page URL.

   USE (every call resolves to the response "data"):
     const orders = await API.get('/vendor/orders', { status: 'placed' });
     await API.patch('/vendor/orders/1042', { status: 'received' });
     await API.post('/vendor/items', API.formToJson(formEl));
     await API.post('/vendor/items/5/images', new FormData(formEl));   // uploads
     await API.delete('/vendor/items/5');

   ON FAILURE it shows an error toast (via ui.js) and throws an ApiError
   (err.status, err.code, err.message, err.fields), so most pages need no try/catch.

   OPTIONS (last argument):  { silent:true }     no error toast
                             { noRedirect:true } do not redirect on 401
                             { full:true }       resolve the whole JSON body
                                                 (use it to read "meta" paging info)

   AGREED RESPONSE SHAPES  (Person 2's response.php must send exactly these):
     success: { "success": true, "data": ... }
     list:    { "success": true, "data": [...], "meta": { "page":1, "per_page":20, "total":57 } }
     failure: { "success": false, "error": { "code": "OUT_OF_STOCK", "message": "Only 2 left." } }
     invalid: { "success": false, "error": { "code": "VALIDATION", "message": "...",
                                              "fields": { "phone": "Enter a phone number." } } }
     (a plain-string "error" is also tolerated)
   ========================================================================== */
(function (global) {
  'use strict';

  /* >>> FLIP TO false WHEN THE REAL API IS LIVE <<< */
  var MOCK = true;

  // ---- site root, from this script's own URL (…/assets/js/api.js -> …/) ----
  var ROOT = (function () {
    var s = document.currentScript && document.currentScript.src;
    if (s) return s.replace(/assets\/js\/api\.js(\?.*)?$/, '');
    return location.origin + '/';
  })();

  // ?mock=0 / ?mock=1 override, remembered for this browser tab
  try {
    var q = /[?&]mock=([01])/.exec(location.search);
    if (q) sessionStorage.setItem('api-mock', q[1]);
    var saved = sessionStorage.getItem('api-mock');
    if (saved !== null) MOCK = saved === '1';
  } catch (e) { /* storage blocked: keep the default */ }

  var config = {
    base: ROOT + 'api/v1',
    mockBase: ROOT + 'assets/mock',
    loginUrl: ROOT + 'login.html',  // change when Person 1's real login page exists
    csrfMeta: 'csrf-token',         // <meta name="csrf-token" content="..."> in the layout
    mock: MOCK,
    mockDelay: 250                  // ms, so loading spinners are visible in mock mode
  };

  var DEFAULT_MESSAGES = {
    0:   'Cannot reach the server. Check your connection and try again.',
    400: 'That request was not valid.',
    401: 'Please sign in to continue.',
    403: 'You are not allowed to do that.',
    404: 'We could not find that.',
    409: 'That is no longer available.',
    422: 'Please check the highlighted fields.',
    429: 'Too many tries. Please wait a moment and try again.'
  };

  function ApiError(status, code, message, fields) {
    this.name = 'ApiError';
    this.status = status;
    this.code = code || 'ERROR';
    this.message = message;
    this.fields = fields || null;
  }
  ApiError.prototype = Object.create(Error.prototype);
  ApiError.prototype.constructor = ApiError;

  // ---------------------------------------------------------------- helpers
  function csrfToken() {
    var m = document.querySelector('meta[name="' + config.csrfMeta + '"]');
    if (m && m.getAttribute('content')) return m.getAttribute('content');
    var i = document.querySelector('input[name="csrf_token"]');   // hidden input in the forms
    return i ? i.value : '';
  }

  function query(params) {
    var qs = [];
    Object.keys(params || {}).forEach(function (k) {
      var v = params[k];
      if (v === undefined || v === null || v === '') return;
      qs.push(encodeURIComponent(k) + '=' + encodeURIComponent(v));
    });
    return qs.length ? '?' + qs.join('&') : '';
  }

  function cleanPath(path) { return path.charAt(0) === '/' ? path : '/' + path; }

  function toError(status, payload) {
    var code, message, fields, e = payload && payload.error;
    if (typeof e === 'string') message = e;
    else if (e && typeof e === 'object') { code = e.code; message = e.message; fields = e.fields; }
    return new ApiError(status, code, message || DEFAULT_MESSAGES[status] || 'Something went wrong. Please try again.', fields);
  }

  function report(err, opts) {
    if (opts.silent) return;
    if (global.UI && typeof global.UI.error === 'function') global.UI.error(err.message);
    else if (global.console) console.error('[API]', err.status, err.code, err.message);
  }

  function finish(res, payload, opts) {
    if (res.ok && payload && payload.success !== false) {
      if (opts.full) return payload;
      return payload.data !== undefined ? payload.data : payload;
    }
    var err = toError(res.status, payload);
    if (res.status === 401 && !opts.noRedirect) {
      location.href = config.loginUrl + '?next=' + encodeURIComponent(location.pathname + location.search);
    } else {
      report(err, opts);
    }
    throw err;
  }

  function readJson(res) {
    return res.json().then(function (p) { return { res: res, payload: p }; },
                           function () { return { res: res, payload: null }; });
  }

  function wait(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

  // ------------------------------------------------------------- real API
  function realRequest(method, path, params, body, opts) {
    var headers = { 'Accept': 'application/json' };
    var init = { method: method, headers: headers, credentials: 'same-origin' };

    if (body instanceof FormData) {
      init.body = body;                                  // browser sets the multipart header
    } else if (body !== undefined && body !== null) {
      headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(body);
    }
    if (method !== 'GET') {
      var t = csrfToken();
      if (t) headers['X-CSRF-Token'] = t;
    }

    return fetch(config.base + cleanPath(path) + query(params), init).then(readJson, function () {
      var err = new ApiError(0, 'NETWORK', DEFAULT_MESSAGES[0]);
      report(err, opts);
      throw err;
    }).then(function (r) { return finish(r.res, r.payload, opts); });
  }

  // ------------------------------------------------------------ mock mode
  function mockGet(path) {
    var file = config.mockBase + cleanPath(path).replace(/\/$/, '') + '.json';
    return fetch(file).then(function (res) {
      if (res.ok) return res.json().then(function (p) { return { res: res, payload: p }; });
      // /vendor/items/3 -> look inside /vendor/items.json for id 3
      var m = /^(.*)\/([^\/]+)$/.exec(cleanPath(path));
      if (!m || !m[1]) return { res: res, payload: null };
      return fetch(config.mockBase + m[1] + '.json').then(function (lr) {
        if (!lr.ok) return { res: res, payload: null };
        return lr.json().then(function (list) {
          var hit = (Array.isArray(list.data) ? list.data : []).filter(function (x) { return String(x.id) === m[2]; })[0];
          return hit ? { res: lr, payload: { success: true, data: hit } } : { res: res, payload: null };
        });
      });
    });
  }

  function mockRequest(method, path, params, body, opts) {
    return wait(config.mockDelay).then(function () {
      if (method === 'GET') {
        return mockGet(path).then(function (r) {
          if (!r.payload) {
            r.payload = { success: false, error: { code: 'NO_MOCK', message: 'No mock data for GET ' + path + ' (add assets/mock' + cleanPath(path) + '.json)' } };
            return finish({ ok: false, status: 404 }, r.payload, opts);
          }
          var p = r.payload;
          if (p.success !== false && Array.isArray(p.data) && params) {      // tiny filter: ?status= and ?q=
            p = { success: true, meta: p.meta, data: p.data.filter(function (x) {
              if (params.status && x.status !== undefined && x.status !== params.status) return false;
              if (params.q && x.name && x.name.toLowerCase().indexOf(String(params.q).toLowerCase()) < 0) return false;
              return true;
            }) };
          }
          return finish(r.res, p, opts);
        });
      }
      var echo = body instanceof FormData ? {} : (body || {});
      var data = {}; Object.keys(echo).forEach(function (k) { data[k] = echo[k]; });
      var pid = /\/(\d+)(?:\/[^\/]+)?$/.exec(path);
      data.id = data.id || (pid ? Number(pid[1]) : Math.floor(Math.random() * 9000) + 1000);
      data.mock = true;
      return finish({ ok: true, status: method === 'POST' ? 201 : 200 }, { success: true, data: data }, opts);
    });
  }

  function request(method, path, params, body, opts) {
    opts = opts || {};
    return (config.mock ? mockRequest : realRequest)(method, path, params, body, opts);
  }

  // --------------------------------------------------- forms -> JSON helper
  // <input name="hours[mon][open]"> becomes { hours: { mon: { open: ... } } };
  // checkboxes become true/false; "csrf_token" and file inputs are skipped.
  function formToJson(form) {
    var out = {};
    function put(name, value) {
      var keys = name.replace(/\]/g, '').split('[');
      var o = out;
      for (var i = 0; i < keys.length - 1; i++) {
        if (typeof o[keys[i]] !== 'object' || o[keys[i]] === null) o[keys[i]] = {};
        o = o[keys[i]];
      }
      o[keys[keys.length - 1]] = value;
    }
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || el.disabled || el.name === 'csrf_token' || el.type === 'file' || el.type === 'submit' || el.type === 'button') return;
      if (el.type === 'checkbox') {
        var group = form.querySelectorAll('input[type=checkbox][name="' + el.name + '"]');
        if (group.length === 1) put(el.name, el.checked);
        else if (el.checked) { /* several boxes, same name: collect into a list */
          var list = [];
          group.forEach(function (c) { if (c.checked) list.push(c.value); });
          put(el.name.replace(/\[\]$/, ''), list);
        }
        return;
      }
      if (el.type === 'radio') { if (el.checked) put(el.name, el.value); return; }
      put(el.name, el.value);
    });
    return out;
  }

  // -------------------------------------------------------- mock indicator
  if (config.mock) {
    document.addEventListener('DOMContentLoaded', function () {
      var t = document.createElement('div');
      t.textContent = 'MOCK DATA';
      t.setAttribute('aria-hidden', 'true');
      t.style.cssText = 'position:fixed;top:6px;right:8px;z-index:200;padding:2px 8px;border-radius:999px;' +
        'background:#fdf0d9;color:#9a5b00;font:600 10px/1.6 system-ui,sans-serif;letter-spacing:.04em;pointer-events:none;';
      document.body.appendChild(t);
    });
    if (global.console) console.info('[API] MOCK mode on: reading ' + config.mockBase + '/…  (set MOCK = false in api.js when the PHP API is live)');
  }

  global.API = {
    config: config,
    ApiError: ApiError,
    request: request,
    formToJson: formToJson,
    get:    function (path, params, opts) { return request('GET', path, params, null, opts); },
    post:   function (path, body, opts)   { return request('POST', path, null, body === undefined ? {} : body, opts); },
    patch:  function (path, body, opts)   { return request('PATCH', path, null, body === undefined ? {} : body, opts); },
    delete: function (path, opts)         { return request('DELETE', path, null, null, opts); }
  };
})(window);
