/* vendor/notifications.js: Notifications page (P4, Stage 3).
   GET /api/v1/notifications, PATCH /api/v1/notifications/{id}/read, POST /api/v1/notifications/read-all */
(function () {
  'use strict';

  var el = V.el, icon = V.icon;
  var list = document.getElementById('notif-list');
  var unreadText = document.getElementById('unread-text');
  var readAllBtn = document.getElementById('read-all');

  // which icon each kind of notification gets
  var ICON = {
    order_new: 'receipt', order_status: 'bag', low_stock: 'alert', review: 'star',
    vendor_approved: 'shield', vendor_request: 'store'
  };

  function setUnread(n) {
    unreadText.textContent = n ? n + ' unread' : 'You are all caught up';
    readAllBtn.disabled = !n;
    document.querySelectorAll('.v-iconbtn .v-dot-count').forEach(function (d) { d.textContent = n; d.hidden = !n; });
  }

  function row(n) {
    var li = el('li', { className: 'v-notif' + (n.is_read ? '' : ' unread') }, [
      el('span', { className: 'v-rowi-icon' + (n.is_read ? '' : ' red') }, [icon(ICON[n.type] || 'bell')]),
      el('div', { className: 'v-notif-body' }, [
        el('p', { text: n.message }),
        el('span', { className: 'v-notif-time', text: V.timeAgo(n.sent_at) })
      ])
    ]);
    if (n.link) {
      var a = el('a', { className: 'v-btn v-btn-outline v-btn-sm', href: V.root + n.link, text: 'View' });
      a.addEventListener('click', function (e) {
        if (n.is_read) return;            // already read: just follow the link
        e.preventDefault();
        API.patch('/notifications/' + n.id + '/read', {}, { silent: true })
          .catch(function () { /* still go to the page */ })
          .then(function () { location.href = a.href; });
      });
      li.appendChild(a);
    } else if (!n.is_read) {
      li.appendChild(el('button', { className: 'v-btn v-btn-outline v-btn-sm', type: 'button', text: 'Mark read',
        onclick: function () { API.patch('/notifications/' + n.id + '/read', {}).then(load); } }));
    }
    return li;
  }

  function load() {
    return API.get('/notifications', null, { full: true }).then(function (res) {
      setUnread(res.meta.unread_count);
      list.textContent = '';
      if (!res.data.length) {
        list.appendChild(el('li', { className: 'v-notif' }, [el('p', { className: 'muted', text: 'No notifications yet. New orders will show up here.' })]));
        return;
      }
      res.data.forEach(function (n) { list.appendChild(row(n)); });
    });
  }

  readAllBtn.addEventListener('click', function () {
    UI.run(readAllBtn, function () { return API.post('/notifications/read-all', {}); }, 'Saving…').then(load);
  });

  V.boot().then(load).catch(function () { /* handled */ });
})();
