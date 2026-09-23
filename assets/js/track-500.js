/* 500세대 이상 집단등기 실적 (수임실적 증명서 기준) — data/track-500.json
   법인 증명서(수임실적표)의 행을 그대로 옮긴 자료다.
   단지 수와 기간은 자료에서 계산한다. 화면에 숫자를 직접 적지 않는다.
   못 불러오면 구역 전체를 감춘다. */
(function () {
  'use strict';

  var root = document.querySelector('[data-track-500]');
  if (!root) return;

  var sumEl = root.querySelector('[data-t5-sum]');
  var barEl = root.querySelector('[data-t5-years]');
  var cntEl = root.querySelector('[data-t5-count]');
  var bodyEl = root.querySelector('[data-t5-body]');

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  // "2024-06" → "2024년 6월"
  function ymText(ym) {
    var m = /^(\d{4})-(\d{2})$/.exec(ym || '');
    return m ? m[1] + '년 ' + parseInt(m[2], 10) + '월' : esc(ym);
  }
  // 세대수는 숫자면 천 단위 쉼표, 문자열(예: 693/166)은 원문 그대로
  function hh(v) {
    return typeof v === 'number' ? v.toLocaleString('ko-KR') : esc(v);
  }

  var items = [];

  function draw(year) {
    var rows = year ? items.filter(function (it) { return it.ym.slice(0, 4) === year; }) : items;
    bodyEl.innerHTML = rows.map(function (it) {
      return '<tr><td>' + esc(it.name) + '</td>' +
             '<td class="num">' + hh(it.households) + '</td>' +
             '<td class="ym">' + ymText(it.ym) + '</td></tr>';
    }).join('');
    cntEl.textContent = (year ? year + '년 ' : '전체 ') + rows.length + '곳';
    barEl.querySelectorAll('button').forEach(function (b) {
      var on = b.getAttribute('data-year') === year;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', String(on));
    });
  }

  fetch('data/track-500.json', { cache: 'no-cache' })
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (d) {
      items = (d.items || []).filter(function (it) {
        return it && it.name && /^\d{4}-\d{2}$/.test(it.ym || '');
      });
      if (!items.length) throw new Error('실적 자료가 비어 있습니다');

      var yms = items.map(function (it) { return it.ym; }).sort();
      var years = [];
      yms.forEach(function (ym) {
        var y = ym.slice(0, 4);
        if (years.indexOf(y) < 0) years.push(y);
      });

      sumEl.innerHTML =
        '<span class="s">' + ymText(yms[0]) + '부터 ' + ymText(yms[yms.length - 1]) + '까지 등기한 단지입니다.</span>' +
        '<span class="s">500세대 이상 단지 <b>' + items.length + '곳</b>의 집단등기를 맡았습니다.</span>';

      barEl.innerHTML =
        '<button type="button" data-year="">전체</button>' +
        years.map(function (y) {
          return '<button type="button" data-year="' + y + '">' + y + '</button>';
        }).join('');
      barEl.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-year]');
        if (b) draw(b.getAttribute('data-year'));
      });

      draw('');
      root.removeAttribute('hidden');
    })
    .catch(function (err) {
      console.error('[track-500]', err);
      root.remove();
    });
})();
