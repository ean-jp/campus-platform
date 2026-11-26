/* vendor/sales.js: Sales page (P4, Stage 3).  GET /api/v1/vendor/sales?range=7d|30d|all */
(function () {
  'use strict';

  var el = V.el;
  var range = document.getElementById('range');
  function $(id) { return document.getElementById(id); }

  var DAY = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  function hourText(h) {           // 11 -> "11 am–12 pm"
    if (h === null) return '–';
    function one(x) { x = x % 24; return ((x % 12) || 12) + (x < 12 ? ' am' : ' pm'); }
    return one(h) + '–' + one(h + 1);
  }

  function drawChart(days) {
    var bars = $('bars'), labels = $('bar-labels');
    bars.textContent = ''; labels.textContent = '';
    var many = days.length > 7;
    bars.classList.toggle('v-bars-many', many);
    var max = Math.max.apply(null, days.map(function (d) { return d.total; })) || 1;

    days.forEach(function (d, i) {
      var isToday = i === days.length - 1;
      var date = new Date(d.date + 'T12:00:00');
      var bar = el('div', { className: 'v-bar', style: 'height:' + Math.max(2, Math.round(d.total / max * 100)) + '%' },
        [el('span', { className: 'v-bar-value', text: Math.round(d.total) })]);
      bars.appendChild(el('div', { className: 'v-bar-col' + (isToday ? ' today' : ''), title: date.toDateString() + ': ' + V.money(d.total) }, [bar]));
      var text = isToday ? 'Today' : (many ? (i % 5 === 0 ? date.getDate() + '/' + (date.getMonth() + 1) : '') : DAY[date.getDay()]);
      labels.appendChild(el('span', { text: text }));
    });
    bars.setAttribute('aria-label', 'Sales per day: ' + days.map(function (d) { return d.date + ' ' + d.total + ' cedis'; }).join(', '));
    $('today-badge').textContent = 'Today: ' + V.money(days[days.length - 1].total);
    $('chart-title').textContent = 'Sales per day, last ' + days.length + ' days (GH₵)';
  }

  function drawBest(list) {
    var box = $('best-list');
    box.textContent = '';
    if (!list.length) { box.appendChild(el('li', { className: 'muted', text: 'No completed sales in this period yet.' })); return; }
    var top = list[0].qty;
    list.forEach(function (b) {
      box.appendChild(el('li', {}, [
        el('span', { text: b.name }),
        el('span', { className: 'muted', text: b.qty + ' · ' + V.money(b.revenue) }),
        el('div', { className: 'v-rank-bar' }, [el('span', { style: 'width:' + Math.round(b.qty / top * 100) + '%' })])
      ]));
    });
  }

  function drawRecent(list) {
    var body = $('recent-body');
    body.textContent = '';
    if (!list.length) {
      body.appendChild(el('tr', {}, [el('td', { colspan: '6', className: 'muted', text: 'No completed orders yet.' })]));
      return;
    }
    list.forEach(function (o) {
      body.appendChild(el('tr', {}, [
        el('td', {}, [el('strong', { text: '#' + o.id })]),
        el('td', { text: V.timeAgo(o.completed_at) }),
        el('td', { text: o.buyer }),
        el('td', { text: o.items }),
        el('td', { text: o.fulfilment === 'delivery' ? 'Delivery' : 'Pickup' }),
        el('td', { className: 'v-red' }, [el('strong', { text: V.money(o.total) })])
      ]));
    });
  }

  function load() {
    history.replaceState(null, '', 'sales.html' + (range.value !== '7d' ? '?range=' + range.value : ''));
    return API.get('/vendor/sales', { range: range.value }).then(function (s) {
      $('s-total').textContent = V.money(s.sales);
      var note = $('s-total-note');
      if (s.previous > 0) {
        var up = s.sales >= s.previous;
        note.textContent = (up ? '▲ ' : '▼ ') + 'from ' + V.money(s.previous) + ' the ' + (s.range === '30d' ? '30' : '7') + ' days before';
        note.className = 'v-tile-note' + (up ? ' up' : '');
      } else {
        note.textContent = s.range === 'all' ? 'since you joined' : 'no sales the period before';
        note.className = 'v-tile-note';
      }
      $('s-done').textContent = s.completed;
      $('s-done-note').textContent = s.cancelled + ' cancelled';
      $('s-avg').textContent = V.money(s.average);
      $('s-busy').textContent = hourText(s.busiest_hour);
      drawChart(s.by_day);
      drawBest(s.best_sellers);
      drawRecent(s.recent);
    });
  }

  range.value = new URLSearchParams(location.search).get('range') || '7d';
  range.addEventListener('change', load);
  V.boot().then(load).catch(function () { /* handled */ });
})();
